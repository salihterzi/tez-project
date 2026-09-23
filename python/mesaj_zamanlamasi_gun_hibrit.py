"""
Gün Hibrit (Öğrenci x Faz Bazlı, Kategorik, Top-N) Tahmin
====================================================
`mesaj_zamanlamasi_hibrit.py` saat için Seçenek A/B kararını (öğrenci, faz)
ÇİFTİ düzeyinde veriyordu -- burada AYNI karar mekanizması gün tahmini için
tekrarlanır. Farkı: saat dairesel bir regresyon problemiydi (saat_sin/saat_cos
üzerinde mixedlm), gün burada KATEGORİK ele alınır -- statsmodels'te kategorik
(multinomial) karma-etkiler modeli pratik desteklenmediği için, mixedlm/BLUP
yerine her (öğrenci, FAZ) hücresinde EN SIK görülen N gün (frekans sıralaması)
hesaplanır. N faza göre değişir -- normal haftada etkileşim daha dağınık
olduğundan 3 gün, sınav öncesi ~7 günlük pencerede etkileşim daha yoğun
olduğundan 4 gün istenir (bkz. FAZ_TOP_N):

  - Bir öğrencinin bir fazda >= GUN_MIN_FAZ_GOZLEM_ESIGI gözlemi varsa, o
    öğrenci-faz kombinasyonu için KENDİ en sık N günü kullanılır (Seçenek B
    davranışı, bireysel). Öğrencinin kendi verisinde N'den az farklı gün
    varsa (az gözlemle sınırlı çeşitlilik), kalan sıralar POPÜLASYONUN top-N
    listesinden (bireyselde zaten olanlar hariç) tamamlanır -- her satırın
    `yontem` kolonu bu günün bireysel mi yoksa popülasyonla mı tamamlandığını
    gösterir.
  - Öğrencinin fazda hiç yeterli verisi yoksa VE önceki dönemden o öğrenci-faz
    için bireysel (Seçenek B kökenli) bir sonuç varsa, popülasyona düşmeden
    ÖNCE o önceki dönem sonucu "önsel" olarak kullanılır (Seçenek C --
    `secenek_c_onceki_donem_onseli`). Gerekçe: pipeline her dönemi kendi
    verisiyle SIFIRDAN çalıştırır (bkz. aşağıdaki ÖNEMLİ not) -- bu da
    geçmişte güçlü bir bireysel örüntüsü kanıtlanmış bir öğrenciyi, yeni
    dönemin ilk haftalarında (henüz GUN_MIN_FAZ_GOZLEM_ESIGI'ye ulaşmadan)
    sıfırdan bir yabancı gibi (saf popülasyon) ele almak anlamına gelirdi --
    oysa elimizde zaten onun hakkında güçlü bir sinyal var. Önceki dönemin
    kendisi de fallback/önsel ise (gerçek bireysel sinyal değilse) zincirleme
    yapılmaz, doğrudan popülasyona düşülür.
  - Öğrencinin fazda hiç yeterli verisi yoksa VE önceki dönem önseli de yoksa
    (yeni öğrenci veya önceki dönemde de fallback), o fazdaki POPÜLASYON
    genelinde (>= MIN_GOZLEM_ESIGI toplam gözlemi olan öğrenci havuzunda) en
    sık N gün kullanılır (Seçenek A davranışı). Bu fallback yolu, hiç
    davranışsal verisi olmayan (n_faz=0) öğrencileri de otomatik kapsar --
    saat pipeline'ındaki gibi ayrı bir cold-start adımına gerek kalmaz.

ÖNEMLİ: GUN_MIN_FAZ_GOZLEM_ESIGI, saat modelinin MIN_FAZ_GOZLEM_ESIGI'sinden
(mixedlm BLUP shrinkage formülünden türetilmiş) KASITLI olarak AYRI ve FARKLI
bir sabittir -- o formül (sigma^2/tau^2) sürekli/dairesel regresyona özgüdür,
buradaki top-N mod/frekans mekanizmasına aktarılamaz (bkz. proje sohbet
geçmişi). Bunun yerine ampirik alt-örnekleme (subsampling) ile türetildi:
veri-zengin öğrencilerin (>=40 gözlem) TÜM verisinden çıkan top-N "gerçek"
kabul edilip, farklı n boyutlarında rastgele alt-örneklemlerin bu "gerçek"le
örtüşme oranı ölçüldü (bkz. python/_sim_gun_esik.py). %70 hedef örtüşme oranını
ilk aşan n=10 çıktı -- üç fazda da aynı (top_n 3 veya 4 olsa da), bu yüzden
faza özel ayrım yerine tek sabit kullanılıyor. Not: n=top_n (örn. 4) ile
örtüşme sadece ~%55 idi -- top-N kadar günü anlamlı sıralamak için top_n'in
kendisi değil, birkaç katı gözlem gerekiyor.

Çıktı UZUN (long) formattadır: her (öğrenci, FAZ) için `top_n` kadar satır
üretilir, `gun_sira` (1..top_n) o günün sıralamadaki yerini gösterir.

KULLANIM (python container'ı içinde):
    docker compose exec python python mesaj_zamanlamasi_gun_hibrit.py
"""

