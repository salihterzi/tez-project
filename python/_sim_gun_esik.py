"""
Geçici deneme: Gün modelinin (top-N mod) kendi min-faz-gözlem eşiğini, saat
modelinin BLUP shrinkage formülünü ödünç almadan, ampirik alt-örnekleme
(subsampling) ile türetir.

Yöntem:
  1) Her FAZ için, o fazda >= MIN_TRUE_N gözlemi olan "veri zengin" öğrenciler
     bulunur -- bunların TÜM verisinden çıkan top-N gün, o öğrenci için
     "gerçek" (ground-truth) kabul edilir.
  2) Aday n değerleri için, her veri-zengin öğrencinin gözlemlerinden rastgele
     n tanesi (TEKRAR kez) örneklenir, o örneklemden top-N hesaplanır, ve
     "gerçek" top-N ile örtüşme oranı (|kesişim| / top_n) ölçülür.
  3) Tüm öğrenciler ve tekrarlar üzerinden ortalama örtüşme oranı, n'nin
     fonksiyonu olarak raporlanır. Hedef oran (0.7) ilk aşılan n, o fazın
     n_esik'i olarak önerilir.

Bu dosya kalıcı değil -- sadece n_esik'in gerçek veride ne çıktığını görmek için.
"""

import importlib.util
import pathlib
import warnings

import numpy as np
import pandas as pd

from db import get_engine

warnings.filterwarnings('ignore')

_pipeline_path = pathlib.Path(__file__).parent / "mesaj_zamanlamasi_pipeline-v2.py"
_spec = importlib.util.spec_from_file_location("mesaj_zamanlamasi_pipeline_v2", _pipeline_path)
pipeline = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(pipeline)

_gun_path = pathlib.Path(__file__).parent / "mesaj_zamanlamasi_gun_hibrit.py"
_spec2 = importlib.util.spec_from_file_location("mesaj_zamanlamasi_gun_hibrit", _gun_path)
gun_mod = importlib.util.module_from_spec(_spec2)
_spec2.loader.exec_module(gun_mod)

FAZLAR = pipeline.FAZLAR
FAZ_TOP_N = gun_mod.FAZ_TOP_N
_top_n_gun = gun_mod._top_n_gun

MIN_TRUE_N = 40      # "gerçek" top-N'i güvenilir saymak için gereken minimum toplam gözlem
MAKS_OGRENCI = 300   # hız için, veri-zengin havuzdan örneklenecek maksimum öğrenci
ADAY_N = [1, 2, 3, 4, 5, 6, 8, 10, 12, 15, 20, 25, 30, 35]
TEKRAR = 50          # her (öğrenci, n) için kaç kez rastgele alt-örnekleme yapılacak
HEDEF_ORAN = 0.7

rng = np.random.default_rng(42)


