"""
Hibrit (Öğrenci x Faz Bazlı) Seçenek A/B Kararı
====================================================
`mesaj_zamanlamasi_pipeline-v2.py`'deki `rastgele_egim_icin_veri_yeterli_mi()`
kararı GLOBAL'dir: öğrencilerin en az %50'si her fazda >= MIN_FAZ_GOZLEM_ESIGI
gözleme sahipse TÜM öğrenciler için Seçenek B (kişiye özel faz eğimi), değilse
TÜM öğrenciler için Seçenek A (sabit/ortak faz etkisi) kullanılır.

Bu dosya, kararı (öğrenci, faz) ÇİFTİ düzeyine indirir, ve SADECE faz-bazlı eşiği
(MIN_FAZ_GOZLEM_ESIGI) kullanır -- toplam gözlem eşiği (eski MIN_GOZLEM_ESIGI)
YOKTUR, ayrı bir cold-start adımına da gerek kalmaz:
  - Bir öğrencinin bir fazda >= MIN_FAZ_GOZLEM_ESIGI gözlemi varsa, o öğrenci-faz
    kombinasyonu için KENDİ BLUP faz-eğimi kullanılır (Seçenek B davranışı).
  - Yetersizse, o öğrenci-faz kombinasyonu için kişiye özel (güvenilmez) slope
    BLUP'una güvenilmez; popülasyon (sabit) faz etkisine düşülür (Seçenek A
    davranışı) -- öğrencinin mat'te en az 1 gözlemi varsa buna kendi random
    intercept'i de eklenir, hiç gözlemi yoksa (mixedlm'in random_effects'inde
    bulunmaz -- rastgele etki tahmin edilemez) sadece sabit etki kullanılır.
    Her iki durum da 'secenek_a_fallback' olarak etiketlenir; tek fark verinin
    hiç mi yoksa yetersiz mi olduğu (`n_gozlem_faz`/`n_gozlem_toplam` kolonlarından
    ayırt edilebilir).

ÖNEMLİ: mixedlm modeli TEK SEFER "(1+FAZ|öğrenci)" formülüyle, mat'te EN AZ 1
gözlemi olan TÜM öğrenciler üzerinde fit edilir -- öğrenci bazında ayrı
formüllerle fit etmek mümkün değil (bkz. mesaj_zamanlamasi_pipeline-v2.py'deki
tartışma). Ayrım, model fit edildikten SONRA, tahmin üretilirken (post-hoc)
uygulanır: düşük veri olan öğrenci-faz kombinasyonları için o öğrencinin kendi
slope BLUP'u kullanılmaz, doğrudan sabit etkiye düşülür. Çok az (1-2) toplam
gözlemi olan öğrencilerin de fit'e dahil edilmesi, mixedlm'in doğal shrinkage
davranışına bırakılır (rastgele etkileri veri yokluğundan zaten popülasyon
ortalamasına yakınsar); bu, eski MIN_GOZLEM_ESIGI eşiğinin varlığından daha
tutarlı bir metodolojidir çünkü tüm karar TEK bir mekanizmaya (faz-bazlı eşik)
bağlanmış olur.

HASSASİYET HARMANI (2026-10-16, kullanıcı kararı -- bkz. proje sohbet geçmişi
/ "Hassasiyet Harmanı" tasarım notu): Seçenek A/B kararı YUKARIDAKİ gibi kalır,
ama `yontem='secenek_a_fallback'` çıkan bir öğrenci-faz için, önceki dönemde
GERÇEK bir Seçenek B sonucu varsa (bkz. `saat_tahmini_gecmisi` /
`kisi_bazli_son_onselleri_getir()`), mevcut dönemin tahmini o sonuçla
hassasiyete (1/varyans) göre ağırlıklandırılıp harmanlanır -- popülasyona
düşmeden önce. Ağırlık formülü (`ağırlık_mevcut(n)=n/(n+n₀)`) sürekli/kademeli:
mevcut dönem verisi arttıkça önceki dönemin etkisi otomatik söner, ayrı bir
"sadece eşik altındaysa" dalı yok. n₀, önceki dönemin "kaç yeni gözleme
eşdeğer" güçte olduğunu, dönem geçişi belirsizliğine (Q -- "öğrencinin GERÇEK
tercihi dönemler arası ne kadar kayabilir", henüz ampirik türetilmedi, bkz.
Q_ORANI) bir TAVAN koyarak hesaplar -- sınırsız geçmiş veri sınırsız güvene
dönüşmez. Detaylar `model_kur_ve_tahmin_et_hibrit()`'in içindeki ilgili bloka
bakınız.

KULLANIM (python container'ı içinde):
    docker compose exec python python mesaj_zamanlamasi_hibrit.py
"""

