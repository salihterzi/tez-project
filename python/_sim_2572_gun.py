"""
Geçici deneme: Öğrenci 2572'nin normal_hafta fazındaki gerçek gözlemlerini
KRONOLOJİK sırayla (yeni dönemde veri birikir gibi) artan n (2,4,6,8,10) ile
işleyip, top-3 gün tahmininin nasıl değiştiğini/stabilize olduğunu gösterir.

Hem "sadece o ana kadarki kendi verisinden çıkan ham top-3"yi (n eşiği yokmuş
gibi), hem de GUN_MIN_FAZ_GOZLEM_ESIGI=10 eşiğiyle modelin o anda GERÇEKTE ne
döndüreceğini (n<10 iken popülasyon fallback) ayrı ayrı gösterir.

Bu dosya kalıcı değil -- sadece somut bir örnekle eşiğin etkisini görmek için.
"""

import importlib.util
import pathlib
import warnings

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

_top_n_gun = gun_mod._top_n_gun
GUN_ADLARI = gun_mod.GUN_ADLARI
FAZ_TOP_N = gun_mod.FAZ_TOP_N
GUN_MIN_FAZ_GOZLEM_ESIGI = gun_mod.GUN_MIN_FAZ_GOZLEM_ESIGI

OGRENCI_NO = 2572
FAZ = 'normal_hafta'
ADAY_N = [2, 4, 6, 8, 10, 15, 20, 30, 50, 80, 110, 134]


def gunler_str(indexler):
    return ", ".join(GUN_ADLARI[i] for i in indexler) if indexler else "(yok)"


def main():
    engine = get_engine()
    mat, _, dem = pipeline.veriyi_yukle(engine, yil=2024, donem=2)
    mat = pipeline.oznitelik_olustur(mat, pipeline.BAHAR_2024_TAKVIMI)

    top_n = FAZ_TOP_N[FAZ]

    ogr = mat[(mat['ogrenci_no'] == OGRENCI_NO) & (mat['FAZ'] == FAZ)].sort_values('islem_zamani')
    print(f"Öğrenci {OGRENCI_NO}, FAZ={FAZ}: toplam {len(ogr)} gözlem "
          f"({ogr['islem_zamani'].min()} -- {ogr['islem_zamani'].max()})")
    print(f"top_n (bu fazda seçilecek gün sayısı) = {top_n}, "
          f"GUN_MIN_FAZ_GOZLEM_ESIGI = {GUN_MIN_FAZ_GOZLEM_ESIGI}\n")

    gercek_top = _top_n_gun(ogr['gun_index'], top_n)
    print(f"[Referans] TÜM {len(ogr)} gözlemden top-{top_n}: {gunler_str(gercek_top)}\n")

    # popülasyon fallback (normal_hafta) -- MIN_GOZLEM_ESIGI eşiğini geçen öğrenci havuzundan
    counts_toplam = mat.groupby('ogrenci_no').size()
    yeterli = counts_toplam[counts_toplam >= pipeline.MIN_GOZLEM_ESIGI].index
    sub = mat[mat['ogrenci_no'].isin(yeterli)]
    pop_top = _top_n_gun(sub.loc[sub['FAZ'] == FAZ, 'gun_index'], top_n)
    print(f"[Popülasyon fallback] {FAZ} için top-{top_n}: {gunler_str(pop_top)}\n")

    print(f"{'n':>3} | {'ham bireysel top-'+str(top_n):40} | {'gün dağılımı (o ana kadar)':30} | model çıktısı (eşik={GUN_MIN_FAZ_GOZLEM_ESIGI})")
    print("-" * 130)
    for n in ADAY_N:
        alt = ogr.iloc[:n]
        gunler = alt['gun_index']
        ham_top = _top_n_gun(gunler, top_n)
        dagilim = gunler.value_counts().sort_values(ascending=False)
        dagilim_str = ", ".join(f"{GUN_ADLARI[g]}={c}" for g, c in dagilim.items())

        if n >= GUN_MIN_FAZ_GOZLEM_ESIGI:
            model_sonuc = ham_top.copy()
            for g in pop_top:
                if len(model_sonuc) >= top_n:
                    break
                if g not in model_sonuc:
                    model_sonuc.append(g)
            model_str = gunler_str(model_sonuc) + "  [bireysel]"
        else:
            model_str = gunler_str(pop_top) + "  [popülasyon fallback]"

        print(f"{n:>3} | {gunler_str(ham_top):40} | {dagilim_str:30} | {model_str}")


if __name__ == "__main__":
    main()
