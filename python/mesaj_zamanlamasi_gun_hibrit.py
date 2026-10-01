"""
Gün Hibrit (Öğrenci x Faz Bazlı, Kategorik, Top-N) Tahmin
====================================================
`mesaj_zamanlamasi_hibrit.py` saat için Seçenek A/B kararını (öğrenci, faz)
ÇİFTİ düzeyinde veriyordu -- burada AYNI karar mekanizması gün tahmini için
tekrarlanır. Farkı: saat dairesel bir regresyon problemiydi (saat_sin/saat_cos
üzerinde mixedlm), gün burada KATEGORİK ele alınır -- statsmodels'te kategorik
(multinomial) karma-etkiler modeli pratik desteklenmediği için, mixedlm/BLUP
yerine her (öğrenci, FAZ) hücresinde EN SIK görülen N gün (frekans sıralaması)
hesaplanır. N faza göre değişir -- normal haftada etkileşim daha dağınık
olduğundan 3 gün, sınav öncesi ~7 günlük pencerede etkileşim daha yoğun
olduğundan 4 gün istenir (bkz. FAZ_TOP_N):

  - Bir öğrencinin bir fazda >= GUN_MIN_FAZ_GOZLEM_ESIGI gözlemi varsa, o
    öğrenci-faz kombinasyonu için KENDİ en sık N günü kullanılır (Seçenek B
    davranışı, bireysel). Öğrencinin kendi verisinde N'den az farklı gün
    varsa (az gözlemle sınırlı çeşitlilik), kalan sıralar POPÜLASYONUN top-N
    listesinden (bireyselde zaten olanlar hariç) tamamlanır -- her satırın
    `yontem` kolonu bu günün bireysel mi yoksa popülasyonla mı tamamlandığını
    gösterir.
  - Öğrencinin fazda hiç yeterli verisi yoksa VE geçmiş bir dönemden o
    öğrenci-faz için bireysel (Seçenek B kökenli) bir sonuç varsa, popülasyona
    düşmeden ÖNCE o sonuç "önsel" olarak kullanılır (Seçenek C --
    `secenek_c_onceki_donem_onseli`). Gerekçe: pipeline her dönemi kendi
    verisiyle SIFIRDAN çalıştırır (bkz. aşağıdaki ÖNEMLİ not) -- bu da
    geçmişte güçlü bir bireysel örüntüsü kanıtlanmış bir öğrenciyi, yeni
    dönemin ilk haftalarında (henüz GUN_MIN_FAZ_GOZLEM_ESIGI'ye ulaşmadan)
    sıfırdan bir yabancı gibi (saf popülasyon) ele almak anlamına gelirdi --
    oysa elimizde zaten onun hakkında güçlü bir sinyal var. Geçmiş dönemin
    kendisi de fallback/önsel ise (gerçek bireysel sinyal değilse) zincirleme
    yapılmaz, doğrudan popülasyona düşülür.

    Önsel kaynağı DB'dir, CSV DEĞİL (kullanıcı kararı) -- `gun_tahmini_gecmisi`
    tablosu, öğrenci x (yıl, dönem) x FAZ bazında geçmişte üretilmiş TÜM
    sonuçları biriktirir. Her çalıştırmada: (1) aktif dönem DIŞINDAKİ, veri
    tabanında görülen ama `gun_tahmini_gecmisi`'nde henüz kaydı olmayan her
    dönem otomatik hesaplanıp bu tabloya yazılır (bkz. `TAKVIM_KAYITLARI`,
    `mesaj_zamanlamasi_pipeline-v2.py`), (2) her öğrenci-faz için KİŞİ BAZLI EN
    SON önsel (hangi geçmiş dönemde olursa olsun, sırf "bir önceki dönem"
    değil -- örn. bir dönem ara vermiş öğrenci için de en son bilinen bireysel
    sinyal bulunur) DB'den okunur, (3) aktif dönemin kendi sonucu da hesaplanır
    hesaplanmaz aynı tabloya yazılır -- böylece bir SONRAKİ dönem çalıştığında
    bu dönemin önseli otomatik hazır olur.
  - Öğrencinin fazda hiç yeterli verisi yoksa VE önceki dönem önseli de yoksa
    (yeni öğrenci veya önceki dönemde de fallback), o fazdaki POPÜLASYON
    genelinde (>= MIN_GOZLEM_ESIGI toplam gözlemi olan öğrenci havuzunda) en
    sık N gün kullanılır (Seçenek A davranışı). Bu fallback yolu, hiç
    davranışsal verisi olmayan (n_faz=0) öğrencileri de otomatik kapsar --
    saat pipeline'ındaki gibi ayrı bir cold-start adımına gerek kalmaz.

ÖNEMLİ: GUN_MIN_FAZ_GOZLEM_ESIGI, saat modelinin MIN_FAZ_GOZLEM_ESIGI'sinden
(mixedlm BLUP shrinkage formülünden türetilmiş) KASITLI olarak AYRI ve FARKLI
bir sabittir -- o formül (sigma^2/tau^2) sürekli/dairesel regresyona özgüdür,
buradaki top-N mod/frekans mekanizmasına aktarılamaz (bkz. proje sohbet
geçmişi). Bunun yerine ampirik alt-örnekleme (subsampling) ile türetildi:
veri-zengin öğrencilerin (>=40 gözlem) TÜM verisinden çıkan top-N "gerçek"
kabul edilip, farklı n boyutlarında rastgele alt-örneklemlerin bu "gerçek"le
örtüşme oranı ölçüldü (bkz. python/_sim_gun_esik.py). %70 hedef örtüşme oranını
ilk aşan n=10 çıktı -- üç fazda da aynı (top_n 3 veya 4 olsa da), bu yüzden
faza özel ayrım yerine tek sabit kullanılıyor. Not: n=top_n (örn. 4) ile
örtüşme sadece ~%55 idi -- top-N kadar günü anlamlı sıralamak için top_n'in
kendisi değil, birkaç katı gözlem gerekiyor.

Çıktı UZUN (long) formattadır: her (öğrenci, FAZ) için `top_n` kadar satır
üretilir, `gun_sira` (1..top_n) o günün sıralamadaki yerini gösterir.

KULLANIM (python container'ı içinde):
    docker compose exec python python mesaj_zamanlamasi_gun_hibrit.py
"""

