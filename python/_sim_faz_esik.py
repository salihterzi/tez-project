"""
Geçici simülasyon betiği (kalıcı pipeline'ın parçası DEĞİL).

Senaryo: sentetik öğrenci (999999001), YENİ dönemde (2024/2 verisine eklenen
sentetik gözlemlerle simüle ediliyor) tutarlı biçimde GECE ~23:00 civarı
materyale erişiyor. Amaç: bu öğrenci, MIN_GOZLEM_ESIGI (=4) eşiğini AŞMADAN
önce ve AŞTIKTAN sonra, her fazın tahmini_saat'inin nasıl değiştiğini göstermek.

Basitleştirme (hız için): yaş/cinsiyet'in HİÇBİR fazda anlamlı olmadığı bu
veri setinde daha önce tekrar tekrar doğrulandı (p>>0.05 üç fazda da) — bu
yüzden burada demografik anlamlılık testi ATLANIYOR, doğrudan "saat_sin ~ 1"
/ "saat_cos ~ 1" fit ediliyor. Bu, gerçek pipeline'ın üreteceği sonuçla
birebir aynı (çünkü gerçek pipeline de aynı nedenle terimsiz modele düşüyor).

Performans optimizasyonu: bir fazın girdi verisi bir adımdan diğerine
DEĞİŞMEDİYSE (sentetik öğrenci o faza yeni gözlem eklemediyse ve nüfus
"yeterli" kümesi o fazı etkilemediyse), o fazın modeli YENİDEN FIT EDİLMEZ,
önceki sonuç önbellekten kullanılır.
"""
import pandas as pd
import numpy as np
import statsmodels.formula.api as smf
import importlib.util

spec = importlib.util.spec_from_file_location('pipeline', 'mesaj_zamanlamasi_pipeline-v2.py')
pipeline = importlib.util.module_from_spec(spec)
spec.loader.exec_module(pipeline)

SENTETIK_ID = 999999001
TAKVIM = pipeline.BAHAR_2024_TAKVIMI

engine = pipeline.get_engine()
mat_ham, sin_ham, dem = pipeline.veriyi_yukle(engine, yil=2024, donem=2)
mat_ham = pipeline.mat_sin_birlestir(mat_ham, sin_ham)

NORMAL_TARIH = pd.Timestamp('2025-02-15')
ARA_SINAV_ONCESI_TARIH = TAKVIM.ara_sinav_oncesi_baslangic + pd.Timedelta(days=3)
FINAL_ONCESI_TARIH = TAKVIM.final_oncesi_baslangic + pd.Timedelta(days=3)

def sentetik_satirlar(tarih, saatler):
    satirlar = []
    for s in saatler:
        zaman = tarih + pd.Timedelta(hours=s)
        satirlar.append({
            'ogrenci_no': SENTETIK_ID, 'ders_kodu': 'SIM101', 'yil': 2024, 'donem': 2,
            'islem_zamani': zaman, 'etkinlik_tipi': 'materyal',
        })
    return satirlar

SAATLER = [22.75, 23.25]  # ortalama tam 23:00

adimlar = [
    ("0) n=0  (hic gozlem yok, esik ONCESI)", []),
    ("1) n=2  (+normal_hafta, esik ONCESI)", sentetik_satirlar(NORMAL_TARIH, SAATLER)),
    ("2) n=4  (+ara_sinav_oncesi, esik ASILDI)", sentetik_satirlar(ARA_SINAV_ONCESI_TARIH, SAATLER)),
    ("3) n=6  (+final_oncesi)", sentetik_satirlar(FINAL_ONCESI_TARIH, SAATLER)),
]

onbellek = {}  # faz -> (r_sin, r_cos)

def faz_modelini_getir(faz, faz_df):
    """Fazın girdi satırları (sentetik olmayanlar + sentetik) aynıysa önbellekten döner."""
    imza = (faz, len(faz_df), faz_df['ogrenci_no'].nunique())
    if faz in onbellek and onbellek[faz][0] == imza:
        return onbellek[faz][1]
    r_sin = smf.mixedlm("saat_sin ~ 1", faz_df, groups=faz_df['ogrenci_no_str']).fit()
    r_cos = smf.mixedlm("saat_cos ~ 1", faz_df, groups=faz_df['ogrenci_no_str']).fit()
    onbellek[faz] = (imza, (r_sin, r_cos))
    return r_sin, r_cos

birikmis = []
print(f"{'Adim':<40} {'n':>3} {'normal_hafta':>14} {'ara_sinav_oncesi':>17} {'final_oncesi':>14}")
print("-" * 95)

onceki = {}
for etiket, yeni_satirlar in adimlar:
    birikmis.extend(yeni_satirlar)
    mat = pd.concat([mat_ham, pd.DataFrame(birikmis)], ignore_index=True) if birikmis else mat_ham.copy()
    mat = pipeline.oznitelik_olustur(mat, TAKVIM)

    n = int((mat['ogrenci_no'] == SENTETIK_ID).sum())

    counts = mat.groupby('ogrenci_no').size()
    yeterli = counts[counts >= pipeline.MIN_GOZLEM_ESIGI].index
    sub = mat[mat['ogrenci_no'].isin(yeterli)].copy()
    sub['ogrenci_no_str'] = sub['ogrenci_no'].astype(str)

    sonuclar = {}
    yontemler = {}
    for faz in pipeline.FAZLAR:
        faz_df = sub[sub['FAZ'] == faz]
        r_sin, r_cos = faz_modelini_getir(faz, faz_df)
        kn = str(SENTETIK_ID)
        kisisel_mi = kn in r_sin.random_effects
        rastgele_sin = r_sin.random_effects.get(kn, {}).get('Group', 0)
        rastgele_cos = r_cos.random_effects.get(kn, {}).get('Group', 0)
        tahmini_sin = r_sin.fe_params['Intercept'] + rastgele_sin
        tahmini_cos = r_cos.fe_params['Intercept'] + rastgele_cos
        aci = np.arctan2(tahmini_sin, tahmini_cos)
        if aci < 0:
            aci += 2 * np.pi
        tahmini_saat = aci / (2 * np.pi) * 24
        sonuclar[faz] = tahmini_saat
        yontemler[faz] = 'kisisel' if kisisel_mi else 'popülasyon'

    def fmt(faz):
        v = sonuclar[faz]
        hhmm = pipeline.ondalik_saat_to_hhmm(v)
        etiket_y = 'K' if yontemler[faz] == 'kisisel' else 'P'
        degisti = onceki.get(faz) is not None and abs(onceki[faz] - v) > 0.001
        return f"{hhmm}({etiket_y}){'*' if degisti else ''}"

    print(f"{etiket:<40} {n:>3} {fmt('normal_hafta'):>14} {fmt('ara_sinav_oncesi'):>17} {fmt('final_oncesi'):>14}")
    onceki = dict(sonuclar)

print()
print("K = kisisel BLUP (bu fazda gozlemi var)   P = populasyon ortalamasi (bu fazda gozlemi yok)")
print("* = onceki adima gore degisti")