def main():
    engine = get_engine()
    mat, _, dem = pipeline.veriyi_yukle(engine, yil=2024, donem=2)
    mat = pipeline.oznitelik_olustur(mat, pipeline.BAHAR_2024_TAKVIMI)

    # Popülasyon havuzu (MIN_GOZLEM_ESIGI'yi geçen öğrenciler) -- gun_hibrit.py'deki
    # fallback ile aynı tanım.
    counts_toplam = mat.groupby('ogrenci_no').size()
    yeterli = counts_toplam[counts_toplam >= pipeline.MIN_GOZLEM_ESIGI].index
    sub_pop = mat[mat['ogrenci_no'].isin(yeterli)]

    sonuc_satirlari = []
    baseline_satirlari = []
    for faz in FAZLAR:
        top_n = FAZ_TOP_N[faz]
        faz_mat = mat[mat['FAZ'] == faz]
        counts = faz_mat.groupby('ogrenci_no').size()
        veri_zengin = counts[counts >= MIN_TRUE_N].index

        print(f"FAZ={faz}: veri zengin öğrenci sayısı (>={MIN_TRUE_N} gözlem) = {len(veri_zengin)}")

        if len(veri_zengin) > MAKS_OGRENCI:
            veri_zengin = rng.choice(veri_zengin, size=MAKS_OGRENCI, replace=False)

        ogrenci_gunler = {
            o: faz_mat.loc[faz_mat['ogrenci_no'] == o, 'gun_index'].values
            for o in veri_zengin
        }
        gercek_top = {
            o: set(_top_n_gun(pd.Series(g), top_n))
            for o, g in ogrenci_gunler.items()
        }

        # BASELINE: hiç kişiselleştirme yapmadan, HERKESE popülasyonun top-N'ini
        # atasaydık, ortalama örtüşme ne olurdu? (bireysel verinin "değer katmaya
        # başladığı" n'i bulmak için referans çizgi.)
        pop_top = set(_top_n_gun(sub_pop.loc[sub_pop['FAZ'] == faz, 'gun_index'], top_n))
        baseline_oranlar = [len(pop_top & gercek_top[o]) / top_n for o in ogrenci_gunler]
        baseline_ort = float(np.mean(baseline_oranlar))
        baseline_satirlari.append({'FAZ': faz, 'top_n': top_n,
                                    'populasyon_baseline_orani': round(baseline_ort, 3)})

        for n in ADAY_N:
            oranlar = []
            for o, gunler in ogrenci_gunler.items():
                if len(gunler) < n:
                    continue
                gercek = gercek_top[o]
                for _ in range(TEKRAR):
                    orn = rng.choice(gunler, size=n, replace=False)
                    tahmin = set(_top_n_gun(pd.Series(orn), top_n))
                    oran = len(tahmin & gercek) / top_n
                    oranlar.append(oran)
            if oranlar:
                ort_oran = float(np.mean(oranlar))
                sonuc_satirlari.append({
                    'FAZ': faz, 'n': n, 'top_n': top_n,
                    'ort_ortusme_orani': round(ort_oran, 3),
                    'n_ogrenci': len(oranlar) // TEKRAR,
                })

    sonuc = pd.DataFrame(sonuc_satirlari)
    baseline = pd.DataFrame(baseline_satirlari)
    print()
    print("=== Popülasyon baseline (hiç kişiselleştirme yapılmasa, ortalama örtüşme) ===")
    print(baseline.to_string(index=False))
    print()
    print(sonuc.to_string(index=False))

    print(f"\n=== Hedef örtüşme oranı >= {HEDEF_ORAN} için ilk n ===")
    for faz in FAZLAR:
        alt = sonuc[sonuc['FAZ'] == faz].sort_values('n')
        yeterli = alt[alt['ort_ortusme_orani'] >= HEDEF_ORAN]
        if len(yeterli) > 0:
            print(f"{faz}: n_esik (hedef %70) = {int(yeterli.iloc[0]['n'])}")
        else:
            print(f"{faz}: hiçbir aday n, hedef orana ulaşamadı "
                  f"(en yüksek: {alt['ort_ortusme_orani'].max():.3f})")

    print(f"\n=== Bireysel verinin popülasyon baseline'ını İLK GEÇTİĞİ n (crossover) ===")
    for faz in FAZLAR:
        alt = sonuc[sonuc['FAZ'] == faz].sort_values('n')
        taban = baseline.loc[baseline['FAZ'] == faz, 'populasyon_baseline_orani'].iloc[0]
        gecen = alt[alt['ort_ortusme_orani'] > taban]
        if len(gecen) > 0:
            print(f"{faz}: popülasyon baseline={taban:.3f}, bunu ilk geçen n = "
                  f"{int(gecen.iloc[0]['n'])} (o n'de oran={gecen.iloc[0]['ort_ortusme_orani']:.3f})")
        else:
            print(f"{faz}: popülasyon baseline={taban:.3f}, hiçbir aday n bunu geçemedi")


if __name__ == "__main__":
    main()
