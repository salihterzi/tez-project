"""
Mesaj Zamanlaması Tahmin Pipeline'ı
=====================================
Metodoloji: mesaj_zamanlamasi_metodoloji_README.md dosyasındaki Adım 1-8'i uygular.

KULLANIM (python container'ı içinde):
    docker compose up -d python
    docker compose exec python python mesaj_zamanlamasi_pipeline-v2.py

Veritabanı bağlantısı elle girilmez — `db.py`'deki `get_engine()` üzerinden, compose.yaml'ın
`python` servisine enjekte ettiği DATABASE_URL ortam değişkeninden okunur (python/README.md'ye
bakın). Yeni bağımlılık gerekiyorsa python/requirements.txt'e ekleyip imajı yeniden build edin.
"""

import argparse
import pandas as pd
import numpy as np
import patsy
import statsmodels.formula.api as smf
import warnings

from dataclasses import dataclass
from db import get_engine

warnings.filterwarnings('ignore')

# =====================================================================
# KONFIGÜRASYON
# =====================================================================

@dataclass(frozen=True)
class DonemTakvimi:
    """Bir dönemin sınav takvimi. Her dönemin sınav tarihleri farklı olduğundan
    (kademeli güncelleme: yeni dönem başladıkça pipeline o dönemin kendi takvimiyle
    yeniden çalıştırılır), bu artık modül sabiti değil, main()'e parametre olarak
    geçilen bir değerdir.
    """
    ara_sinav_baslangic: pd.Timestamp
    ara_sinav_bitis: pd.Timestamp
    final_baslangic: pd.Timestamp
    final_bitis: pd.Timestamp

    @property
    def ara_sinav_oncesi_baslangic(self):
        # sınav öncesi pencere: sınav gününün kendisi "öncesi" penceresine dahildir,
        # sınav SONRASI ayrı bir faz değil, normal_hafta'nın parçası sayılır.
        return self.ara_sinav_baslangic - pd.Timedelta(days=7)

    @property
    def final_oncesi_baslangic(self):
        return self.final_baslangic - pd.Timedelta(days=7)


# 2024/2 bahar dönemi (DB'deki mevcut veri) — varsayılan/geriye dönük uyumluluk
BAHAR_2024_TAKVIMI = DonemTakvimi(
    ara_sinav_baslangic=pd.Timestamp('2025-04-19'),
    ara_sinav_bitis=pd.Timestamp('2025-04-20 23:59:59'),
    final_baslangic=pd.Timestamp('2025-05-24'),
    final_bitis=pd.Timestamp('2025-05-25 23:59:59'),
)

# Dönem takvimi kaydı: yeni bir dönem eklendiğinde (materyal_erisim_log/sinav_sonucu'na
# o dönemin verisi girdiğinde) buraya (yil, donem) -> DonemTakvimi eklenmeli. Gün modelinin
# (mesaj_zamanlamasi_gun_hibrit.py) aktif dönem DIŞINDAKİ geçmiş dönemleri otomatik DB'den
# hesaplayıp önsel (Seçenek C) olarak kullanabilmesi için, her geçmiş dönemin kendi FAZ
# atamasına ihtiyacı var -- takvim kaydı olmayan bir dönem atlanır (bkz. o script'in
# gecmis_donemleri_hesapla_ve_yaz() fonksiyonu).
TAKVIM_KAYITLARI = {
    (2024, 2): BAHAR_2024_TAKVIMI,
}

FAZLAR = ['normal_hafta', 'ara_sinav_oncesi', 'final_oncesi']


def tum_donem_kombinasyonlarini_bul(engine):
    """`materyal_erisim_log` ve `sinav_sonucu`'nda görülen tüm (yil, donem) çiftlerini
    (en yeniden en eskiye sıralı) döner. Gün modelinin geçmiş dönem taramasında
    ("aktif dönem dışındaki dönemler için de hesaplama yapılmalı" -- kullanıcı kararı)
    kullanılır.
    """
    sorgu = """
        SELECT DISTINCT yil, donem FROM (
            SELECT yil, donem FROM materyal_erisim_log
            UNION
            SELECT yil, donem FROM sinav_sonucu
        ) t
        ORDER BY yil DESC, donem DESC
    """
    df = pd.read_sql(sorgu, engine)
    return list(df[['yil', 'donem']].itertuples(index=False, name=None))

