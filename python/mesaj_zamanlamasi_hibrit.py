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

KULLANIM (python container'ı içinde):
    docker compose exec python python mesaj_zamanlamasi_hibrit.py
"""

import importlib.util
import pathlib

import numpy as np
import pandas as pd
import statsmodels.formula.api as smf
import warnings

from db import get_engine

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


# =====================================================================
# HİBRİT MODEL
# =====================================================================

def model_kur_ve_tahmin_et_hibrit(mat, dem, min_faz_gozlem=MIN_FAZ_GOZLEM_ESIGI):
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
            })

    return pd.DataFrame(sonuclar)


# =====================================================================
# ANA AKIŞ
# =====================================================================

def main():
    engine = get_engine()

    print("1) Veri yükleniyor...")
    # DB'de şu an tek dönem var: 2024/2 (bahar) — pipeline-v2.py ile aynı.
    mat, _, dem = pipeline.veriyi_yukle(engine, yil=2024, donem=2)

    print("2) Öznitelikler oluşturuluyor...")
    mat = pipeline.oznitelik_olustur(mat, pipeline.BAHAR_2024_TAKVIMI)
    dem = pipeline.demografik_hazirla(dem)

    print("3) Hibrit model (öğrenci x faz bazlı Seçenek A/B) kuruluyor...")
    tahmin_df = model_kur_ve_tahmin_et_hibrit(mat, dem)

    dagilim = tahmin_df['yontem'].value_counts()
    print("\nYöntem dağılımı (öğrenci x faz satırı bazında):")
    print(dagilim.to_string())
    toplam = len(tahmin_df)
    print(f"\n-> %{100 * dagilim.get('secenek_b_bireysel', 0) / toplam:.1f} satırda öğrencinin "
          f"kendi faz-eğimi (Seçenek B), "
          f"%{100 * dagilim.get('secenek_a_fallback', 0) / toplam:.1f} satırda popülasyon faz "
          f"etkisine (Seçenek A -- az veri olan kendi intercept'iyle, hiç veri olmayan saf "
          f"popülasyon ortalamasıyla) düşüldü.")

    print("\n4) Çalışma saati filtresi uygulanıyor...")
    tahmin_df = pipeline.calisma_saati_filtresi_uygula(tahmin_df, dem)

    tahmin_df['tahmini_saat_hhmm'] = tahmin_df['tahmini_saat'].apply(pipeline.ondalik_saat_to_hhmm)

    print("\nSonuç örneği:")
    print(tahmin_df.head(10).to_string(index=False))

    tahmin_df.to_csv('bahar_mesaj_zamanlamasi_tahminleri_hibrit.csv', index=False)
    print("\nKaydedildi: bahar_mesaj_zamanlamasi_tahminleri_hibrit.csv")


if __name__ == "__main__":
    main()