import importlib.util
import pathlib

import pandas as pd
import warnings

from db import get_engine

warnings.filterwarnings('ignore')

# Modül adı tire içerdiği için (`mesaj_zamanlamasi_pipeline-v2.py`) doğrudan import
# edilemiyor; mesaj_zamanlamasi_hibrit.py'deki gibi dosyayı yoluna göre yüklüyoruz --
# veri yükleme/öznitelik fonksiyonlarını yeniden yazıp dosyaların birbirinden
# sapmasına yol açmamak için.
_pipeline_path = pathlib.Path(__file__).parent / "mesaj_zamanlamasi_pipeline-v2.py"
_spec = importlib.util.spec_from_file_location("mesaj_zamanlamasi_pipeline_v2", _pipeline_path)
pipeline = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(pipeline)

FAZLAR = pipeline.FAZLAR

MIN_GOZLEM_ESIGI = pipeline.MIN_GOZLEM_ESIGI    # popülasyon havuzuna dahil olmak için (toplam)

# Saat modelinin MIN_FAZ_GOZLEM_ESIGI'sinden KASITLI olarak ayrı -- ampirik
# alt-örnekleme ile türetildi (bkz. python/_sim_gun_esik.py): veri-zengin
# öğrencilerin TÜM verisinden çıkan top-N "gerçek" kabul edilip, n boyutundaki
# alt-örneklemlerin bununla örtüşme oranı ölçüldü; %70 hedef oranı ilk aşan
# n=10, üç fazda da (normal_hafta/ara_sinav_oncesi/final_oncesi) aynı çıktı.
GUN_MIN_FAZ_GOZLEM_ESIGI = 10

GUN_ADLARI = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar']

# Faz başına istenen gün sayısı: normal haftada etkileşim günlere daha dağınık,
# sınav öncesi ~7 günlük pencerede daha yoğun olduğundan farklı N kullanılır.
FAZ_TOP_N = {'normal_hafta': 3, 'ara_sinav_oncesi': 4, 'final_oncesi': 4}


# =====================================================================
# GÜN HİBRİT MODEL (KATEGORİK, TOP-N)
# =====================================================================

def _top_n_gun(seri, n):
    """gun_index serisinde en sık görülen n günü (sayıma göre azalan, eşitlikte
    gün indeksine göre artan -- deterministik) döner. Serideki farklı gün
    sayısı n'den azsa, olabildiğince az elemanlı liste döner. Seri boşsa []
    döner."""
    if len(seri) == 0:
        return []
    sayimlar = seri.value_counts().reset_index()
    sayimlar.columns = ['gun_index', 'sayim']
    sayimlar = sayimlar.sort_values(['sayim', 'gun_index'], ascending=[False, True])
    return sayimlar['gun_index'].head(n).astype(int).tolist()


