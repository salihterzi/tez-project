"""
Geçici deneme: saat modelinin hassasiyet harmanındaki dönem geçişi belirsizliği
sabitini (Q_ORANI = Q/τ²), iki gerçek dönem arasındaki geçişi kullanarak
ampirik olarak türetir -- _sim_gun_esik.py'nin ızgara taraması + alt-örnekleme
metodolojisiyle AYNI ruhta (bkz. o dosyanın docblock'u).

Yöntem:
  1) AKTIF_DONEM'de (test/doğrulama dönemi) veri-zengin (>= MIN_TRUE_N gözlem)
     öğrenciler bulunur -- bunların TÜM verisinden çıkan dairesel ortalama
     saat, o öğrenci-faz için "gerçek" (ground-truth) kabul edilir.
  2) Aynı öğrencilerin ONCEKI dönemdeki (zaten `saat_tahmini_gecmisi`'nde
     kayıtlı, `kisi_bazli_son_onselleri_getir()` ile okunan) secenek_b_bireysel
     sonucu "önceki dönem" girdisi olarak kullanılır -- hangi dönem olduğu
     elle seçilmiyor, gün modeliyle AYNI "kişi bazlı en son önsel" kuralıyla
     otomatik bulunuyor.
  3) Aday n (AKTIF_DONEM'den rastgele alınan alt-örnek büyüklüğü) ve aday
     Q_ORANI değerleri için, her kombinasyon TEKRAR kez denenir:
       - AKTIF_DONEM'in n'lik rastgele alt-örneğinden "mevcut" tahmini (ham
         dairesel ortalama -- gerçek pipeline'daki BLUP-shrink edilmiş
         tahmin_mevcut'un ucuz bir vekili, _sim_gun_esik.py'nin ham top-N
         kullanmasıyla aynı sadeleştirme mantığı) hesaplanır.
       - ÖNCEKİ dönemin bilinen sonucuyla, adaydaki Q_ORANI'yla harmanlanır
         (`mesaj_zamanlamasi_hibrit.py`'deki formülün AYNISI, bkz. _harmanla()).
       - Harman sonucu, adım 1'deki GERÇEK değerle dairesel hata (saat
         biriminde, mutlak açısal fark) üzerinden kıyaslanır.
  4) Tüm öğrenciler/tekrarlar/n değerleri üzerinden ortalama hatayı EN DÜŞÜK
     yapan Q_ORANI, önerilen sabit olarak raporlanır.

Basitleştirme: sin ve cos modellerinin kendi τ²/σ²'si ayrı ayrı değil, İKİSİNİN
ORTALAMASI tek bir "temsilci" τ²/σ² olarak kullanılıyor -- amaç Q_ORANI'yı
kabaca bulmak, üretimdeki nihai hassasiyeti değil (üretim kodu,
`mesaj_zamanlamasi_hibrit.py`, sin/cos'u hâlâ ayrı ayrı harmanlıyor).

ÇALIŞTIRMADAN ÖNCE: AKTIF_DONEM'i gerçek bir dönemle doldurman gerekiyor --
DB'de şu an (2026-10-16) tek dönem (2024/2 bahar) var, bu yüzden bu script
HENÜZ ÇALIŞTIRILAMAZ (kisi_bazli_son_onselleri_getir hiçbir satır bulamaz,
hiçbir FAZ için veri-zengin+önceki-dönemi-olan öğrenci kalmaz). İkinci gerçek
dönem verisi gelip en az bir kere `mesaj_zamanlamasi_hibrit.py` ile çalıştırılıp
`saat_tahmini_gecmisi`'ye yazılınca kullanılabilir hale gelecek.

Bu dosya kalıcı değil -- sadece Q_ORANI'nin gerçek veride ne çıktığını görmek
için (bkz. _sim_n_esik.py / _sim_gun_esik.py ile aynı amaç).
"""

import importlib.util
import pathlib
import warnings

import numpy as np
import pandas as pd
import statsmodels.formula.api as smf

from db import get_connection, get_engine

warnings.filterwarnings('ignore')

_pipeline_path = pathlib.Path(__file__).parent / "mesaj_zamanlamasi_pipeline-v2.py"
_spec = importlib.util.spec_from_file_location("mesaj_zamanlamasi_pipeline_v2", _pipeline_path)
pipeline = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(pipeline)

_hibrit_path = pathlib.Path(__file__).parent / "mesaj_zamanlamasi_hibrit.py"
_spec2 = importlib.util.spec_from_file_location("mesaj_zamanlamasi_hibrit", _hibrit_path)
hibrit_mod = importlib.util.module_from_spec(_spec2)
_spec2.loader.exec_module(hibrit_mod)