import importlib.util
import pathlib

import numpy as np
import pandas as pd
import statsmodels.formula.api as smf
import warnings

from db import get_connection, get_engine

warnings.filterwarnings('ignore')

# Modül adı tire içerdiği için (`mesaj_zamanlamasi_pipeline-v2.py`) doğrudan import
# edilemiyor; veri yükleme/öznitelik/filtre fonksiyonlarını yeniden yazıp iki dosyanın
# birbirinden sapmasına yol açmamak için dosyayı yoluna göre yüklüyoruz.
_pipeline_path = pathlib.Path(__file__).parent / "mesaj_zamanlamasi_pipeline-v2.py"
_spec = importlib.util.spec_from_file_location("mesaj_zamanlamasi_pipeline_v2", _pipeline_path)
pipeline = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(pipeline)

FAZLAR = pipeline.FAZLAR

MIN_FAZ_GOZLEM_ESIGI = pipeline.MIN_FAZ_GOZLEM_ESIGI    # (öğrenci, faz) için Seçenek B eşiği

# Gün modelinin (`mesaj_zamanlamasi_gun_hibrit.py`) `gun_tahmini_gecmisi`'yle AYNI
# desen: Doctrine şemasının parçası değil, PHP hiç okumuyor/yazmıyor -- Python
# kendi şemasını kendi yönetir (idempotent CREATE TABLE IF NOT EXISTS), ayrı bir
# PHP migration'ına ihtiyaç yok. Tek fark: gün modeli sadece günler+yöntem
# saklarken, burada `n_gozlem` da saklanıyor -- gelecekteki hassasiyet harmanı
# (Seçenek C'nin saat modeli karşılığı; bkz. proje sohbet geçmişi) λ_önceki'yi
# hesaplamak için gözlem SAYISINA ihtiyaç duyuyor, sıralı bir listeye değil.
GECMIS_TABLOSU = 'saat_tahmini_gecmisi'

# Hassasiyet harmanı (n₀/Q formülü) -- bkz. proje sohbet geçmişi / "Hassasiyet
# Harmanı" tasarım notu. Q, dönem geçişi belirsizliği: öğrencinin GERÇEK
# tercihinin (iş durumu, ders programı değişebilir) dönemler arası ne kadar
# kayabileceği -- önceki dönem ne kadar çok veriye dayansa da güvenini bir
# tavanda (n₀_max = σ²/Q) tutar. Henüz AMPİRİK olarak türetilmedi (en az 1
# tamamlanmış dönem geçişi -- ideali 2 -- gerektiriyor, DB'de şu an 0 geçiş
# var) -- τ²(faz)'in bir ORANI olarak tutuluyor ki sin/cos ve faz başına
# otomatik ölçeklensin. DEĞİŞTİRİLECEK: ikinci gerçek dönem verisi geldiğinde
# ampirik olarak yeniden kalibre edilmeli.
Q_ORANI = 1 / 3


def _tau2_birlesik(cov_re, faz):
    """Bir FAZ'ın kişiler-arası (between-student) birleşik varyansı --
    normal_hafta için sadece Var(Group) (random intercept varyansı); diğer
    fazlar için Var(Group) + Var(faz_slope) + 2*Cov(Group, faz_slope).
    `MIN_FAZ_GOZLEM_ESIGI`'nin türetildiği `_sim_n_esik.py`'deki AYNI formül --
    kod tekrarını önlemek için buraya taşınmadı, çünkü o dosya kalıcı değil
    (bkz. kendi docblock'u); burada hassasiyet harmanı için yeniden üretiliyor."""
    if faz == 'normal_hafta':
        return cov_re.loc['Group', 'Group']
    kolon = f'C(FAZ)[T.{faz}]'
    if kolon not in cov_re.columns:
        return None
    return (cov_re.loc['Group', 'Group']
            + cov_re.loc[kolon, kolon]
            + 2 * cov_re.loc['Group', kolon])