import importlib.util
import pathlib

import json
import pandas as pd
import warnings

from db import get_connection, get_engine

warnings.filterwarnings('ignore')

GECMIS_TABLOSU = 'gun_tahmini_gecmisi'

# Modül adı tire içerdiği için (`mesaj_zamanlamasi_pipeline-v2.py`) doğrudan import
# edilemiyor; mesaj_zamanlamasi_hibrit.py'deki gibi dosyayı yoluna göre yüklüyoruz --
# veri yükleme/öznitelik fonksiyonlarını yeniden yazıp dosyaların birbirinden
# sapmasına yol açmamak için.
_pipeline_path = pathlib.Path(__file__).parent / "mesaj_zamanlamasi_pipeline-v2.py"
_spec = importlib.util.spec_from_file_location("mesaj_zamanlamasi_pipeline_v2", _pipeline_path)
pipeline = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(pipeline)

FAZLAR = pipeline.FAZLAR

MIN_GOZLEM_ESIGI = pipeline.MIN_GOZLEM_ESIGI    # popülasyon havuzuna dahil olmak için (toplam)

# Saat modelinin MIN_FAZ_GOZLEM_ESIGI'sinden KASITLI olarak ayrı -- ampirik
# alt-örnekleme ile türetildi (bkz. python/_sim_gun_esik.py): veri-zengin
# öğrencilerin TÜM verisinden çıkan top-N "gerçek" kabul edilip, n boyutundaki
# alt-örneklemlerin bununla örtüşme oranı ölçüldü; %70 hedef oranı ilk aşan
# n=10, üç fazda da (normal_hafta/ara_sinav_oncesi/final_oncesi) aynı çıktı.
GUN_MIN_FAZ_GOZLEM_ESIGI = 10

GUN_ADLARI = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar']

# Faz başına istenen gün sayısı: normal haftada etkileşim günlere daha dağınık,
# sınav öncesi ~7 günlük pencerede daha yoğun olduğundan farklı N kullanılır.
FAZ_TOP_N = {'normal_hafta': 3, 'ara_sinav_oncesi': 4, 'final_oncesi': 4}