MIN_GOZLEM_ESIGI = 4          # bireysel modele dahil olmak için min. materyal erişimi
PRIOR_AGIRLIK_MANUEL = None   # None -> karma etkiler modelinin otomatik BLUP'unu kullan (önerilen)
COLD_START_ALPHA = 0.05       # cold-start yardımcı modelinde yas/cinsiyet anlamlılık eşiği

# =====================================================================
# 1. VERİ YÜKLEME (Adım 1)
# =====================================================================

def veriyi_yukle(engine, yil, donem):
    materyal_sorgu = f"""
        SELECT ogrenci_no, ders_kodu, yil, donem, materyal_tipi, unite_no, islem_zamani
        FROM materyal_erisim_log
        WHERE yil = {yil} AND donem = {donem}
    """
    sinav_sorgu = f"""
        SELECT ogrenci_no, ders_kodu, yil, donem, puan, sure, islem_zamani
        FROM sinav_sonucu
        WHERE yil = {yil} AND donem = {donem}
    """
    demografik_sorgu = """
        SELECT ogrenci_no, cinsiyet, dogum_tarihi, calisma_saati_baslangic,
               calisma_saati_bitis, aile_sorumlulugu
        FROM ogrenci
    """
    mat = pd.read_sql(materyal_sorgu, engine)
    sin = pd.read_sql(sinav_sorgu, engine)
    dem = pd.read_sql(demografik_sorgu, engine)

    mat['islem_zamani'] = pd.to_datetime(mat['islem_zamani'])
    sin['islem_zamani'] = pd.to_datetime(sin['islem_zamani'])
    return mat, sin, dem


def mat_sin_birlestir(mat, sin):
    """Materyal erişimi (materyal_erisim_log) ve sınav erişimini (sinav_sonucu)
    TEK bir davranışsal olay günlüğünde birleştirir — zamanlama modeli için
    ikisi de "öğrencinin platformla ne zaman etkileşime girdiği" sinyali
    sayılır. Ortak olmayan kolonlar (materyal_tipi/unite_no vs puan/sure)
    zamanlama analizinde hiç kullanılmadığı için atılır; hangi kaynaktan
    geldiği 'etkinlik_tipi' kolonuyla korunur (izlenebilirlik için).

    Bilinçli varsayım: sinav_sonucu.islem_zamani sınavın ÇÖZÜLDÜĞÜ an anlamına
    gelir — materyal erişiminden farklı olarak bu, öğrencinin tamamen serbestçe
    seçtiği bir zaman olmayabilir (sınav belirli bir pencerede açık olabilir).
    Bu varsayım tezde açıkça belirtilmelidir; iki kaynak "ne zaman platforma
    dokunuyor" anlamında birleştiriliyor, "ne zaman gönüllü çalışıyor"
    anlamında birebir eşdeğer sayılmıyor.
    """
    ortak_kolonlar = ['ogrenci_no', 'ders_kodu', 'yil', 'donem', 'islem_zamani']
    mat_ = mat[ortak_kolonlar].copy()
    mat_['etkinlik_tipi'] = 'materyal'
    sin_ = sin[ortak_kolonlar].copy()
    sin_['etkinlik_tipi'] = 'sinav'
    return pd.concat([mat_, sin_], ignore_index=True)


# =====================================================================
# 2. ÖZNİTELİK MÜHENDİSLİĞİ (Adım 1 devamı — göreli zaman + faz)
# =====================================================================

def oznitelik_olustur(mat, takvim):
    mat = mat.copy()
    mat['saat'] = mat['islem_zamani'].dt.hour + mat['islem_zamani'].dt.minute / 60
    mat['gun_index'] = mat['islem_zamani'].dt.dayofweek  # 0=Pazartesi

    # dairesel saat bileşenleri (23:00-01:00 yakınlığını korumak için)
    saat_rad = mat['saat'] / 24 * 2 * np.pi
    mat['saat_sin'] = np.sin(saat_rad)
    mat['saat_cos'] = np.cos(saat_rad)

    def faz_ata(t):
        if takvim.ara_sinav_oncesi_baslangic <= t <= takvim.ara_sinav_bitis:
            return 'ara_sinav_oncesi'
        elif takvim.final_oncesi_baslangic <= t <= takvim.final_bitis:
            return 'final_oncesi'
        return 'normal_hafta'

    mat['FAZ'] = mat['islem_zamani'].apply(faz_ata)
    return mat


