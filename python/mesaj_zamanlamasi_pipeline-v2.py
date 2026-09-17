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

import pandas as pd
import numpy as np
import statsmodels.formula.api as smf
import warnings

from db import get_engine

warnings.filterwarnings('ignore')

# =====================================================================
# KONFIGÜRASYON
# =====================================================================

# Gerçek akademik takvim (varsayımsal DEĞİL — burayı kesin tarihlerle doldur)
ARA_SINAV_BASLANGIC = pd.Timestamp('2025-04-19')
ARA_SINAV_BITIS     = pd.Timestamp('2025-04-20 23:59:59')
FINAL_BASLANGIC     = pd.Timestamp('2025-05-24')
FINAL_BITIS         = pd.Timestamp('2025-05-25 23:59:59')

# Faz pencereleri (sınav öncesi) - sınav günlerinin kendisi "öncesi" penceresine dahildir.
# Sınav SONRASI ayrı bir faz değil, normal_hafta'nın parçası sayılır.
ARA_SINAV_ONCESI_BASLANGIC = ARA_SINAV_BASLANGIC - pd.Timedelta(days=7)
ARA_SINAV_ONCESI_BITIS     = ARA_SINAV_BITIS
FINAL_ONCESI_BASLANGIC     = FINAL_BASLANGIC - pd.Timedelta(days=7)
FINAL_ONCESI_BITIS         = FINAL_BITIS

FAZLAR = ['normal_hafta', 'ara_sinav_oncesi', 'final_oncesi']

MIN_GOZLEM_ESIGI = 4          # bireysel modele dahil olmak için min. materyal erişimi
PRIOR_AGIRLIK_MANUEL = None   # None -> karma etkiler modelinin otomatik BLUP'unu kullan (önerilen)

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


# =====================================================================
# 2. ÖZNİTELİK MÜHENDİSLİĞİ (Adım 1 devamı — göreli zaman + faz)
# =====================================================================

def oznitelik_olustur(mat):
    mat = mat.copy()
    mat['saat'] = mat['islem_zamani'].dt.hour + mat['islem_zamani'].dt.minute / 60
    mat['gun_index'] = mat['islem_zamani'].dt.dayofweek  # 0=Pazartesi

    # dairesel saat bileşenleri (23:00-01:00 yakınlığını korumak için)
    saat_rad = mat['saat'] / 24 * 2 * np.pi
    mat['saat_sin'] = np.sin(saat_rad)
    mat['saat_cos'] = np.cos(saat_rad)

    def faz_ata(t):
        if ARA_SINAV_ONCESI_BASLANGIC <= t <= ARA_SINAV_ONCESI_BITIS:
            return 'ara_sinav_oncesi'
        elif FINAL_ONCESI_BASLANGIC <= t <= FINAL_ONCESI_BITIS:
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

MIN_FAZ_GOZLEM_ESIGI = 2  # öğrenci başına, HER fazda gereken minimum gözlem

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


def model_kur_ve_tahmin_et(mat, min_gozlem=MIN_GOZLEM_ESIGI):
    counts = mat.groupby('ogrenci_no').size()
    yeterli = counts[counts >= min_gozlem].index
    sub = mat[mat['ogrenci_no'].isin(yeterli)].copy()
    sub['ogrenci_no_str'] = sub['ogrenci_no'].astype(str)
    sub['FAZ'] = pd.Categorical(sub['FAZ'], categories=FAZLAR)

    m_sin = smf.mixedlm("saat_sin ~ C(FAZ)", sub, groups=sub['ogrenci_no_str'])
    r_sin = m_sin.fit()
    m_cos = smf.mixedlm("saat_cos ~ C(FAZ)", sub, groups=sub['ogrenci_no_str'])
    r_cos = m_cos.fit()

    # faz anlamlılığını kontrol et (Adım 5 - anlamsızsa devre dışı bırak)
    faz_anlamli_mi = (r_sin.pvalues.filter(like='FAZ').min() < 0.05) or \
                      (r_cos.pvalues.filter(like='FAZ').min() < 0.05)
    if not faz_anlamli_mi:
        print("UYARI: Faz etkisi istatistiksel olarak anlamlı değil. "
              "Faz düzeltmesi devre dışı bırakılıyor, sadece temel eğilim kullanılacak.")

    fazlar = FAZLAR

    def sabit_etki(fe, faz):
        val = fe['Intercept']
        if faz != 'normal_hafta' and faz_anlamli_mi:
            val += fe.get(f'C(FAZ)[T.{faz}]', 0)
        return val

    sonuclar = []
    for ogrenci_no in sub['ogrenci_no'].unique():
        kn = str(ogrenci_no)
        rastgele_sin = r_sin.random_effects.get(kn, {}).get('Group', 0)
        rastgele_cos = r_cos.random_effects.get(kn, {}).get('Group', 0)
        n = counts[ogrenci_no]

        for faz in fazlar:
            tahmini_sin = sabit_etki(r_sin.fe_params, faz) + rastgele_sin
            tahmini_cos = sabit_etki(r_cos.fe_params, faz) + rastgele_cos
            aci = np.arctan2(tahmini_sin, tahmini_cos)
            if aci < 0:
                aci += 2 * np.pi
            tahmini_saat = aci / (2 * np.pi) * 24
            sonuclar.append({'ogrenci_no': ogrenci_no, 'n_gozlem': n, 'FAZ': faz,
                              'tahmini_saat': round(tahmini_saat, 2)})

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