def _onceki_donem_gunleri(onceki_donem_df, ogrenci_no, faz):
    """Önceki dönemin gün tahmini çıktısından (aynı uzun formatta bir DataFrame),
    o öğrenci-faz için Seçenek B kökenli (bireysel sinyal İÇEREN) günleri,
    sıralarına göre döner. Önceki dönemde de fallback'e (secenek_a_fallback)
    düşülmüşse boş liste döner -- fallback'ten fallback'e zincirlemek bilgi
    katmaz, doğrudan güncel popülasyona düşülmesi daha doğrudur."""
    if onceki_donem_df is None:
        return []
    satirlar = onceki_donem_df[
        (onceki_donem_df['ogrenci_no'] == ogrenci_no)
        & (onceki_donem_df['FAZ'] == faz)
        & (onceki_donem_df['yontem'].str.startswith('secenek_b'))
    ].sort_values('gun_sira')
    return satirlar['tahmini_gun_index'].astype(int).tolist()


def model_kur_ve_tahmin_et_gun_hibrit(mat, dem, min_gozlem=MIN_GOZLEM_ESIGI,
                                       min_faz_gozlem=GUN_MIN_FAZ_GOZLEM_ESIGI,
                                       faz_top_n=None, onceki_donem_df=None):
    faz_top_n = faz_top_n or FAZ_TOP_N
    counts_toplam = mat.groupby('ogrenci_no').size()
    yeterli = counts_toplam[counts_toplam >= min_gozlem].index
    sub = mat[mat['ogrenci_no'].isin(yeterli)]

    # Popülasyon (fallback) top-N günü: FAZ başına, 'yeterli' öğrenci havuzunun
    # tamamından hesaplanır -- saat modelindeki sabit (popülasyon) etkiye karşılık gelir.
    populasyon_top_gun = {
        faz: _top_n_gun(sub.loc[sub['FAZ'] == faz, 'gun_index'], faz_top_n[faz])
        for faz in FAZLAR
    }

    faz_counts = mat.groupby(['ogrenci_no', 'FAZ']).size().unstack(fill_value=0)
    for faz in FAZLAR:
        if faz not in faz_counts.columns:
            faz_counts[faz] = 0

    sonuclar = []
    # dem'deki TÜM kayıtlı öğrenciler kapsanır (mat'te hiç satırı olmayanlar dahil) --
    # onlar için her fazda n_faz=0 olacağından otomatik olarak popülasyon top-N'ine düşülür.
    for ogrenci_no in dem['ogrenci_no']:
        n_toplam = int(counts_toplam.get(ogrenci_no, 0))
        ogrenci_mat = mat[mat['ogrenci_no'] == ogrenci_no]

        for faz in FAZLAR:
            n = faz_top_n[faz]
            n_faz = int(faz_counts.loc[ogrenci_no, faz]) if ogrenci_no in faz_counts.index else 0
            yeterli_veri = n_faz >= min_faz_gozlem
            pop_gunler = populasyon_top_gun[faz]

            if yeterli_veri:
                bireysel_gunler = _top_n_gun(
                    ogrenci_mat.loc[ogrenci_mat['FAZ'] == faz, 'gun_index'], n)
                gunler_kaynak = [(g, 'secenek_b_bireysel') for g in bireysel_gunler]
                # Öğrencinin kendi verisinde n farklı gün yoksa (az gözlemle sınırlı
                # çeşitlilik), kalan sıralar popülasyonun top-N'inden (bireyselde
                # zaten olanlar hariç) tamamlanır.
                for g in pop_gunler:
                    if len(gunler_kaynak) >= n:
                        break
                    if g not in bireysel_gunler:
                        gunler_kaynak.append((g, 'secenek_b_populasyonla_tamamlanmis'))
            else:
                onceki_gunler = _onceki_donem_gunleri(onceki_donem_df, ogrenci_no, faz)
                if onceki_gunler:
                    gunler_kaynak = [(g, 'secenek_c_onceki_donem_onseli') for g in onceki_gunler[:n]]
                    var_olanlar = set(onceki_gunler[:n])
                    for g in pop_gunler:
                        if len(gunler_kaynak) >= n:
                            break
                        if g not in var_olanlar:
                            gunler_kaynak.append((g, 'secenek_c_onceki_donem_onseli_populasyonla_tamamlanmis'))
                            var_olanlar.add(g)
                else:
                    gunler_kaynak = [(g, 'secenek_a_fallback') for g in pop_gunler]

            for sira, (gun_idx, yontem) in enumerate(gunler_kaynak, start=1):
                sonuclar.append({
                    'ogrenci_no': ogrenci_no,
                    'FAZ': faz,
                    'gun_sira': sira,
                    'top_n': n,
                    'n_gozlem_faz': n_faz,
                    'n_gozlem_toplam': n_toplam,
                    'tahmini_gun_index': gun_idx,
                    'tahmini_gun': GUN_ADLARI[gun_idx],
                    'yontem': yontem,
                })

    return pd.DataFrame(sonuclar)