def _saate_cevir(deger):
    """MySQL `TIME` kolonları (calisma_saati_baslangic/bitis) PyMySQL+pandas ile `Timedelta`
    olarak gelir (örn. `0 days 09:15:00`) — `tahmini_saat` ile aynı ölçekte (0-24 arası
    float saat) karşılaştırılabilmesi için çevrilir. NaT/NaN ise NaN döner.
    """
    if pd.isna(deger):
        return np.nan
    return deger.total_seconds() / 3600


def demografik_hazirla(dem):
    dem = dem.copy()
    dem['dogum_tarihi'] = pd.to_datetime(dem['dogum_tarihi'])
    dem['yas'] = ((pd.Timestamp.now() - dem['dogum_tarihi']).dt.days / 365.25).round(1)
    dem['calisma_saati_baslangic'] = dem['calisma_saati_baslangic'].apply(_saate_cevir)
    dem['calisma_saati_bitis'] = dem['calisma_saati_bitis'].apply(_saate_cevir)
    # aile_sorumlulugu DB'de 0/1 (tinyint) olarak geliyor; olduğu gibi kullanılabilir.
    return dem


# =====================================================================
# 3. DEVAMLILIK TESTİ — ICC (Adım 4, karar noktası)
# =====================================================================

def icc_hesapla(df, degisken, min_gozlem=MIN_GOZLEM_ESIGI):
    counts = df.groupby('ogrenci_no').size()
    yeterli = counts[counts >= min_gozlem].index
    sub = df[df['ogrenci_no'].isin(yeterli)].copy()
    sub['ogrenci_no_str'] = sub['ogrenci_no'].astype(str)

    model = smf.mixedlm(f"{degisken} ~ 1", sub, groups=sub['ogrenci_no_str'])
    sonuc = model.fit()

    tau2 = sonuc.cov_re.iloc[0, 0]
    sigma2 = sonuc.scale
    icc = tau2 / (tau2 + sigma2) if (tau2 + sigma2) > 0 else 0.0
    dogal_prior_agirlik = sigma2 / tau2 if tau2 > 0 else float('inf')

    return {
        'model': sonuc,
        'icc': icc,
        'tau2': tau2,
        'sigma2': sigma2,
        'dogal_prior_agirlik': dogal_prior_agirlik,
    }


def devamlilik_raporu(mat):
    print("=== DEVAMLILIK TESTİ (ICC) ===")
    for degisken in ['saat_sin', 'saat_cos']:
        r = icc_hesapla(mat, degisken)
        print(f"{degisken}: ICC={r['icc']:.3f}, doğal_prior_ağırlık={r['dogal_prior_agirlik']:.1f}")
        if r['icc'] < 0.1:
            print(f"  -> UYARI: ICC düşük. Bireysel BLUP'lara güvenmeden önce dikkatli ol; "
                  f"popülasyon fallback'ini (Adım 4) düşün.")
    print()


# =====================================================================
# 3.5 SEÇENEK A/B KARAR MEKANİZMASI — rastgele eğim modeli için veri yeterli mi?
# =====================================================================

MIN_FAZ_GOZLEM_ESIGI = 4  # öğrenci başına, HER fazda gereken minimum gözlem
# ^ Rastgele seçilmedi -- BLUP shrinkage (güvenilirlik) formülünden türetildi:
#     lambda = n / (n + w),  w = sigma^2 / tau^2(faz)
#   sigma^2 = mixedlm'in kalıntı (residual) varyansı, tau^2(faz) = o fazın
#   kişiler-arası (between-student) birleşik varyansı (bkz. mesaj_zamanlamasi_hibrit.py
#   fit'indeki r.cov_re / r.scale). lambda, bireysel BLUP'un ne kadar "kendi
#   veriye" dayandığını gösteren güvenilirlik katsayısı (0=saf popülasyon,
#   1=saf birey).
#
#   lambda=0.7 (Nunnally 1978'deki "kabul edilebilir güvenilirlik" konvansiyonu,
#   psikometri literatüründen ödünç alındı) formülde n için çözülüp
#   (n_esik = w*lambda/(1-lambda)) gerçek veriyle (2024/2 bahar, 3.78M gözlem,
#   49.384 öğrenci) hesaplandığında üç faz için de ~3.4-3.8 arası çıktı (bkz.
#   python/_sim_n_esik.py) -- tavana yuvarlanınca üçü de 4'e denk geliyor, bu
#   yüzden fazlar arası ayrı sabit yerine TEK bir n=4 kullanılıyor. (lambda=0.5,
#   yani "kendi veri ile popülasyon önseli eşit ağırlıklı" nötr referans
#   noktası, ~1.4-1.6 verip eskiden kullanılan sabit "2"yi doğruluyordu; 0.7
#   daha muhafazakâr/savunulabilir bir eşik olduğu için tercih edildi.)