# =====================================================================
# GÜN HİBRİT MODEL (KATEGORİK, TOP-N)
# =====================================================================

def _top_n_gun(seri, n):
    """gun_index serisinde en sık görülen n günü (sayıma göre azalan, eşitlikte
    gün indeksine göre artan -- deterministik) döner. Serideki farklı gün
    sayısı n'den azsa, olabildiğince az elemanlı liste döner. Seri boşsa []
    döner."""
    if len(seri) == 0:
        return []
    sayimlar = seri.value_counts().reset_index()
    sayimlar.columns = ['gun_index', 'sayim']
    sayimlar = sayimlar.sort_values(['sayim', 'gun_index'], ascending=[False, True])
    return sayimlar['gun_index'].head(n).astype(int).tolist()


def _onceki_donem_gunleri(onceki_donem_df, ogrenci_no, faz):
    """Önceki dönemin gün tahmini çıktısından (aynı uzun formatta bir DataFrame),
    o öğrenci-faz için Seçenek B kökenli (bireysel sinyal İÇEREN) günleri,
    sıralarına göre döner. Önceki dönemde de fallback'e (secenek_a_fallback)
    düşülmüşse boş liste döner -- fallback'ten fallback'e zincirlemek bilgi
    katmaz, doğrudan güncel popülasyona düşülmesi daha doğrudur."""
    if onceki_donem_df is None:
        return []
    satirlar = onceki_donem_df[
        (onceki_donem_df['ogrenci_no'] == ogrenci_no)
        & (onceki_donem_df['FAZ'] == faz)
        & (onceki_donem_df['yontem'].str.startswith('secenek_b'))
    ].sort_values('gun_sira')
    return satirlar['tahmini_gun_index'].astype(int).tolist()


def model_kur_ve_tahmin_et_gun_hibrit(mat, dem, min_gozlem=MIN_GOZLEM_ESIGI,
                                       min_faz_gozlem=GUN_MIN_FAZ_GOZLEM_ESIGI,
                                       faz_top_n=None, onceki_donem_df=None):
    faz_top_n = faz_top_n or FAZ_TOP_N
    counts_toplam = mat.groupby('ogrenci_no').size()
    yeterli = counts_toplam[counts_toplam >= min_gozlem].index
    sub = mat[mat['ogrenci_no'].isin(yeterli)]

    # Popülasyon (fallback) top-N günü: FAZ başına, 'yeterli' öğrenci havuzunun
    # tamamından hesaplanır -- saat modelindeki sabit (popülasyon) etkiye karşılık gelir.
    populasyon_top_gun = {
        faz: _top_n_gun(sub.loc[sub['FAZ'] == faz, 'gun_index'], faz_top_n[faz])
        for faz in FAZLAR
    }

    faz_counts = mat.groupby(['ogrenci_no', 'FAZ']).size().unstack(fill_value=0)
    for faz in FAZLAR:
        if faz not in faz_counts.columns:
            faz_counts[faz] = 0

    sonuclar = []
    # dem'deki TÜM kayıtlı öğrenciler kapsanır (mat'te hiç satırı olmayanlar dahil) --
    # onlar için her fazda n_faz=0 olacağından otomatik olarak popülasyon top-N'ine düşülür.
    for ogrenci_no in dem['ogrenci_no']:
        n_toplam = int(counts_toplam.get(ogrenci_no, 0))
        ogrenci_mat = mat[mat['ogrenci_no'] == ogrenci_no]

        for faz in FAZLAR:
            n = faz_top_n[faz]
            n_faz = int(faz_counts.loc[ogrenci_no, faz]) if ogrenci_no in faz_counts.index else 0
            yeterli_veri = n_faz >= min_faz_gozlem
            pop_gunler = populasyon_top_gun[faz]

            if yeterli_veri:
                bireysel_gunler = _top_n_gun(
                    ogrenci_mat.loc[ogrenci_mat['FAZ'] == faz, 'gun_index'], n)
                gunler_kaynak = [(g, 'secenek_b_bireysel') for g in bireysel_gunler]
                # Öğrencinin kendi verisinde n farklı gün yoksa (az gözlemle sınırlı
                # çeşitlilik), kalan sıralar popülasyonun top-N'inden (bireyselde
                # zaten olanlar hariç) tamamlanır.
                for g in pop_gunler:
                    if len(gunler_kaynak) >= n:
                        break
                    if g not in bireysel_gunler:
                        gunler_kaynak.append((g, 'secenek_b_populasyonla_tamamlanmis'))
            else:
                onceki_gunler = _onceki_donem_gunleri(onceki_donem_df, ogrenci_no, faz)
                if onceki_gunler:
                    gunler_kaynak = [(g, 'secenek_c_onceki_donem_onseli') for g in onceki_gunler[:n]]
                    var_olanlar = set(onceki_gunler[:n])
                    for g in pop_gunler:
                        if len(gunler_kaynak) >= n:
                            break
                        if g not in var_olanlar:
                            gunler_kaynak.append((g, 'secenek_c_onceki_donem_onseli_populasyonla_tamamlanmis'))
                            var_olanlar.add(g)
                else:
                    gunler_kaynak = [(g, 'secenek_a_fallback') for g in pop_gunler]

            for sira, (gun_idx, yontem) in enumerate(gunler_kaynak, start=1):
                sonuclar.append({
                    'ogrenci_no': ogrenci_no,
                    'FAZ': faz,
                    'gun_sira': sira,
                    'top_n': n,
                    'n_gozlem_faz': n_faz,
                    'n_gozlem_toplam': n_toplam,
                    'tahmini_gun_index': gun_idx,
                    'tahmini_gun': GUN_ADLARI[gun_idx],
                    'yontem': yontem,
                })

    return pd.DataFrame(sonuclar)


