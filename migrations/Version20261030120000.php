<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'oturum_mesaji tablosu + student_profile.oturum_id (2026-10-30, kullanıcı kararı): '
            .'karar ağacının gelen/giden mesaj geçmişi hiçbir yerde loglanmıyordu — `conversation_message` '
            .'yalnızca eski AI-sohbet dalı için yazılıyor (bkz. PILOT_KARAR_AGACI_AKTIF), o tabloya karar '
            .'ağacının 11 gönderim noktası hiç dokunmuyor. `conversation_message.session` ZORUNLU olduğu '
            .'için (her mesaj bir ConversationSession\'a bağlı olmalı) karar ağacını o şemaya zorlamak yerine '
            .'(ConversationSession.turnCount/maxTurns tamamen AI-sohbet turu mantığına özgü, karar ağacının '
            .'kendi durumu zaten student_profile üzerinde) AYRI bir tablo açılıyor. `oturum_id`, '
            .'StudentProfile::oturumuBaslat() içinde üretilen, bir OTURUMA (bkz. aynı metodun docblock\'u) '
            .'ait tüm mesajları (proaktif açılış + sonraki tüm gelen/giden) GRUPLAMAK için kullanılan bir '
            .'korelasyon anahtarı — ayrı bir "oturum" tablosu YOK, oturumun kendisi hâlâ student_profile\'ın '
            .'anlık durumu; bu sütun sadece o anki oturumun ID\'sinin mesaj loguna damgalanmasını sağlıyor.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile ADD oturum_id VARCHAR(32) DEFAULT NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE oturum_mesaji (
                id INT AUTO_INCREMENT NOT NULL,
                ogrenci_no INT NOT NULL,
                oturum_id VARCHAR(32) DEFAULT NULL,
                yon VARCHAR(10) NOT NULL,
                metin LONGTEXT NOT NULL,
                wamid VARCHAR(255) DEFAULT NULL,
                olusturulan_at DATETIME NOT NULL,
                teslim_edildi_at DATETIME DEFAULT NULL,
                okundu_at DATETIME DEFAULT NULL,
                basarisiz_at DATETIME DEFAULT NULL,
                hata_detay LONGTEXT DEFAULT NULL,
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
        $this->addSql('CREATE INDEX idx_oturum_mesaji_ogrenci_no_olusturulan_at ON oturum_mesaji (ogrenci_no, olusturulan_at)');
        $this->addSql('CREATE INDEX idx_oturum_mesaji_oturum_id ON oturum_mesaji (oturum_id)');
        $this->addSql('CREATE INDEX idx_oturum_mesaji_wamid ON oturum_mesaji (wamid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE oturum_mesaji');
        $this->addSql('ALTER TABLE student_profile DROP oturum_id');
    }
}