def rastgele_egim_icin_veri_yeterli_mi(mat, esik=MIN_FAZ_GOZLEM_ESIGI, min_oran=0.5):
    """
    Seçenek B (1+FAZ|öğrenci_id) için, öğrencilerin yeterince büyük bir
    kısmının HER fazda en az `esik` gözlemi olup olmadığını kontrol eder.
    min_oran: öğrencilerin en az bu oranı şartı karşılamalı (varsayılan %50).

    Yetersizse Seçenek A'ya (sabit faz etkisi) otomatik düşülür - aksi halde
    BLUP'lar veri yokluğundan sıfıra yakınsar, bu da "gerçek fark yok" ile
    "veri yok" durumlarını karıştırmamıza (yanlış yoruma) yol açar.
    """
    tablo = mat.groupby(['ogrenci_no', 'FAZ']).size().unstack(fill_value=0)
    for faz in FAZLAR:
        if faz not in tablo.columns:
            tablo[faz] = 0
    yeterli_ogrenci = tablo[(tablo >= esik).all(axis=1)]
    oran = len(yeterli_ogrenci) / len(tablo) if len(tablo) > 0 else 0

    print(f"  Her fazda >= {esik} gözlemi olan öğrenci oranı: %{oran*100:.1f} "
          f"({len(yeterli_ogrenci)}/{len(tablo)})")

    return oran >= min_oran


# =====================================================================
# 4. KARMA ETKİLER MODELİ + FAZ DÜZELTMESİ (Adım 3 ve 5)
#    Seçenek A: sabit (ana) faz etkisi -- (1|öğrenci)
#    Seçenek B: kişiye özel faz eğimi  -- (1+FAZ|öğrenci)  [veri yeterliyse]
# =====================================================================

def model_kur_ve_tahmin_et_secenek_b(mat, min_gozlem=MIN_GOZLEM_ESIGI):
    """
    Seçenek B: her öğrencinin KENDİ faz tepkisini de BLUP ile çıkarır.
    SADECE rastgele_egim_icin_veri_yeterli_mi() TRUE dönerse çağrılmalı.
    """
    counts = mat.groupby('ogrenci_no').size()
    yeterli = counts[counts >= min_gozlem].index
    sub = mat[mat['ogrenci_no'].isin(yeterli)].copy()
    sub['ogrenci_no_str'] = sub['ogrenci_no'].astype(str)
    sub['FAZ'] = pd.Categorical(sub['FAZ'], categories=FAZLAR)

    # re_formula="~C(FAZ)" -> her öğrenci için intercept + her faz için ayrı eğim BLUP'u
    m_sin = smf.mixedlm("saat_sin ~ C(FAZ)", sub, groups=sub['ogrenci_no_str'],
                         re_formula="~C(FAZ)")
    r_sin = m_sin.fit()
    m_cos = smf.mixedlm("saat_cos ~ C(FAZ)", sub, groups=sub['ogrenci_no_str'],
                         re_formula="~C(FAZ)")
    r_cos = m_cos.fit()

    fazlar = FAZLAR
    sonuclar = []
    for ogrenci_no in sub['ogrenci_no'].unique():
        kn = str(ogrenci_no)
        re_sin = r_sin.random_effects.get(kn, None)
        re_cos = r_cos.random_effects.get(kn, None)
        if re_sin is None or re_cos is None:
            continue
        n = counts[ogrenci_no]

        for faz in fazlar:
            faz_kolon = 'Group' if faz == 'normal_hafta' else f'C(FAZ)[T.{faz}]'
            sabit_sin = r_sin.fe_params['Intercept'] + (
                r_sin.fe_params.get(f'C(FAZ)[T.{faz}]', 0) if faz != 'normal_hafta' else 0)
            sabit_cos = r_cos.fe_params['Intercept'] + (
                r_cos.fe_params.get(f'C(FAZ)[T.{faz}]', 0) if faz != 'normal_hafta' else 0)

            rastgele_sin = re_sin.get('Group', 0) + (
                re_sin.get(faz_kolon, 0) if faz != 'normal_hafta' else 0)
            rastgele_cos = re_cos.get('Group', 0) + (
                re_cos.get(faz_kolon, 0) if faz != 'normal_hafta' else 0)

            tahmini_sin = sabit_sin + rastgele_sin
            tahmini_cos = sabit_cos + rastgele_cos
            aci = np.arctan2(tahmini_sin, tahmini_cos)
            if aci < 0:
                aci += 2 * np.pi
            tahmini_saat = aci / (2 * np.pi) * 24
            sonuclar.append({'ogrenci_no': ogrenci_no, 'n_gozlem': n, 'FAZ': faz,
                              'tahmini_saat': round(tahmini_saat, 2),
                              'yontem': 'secenek_b_rastgele_egim'})

    return pd.DataFrame(sonuclar)