# =====================================================================
# GEÇMİŞ DÖNEM ÖNSELLERİ (Seçenek C, DB kaynaklı)
# =====================================================================
#
# `gun_tahmini_gecmisi`: öğrenci x (yıl, dönem) x FAZ bazında, o dönem için
# üretilmiş TÜM tahmin_df satırlarını (yontem'i ne olursa olsun) biriktiren
# bir tablo. Bu tablo PHP tarafında YOK -- Doctrine şemasının bir parçası
# değil, çünkü PHP hiçbir zaman okumuyor/yazmıyor; tamamen bu script'in KENDİ
# geçmiş dönem önsellerini (Seçenek C) DB'de saklamak için kullandığı dahili
# bir durum tablosu. Bu yüzden Python kendi şemasını kendi yönetir (idempotent
# CREATE TABLE IF NOT EXISTS) -- ayrı bir PHP migration'ına ihtiyaç yok.

def _gecmis_tablosunu_hazirla(conn):
    with conn.cursor() as cursor:
        cursor.execute(f"""
            CREATE TABLE IF NOT EXISTS {GECMIS_TABLOSU} (
                ogrenci_no INT NOT NULL,
                yil INT NOT NULL,
                donem INT NOT NULL,
                faz VARCHAR(32) NOT NULL,
                gun_sira INT NOT NULL,
                tahmini_gun_index INT NOT NULL,
                tahmini_gun VARCHAR(16) NOT NULL,
                yontem VARCHAR(64) NOT NULL,
                hesaplanma_tarihi DATETIME NOT NULL,
                PRIMARY KEY (ogrenci_no, yil, donem, faz, gun_sira),
                INDEX idx_gun_tahmini_gecmisi_ogrenci_faz (ogrenci_no, faz)
            ) DEFAULT CHARACTER SET utf8mb4
        """)
    conn.commit()


def gecmis_donem_var_mi(conn, yil, donem):
    """Bu (yil, donem) için `gun_tahmini_gecmisi`'nde zaten kayıt var mı? Geçmiş
    dönemlerin verisi sabit olduğundan (tarihte kalmış bir dönem bir daha
    değişmez), bir kere hesaplanan dönem TEKRAR hesaplanmaz."""
    with conn.cursor() as cursor:
        cursor.execute(
            f"SELECT 1 FROM {GECMIS_TABLOSU} WHERE yil = %s AND donem = %s LIMIT 1",
            (yil, donem),
        )
        return cursor.fetchone() is not None


