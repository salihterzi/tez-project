"""
Aylık Gözlem Sayısı Raporu
=============================
Her öğrencinin materyal erişim (gözlem) sayısını AY bazında kırar. `faz_gozlem_raporu.py`
ile aynı mantık — sadece kırılım FAZ yerine (yıl, ay).

KULLANIM (python container'ı içinde):
    docker compose exec python python aylik_gozlem_raporu.py
"""

import importlib.util
import pathlib

import pandas as pd

from db import get_engine

# Modül adı tire içerdiği için (`mesaj_zamanlamasi_pipeline-v2.py`) doğrudan import
# edilemiyor; veriyi_yukle fonksiyonunu yeniden yazıp veri hazırlama mantığının
# iki dosyada birbirinden sapmasına yol açmamak için dosyayı yoluna göre yüklüyoruz.
_pipeline_path = pathlib.Path(__file__).parent / "mesaj_zamanlamasi_pipeline-v2.py"
_spec = importlib.util.spec_from_file_location("mesaj_zamanlamasi_pipeline_v2", _pipeline_path)
pipeline = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(pipeline)


def aylik_gozlem_tablosu(mat: pd.DataFrame) -> pd.DataFrame:
    """Öğrenci x Ay (YYYY-MM) kırılımında gözlem sayısı tablosu döner (+ satır toplamı)."""
    mat = mat.copy()
    mat['yil_ay'] = mat['islem_zamani'].dt.to_period('M').astype(str)

    tablo = mat.groupby(['ogrenci_no', 'yil_ay']).size().unstack(fill_value=0)
    tablo = tablo[sorted(tablo.columns)]  # ayları kronolojik sırala
    tablo['toplam'] = tablo.sum(axis=1)
    return tablo.reset_index()


def main():
    engine = get_engine()

    print("Veri yükleniyor...")
    # DB'de şu an tek dönem var: 2024/2 (bahar) — pipeline-v2.py ile aynı.
    mat, _, _ = pipeline.veriyi_yukle(engine, yil=2024, donem=2)

    tablo = aylik_gozlem_tablosu(mat)

    print("\nAy başına gözlem sayısı:")
    print(tablo.to_string(index=False))

    tablo.to_csv('aylik_gozlem_sayilari.csv', index=False)
    print("\nKaydedildi: aylik_gozlem_sayilari.csv")


if __name__ == "__main__":
    main()