def _sabit_etki_terimlerini_sec(faz_df, degisken, alpha):
    """yas ve cinsiyet'i aday sabit-etki terimi olarak dener (tıpkı eski
    cold_start_tahmin'deki gibi), p<alpha olanları döner. Fit başarısız olursa
    (ör. çok az veri) terimsiz (sadece Intercept) döner."""
    try:
        tam_model = smf.mixedlm(f"{degisken} ~ yas + C(cinsiyet)", faz_df,
                                 groups=faz_df['ogrenci_no_str']).fit()
    except Exception:
        return []
    terimler = []
    if tam_model.pvalues.get('yas', 1.0) < alpha:
        terimler.append('yas')
    cinsiyet_terimleri = [t for t in tam_model.pvalues.index if t.startswith('C(cinsiyet)')]
    if any(tam_model.pvalues[t] < alpha for t in cinsiyet_terimleri):
        terimler.append('C(cinsiyet)')
    return terimler


def model_kur_ve_tahmin_et(mat, dem, min_gozlem=MIN_GOZLEM_ESIGI, alpha=COLD_START_ALPHA):
    """
    Seçenek A (faz-bazlı BAĞIMSIZ modeller + demografik sabit-etki): her faz
    için AYRI, basit bir (1|öğrenci) karma etkiler modeli kurulur — sadece o
    fazın gözlemleriyle. Bir öğrencinin bir fazdaki yeni gözlemi SADECE o
    fazın tahminini değiştirir, diğer fazlar etkilenmez.

    Sabit etki (fixed effect) formülüne, tıpkı eski cold_start_tahmin'deki
    gibi, yas ve cinsiyet aday terim olarak denenir ve SADECE o fazda p<alpha
    ile anlamlı çıkarlarsa dahil edilir (bkz. _sabit_etki_terimlerini_sec).

    Bu sayede tahmin, dem'deki TÜM kayıtlı öğrenciler için üretilir — mat'te
    hiç satırı olmayan (0 erişim) öğrenciler dahil: onlar için rastgele etki
    (BLUP) yoktur (fit'e hiç girmediler), ama sabit etki kısmı (popülasyon
    ortalaması + varsa anlamlı yas/cinsiyet katkısı) kendi demografik
    bilgileriyle hesaplanıp atanır. Ayrı bir cold-start adımına gerek kalmaz
    ve üstelik bu tahmin artık faz-bazlıdır (eski cold-start'ta tek sabit
    saattı). Az/orta veri sahibi öğrenciler için de BLUP, veri arttıkça
    fixed-effect'ten kişisel sapmaya doğru mixedlm'in doğal küçülmesiyle
    kayar.
    """
    counts = mat.groupby('ogrenci_no').size()
    yeterli = counts[counts >= min_gozlem].index
    sub = mat[mat['ogrenci_no'].isin(yeterli)].merge(
        dem[['ogrenci_no', 'yas', 'cinsiyet']], on='ogrenci_no', how='left')
    sub['ogrenci_no_str'] = sub['ogrenci_no'].astype(str)

    dem_index = dem.set_index('ogrenci_no')[['yas', 'cinsiyet']]

    sonuclar = []
    for faz in FAZLAR:
        faz_df = sub[sub['FAZ'] == faz]

        if faz_df['ogrenci_no'].nunique() < 2:
            print(f"UYARI: '{faz}' fazında kişiselleştirme için yeterli öğrenci yok "
                  f"({faz_df['ogrenci_no'].nunique()} öğrenci); tüm öğrencilere bu fazın "
                  f"ham popülasyon ortalaması atanacak.")
            if len(faz_df) == 0:
                continue
            sin_ort, cos_ort = faz_df['saat_sin'].mean(), faz_df['saat_cos'].mean()
            aci = np.arctan2(sin_ort, cos_ort)
            if aci < 0:
                aci += 2 * np.pi
            tahmini_saat = round(aci / (2 * np.pi) * 24, 2)
            for ogrenci_no in dem['ogrenci_no']:
                sonuclar.append({'ogrenci_no': ogrenci_no, 'n_gozlem': counts.get(ogrenci_no, 0),
                                  'FAZ': faz, 'tahmini_saat': tahmini_saat,
                                  'yontem': 'faz_ham_ortalama'})
            continue

        modeller = {}
        for degisken in ('saat_sin', 'saat_cos'):
            terimler = _sabit_etki_terimlerini_sec(faz_df, degisken, alpha)
            formul = f"{degisken} ~ " + (" + ".join(terimler) if terimler else "1")
            modeller[degisken] = smf.mixedlm(formul, faz_df, groups=faz_df['ogrenci_no_str']).fit()
        r_sin, r_cos = modeller['saat_sin'], modeller['saat_cos']

        design_sin = r_sin.model.data.design_info
        design_cos = r_cos.model.data.design_info
        X_sin = patsy.dmatrix(design_sin, data=dem_index, return_type='dataframe')
        X_cos = patsy.dmatrix(design_cos, data=dem_index, return_type='dataframe')
        sabit_sin_tum = pd.Series(X_sin.values @ r_sin.fe_params.values, index=dem_index.index)
        sabit_cos_tum = pd.Series(X_cos.values @ r_cos.fe_params.values, index=dem_index.index)

        for ogrenci_no in dem['ogrenci_no']:
            kn = str(ogrenci_no)
            kisisel_mi = kn in r_sin.random_effects
            rastgele_sin = r_sin.random_effects.get(kn, {}).get('Group', 0)
            rastgele_cos = r_cos.random_effects.get(kn, {}).get('Group', 0)
            tahmini_sin = sabit_sin_tum[ogrenci_no] + rastgele_sin
            tahmini_cos = sabit_cos_tum[ogrenci_no] + rastgele_cos
            aci = np.arctan2(tahmini_sin, tahmini_cos)
            if aci < 0:
                aci += 2 * np.pi
            tahmini_saat = aci / (2 * np.pi) * 24
            sonuclar.append({'ogrenci_no': ogrenci_no, 'n_gozlem': counts.get(ogrenci_no, 0),
                              'FAZ': faz, 'tahmini_saat': round(tahmini_saat, 2),
                              'yontem': 'blup_kisisel' if kisisel_mi else 'sabit_etki_demografik'})

    return pd.DataFrame(sonuclar)


