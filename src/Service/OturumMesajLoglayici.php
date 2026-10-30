<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Karar ağacının gelen/giden mesaj geçmişini `oturum_mesaji` tablosuna loglar (2026-10-30,
 * kullanıcı kararı).
 *
 * NEDEN AYRI BİR TABLO (ve ORM entity DEĞİL, {@see WebhookMesajTekrarKorumasi} ile AYNI
 * desende ham DBAL): {@see ConversationMessage} yalnızca eski AI-sohbet dalı
 * (`PILOT_KARAR_AGACI_AKTIF=0`) için yazılıyor; o entity'nin `session` alanı ZORUNLU
 * (her mesaj bir {@see \App\Entity\ConversationSession}'a bağlı olmak zorunda), ama o
 * entity'nin `turnCount`/`maxTurns` gibi alanları tamamen AI-sohbet turu mantığına özgü —
 * karar ağacının kendi durumu zaten {@see \App\Entity\StudentProfile} üzerinde
 * (`pending_flow_step`, `oturum_kaynagi` vb.). Karar ağacı mesajlarını o şemaya zorlamak
 * kavramsal bir uyumsuzluk yaratırdı.
 *
 * GRUPLAMA: Ayrı bir "oturum" tablosu YOK — oturumun kendisi hâlâ `student_profile`'ın anlık
 * durumu. `oturum_id`, {@see \App\Entity\StudentProfile::oturumuBaslat()} içinde üretilen bir
 * korelasyon anahtarı; bir oturuma ait tüm mesajlar (proaktif açılış + sonraki gelen/giden)
 * loglanırken o anki `$profil->getOturumId()` değeriyle damgalanır — aynı oturuma ait
 * satırlar `WHERE oturum_id = ?` ile birlikte çekilebilir.
 */
class OturumMesajLoglayici
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function gelenKaydet(int $ogrenciNo, ?string $oturumId, string $metin, string $wamid, \DateTimeImmutable $simdi): void
    {
        $this->connection->insert('oturum_mesaji', [
            'ogrenci_no' => $ogrenciNo,
            'oturum_id' => $oturumId,
            'yon' => 'gelen',
            'metin' => $metin,
            'wamid' => $wamid,
            'olusturulan_at' => $simdi->format('Y-m-d H:i:s'),
        ]);
    }

    public function gidenKaydet(int $ogrenciNo, ?string $oturumId, string $metin, ?string $wamid, \DateTimeImmutable $simdi): void
    {
        $this->connection->insert('oturum_mesaji', [
            'ogrenci_no' => $ogrenciNo,
            'oturum_id' => $oturumId,
            'yon' => 'giden',
            'metin' => $metin,
            'wamid' => $wamid,
            'olusturulan_at' => $simdi->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Meta'nın `statuses` bildirimindeki bir kaydı (delivered/read/failed) ilgili satıra işler.
     * wamid bu tabloda yoksa (ör. AI-sohbet dalının mesajıysa) sessizce hiçbir satır etkilenmez.
     *
     * @param array<mixed>|null $hata Meta'nın `errors` dizisi (yalnızca `failed` durumunda dolu)
     */
    public function teslimDurumuGuncelle(string $wamid, string $status, \DateTimeImmutable $zaman, ?array $hata = null): void
    {
        $zamanStr = $zaman->format('Y-m-d H:i:s');

        match ($status) {
            'delivered' => $this->connection->executeStatement(
                'UPDATE oturum_mesaji SET teslim_edildi_at = COALESCE(teslim_edildi_at, ?) WHERE wamid = ?',
                [$zamanStr, $wamid],
            ),
            'read' => $this->connection->executeStatement(
                'UPDATE oturum_mesaji SET teslim_edildi_at = COALESCE(teslim_edildi_at, ?), okundu_at = ? WHERE wamid = ?',
                [$zamanStr, $zamanStr, $wamid],
            ),
            'failed' => $this->connection->executeStatement(
                'UPDATE oturum_mesaji SET basarisiz_at = ?, hata_detay = ? WHERE wamid = ?',
                [$zamanStr, null !== $hata ? json_encode($hata) : null, $wamid],
            ),
            default => null,
        };
    }
}