# =====================================================================
# HİBRİT MODEL
# =====================================================================

def model_kur_ve_tahmin_et_hibrit(mat, dem, min_faz_gozlem=MIN_FAZ_GOZLEM_ESIGI,
                                   onceki_donem_df=None):
    sub = mat.copy()
    sub['ogrenci_no_str'] = sub['ogrenci_no'].astype(str)
    sub['FAZ'] = pd.Categorical(sub['FAZ'], categories=FAZLAR)

    counts_toplam = mat.groupby('ogrenci_no').size()

    faz_counts = sub.groupby(['ogrenci_no', 'FAZ']).size().unstack(fill_value=0)
    for faz in FAZLAR:
        if faz not in faz_counts.columns:
            faz_counts[faz] = 0

    # Model tek seferde, (1+FAZ|öğrenci) formülüyle, mat'te en az 1 gözlemi olan
    # TÜM öğrenciler üzerinde fit edilir.
    m_sin = smf.mixedlm("saat_sin ~ C(FAZ)", sub, groups=sub['ogrenci_no_str'],
                         re_formula="~C(FAZ)")
    r_sin = m_sin.fit()
    m_cos = smf.mixedlm("saat_cos ~ C(FAZ)", sub, groups=sub['ogrenci_no_str'],
                         re_formula="~C(FAZ)")
    r_cos = m_cos.fit()

    # Hassasiyet harmanı için her fazın τ²'si (sin ve cos ayrı modeller olduğu
    # için ayrı ayrı) bir kere hesaplanır -- öğrenci döngüsünde tekrar fit
    # edilmiyor, sadece ZATEN fit edilmiş r_sin/r_cos'un cov_re'sinden okunuyor.
    tau2_sin = {faz: _tau2_birlesik(r_sin.cov_re, faz) for faz in FAZLAR}
    tau2_cos = {faz: _tau2_birlesik(r_cos.cov_re, faz) for faz in FAZLAR}
    sigma2_sin, sigma2_cos = r_sin.scale, r_cos.scale

    # `onceki_donem_df` (ogrenci_no, FAZ, n_gozlem, tahmini_saat, yontem) --
    # `kisi_bazli_son_onselleri_getir()`'den geliyorsa SADECE secenek_b kökenli
    # satırlar içerir, yani burada bir satır bulmak başlı başına "gerçek
    # bireysel önceki dönem var" gate'inin kendisi -- ayrı bir eşik kontrolüne
    # gerek yok. O(1) bakış için (ogrenci_no, faz) -> (n_onceki, tahmini_onceki).
    onceki_lookup = {}
    if onceki_donem_df is not None and len(onceki_donem_df) > 0:
        for r in onceki_donem_df.itertuples(index=False):
            onceki_lookup[(int(r.ogrenci_no), r.FAZ)] = (int(r.n_gozlem), float(r.tahmini_saat))

    sonuclar = []
    # dem'deki TÜM kayıtlı öğrenciler kapsanır -- mat'te hiç satırı olmayanlar
    # (n_toplam=0) mixedlm'in random_effects'inde de bulunmaz; rastgele etki
    # tahmin edilemeyeceği için onlara sadece popülasyonun sabit faz ortalaması atanır.
    for ogrenci_no in dem['ogrenci_no']:
        kn = str(ogrenci_no)
        re_sin = r_sin.random_effects.get(kn)
        re_cos = r_cos.random_effects.get(kn)
        n_toplam = int(counts_toplam.get(ogrenci_no, 0))

        for faz in FAZLAR:
            n_faz = int(faz_counts.loc[ogrenci_no, faz]) if ogrenci_no in faz_counts.index else 0
            yeterli_veri = n_faz >= min_faz_gozlem

            sabit_sin = r_sin.fe_params['Intercept'] + (
                r_sin.fe_params.get(f'C(FAZ)[T.{faz}]', 0) if faz != 'normal_hafta' else 0)
            sabit_cos = r_cos.fe_params['Intercept'] + (
                r_cos.fe_params.get(f'C(FAZ)[T.{faz}]', 0) if faz != 'normal_hafta' else 0)

            if re_sin is None or re_cos is None:
                # mat'te hiç gözlemi yok: rastgele etki tahmin edilemez, saf popülasyon ortalaması.
                # Bu da Seçenek A fallback sayılır -- ayrı bir kategori açılmıyor.
                rastgele_sin = 0
                rastgele_cos = 0
                yontem = 'secenek_a_fallback'
            else:
                rastgele_intercept_sin = re_sin.get('Group', 0)
                rastgele_intercept_cos = re_cos.get('Group', 0)

                if yeterli_veri:
                    # Seçenek B: bu öğrenci-faz kombinasyonu için kendi slope BLUP'u da dahil
                    faz_kolon = f'C(FAZ)[T.{faz}]'
                    rastgele_sin = rastgele_intercept_sin + (
                        re_sin.get(faz_kolon, 0) if faz != 'normal_hafta' else 0)
                    rastgele_cos = rastgele_intercept_cos + (
                        re_cos.get(faz_kolon, 0) if faz != 'normal_hafta' else 0)
                    yontem = 'secenek_b_bireysel'
                else:
                    # Seçenek A fallback: sadece random intercept + popülasyon faz etkisi
                    rastgele_sin = rastgele_intercept_sin
                    rastgele_cos = rastgele_intercept_cos
                    yontem = 'secenek_a_fallback'

            tahmini_sin = sabit_sin + rastgele_sin
            tahmini_cos = sabit_cos + rastgele_cos

            # Hassasiyet harmanı: önceki dönemde bu öğrenci-faz için gerçek
            # bireysel bir sonuç varsa (onceki_lookup'ta bulunması bunun
            # KENDİSİ -- bkz. yukarıdaki not), mevcut dönemin tahminini
            # ONUNLA harmanlar. n_faz=0 (hiç mevcut veri yok) dahil HER
            # durumda çalışır -- ağırlık formülü n_faz büyüdükçe önceki
            # dönemin etkisini kendiliğinden söndürür, ayrı bir "sadece
            # eşik altındaysa" koşuluna gerek yok.
            onceki = onceki_lookup.get((ogrenci_no, faz))
            if onceki is not None:
                n_onceki, tahmini_saat_onceki = onceki
                aci_onceki = tahmini_saat_onceki / 24 * 2 * np.pi
                sin_onceki, cos_onceki = np.sin(aci_onceki), np.cos(aci_onceki)

                for bilesen, tau2_map, sigma2, mevcut_deger, onceki_deger in (
                    ('sin', tau2_sin, sigma2_sin, tahmini_sin, sin_onceki),
                    ('cos', tau2_cos, sigma2_cos, tahmini_cos, cos_onceki),
                ):
                    tau2 = tau2_map[faz]
                    if tau2 is None or tau2 <= 0:
                        continue
                    w_bilesen = sigma2 / tau2
                    lambda_onceki = n_onceki / (n_onceki + w_bilesen)
                    q = tau2 * Q_ORANI
                    kalan_belirsizlik = tau2 * (1 - lambda_onceki) + q
                    precision_onceki = 1 / kalan_belirsizlik
                    n0 = sigma2 * precision_onceki
                    agirlik_mevcut = n_faz / (n_faz + n0)
                    harmanli = agirlik_mevcut * mevcut_deger + (1 - agirlik_mevcut) * onceki_deger
                    if bilesen == 'sin':
                        tahmini_sin = harmanli
                    else:
                        tahmini_cos = harmanli

                # n_faz kendi başına yetersizse (<min_faz_gozlem) ama önceki
                # dönem gerçek bireyselse, harman sayesinde tahmin artık salt
                # popülasyon değil -- yöntem buna göre güncellenir. İki eşik de
                # DEĞİŞMİYOR (min_faz_gozlem burada, önceki dönemin kendi
                # secenek_b eşiği zaten kisi_bazli_son_onselleri_getir()'de
                # uygulanmış), sadece "ikisinden biri yeterliyse bireysel say"
                # diye VEYA ile birleşiyor.
                if yontem == 'secenek_a_fallback':
                    yontem = 'secenek_b_bireysel'
                harman_uygulandi = True
            else:
                harman_uygulandi = False

            aci = np.arctan2(tahmini_sin, tahmini_cos)
            if aci < 0:
                aci += 2 * np.pi
            tahmini_saat = aci / (2 * np.pi) * 24

            sonuclar.append({
                'ogrenci_no': ogrenci_no,
                'FAZ': faz,
                'n_gozlem_faz': n_faz,
                'n_gozlem_toplam': n_toplam,
                'tahmini_saat': round(tahmini_saat, 2),
                'yontem': yontem,
                'harman_uygulandi': harman_uygulandi,
            })

    return pd.DataFrame(sonuclar)