FAZLAR = pipeline.FAZLAR

# ÇALIŞTIRMADAN ÖNCE DOLDUR: en az 1 tamamlanmış dönem geçişi gerekiyor --
# AKTIF_DONEM'den ÖNCE, saat_tahmini_gecmisi'nde kayıtlı en az bir dönem olmalı
# (bkz. modül docstring'i). İkinci bir geçişi de kontrol etmek istersen (bu
# projenin "tek okumaya güvenme" prensibi -- bkz. eşik=4/eşik=10'un türetimi),
# scripti bir de bir SONRAKİ dönemi AKTIF_DONEM yaparak tekrar çalıştır.
AKTIF_DONEM = None  # örn. (2025, 1)

MIN_TRUE_N = 40          # "gerçek" saati güvenilir saymak için gereken minimum gözlem
MAKS_OGRENCI = 300       # hız için, veri-zengin havuzdan örneklenecek maksimum öğrenci
ADAY_N = [0, 1, 2, 3, 4, 5, 6, 8, 10, 15, 20, 30]
ADAY_Q_ORANI = [0.0, 1 / 10, 1 / 5, 1 / 3, 1 / 2, 1.0, 2.0]
TEKRAR = 50

rng = np.random.default_rng(42)


def _dairesel_ortalama_saat(saatler):
    """Bir saat dizisinin (0-24 ondalik) dairesel ortalamasını döner."""
    acilar = np.asarray(saatler) / 24 * 2 * np.pi
    sin_ort, cos_ort = np.mean(np.sin(acilar)), np.mean(np.cos(acilar))
    aci = np.arctan2(sin_ort, cos_ort)
    if aci < 0:
        aci += 2 * np.pi
    return aci / (2 * np.pi) * 24


def _dairesel_fark_saat(tahmin_saat, gercek_saat):
    """İki saat arasındaki dairesel (23:50 ile 00:10 gibi) mutlak farkı, saat
    biriminde ([0, 12]) döner."""
    fark_radyan = (tahmin_saat - gercek_saat) / 24 * 2 * np.pi
    fark_radyan = np.arctan2(np.sin(fark_radyan), np.cos(fark_radyan))
    return abs(fark_radyan) / (2 * np.pi) * 24


def _harmanla(tahmin_mevcut_saat, n_mevcut, tahmin_onceki_saat, n_onceki, tau2, sigma2, q_orani):
    """`mesaj_zamanlamasi_hibrit.py`'deki hassasiyet harmanı formülünün AYNISI
    -- içe aktarılmadı, çünkü orada sin/cos bileşenleri üzerinde ayrı ayrı
    çalışıyor; burada tek bir "temsilci" τ²/σ² (sin ve cos'un ortalaması) ile
    sadeleştirilmiş bir versiyon kullanılıyor (bkz. modül docstring'i)."""
    aci_mevcut = tahmin_mevcut_saat / 24 * 2 * np.pi
    aci_onceki = tahmin_onceki_saat / 24 * 2 * np.pi
    w = sigma2 / tau2
    lambda_onceki = n_onceki / (n_onceki + w)
    q = tau2 * q_orani
    kalan_belirsizlik = tau2 * (1 - lambda_onceki) + q
    precision_onceki = 1 / kalan_belirsizlik
    n0 = sigma2 * precision_onceki
    agirlik_mevcut = n_mevcut / (n_mevcut + n0)

    sin_h = agirlik_mevcut * np.sin(aci_mevcut) + (1 - agirlik_mevcut) * np.sin(aci_onceki)
    cos_h = agirlik_mevcut * np.cos(aci_mevcut) + (1 - agirlik_mevcut) * np.cos(aci_onceki)
    aci_h = np.arctan2(sin_h, cos_h)
    if aci_h < 0:
        aci_h += 2 * np.pi
    return aci_h / (2 * np.pi) * 24