def gecmis_donemi_yaz(conn, tahmin_df, yil, donem):
    """`tahmin_df`'in TÜMÜNÜ (yontem'i ne olursa olsun -- Seçenek C'nin ileride
    hangi satırların bireysel olduğunu filtreleyebilmesi için) bir (yil, donem)
    için `gun_tahmini_gecmisi`'ye yazar. Aynı (yil, donem) için önce eski
    satırlar silinir (yeniden çalıştırma idempotent olsun diye), sonra güncel
    satırlar eklenir. Döner: yazılan satır sayısı."""
    if 0 == len(tahmin_df):
        return 0

    satirlar = [
        (
            int(r.ogrenci_no), yil, donem, r.FAZ, int(r.gun_sira),
            int(r.tahmini_gun_index), r.tahmini_gun, r.yontem,
        )
        for r in tahmin_df.itertuples(index=False)
    ]

    with conn.cursor() as cursor:
        cursor.execute(f"DELETE FROM {GECMIS_TABLOSU} WHERE yil = %s AND donem = %s", (yil, donem))
        cursor.executemany(f"""
            INSERT INTO {GECMIS_TABLOSU}
                (ogrenci_no, yil, donem, faz, gun_sira, tahmini_gun_index, tahmini_gun, yontem, hesaplanma_tarihi)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW())
        """, satirlar)
    conn.commit()

    return len(satirlar)


def kisi_bazli_son_onselleri_getir(conn, aktif_yil, aktif_donem):
    """Her (ogrenci_no, FAZ) için, aktif dönem HARİÇ, en güncel (yil, donem)'de
    Seçenek B kökenli (bireysel sinyal İÇEREN) satırları döner --
    `_onceki_donem_gunleri()`'nin beklediğiyle aynı formatta bir DataFrame
    (ogrenci_no, FAZ, gun_sira, tahmini_gun_index, yontem).

    "Kişi bazlı en son önsel" (kullanıcı kararı): sırf BİR ÖNCEKİ dönem değil,
    öğrencinin geçmişte bireysel sinyalinin kanıtlandığı EN SON dönem
    kullanılır -- örn. bir dönem ara vermiş bir öğrenci için de en son bilinen
    gerçek sinyal bulunur, aradaki boş dönem yüzünden popülasyona düşülmez.

    Ham pymysql cursor'la (SQLAlchemy engine yerine) yazıldı -- `%s` (pymysql
    paramstyle'i) ile SQLAlchemy'nin `text()` bind stiline karışma riskini
    baştan ortadan kaldırmak için (bkz. db.py'deki diğer yazma fonksiyonlarıyla
    aynı örüntü).
    """
    aktif_kodu = aktif_yil * 100 + aktif_donem
    sorgu = f"""
        SELECT g.ogrenci_no, g.faz AS FAZ, g.gun_sira, g.tahmini_gun_index, g.yontem
        FROM {GECMIS_TABLOSU} g
        INNER JOIN (
            SELECT ogrenci_no, faz, MAX(yil * 100 + donem) AS son_donem_kodu
            FROM {GECMIS_TABLOSU}
            WHERE yontem LIKE 'secenek_b%%' AND (yil * 100 + donem) < %s
            GROUP BY ogrenci_no, faz
        ) son ON son.ogrenci_no = g.ogrenci_no AND son.faz = g.faz
             AND (g.yil * 100 + g.donem) = son.son_donem_kodu
        WHERE g.yontem LIKE 'secenek_b%%'
        ORDER BY g.ogrenci_no, g.faz, g.gun_sira
    """
    with conn.cursor() as cursor:
        cursor.execute(sorgu, (aktif_kodu,))
        satirlar = cursor.fetchall()

    return pd.DataFrame(satirlar, columns=['ogrenci_no', 'FAZ', 'gun_sira', 'tahmini_gun_index', 'yontem'])