# =====================================================================
# GEÇMİŞ DÖNEM ÖNSELLERİ (hassasiyet harmanının n_önceki kaynağı, DB tabanlı)
# =====================================================================
#
# `saat_tahmini_gecmisi`: öğrenci x (yıl, dönem) x FAZ bazında, o dönem için
# üretilmiş TÜM tahmin_df satırlarını (yontem'i ne olursa olsun) biriktiren bir
# tablo -- gün modelindeki `gun_tahmini_gecmisi` ile birebir aynı amaç ve desen
# (bkz. GECMIS_TABLOSU'nun yukarıdaki docblock'u). `kisi_bazli_son_onselleri_
# getir()`'in döndürdüğü DataFrame, `model_kur_ve_tahmin_et_hibrit()`'in
# `onceki_donem_df` parametresine beslenip hassasiyet harmanında (n₀/Q,
# λ_önceki -- bkz. o fonksiyonun içindeki blok) kullanılır; bkz. aşağıdaki
# main()'deki akış.

def _gecmis_tablosunu_hazirla(conn):
    with conn.cursor() as cursor:
        cursor.execute(f"""
            CREATE TABLE IF NOT EXISTS {GECMIS_TABLOSU} (
                ogrenci_no INT NOT NULL,
                yil INT NOT NULL,
                donem INT NOT NULL,
                faz VARCHAR(32) NOT NULL,
                n_gozlem INT NOT NULL,
                tahmini_saat DECIMAL(5,2) NOT NULL,
                yontem VARCHAR(32) NOT NULL,
                hesaplanma_tarihi DATETIME NOT NULL,
                PRIMARY KEY (ogrenci_no, yil, donem, faz),
                INDEX idx_saat_tahmini_gecmisi_ogrenci_faz (ogrenci_no, faz)
            ) DEFAULT CHARACTER SET utf8mb4
        """)
    conn.commit()