def ondalik_saat_to_hhmm(deger):
    """0-24 arası ondalık saati 'HH:MM' string'ine çevirir (örn. 6.98 -> '06:59').
    `tahmini_saat` kolonu ondalık saat taşıyor (6.98 = 6 saat 58.8 dakika, "6 saat
    98 dakika" DEĞİL) — bu kolon yanlış okumayı önlemek için insan-okunur karşılığını verir.
    """
    if pd.isna(deger):
        return None
    dakika_toplam = int(round(deger * 60)) % (24 * 60)
    saat, dakika = divmod(dakika_toplam, 60)
    return f"{saat:02d}:{dakika:02d}"


# =====================================================================
# 5. ÇALIŞMA SAATİ FİLTRESİ (Adım 2 — sert kısıt, tahminden SONRA uygulanır)
# =====================================================================

def calisma_saati_filtresi_uygula(tahmin_df, dem):
    """
    dem içinde calisma_saati_baslangic / calisma_saati_bitis (saat, 0-24 float) varsa,
    tahmini_saat bu aralığa denk geliyorsa en yakın sınırın (başlangıcın hemen öncesi
    ya da bitişin hemen sonrası, hangisi daha yakınsa) dışına kaydırılır. Sabit "+3 saat"
    kaydırma, çalışma bloğu uzun olduğunda (örn. 8 saatlik blok) hâlâ blok içine
    düşebiliyordu; bu yaklaşım blok uzunluğundan ve gece yarısını sarıp sarmamasından
    bağımsız olarak her zaman bloğun dışına çıkarır.
    """
    df = tahmin_df.merge(dem[['ogrenci_no', 'calisma_saati_baslangic', 'calisma_saati_bitis']],
                          on='ogrenci_no', how='left')

    def cakisiyor_mu(row):
        if pd.isna(row['calisma_saati_baslangic']):
            return False
        b, s = row['calisma_saati_baslangic'], row['calisma_saati_bitis']
        t = row['tahmini_saat']
        if b < s:
            return b <= t <= s
        else:  # gece calismasi (örn. 22:00-06:00)
            return t >= b or t <= s

    df['calisma_saatiyle_cakisiyor'] = df.apply(cakisiyor_mu, axis=1)

    TAMPON_SAAT = 0.05  # ~3 dakika; sınıra tam denk gelmeyi önlemek için küçük pay

    def en_yakin_sinira_kaydir(row):
        if not row['calisma_saatiyle_cakisiyor']:
            return row['tahmini_saat']
        b, s, t = row['calisma_saati_baslangic'], row['calisma_saati_bitis'], row['tahmini_saat']
        ileri_mesafe = (s - t) % 24   # bitişin hemen sonrasına ileri giderek çıkma mesafesi
        geri_mesafe = (t - b) % 24    # başlangıcın hemen öncesine geri giderek çıkma mesafesi
        if ileri_mesafe <= geri_mesafe:
            return (s + TAMPON_SAAT) % 24
        return (b - TAMPON_SAAT) % 24

    df['tahmini_saat'] = df.apply(en_yakin_sinira_kaydir, axis=1)
    return df