def main():
    if AKTIF_DONEM is None:
        raise SystemExit(
            "AKTIF_DONEM henüz doldurulmadı -- DB'de ikinci gerçek dönem "
            "verisi gelince bu dosyanın başındaki sabiti doldurup çalıştır "
            "(bkz. modül docstring'i)."
        )

    engine = get_engine()
    yil, donem = AKTIF_DONEM

    print(f"1) Aktif dönem ({yil}/{donem}) verisi yükleniyor...")
    takvim = pipeline.TAKVIM_KAYITLARI.get((yil, donem), pipeline.BAHAR_2024_TAKVIMI)
    mat, _, dem = pipeline.veriyi_yukle(engine, yil=yil, donem=donem)
    mat = pipeline.oznitelik_olustur(mat, takvim)

    print("2) Aktif dönemin kendi mixedlm'i fit ediliyor (τ²/σ² için)...")
    sub = mat.copy()
    sub['ogrenci_no_str'] = sub['ogrenci_no'].astype(str)
    sub['FAZ'] = pd.Categorical(sub['FAZ'], categories=FAZLAR)
    r_sin = smf.mixedlm("saat_sin ~ C(FAZ)", sub, groups=sub['ogrenci_no_str'],
                         re_formula="~C(FAZ)").fit()
    r_cos = smf.mixedlm("saat_cos ~ C(FAZ)", sub, groups=sub['ogrenci_no_str'],
                         re_formula="~C(FAZ)").fit()

    print("3) Önceki dönemin secenek_b_bireysel sonuçları saat_tahmini_gecmisi'nden "
          "okunuyor (kişi bazlı en son önsel)...")
    with get_connection() as conn:
        hibrit_mod._gecmis_tablosunu_hazirla(conn)
        onceki_df = hibrit_mod.kisi_bazli_son_onselleri_getir(conn, yil, donem)
    onceki_lookup = {
        (int(r.ogrenci_no), r.FAZ): (int(r.n_gozlem), float(r.tahmini_saat))
        for r in onceki_df.itertuples(index=False)
    }
    print(f"   {len(onceki_lookup)} öğrenci x faz için önceki dönem sonucu bulundu.")

    sonuc_satirlari = []
    for faz in FAZLAR:
        tau2_sin = hibrit_mod._tau2_birlesik(r_sin.cov_re, faz)
        tau2_cos = hibrit_mod._tau2_birlesik(r_cos.cov_re, faz)
        if tau2_sin is None or tau2_cos is None or tau2_sin <= 0 or tau2_cos <= 0:
            print(f"   ! {faz}: τ² hesaplanamadı, atlanıyor.")
            continue
        tau2 = (tau2_sin + tau2_cos) / 2       # basitleştirme: sin/cos ortalaması
        sigma2 = (r_sin.scale + r_cos.scale) / 2

        faz_mat = mat[mat['FAZ'] == faz]
        counts = faz_mat.groupby('ogrenci_no').size()
        veri_zengin = [
            int(o) for o in counts[counts >= MIN_TRUE_N].index
            if (int(o), faz) in onceki_lookup
        ]

        print(f"FAZ={faz}: veri zengin VE önceki dönemi olan öğrenci sayısı = {len(veri_zengin)}")
        if len(veri_zengin) == 0:
            continue
        if len(veri_zengin) > MAKS_OGRENCI:
            veri_zengin = list(rng.choice(veri_zengin, size=MAKS_OGRENCI, replace=False))

        ogrenci_saatleri = {
            o: faz_mat.loc[faz_mat['ogrenci_no'] == o, 'saat'].values
            for o in veri_zengin
        }
        gercek_saat = {o: _dairesel_ortalama_saat(s) for o, s in ogrenci_saatleri.items()}

        for q_orani in ADAY_Q_ORANI:
            for n in ADAY_N:
                hatalar = []
                for o in veri_zengin:
                    saatler = ogrenci_saatleri[o]
                    if n > 0 and len(saatler) < n:
                        continue
                    n_onceki, tahmin_onceki = onceki_lookup[(o, faz)]

                    for _ in range(TEKRAR):
                        if n == 0:
                            tahmin_h = tahmin_onceki
                        else:
                            orn = rng.choice(saatler, size=n, replace=False)
                            tahmin_mevcut = _dairesel_ortalama_saat(orn)
                            tahmin_h = _harmanla(tahmin_mevcut, n, tahmin_onceki, n_onceki,
                                                  tau2, sigma2, q_orani)
                        hatalar.append(_dairesel_fark_saat(tahmin_h, gercek_saat[o]))

                if hatalar:
                    sonuc_satirlari.append({
                        'FAZ': faz, 'Q_ORANI': q_orani, 'n': n,
                        'ort_hata_saat': round(float(np.mean(hatalar)), 3),
                        'n_ogrenci': len(hatalar) // TEKRAR,
                    })

    sonuc = pd.DataFrame(sonuc_satirlari)
    print()
    print(sonuc.to_string(index=False))

    print("\n=== Her FAZ için, TÜM n'ler üzerinden ortalama hatayı en düşük yapan Q_ORANI ===")
    for faz in FAZLAR:
        alt = sonuc[sonuc['FAZ'] == faz]
        if len(alt) == 0:
            continue
        ozet = alt.groupby('Q_ORANI')['ort_hata_saat'].mean().sort_values()
        print(f"\n{faz}: en iyi Q_ORANI = {ozet.index[0]} (ortalama hata = {ozet.iloc[0]:.3f} saat)")
        print(ozet.to_string())


if __name__ == "__main__":
    main()