def gecmis_donemleri_hesapla_ve_yaz(engine, conn, aktif_yil, aktif_donem):
    """Aktif dönem DIŞINDA, DB'de (`materyal_erisim_log`/`sinav_sonucu`) verisi
    bulunan ama `gun_tahmini_gecmisi`'nde henüz kaydı olmayan her dönemi bulur,
    o dönemin KENDİ verisiyle ve KENDİ takvimiyle (`pipeline.TAKVIM_KAYITLARI`)
    gün modelini çalıştırır ve sonucu `gun_tahmini_gecmisi`'ye yazar (kullanıcı
    kararı: "aktif dönem dışındaki dönemlerin verileri için bu hesaplama
    yapılmalı"). Takvim kaydı olmayan bir dönem varsa (FAZ ataması yapılamaz)
    UYARIYLA atlanır.

    Not: Bu adım geçmiş dönemi SIFIRDAN (kendi onceki_donem_df'i olmadan)
    hesaplar -- zincirleme (geçmişin geçmişi) yapılmaz; modül docstring'indeki
    "fallback'ten fallback'e zincirlemek bilgi katmaz" ilkesiyle tutarlı.
    """
    tum_donemler = pipeline.tum_donem_kombinasyonlarini_bul(engine)
    gecmis_donemler = [(y, d) for (y, d) in tum_donemler if (y, d) != (aktif_yil, aktif_donem)]

    for (y, d) in gecmis_donemler:
        if gecmis_donem_var_mi(conn, y, d):
            continue

        takvim = pipeline.TAKVIM_KAYITLARI.get((y, d))
        if takvim is None:
            print(f"   ! {y}/{d}: TAKVIM_KAYITLARI'nda kaydı yok, atlanıyor.")
            continue

        print(f"   -> {y}/{d} geçmiş dönemi hesaplanıyor (gun_tahmini_gecmisi'nde henüz yok)...")
        g_mat, _, g_dem = pipeline.veriyi_yukle(engine, yil=y, donem=d)
        g_mat = pipeline.oznitelik_olustur(g_mat, takvim)
        g_dem = pipeline.demografik_hazirla(g_dem)
        g_tahmin_df = model_kur_ve_tahmin_et_gun_hibrit(g_mat, g_dem)
        yazilan = gecmis_donemi_yaz(conn, g_tahmin_df, y, d)
        print(f"      {yazilan} satır gun_tahmini_gecmisi'ne yazıldı.")


# Python'un FAZ adları -> PHP `Phase` enum değerleri (src/Enum/Phase.php) --
# observed_days JSON'ının anahtarları PHP'de doğrudan Phase::value ile
# eşleşsin diye (kullanıcı kararı: Phase.php Python'daki gibi ikiye ayrıldı).
FAZ_TO_PHASE_ADI = {
    'normal_hafta': 'Normal',
    'ara_sinav_oncesi': 'Ara_Sinav_Oncesi',
    'final_oncesi': 'Final_Oncesi',
}


def student_profile_gozlem_gunlerini_yaz(tahmin_df):
    """`tahmin_df`'i (uzun format: ogrenci_no, FAZ, gun_sira, tahmini_gun) her
    öğrenci için faz->sıralı gün listesi JSON'ına indirger ve
    `student_profile.observed_days`'e yazar -- CSV'ye YAZMAZ (kullanıcı
    kararı). Örnek değer:
        {"Normal": ["Pazartesi", "Çarşamba", "Cuma"], "Ara_Sinav_Oncesi": [...], "Final_Oncesi": [...]}

    `student_profile` satırı henüz yoksa varsayılan bir profille birlikte
    oluşturulur -- bkz. mesaj_zamanlamasi_hibrit.py:student_profile_gozlem_saatini_yaz().

    Döner: yazılan öğrenci satırı sayısı.
    """
    siralanmis = tahmin_df.sort_values(['ogrenci_no', 'FAZ', 'gun_sira'])

    satirlar = []
    for ogrenci_no, grup in siralanmis.groupby('ogrenci_no'):
        json_obj = {}
        for faz, faz_grubu in grup.groupby('FAZ'):
            phase_adi = FAZ_TO_PHASE_ADI[faz]
            json_obj[phase_adi] = faz_grubu.sort_values('gun_sira')['tahmini_gun'].tolist()
        satirlar.append((int(ogrenci_no), json.dumps(json_obj, ensure_ascii=False)))

    sorgu = """
        INSERT INTO student_profile
            (student_id, current_state, messages_sent_this_week, updated_at, observed_days)
        VALUES (%s, 'YENİ', 0, NOW(), %s)
        ON DUPLICATE KEY UPDATE observed_days = VALUES(observed_days)
    """
    with get_connection() as conn, conn.cursor() as cursor:
        cursor.executemany(sorgu, satirlar)
        conn.commit()

    return len(satirlar)