# =====================================================================
# 6. COLD-START (Adım 7 — yetersiz davranışsal veri)
# =====================================================================

def cold_start_tahmin(dem, mat, min_gozlem=MIN_GOZLEM_ESIGI, alpha=COLD_START_ALPHA):
    counts = mat.groupby('ogrenci_no').size()
    yeterli_id = counts[counts >= min_gozlem].index
    yetersiz = dem[~dem['ogrenci_no'].isin(yeterli_id)]
    if len(yetersiz) == 0:
        return pd.DataFrame()

    # davranışsal verisi yeterli öğrencilerle yardımcı regresyon eğit
    egitim = mat[mat['ogrenci_no'].isin(yeterli_id)].groupby('ogrenci_no').agg(
        ort_saat=('saat', 'mean')).reset_index()
    egitim = egitim.merge(dem, on='ogrenci_no')

    # yas ve cinsiyet şu an sözde veri: ikisinin de gerçekten anlamlı olup olmadığı
    # her çalıştırmada bu veriden belirlenir, sabit kodlanmaz. Tam modelde (yas +
    # cinsiyet) p-değeri alpha'nın altında kalan terimler nihai modele alınır; hiçbiri
    # anlamlı değilse yeterli-veri grubunun ortalama erişim saatine düşülür.
    # aile_sorumlulugu / calisma_saati bilinçli olarak aday değişken DEĞİL: ogrenci
    # tablosunda dönem bazlı tutulmuyor (tek güncel değer), bu yüzden geçmiş dönem
    # (mat) davranışını açıklayan bir kovaryat olarak kullanılmaları dönem uyuşmazlığı
    # yaratır — bugünkü değer, mat'teki dönemde geçerli olan değerle aynı olmayabilir.
    tam_model = smf.ols("ort_saat ~ yas + C(cinsiyet)", egitim).fit()
    terimler = []
    if tam_model.pvalues.get('yas', 1.0) < alpha:
        terimler.append('yas')
    cinsiyet_terimleri = [t for t in tam_model.pvalues.index if t.startswith('C(cinsiyet)')]
    if any(tam_model.pvalues[t] < alpha for t in cinsiyet_terimleri):
        terimler.append('C(cinsiyet)')

    if terimler:
        yardimci_model = smf.ols("ort_saat ~ " + " + ".join(terimler), egitim).fit()
        tahmini_saat = yardimci_model.predict(yetersiz)
        kaynak = 'cold_start_demografik(' + '+'.join(terimler) + ')'
    else:
        tahmini_saat = pd.Series(egitim['ort_saat'].mean(), index=yetersiz.index)
        kaynak = 'cold_start_grup_ortalamasi'

    return pd.DataFrame({'ogrenci_no': yetersiz['ogrenci_no'].values,
                          'tahmini_saat': tahmini_saat.values,
                          'kaynak': kaynak})


# =====================================================================
# ANA AKIŞ
# =====================================================================

