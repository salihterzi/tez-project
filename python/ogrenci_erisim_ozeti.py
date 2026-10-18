"""
Öğrenci Erişim Özeti — Günlük Toplayıcı
====================================================
`DailyTriggerEvaluator`'ın (PHP, T1-T4 karar motoru) OKUDUĞU `ogrenci_erisim_ozeti`
tablosunu, `materyal_erisim_log`/`sinav_sonucu`'ndan (aktif `(yil, donem)` için) türetip
yazar. `OgrenciErisimOzeti` entity'sinin docblock'u bu scripti ZATEN varsayıyordu
("Python tarafında çalışan bir pipeline... günlük... TOPTAN yeniler") ama hiçbir zaman
yazılmamıştı (2026-10-18'de fark edildi) — DB'deki satırlar o güne kadar tek seferlik/elle
yazılmış, bir daha hiç güncellenmeyen bir kopyaydı.

Gün/saat pipeline'larından (`mesaj_zamanlamasi_hibrit.py` / `_gun_hibrit.py`) FARKLI olarak:
  - Seçenek A/B/C gibi bir kişiselleştirme kararı YOK — sadece ham sayma/MIN/MAX, hiç
    mixedlm/top-N yok.
  - Çıktı `student_profile`'a değil, Doctrine'in YÖNETTİĞİ `ogrenci_erisim_ozeti` tablosuna
    yazılır — bu tablo PHP'nin (okuyan tarafın) ŞEMASINI tanımladığı bir entity,
    `saat_tahmini_gecmisi`/`gun_tahmini_gecmisi`'nin aksine (onlar PHP'nin hiç bilmediği,
    Python'un kendi yönettiği tablolar) — bu script tabloyu OLUŞTURMAZ, zaten migration'la
    var olduğunu varsayar.
  - "Bugün"e göre KAYAN PENCERE (bu_hafta/geçen_hafta/kümülatif) olduğu için, PHP'nin
    `app:degerlendir-gunluk-tetikleyiciler`'ı ile AYNI "bugün"le (gerçek ya da simüle
    `--tarih`) çalıştırılmalı — bkz. `main()`'in `bugun` parametresi. Üretimde bu script
    PHP job'ından HEMEN ÖNCE (aynı güne ait) çalışmalı.

KULLANIM (python container'ı içinde):
    docker compose exec python python ogrenci_erisim_ozeti.py

    # Simüle bir "bugün" ile (PHP'nin --tarih'iyle AYNI tarih verilmeli):
    docker compose exec python python -c "
    import ogrenci_erisim_ozeti as o
    o.main(yil=2024, donem=2, bugun='2025-05-01')
    "
"""

import importlib.util
import pathlib
import warnings

import pandas as pd

from db import get_connection, get_engine

warnings.filterwarnings('ignore')

_pipeline_path = pathlib.Path(__file__).parent / "mesaj_zamanlamasi_pipeline-v2.py"
_spec = importlib.util.spec_from_file_location("mesaj_zamanlamasi_pipeline_v2", _pipeline_path)
pipeline = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(pipeline)


# =====================================================================
# ÖZET HESABI
# =====================================================================

def ozet_hesapla(mat, sin, dem, bugun):
    """Her öğrenci için (dem'deki TÜM kayıtlı öğrenciler, mat/sin'de hiç satırı olmayanlar
    DAHİL -- T1'in "hiç erişim yok" durumunu doğal olarak kapsasın diye, ayrı bir cold-start
    adımına gerek kalmadan) ilk/son erişim ve bu hafta/geçen hafta/kümülatif erişim sayısını
    hesaplar.

    `bugun` HARİÇ tutulur (yalnızca `islem_zamani < bugun` olan olaylar sayılır) --
    `DailyTriggerEvaluator::evaluateAll()`'ın `$today->setTime(0,0)` ile aynı "bugünün
    başlangıcına kadarki geçmiş" semantiğiyle hizalı olsun diye (gece 02:30 çalışan job,
    bugünün henüz oluşmamış erişimini zaten sayamaz).

    Döner: {ogrenci_no: {'ilk_erisim', 'son_erisim', 'bu_hafta', 'gecen_hafta', 'kumulatif'}}.
    """
    olaylar = pipeline.mat_sin_birlestir(mat, sin)[['ogrenci_no', 'islem_zamani']]

    bu_hafta_baslangic = bugun - pd.Timedelta(days=7)
    gecen_hafta_baslangic = bugun - pd.Timedelta(days=14)

    kumulatif_olaylar = olaylar[olaylar['islem_zamani'] < bugun]
    bu_hafta_olaylar = kumulatif_olaylar[kumulatif_olaylar['islem_zamani'] >= bu_hafta_baslangic]
    gecen_hafta_olaylar = kumulatif_olaylar[
        (kumulatif_olaylar['islem_zamani'] >= gecen_hafta_baslangic)
        & (kumulatif_olaylar['islem_zamani'] < bu_hafta_baslangic)
    ]

    ilk_erisim = kumulatif_olaylar.groupby('ogrenci_no')['islem_zamani'].min()
    son_erisim = kumulatif_olaylar.groupby('ogrenci_no')['islem_zamani'].max()
    bu_hafta_sayim = bu_hafta_olaylar.groupby('ogrenci_no').size()
    gecen_hafta_sayim = gecen_hafta_olaylar.groupby('ogrenci_no').size()
    kumulatif_sayim = kumulatif_olaylar.groupby('ogrenci_no').size()

    sonuc = {}
    for ogrenci_no in dem['ogrenci_no']:
        sonuc[int(ogrenci_no)] = {
            'ilk_erisim': ilk_erisim.get(ogrenci_no),
            'son_erisim': son_erisim.get(ogrenci_no),
            'bu_hafta': int(bu_hafta_sayim.get(ogrenci_no, 0)),
            'gecen_hafta': int(gecen_hafta_sayim.get(ogrenci_no, 0)),
            'kumulatif': int(kumulatif_sayim.get(ogrenci_no, 0)),
        }

    return sonuc