# =====================================================================
# ANA AKIŞ
# =====================================================================

def main(onceki_donem_csv=None):
    """onceki_donem_csv: bir ÖNCEKİ dönem için bu script'in ürettiği çıktı CSV'sinin
    yolu (örn. '2024_1_mesaj_zamanlamasi_gun_tahminleri_hibrit.csv'). Verilirse,
    yeni dönemde henüz GUN_MIN_FAZ_GOZLEM_ESIGI'ye ulaşmamış öğrenci-faz
    hücreleri için popülasyona düşmeden önce o önceki bireysel sonuç önsel
    olarak kullanılır (bkz. modül docstring'i, Seçenek C). DB'de şu an tek
    dönem (2024/2 bahar) olduğu için varsayılan None -- ileride yeni bir dönem
    eklendiğinde bu script o yeni dönem için çalıştırılırken bahar'ın çıktı
    CSV'si burada verilir."""
    engine = get_engine()

    onceki_donem_df = None
    if onceki_donem_csv is not None:
        onceki_donem_df = pd.read_csv(onceki_donem_csv)
        print(f"0) Önceki dönem önseli yüklendi: {onceki_donem_csv} "
              f"({len(onceki_donem_df)} satır)")

    print("1) Veri yükleniyor...")
    # DB'de şu an tek dönem var: 2024/2 (bahar) -- pipeline-v2.py ile aynı.
    mat, _, dem = pipeline.veriyi_yukle(engine, yil=2024, donem=2)

    print("2) Öznitelikler oluşturuluyor...")
    mat = pipeline.oznitelik_olustur(mat, pipeline.BAHAR_2024_TAKVIMI)
    dem = pipeline.demografik_hazirla(dem)

    print("3) Gün hibrit modeli (öğrenci x faz bazlı Seçenek A/B/C, kategorik top-N) kuruluyor...")
    tahmin_df = model_kur_ve_tahmin_et_gun_hibrit(mat, dem, onceki_donem_df=onceki_donem_df)

    dagilim = tahmin_df['yontem'].value_counts()
    print("\nYöntem dağılımı (gün satırı bazında):")
    print(dagilim.to_string())
    toplam = len(tahmin_df)
    print(f"\n-> %{100 * dagilim.get('secenek_b_bireysel', 0) / toplam:.1f} bireysel veriden, "
          f"%{100 * dagilim.get('secenek_b_populasyonla_tamamlanmis', 0) / toplam:.1f} "
          f"bireysel+popülasyonla tamamlanmış, "
          f"%{100 * dagilim.get('secenek_c_onceki_donem_onseli', 0) / toplam:.1f} "
          f"önceki dönem önseli, "
          f"%{100 * dagilim.get('secenek_c_onceki_donem_onseli_populasyonla_tamamlanmis', 0) / toplam:.1f} "
          f"önceki dönem önseli+popülasyonla tamamlanmış, "
          f"%{100 * dagilim.get('secenek_a_fallback', 0) / toplam:.1f} tamamen popülasyon "
          f"fallback (Seçenek A / cold-start) günlerinden oluşuyor.")

    print("\nSonuç örneği:")
    print(tahmin_df.head(20).to_string(index=False))

    tahmin_df.to_csv('bahar_mesaj_zamanlamasi_gun_tahminleri_hibrit.csv', index=False)
    print("\nKaydedildi: bahar_mesaj_zamanlamasi_gun_tahminleri_hibrit.csv")


if __name__ == "__main__":
    main()