def gecmis_donem_var_mi(conn, yil, donem):
    """Bu (yil, donem) için `saat_tahmini_gecmisi`'nde zaten kayıt var mı? Geçmiş
    dönemlerin verisi sabit olduğundan (tarihte kalmış bir dönem bir daha
    değişmez), bir kere hesaplanan dönem TEKRAR hesaplanmaz."""
    with conn.cursor() as cursor:
        cursor.execute(
            f"SELECT 1 FROM {GECMIS_TABLOSU} WHERE yil = %s AND donem = %s LIMIT 1",
            (yil, donem),
        )
        return cursor.fetchone() is not None


def gecmis_donemi_yaz(conn, tahmin_df, yil, donem):
    """`tahmin_df`'in TÜMÜNÜ (yontem'i ne olursa olsun -- gelecekteki harman
    formülünün hangi satırların bireysel olduğunu filtreleyebilmesi için) bir
    (yil, donem) için `saat_tahmini_gecmisi`'ye yazar. Aynı (yil, donem) için
    önce eski satırlar silinir (yeniden çalıştırma idempotent olsun diye),
    sonra güncel satırlar eklenir. Döner: yazılan satır sayısı."""
    if 0 == len(tahmin_df):
        return 0

    satirlar = [
        (
            int(r.ogrenci_no), yil, donem, r.FAZ, int(r.n_gozlem_faz),
            float(r.tahmini_saat), r.yontem,
        )
        for r in tahmin_df.itertuples(index=False)
    ]

    with conn.cursor() as cursor:
        cursor.execute(f"DELETE FROM {GECMIS_TABLOSU} WHERE yil = %s AND donem = %s", (yil, donem))
        cursor.executemany(f"""
            INSERT INTO {GECMIS_TABLOSU}
                (ogrenci_no, yil, donem, faz, n_gozlem, tahmini_saat, yontem, hesaplanma_tarihi)
            VALUES (%s, %s, %s, %s, %s, %s, %s, NOW())
        """, satirlar)
    conn.commit()

    return len(satirlar)


