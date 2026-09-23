"""
Geçici deneme: Öğrenci 2572'nin normal_hafta senaryosunu (kronolojik olarak
artan n = 2,4,6,8,10...) YENİ Seçenek C (önceki dönem önseli) mekanizmasıyla
tekrar çalıştırır. "Önceki dönem" olarak, bu öğrencinin TÜM 134 gözleminden
çıkan gerçek top-3'ü (secenek_b_bireysel) kullanılır -- DB'de gerçekten ikinci
bir dönem olmadığı için, bunu simüle edilmiş bir önceki-dönem sonucu olarak
veriyoruz (gerçek üretimde bu, bir önceki dönemin GERÇEK CSV çıktısından gelir).

Her n için gerçek `model_kur_ve_tahmin_et_gun_hibrit` fonksiyonu, hem
onceki_donem_df=None (eski davranış) hem de onceki_donem_df=<simüle edilen
önsel> (yeni davranış) ile çağrılır -- ikisi yan yana karşılaştırılır.

Bu dosya kalıcı değil -- sadece Seçenek C'nin etkisini somut göstermek için.
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
model_kur_ve_tahmin_et_gun_hibrit = gun_mod.model_kur_ve_tahmin_et_gun_hibrit

OGRENCI_NO = 2572
FAZ = 'normal_hafta'
ADAY_N = [2, 4, 6, 8, 10, 15, 20, 30]


def gunler_str(indexler):
    return ", ".join(GUN_ADLARI[i] for i in indexler) if indexler else "(yok)"


def cikti_al(sonuc_df):
    satirlar = sonuc_df[(sonuc_df['ogrenci_no'] == OGRENCI_NO) & (sonuc_df['FAZ'] == FAZ)] \
        .sort_values('gun_sira')
    gunler = satirlar['tahmini_gun_index'].tolist()
    yontemler = sorted(set(satirlar['yontem']))
    return gunler_str(gunler), "+".join(yontemler) if yontemler else "(yok)"


def main():
    engine = get_engine()
    mat, _, dem_tum = pipeline.veriyi_yukle(engine, yil=2024, donem=2)
    mat = pipeline.oznitelik_olustur(mat, pipeline.BAHAR_2024_TAKVIMI)

    top_n = FAZ_TOP_N[FAZ]
    ogr = mat[(mat['ogrenci_no'] == OGRENCI_NO) & (mat['FAZ'] == FAZ)].sort_values('islem_zamani')
    print(f"Öğrenci {OGRENCI_NO}, FAZ={FAZ}: toplam {len(ogr)} gözlem, "
          f"GUN_MIN_FAZ_GOZLEM_ESIGI={GUN_MIN_FAZ_GOZLEM_ESIGI}\n")

    gercek_top = _top_n_gun(ogr['gun_index'], top_n)
    print(f"[Referans/simüle edilen 'önceki dönem'] TÜM {len(ogr)} gözlemden top-{top_n}: "
          f"{gunler_str(gercek_top)}\n")

    onceki_df = pd.DataFrame([
        {'ogrenci_no': OGRENCI_NO, 'FAZ': FAZ, 'gun_sira': i + 1,
         'tahmini_gun_index': g, 'yontem': 'secenek_b_bireysel'}
        for i, g in enumerate(gercek_top)
    ])

    # Popülasyon havuzu değişmeyeceği için dem'i SADECE 2572'ye indirip döngüyü
    # hızlandırıyoruz -- mat (popülasyon hesaplaması için) tam kalıyor.
    dem_tek = pd.DataFrame({'ogrenci_no': [OGRENCI_NO]})

    mat_disinda = mat[~((mat['ogrenci_no'] == OGRENCI_NO) & (mat['FAZ'] == FAZ))]

    print(f"{'n':>3} | {'ESKİ (onceki_donem_df=None)':45} | {'YENİ (Seçenek C aktif)':45}")
    print("-" * 100)
    for n in ADAY_N:
        alt = ogr.iloc[:n]
        mat_n = pd.concat([mat_disinda, alt], ignore_index=True)

        eski = model_kur_ve_tahmin_et_gun_hibrit(mat_n, dem_tek, onceki_donem_df=None)
        yeni = model_kur_ve_tahmin_et_gun_hibrit(mat_n, dem_tek, onceki_donem_df=onceki_df)

        eski_gunler, eski_yontem = cikti_al(eski)
        yeni_gunler, yeni_yontem = cikti_al(yeni)

        print(f"{n:>3} | {eski_gunler + ' [' + eski_yontem + ']':45} | "
              f"{yeni_gunler + ' [' + yeni_yontem + ']':45}")


if __name__ == "__main__":
    main()