# =====================================================================
# ANA AKIŞ
# =====================================================================

def main(yil=2024, donem=2):
    """`yil`/`donem`: bu çalıştırmanın AKTİF dönemi -- `student_profile.observed_days`'e
    yazılacak (yani "şu an geçerli") sonuç bu dönem için hesaplanır. Seçenek C'nin
    önsel kaynağı artık CSV DEĞİL, DB'dir (kullanıcı kararı) -- bkz. modül
    docstring'i ve `gecmis_donemleri_hesapla_ve_yaz()`/`kisi_bazli_son_onselleri_getir()`.
    """
    engine = get_engine()

    with get_connection() as conn:
        _gecmis_tablosunu_hazirla(conn)

        print("0) Aktif dönem dışındaki geçmiş dönemler taranıyor "
              "(gun_tahmini_gecmisi'nde eksik olanlar hesaplanıp yazılıyor)...")
        gecmis_donemleri_hesapla_ve_yaz(engine, conn, yil, donem)

        print("0b) Kişi bazlı en son önseller DB'den okunuyor...")
        onceki_donem_df = kisi_bazli_son_onselleri_getir(conn, yil, donem)
        print(f"    {onceki_donem_df['ogrenci_no'].nunique() if len(onceki_donem_df) else 0} "
              f"öğrenci için geçmiş dönemden önsel bulundu.")

        print(f"1) Veri yükleniyor ({yil}/{donem})...")
        takvim = pipeline.TAKVIM_KAYITLARI.get((yil, donem), pipeline.BAHAR_2024_TAKVIMI)
        mat, _, dem = pipeline.veriyi_yukle(engine, yil=yil, donem=donem)

        print("2) Öznitelikler oluşturuluyor...")
        mat = pipeline.oznitelik_olustur(mat, takvim)
        dem = pipeline.demografik_hazirla(dem)

        print("3) Gün hibrit modeli (öğrenci x faz bazlı Seçenek A/B/C, kategorik top-N) kuruluyor...")
        tahmin_df = model_kur_ve_tahmin_et_gun_hibrit(mat, dem, onceki_donem_df=onceki_donem_df)

        dagilim = tahmin_df['yontem'].value_counts()
        print("\nYöntem dağılımı (gün satırı bazında):")
        print(dagilim.to_string())
        toplam = len(tahmin_df)
        print(f"\n-> %{100 * dagilim.get('secenek_b_bireysel', 0) / toplam:.1f} bireysel veriden, "
              f"%{100 * dagilim.get('secenek_b_populasyonla_tamamlanmis', 0) / toplam:.1f} "
              f"bireysel+popülasyonla tamamlanmış, "
              f"%{100 * dagilim.get('secenek_c_onceki_donem_onseli', 0) / toplam:.1f} "
              f"geçmiş dönem önseli, "
              f"%{100 * dagilim.get('secenek_c_onceki_donem_onseli_populasyonla_tamamlanmis', 0) / toplam:.1f} "
              f"geçmiş dönem önseli+popülasyonla tamamlanmış, "
              f"%{100 * dagilim.get('secenek_a_fallback', 0) / toplam:.1f} tamamen popülasyon "
              f"fallback (Seçenek A / cold-start) günlerinden oluşuyor.")

        print("\nSonuç örneği:")
        print(tahmin_df.head(20).to_string(index=False))

        print(f"\n4) Bu dönemin ({yil}/{donem}) sonucu gun_tahmini_gecmisi'ne yazılıyor "
              f"(gelecek dönemler için önsel olsun diye)...")
        gecmis_donemi_yaz(conn, tahmin_df, yil, donem)

    print("\n5) student_profile.observed_days yazılıyor...")
    yazilan = student_profile_gozlem_gunlerini_yaz(tahmin_df)
    print(f"Yazıldı: {yazilan} öğrenci satırı.")


if __name__ == "__main__":
    main()