def kisi_bazli_son_onselleri_getir(conn, aktif_yil, aktif_donem):
    """Her (ogrenci_no, FAZ) için, aktif dönem HARİÇ, en güncel (yil, donem)'de
    Seçenek B kökenli (bireysel sinyal İÇEREN) satırı döner -- bir DataFrame
    (ogrenci_no, FAZ, n_gozlem, tahmini_saat, yontem).

    "Kişi bazlı en son önsel" (gün modeliyle AYNI kullanıcı kararı): sırf BİR
    ÖNCEKİ dönem değil, öğrencinin geçmişte bireysel sinyalinin kanıtlandığı EN
    SON dönem kullanılır -- örn. bir dönem ara vermiş bir öğrenci için de en
    son bilinen gerçek sinyal bulunur, aradaki boş dönem yüzünden popülasyona
    düşülmez.

    Ham pymysql cursor'la (SQLAlchemy engine yerine) yazıldı -- `%s` (pymysql
    paramstyle'i) ile SQLAlchemy'nin `text()` bind stiline karışma riskini
    baştan ortadan kaldırmak için (gün modelindeki aynı fonksiyonla tutarlı).
    """
    aktif_kodu = aktif_yil * 100 + aktif_donem
    sorgu = f"""
        SELECT g.ogrenci_no, g.faz AS FAZ, g.n_gozlem, g.tahmini_saat, g.yontem
        FROM {GECMIS_TABLOSU} g
        INNER JOIN (
            SELECT ogrenci_no, faz, MAX(yil * 100 + donem) AS son_donem_kodu
            FROM {GECMIS_TABLOSU}
            WHERE yontem LIKE 'secenek_b%%' AND (yil * 100 + donem) < %s
            GROUP BY ogrenci_no, faz
        ) son ON son.ogrenci_no = g.ogrenci_no AND son.faz = g.faz
             AND (g.yil * 100 + g.donem) = son.son_donem_kodu
        WHERE g.yontem LIKE 'secenek_b%%'
    """
    with conn.cursor() as cursor:
        cursor.execute(sorgu, (aktif_kodu,))
        satirlar = cursor.fetchall()

    return pd.DataFrame(satirlar, columns=['ogrenci_no', 'FAZ', 'n_gozlem', 'tahmini_saat', 'yontem'])


