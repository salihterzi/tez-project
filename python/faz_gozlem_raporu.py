"""
Faz Bazlı Gözlem Sayısı Raporu
=================================
`mesaj_zamanlamasi_pipeline-v2.py` çıktısındaki `n_gozlem` kolonu, öğrencinin
TÜM fazlar toplamındaki materyal erişim sayısını taşır (bkz. model_kur_ve_tahmin_et
içindeki `n = counts[ogrenci_no]` satırı, faz döngüsünün dışında hesaplanıyor) —
bu yüzden 3 faz satırında da aynı görünür.

Bu script, her öğrencinin normal_hafta / ara_sinav_oncesi / final_oncesi
fazlarındaki gözlem sayısını AYRI AYRI hesaplar.

KULLANIM (python container'ı içinde):
    docker compose exec python python faz_gozlem_raporu.py
"""

import importlib.util
import pathlib

import pandas as pd

from db import get_engine

# Modül adı tire içerdiği için (`mesaj_zamanlamasi_pipeline-v2.py`) doğrudan import
# edilemiyor; veriyi_yukle/oznitelik_olustur fonksiyonlarını yeniden yazıp veri
# hazırlama mantığının iki dosyada birbirinden sapmasına yol açmamak için dosyayı
# yoluna göre yüklüyoruz.
_pipeline_path = pathlib.Path(__file__).parent / "mesaj_zamanlamasi_pipeline-v2.py"
_spec = importlib.util.spec_from_file_location("mesaj_zamanlamasi_pipeline_v2", _pipeline_path)
pipeline = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(pipeline)

FAZLAR = pipeline.FAZLAR


def faz_gozlem_tablosu(mat: pd.DataFrame) -> pd.DataFrame:
    """Öğrenci x FAZ kırılımında gözlem sayısı tablosu döner (+ satır toplamı)."""
    tablo = mat.groupby(['ogrenci_no', 'FAZ']).size().unstack(fill_value=0)
    for faz in FAZLAR:
        if faz not in tablo.columns:
            tablo[faz] = 0
    tablo = tablo[FAZLAR]
    tablo['toplam'] = tablo.sum(axis=1)
    return tablo.reset_index()


def main():
    engine = get_engine()

    print("Veri yükleniyor...")
    # DB'de şu an tek dönem var: 2024/2 (bahar) — pipeline-v2.py ile aynı.
    mat, _, _ = pipeline.veriyi_yukle(engine, yil=2024, donem=2)
    mat = pipeline.oznitelik_olustur(mat, pipeline.BAHAR_2024_TAKVIMI)

    tablo = faz_gozlem_tablosu(mat)

    print("\nFaz başına gözlem sayısı:")
    print(tablo.to_string(index=False))

    tablo.to_csv('faz_gozlem_sayilari.csv', index=False)
    print("\nKaydedildi: faz_gozlem_sayilari.csv")


if __name__ == "__main__":
    main()
