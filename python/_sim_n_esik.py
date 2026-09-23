"""
Geçici deneme: MIN_FAZ_GOZLEM_ESIGI'yi rastgele seçmek yerine BLUP shrinkage
formülünden türetmek için, hibrit modeldeki cov_re/scale'den her faz için
n_esik(faz) = w(faz) * lambda / (1 - lambda) değerini hesaplar.

w(faz) = sigma^2 / tau^2(faz)
  sigma^2 = model artık (residual) varyansı (r.scale) -- sin/cos modelinde ortak
  tau^2(faz) = o fazın kişiler-arası (between-student) birleşik varyansı:
    normal_hafta:       Var(Group)
    diger fazlar:        Var(Group) + Var(faz_slope) + 2*Cov(Group, faz_slope)

Bu dosya kalıcı değil -- sadece n_esik'in gerçek veride ne çıktığını görmek için.
"""

import pandas as pd
import statsmodels.formula.api as smf
import warnings

from db import get_engine

warnings.filterwarnings('ignore')

import importlib.util
import pathlib

_pipeline_path = pathlib.Path(__file__).parent / "mesaj_zamanlamasi_pipeline-v2.py"
_spec = importlib.util.spec_from_file_location("mesaj_zamanlamasi_pipeline_v2", _pipeline_path)
pipeline = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(pipeline)

FAZLAR = pipeline.FAZLAR


def tau2_birlesik(cov_re, faz):
    if faz == 'normal_hafta':
        return cov_re.loc['Group', 'Group']
    kolon = f'C(FAZ)[T.{faz}]'
    if kolon not in cov_re.columns:
        return None
    return (cov_re.loc['Group', 'Group']
            + cov_re.loc[kolon, kolon]
            + 2 * cov_re.loc['Group', kolon])


def n_esik_hesapla(r, faz, lam):
    tau2 = tau2_birlesik(r.cov_re, faz)
    if tau2 is None or tau2 <= 0:
        return None
    w = r.scale / tau2
    return w * lam / (1 - lam)


def main():
    engine = get_engine()
    mat, _, dem = pipeline.veriyi_yukle(engine, yil=2024, donem=2)
    mat = pipeline.oznitelik_olustur(mat, pipeline.BAHAR_2024_TAKVIMI)

    sub = mat.copy()
    sub['ogrenci_no_str'] = sub['ogrenci_no'].astype(str)
    sub['FAZ'] = pd.Categorical(sub['FAZ'], categories=FAZLAR)

    print(f"Toplam gözlem: {len(sub)}, öğrenci sayısı: {sub['ogrenci_no'].nunique()}")
    print(sub.groupby('FAZ').size().to_string())
    print()

    m_sin = smf.mixedlm("saat_sin ~ C(FAZ)", sub, groups=sub['ogrenci_no_str'],
                         re_formula="~C(FAZ)")
    r_sin = m_sin.fit()
    m_cos = smf.mixedlm("saat_cos ~ C(FAZ)", sub, groups=sub['ogrenci_no_str'],
                         re_formula="~C(FAZ)")
    r_cos = m_cos.fit()

    print("=== saat_sin modeli ===")
    print(f"scale (sigma^2) = {r_sin.scale:.5f}")
    print("cov_re:")
    print(r_sin.cov_re.to_string())
    print()

    print("=== saat_cos modeli ===")
    print(f"scale (sigma^2) = {r_cos.scale:.5f}")
    print("cov_re:")
    print(r_cos.cov_re.to_string())
    print()

    print("=== n_esik(faz) tablosu ===")
    satirlar = []
    for faz in FAZLAR:
        for lam in (0.5, 0.7):
            n_sin = n_esik_hesapla(r_sin, faz, lam)
            n_cos = n_esik_hesapla(r_cos, faz, lam)
            n_max = max(n_sin, n_cos) if (n_sin is not None and n_cos is not None) else None
            satirlar.append({
                'FAZ': faz, 'lambda': lam,
                'n_esik_sin': round(n_sin, 2) if n_sin is not None else None,
                'n_esik_cos': round(n_cos, 2) if n_cos is not None else None,
                'n_esik_max': round(n_max, 2) if n_max is not None else None,
            })
    sonuc = pd.DataFrame(satirlar)
    print(sonuc.to_string(index=False))


if __name__ == "__main__":
    main()