def cold_start_tahmin(dem, mat, min_gozlem=MIN_GOZLEM_ESIGI):
    counts = mat.groupby('ogrenci_no').size()
    yetersiz = dem[~dem['ogrenci_no'].isin(counts[counts >= min_gozlem].index)]
    if len(yetersiz) == 0:
        return pd.DataFrame()

    # davranışsal verisi yeterli öğrencilerle yardımcı regresyon eğit
    yeterli_id = counts[counts >= min_gozlem].index
    egitim = mat[mat['ogrenci_no'].isin(yeterli_id)].groupby('ogrenci_no').agg(
        ort_saat=('saat', 'mean')).reset_index()
    egitim = egitim.merge(dem, on='ogrenci_no')

    # basit örnek: yaş ve aile sorumluluğuyla regresyon (gerçek şemaya göre genişlet)
    import statsmodels.api as sm
    X = sm.add_constant(egitim[['yas']])  # aile_sorumlulugu vb. eklenebilir
    y = egitim['ort_saat']
    yardimci_model = sm.OLS(y, X).fit()

    X_yeni = sm.add_constant(yetersiz[['yas']])
    tahmini_saat = yardimci_model.predict(X_yeni)

    return pd.DataFrame({'ogrenci_no': yetersiz['ogrenci_no'].values,
                          'tahmini_saat': tahmini_saat.values,
                          'kaynak': 'cold_start_demografik'})


# =====================================================================
# ANA AKIŞ
# =====================================================================

def main():
    engine = get_engine()

    print("1) Veri yükleniyor...")
    # DB'de şu an tek dönem var: 2024/2 (bahar) — ARA_SINAV_*/FINAL_* takvimi de bu döneme ait.
    mat, sin, dem = veriyi_yukle(engine, yil=2024, donem=2)  # bahar dönemi

    print("2) Öznitelikler oluşturuluyor...")
    mat = oznitelik_olustur(mat)
    dem = demografik_hazirla(dem)

    print("3) Devamlılık testi (ICC) çalıştırılıyor...\n")
    devamlilik_raporu(mat)

    print("4) Seçenek A/B kararı için veri yeterliliği kontrol ediliyor...")
    secenek_b_uygun = rastgele_egim_icin_veri_yeterli_mi(mat)

    if secenek_b_uygun:
        print("   -> Veri yeterli: Seçenek B (kişiye özel faz eğimi) kullanılıyor.")
        tahmin_df = model_kur_ve_tahmin_et_secenek_b(mat)
    else:
        print("   -> Veri YETERSİZ: Seçenek A'ya (sabit/ortak faz etkisi) düşülüyor. "
              "(Aksi halde BLUP'lar veri yokluğundan sıfıra yakınsar.)")
        tahmin_df = model_kur_ve_tahmin_et(mat)

    print("5) Çalışma saati filtresi uygulanıyor...")
    tahmin_df = calisma_saati_filtresi_uygula(tahmin_df, dem)

    print("6) Cold-start tahminleri ekleniyor...")
    cold_start_df = cold_start_tahmin(dem, mat)
    if len(cold_start_df) > 0:
        tahmin_df = pd.concat([tahmin_df, cold_start_df], ignore_index=True)

    tahmin_df['tahmini_saat_hhmm'] = tahmin_df['tahmini_saat'].apply(ondalik_saat_to_hhmm)

    print("\nSonuç örneği:")
    print(tahmin_df.head(10))

    tahmin_df.to_csv('bahar_mesaj_zamanlamasi_tahminleri.csv', index=False)
    print("\nKaydedildi: bahar_mesaj_zamanlamasi_tahminleri.csv")


if __name__ == "__main__":
    main()