def main(yil=2024, donem=2, takvim=BAHAR_2024_TAKVIMI):
    """Kademeli güncelleme: pipeline her dönem için o dönemin kendi takvimiyle
    (yeni sınav tarihleri) yeniden çalıştırılır — eski dönemin verisiyle birleştirilmez.
    Cold-start -> ana model terfisi otomatiktir: bir öğrenci MIN_GOZLEM_ESIGI'yi bu
    çalıştırmada geçtiyse, ayrıca bir kod değişikliği gerekmeden ana modelden tahmin alır.

    Seçenek A artık dem'deki TÜM kayıtlı öğrenciler için tahmin üretiyor (bkz.
    model_kur_ve_tahmin_et docstring'i) — ayrı bir cold_start_tahmin adımına gerek
    kalmadı. Seçenek B bu genişletmeyi henüz almadı (hâlâ sadece 'yeterli' popülasyonu
    kapsıyor), bu yüzden cold_start_tahmin SADECE o dal seçildiğinde çağrılıyor.
    """
    engine = get_engine()

    print(f"1) Veri yükleniyor ({yil}/{donem})...")
    mat, sin, dem = veriyi_yukle(engine, yil=yil, donem=donem)

    print("2) Materyal erişimi ve sınav erişimi birleştiriliyor...")
    mat = mat_sin_birlestir(mat, sin)

    print("3) Öznitelikler oluşturuluyor...")
    mat = oznitelik_olustur(mat, takvim)
    dem = demografik_hazirla(dem)

    print("4) Devamlılık testi (ICC) çalıştırılıyor...\n")
    devamlilik_raporu(mat)

    print("5) Seçenek A/B kararı için veri yeterliliği kontrol ediliyor...")
    secenek_b_uygun = rastgele_egim_icin_veri_yeterli_mi(mat)

    if secenek_b_uygun:
        print("   -> Veri yeterli: Seçenek B (kişiye özel faz eğimi) kullanılıyor.")
        tahmin_df = model_kur_ve_tahmin_et_secenek_b(mat)
        print("6) Cold-start tahminleri ekleniyor (Seçenek B tüm popülasyonu kapsamıyor)...")
        cold_start_df = cold_start_tahmin(dem, mat)
        if len(cold_start_df) > 0:
            tahmin_df = pd.concat([tahmin_df, cold_start_df], ignore_index=True)
    else:
        print("   -> Veri YETERSİZ: Seçenek A'ya (faz-bazlı bağımsız model) düşülüyor. "
              "(Aksi halde BLUP'lar veri yokluğundan sıfıra yakınsar.)")
        print("6) Seçenek A tüm kayıtlı öğrencileri (dem) zaten kapsıyor — "
              "ayrı bir cold-start adımı atlanıyor.")
        tahmin_df = model_kur_ve_tahmin_et(mat, dem)

    # Çalışma saati filtresi tüm tahmin_df'e uygulanır.
    print("7) Çalışma saati filtresi uygulanıyor...")
    tahmin_df = calisma_saati_filtresi_uygula(tahmin_df, dem)

    tahmin_df['tahmini_saat_hhmm'] = tahmin_df['tahmini_saat'].apply(ondalik_saat_to_hhmm)

    print("\nSonuç örneği:")
    print(tahmin_df.head(10))

    dosya_adi = f'{yil}_{donem}_mesaj_zamanlamasi_tahminleri.csv'
    tahmin_df.to_csv(dosya_adi, index=False)
    print(f"\nKaydedildi: {dosya_adi}")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--yil', type=int, default=2024)
    parser.add_argument('--donem', type=int, default=2)
    parser.add_argument('--ara-sinav-baslangic', type=str, default=None,
                         help="YYYY-MM-DD — verilmezse 2024/2 bahar takvimi kullanılır")
    parser.add_argument('--ara-sinav-bitis', type=str, default=None)
    parser.add_argument('--final-baslangic', type=str, default=None)
    parser.add_argument('--final-bitis', type=str, default=None)
    args = parser.parse_args()

    if args.ara_sinav_baslangic:
        secilen_takvim = DonemTakvimi(
            ara_sinav_baslangic=pd.Timestamp(args.ara_sinav_baslangic),
            ara_sinav_bitis=pd.Timestamp(args.ara_sinav_bitis),
            final_baslangic=pd.Timestamp(args.final_baslangic),
            final_bitis=pd.Timestamp(args.final_bitis),
        )
    else:
        secilen_takvim = BAHAR_2024_TAKVIMI

    main(yil=args.yil, donem=args.donem, takvim=secilen_takvim)