def gecmis_donemleri_hesapla_ve_yaz(engine, conn, aktif_yil, aktif_donem):
    """Aktif dönem DIŞINDA, DB'de (`materyal_erisim_log`/`sinav_sonucu`) verisi
    bulunan ama `saat_tahmini_gecmisi`'nde henüz kaydı olmayan her dönemi bulur,
    o dönemin KENDİ verisiyle ve KENDİ takvimiyle (`pipeline.TAKVIM_KAYITLARI`)
    saat modelini çalıştırır ve sonucu `saat_tahmini_gecmisi`'ye yazar (gün
    modeliyle aynı kullanıcı kararı: "aktif dönem dışındaki dönemlerin verileri
    için bu hesaplama yapılmalı"). Takvim kaydı olmayan bir dönem varsa (FAZ
    ataması yapılamaz) UYARIYLA atlanır.

    Not: Bu adım geçmiş dönemi SIFIRDAN (kendi onceki_donem_df'i olmadan)
    hesaplar -- zincirleme (geçmişin geçmişi) yapılmaz.
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

        print(f"   -> {y}/{d} geçmiş dönemi hesaplanıyor (saat_tahmini_gecmisi'nde henüz yok)...")
        g_mat, _, g_dem = pipeline.veriyi_yukle(engine, yil=y, donem=d)
        g_mat = pipeline.oznitelik_olustur(g_mat, takvim)
        g_dem = pipeline.demografik_hazirla(g_dem)
        g_tahmin_df = model_kur_ve_tahmin_et_hibrit(g_mat, g_dem)
        yazilan = gecmis_donemi_yaz(conn, g_tahmin_df, y, d)
        print(f"      {yazilan} satır saat_tahmini_gecmisi'ne yazıldı.")


# =====================================================================
# YAZMA: student_profile.observed_time_window_* (PHP tarafı, TIME kolonları)
# =====================================================================

# Python'un FAZ adları -> PHP `student_profile` kolon adları. Kabalaştırma
# (06-12/12-18/18-24/00-06 gibi 4 dilime İNDİRGEME) YOK -- `tahmini_saat_hhmm`
# ("HH:MM") olduğu gibi TIME kolonuna yazılır (kullanıcı kararı).
FAZ_TO_OBSERVED_KOLON = {
    'normal_hafta': 'observed_time_window_normal',
    'ara_sinav_oncesi': 'observed_time_window_ara_sinav_oncesi',
    'final_oncesi': 'observed_time_window_final_oncesi',
}


def student_profile_gozlem_saatini_yaz(tahmin_df):
    """`tahmin_df`'i (ogrenci_no, FAZ, tahmini_saat_hhmm, yontem) `student_profile`'ın
    üç TIME kolonuna + üç eşlik eden `*_yontem` kolonuna (bkz. FAZ_TO_OBSERVED_KOLON) yazar
    -- CSV'ye YAZMAZ (kullanıcı kararı: "python csv yerine tabloya yazsın"). `yontem` kolonu,
    PHP tarafındaki `MessageTimePriorityResolver`'ın gözlenen saati öğrencinin kendi beyan
    ettiği tercihin önüne geçirip geçiremeyeceğine karar vermesi için gerekli (SADECE
    'secenek_b_bireysel' iken geçebilir; 'secenek_a_fallback' popülasyon ortalaması olduğu
    için öğrenciye özgü sayılmaz).

    `student_profile` satırı henüz yoksa (öğrenci PHP tarafında hiç
    görülmediyse) varsayılan bir profille birlikte oluşturulur
    (current_state=YENİ, messages_sent_this_week=0 -- PHP'nin kendi
    `ensureProfilesExist()`'iyle aynı varsayımlar); satır zaten varsa yalnızca
    gözlem saati/yöntem kolonları güncellenir, diğer alanlara dokunulmaz.

    Döner: yazılan öğrenci satırı sayısı.
    """
    saat_genis = tahmin_df.pivot(index='ogrenci_no', columns='FAZ', values='tahmini_saat_hhmm')
    yontem_genis = tahmin_df.pivot(index='ogrenci_no', columns='FAZ', values='yontem')

    def _deger(genis, ogrenci_no, faz):
        if faz not in genis.columns:
            return None
        v = genis.at[ogrenci_no, faz]
        return v if pd.notna(v) else None

    satirlar = [
        (
            int(ogrenci_no),
            _deger(saat_genis, ogrenci_no, 'normal_hafta'),
            _deger(saat_genis, ogrenci_no, 'ara_sinav_oncesi'),
            _deger(saat_genis, ogrenci_no, 'final_oncesi'),
            _deger(yontem_genis, ogrenci_no, 'normal_hafta'),
            _deger(yontem_genis, ogrenci_no, 'ara_sinav_oncesi'),
            _deger(yontem_genis, ogrenci_no, 'final_oncesi'),
        )
        for ogrenci_no in saat_genis.index
    ]

    sorgu = """
        INSERT INTO student_profile
            (student_id, current_state, messages_sent_this_week, updated_at,
             observed_time_window_normal, observed_time_window_ara_sinav_oncesi,
             observed_time_window_final_oncesi, observed_time_window_normal_yontem,
             observed_time_window_ara_sinav_oncesi_yontem, observed_time_window_final_oncesi_yontem)
        VALUES (%s, 'YENİ', 0, NOW(), %s, %s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            observed_time_window_normal = VALUES(observed_time_window_normal),
            observed_time_window_ara_sinav_oncesi = VALUES(observed_time_window_ara_sinav_oncesi),
            observed_time_window_final_oncesi = VALUES(observed_time_window_final_oncesi),
            observed_time_window_normal_yontem = VALUES(observed_time_window_normal_yontem),
            observed_time_window_ara_sinav_oncesi_yontem = VALUES(observed_time_window_ara_sinav_oncesi_yontem),
            observed_time_window_final_oncesi_yontem = VALUES(observed_time_window_final_oncesi_yontem)
    """
    with get_connection() as conn, conn.cursor() as cursor:
        cursor.executemany(sorgu, satirlar)
        conn.commit()

    return len(satirlar)


# =====================================================================
# ANA AKIŞ
# =====================================================================

def main(yil=2024, donem=2):
    """`yil`/`donem`: bu çalıştırmanın AKTİF dönemi -- `student_profile.observed_
    time_window_*`'e yazılacak (yani "şu an geçerli") sonuç bu dönem için hesaplanır.
    Gün modeliyle aynı desen: `saat_tahmini_gecmisi` DB tablosu, aktif dönem dışındaki
    geçmiş dönemleri otomatik geriye dönük doldurur ve bu dönemin sonucunu da
    gelecekteki çalıştırmalar için oraya yazar (bkz. yukarıdaki GEÇMİŞ DÖNEM
    ÖNSELLERİ bölümü)."""
    engine = get_engine()

    with get_connection() as conn:
        _gecmis_tablosunu_hazirla(conn)

        print("0) Aktif dönem dışındaki geçmiş dönemler taranıyor "
              "(saat_tahmini_gecmisi'nde eksik olanlar hesaplanıp yazılıyor)...")
        gecmis_donemleri_hesapla_ve_yaz(engine, conn, yil, donem)

        print("0b) Kişi bazlı en son önseller DB'den okunuyor...")
        onceki_donem_df = kisi_bazli_son_onselleri_getir(conn, yil, donem)
        print(f"    {onceki_donem_df['ogrenci_no'].nunique() if len(onceki_donem_df) else 0} "
              f"öğrenci için geçmiş dönemden önsel bulundu (hassasiyet harmanına beslenecek).")

        print(f"1) Veri yükleniyor ({yil}/{donem})...")
        takvim = pipeline.TAKVIM_KAYITLARI.get((yil, donem), pipeline.BAHAR_2024_TAKVIMI)
        mat, _, dem = pipeline.veriyi_yukle(engine, yil=yil, donem=donem)

        print("2) Öznitelikler oluşturuluyor...")
        mat = pipeline.oznitelik_olustur(mat, takvim)
        dem = pipeline.demografik_hazirla(dem)

        print("3) Hibrit model (öğrenci x faz bazlı Seçenek A/B + hassasiyet harmanı) kuruluyor...")
        tahmin_df = model_kur_ve_tahmin_et_hibrit(mat, dem, onceki_donem_df=onceki_donem_df)

        dagilim = tahmin_df['yontem'].value_counts()
        print("\nYöntem dağılımı (öğrenci x faz satırı bazında):")
        print(dagilim.to_string())
        toplam = len(tahmin_df)
        print(f"\n-> %{100 * dagilim.get('secenek_b_bireysel', 0) / toplam:.1f} satırda öğrencinin "
              f"kendi faz-eğimi (Seçenek B), "
              f"%{100 * dagilim.get('secenek_a_fallback', 0) / toplam:.1f} satırda popülasyon faz "
              f"etkisine (Seçenek A -- az veri olan kendi intercept'iyle, hiç veri olmayan saf "
              f"popülasyon ortalamasıyla) düşüldü.")
        print(f"-> %{100 * tahmin_df['harman_uygulandi'].mean():.1f} satırda önceki dönemin "
              f"gerçek bireysel sonucuyla hassasiyet harmanı uygulandı.")

        tahmin_df['tahmini_saat_hhmm'] = tahmin_df['tahmini_saat'].apply(pipeline.ondalik_saat_to_hhmm)

        print("\nSonuç örneği:")
        print(tahmin_df.head(10).to_string(index=False))

        print(f"\n4) Bu dönemin ({yil}/{donem}) sonucu saat_tahmini_gecmisi'ne yazılıyor "
              f"(gelecek dönemler için önsel olsun diye)...")
        gecmis_donemi_yaz(conn, tahmin_df, yil, donem)

    print("\n5) student_profile.observed_time_window_normal/_ara_sinav_oncesi/_final_oncesi yazılıyor...")
    yazilan = student_profile_gozlem_saatini_yaz(tahmin_df)
    print(f"Yazıldı: {yazilan} öğrenci satırı.")


if __name__ == "__main__":
    main()