# =====================================================================
# YAZMA: ogrenci_erisim_ozeti (PHP tarafının okuduğu, Doctrine-yönetimli tablo)
# =====================================================================

def erisim_ozetini_yaz(ozet):
    """Hesaplanan özeti `ogrenci_erisim_ozeti`'ye yazar (upsert, PK=student_id) -- CSV'ye
    YAZMAZ (diğer pipeline çıktılarıyla aynı kullanıcı kararı). `saat_tahmini_gecmisi`/
    `gun_tahmini_gecmisi`'nin aksine bu tablonun ŞEMASINI yönetmiyoruz -- zaten migration'la
    var, burada sadece upsert yapılıyor.

    Döner: yazılan öğrenci satırı sayısı.
    """
    simdi = pd.Timestamp.now().to_pydatetime()

    satirlar = [
        (
            ogrenci_no,
            deger['ilk_erisim'].to_pydatetime() if pd.notna(deger['ilk_erisim']) else None,
            deger['son_erisim'].to_pydatetime() if pd.notna(deger['son_erisim']) else None,
            deger['bu_hafta'],
            deger['gecen_hafta'],
            deger['kumulatif'],
            simdi,
        )
        for ogrenci_no, deger in ozet.items()
    ]

    sorgu = """
        INSERT INTO ogrenci_erisim_ozeti
            (student_id, ilk_erisim_tarihi, son_erisim_tarihi, bu_hafta_erisim_sayisi,
             gecen_hafta_erisim_sayisi, donem_kumulatif_erisim_sayisi, guncellenme_tarihi)
        VALUES (%s, %s, %s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            ilk_erisim_tarihi = VALUES(ilk_erisim_tarihi),
            son_erisim_tarihi = VALUES(son_erisim_tarihi),
            bu_hafta_erisim_sayisi = VALUES(bu_hafta_erisim_sayisi),
            gecen_hafta_erisim_sayisi = VALUES(gecen_hafta_erisim_sayisi),
            donem_kumulatif_erisim_sayisi = VALUES(donem_kumulatif_erisim_sayisi),
            guncellenme_tarihi = VALUES(guncellenme_tarihi)
    """
    with get_connection() as conn, conn.cursor() as cursor:
        cursor.executemany(sorgu, satirlar)
        conn.commit()

    return len(satirlar)


# =====================================================================
# ANA AKIŞ
# =====================================================================

def main(yil=2024, donem=2, bugun=None):
    """`bugun`: PHP'nin `app:degerlendir-gunluk-tetikleyiciler --tarih`'iyle AYNI (gerçek ya
    da simüle) tarih verilmeli -- bu_hafta/geçen_hafta/kümülatif tamamen bu tarihe göre kayan
    pencerelerdir. `None` ise gerçek bugünün tarihi kullanılır (saat kısmı sıfırlanır, PHP'nin
    `setTime(0,0)`'ıyla hizalı)."""
    bugun = (pd.Timestamp(bugun) if bugun is not None else pd.Timestamp.now()).normalize()

    engine = get_engine()

    print(f"1) Veri yükleniyor ({yil}/{donem}), bugün={bugun.date()}...")
    mat, sin, dem = pipeline.veriyi_yukle(engine, yil=yil, donem=donem)

    print("2) Özet hesaplanıyor (ilk/son erişim, bu hafta/geçen hafta/kümülatif erişim)...")
    ozet = ozet_hesapla(mat, sin, dem, bugun)

    hic_erisimi_olmayan = sum(1 for d in ozet.values() if d['ilk_erisim'] is None)
    print(f"   {len(ozet)} öğrenci işlendi, {hic_erisimi_olmayan} tanesinin "
          f"(bugüne kadar) hiç erişimi yok (T1 adayı).")

    print("3) ogrenci_erisim_ozeti yazılıyor...")
    yazilan = erisim_ozetini_yaz(ozet)
    print(f"Yazıldı: {yazilan} öğrenci satırı.")


if __name__ == "__main__":
    main()
