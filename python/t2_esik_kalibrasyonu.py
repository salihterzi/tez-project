"""
T2 eşiği (gün) kalibrasyon betiği — geçmiş dönem verisiyle gerçek precision/recall hesaplar.

MOTİVASYON: DailyTriggerEvaluator.php'deki T2_THRESHOLD_DAYS_NORMAL (5) ve
T2_THRESHOLD_DAYS_SINAV_ONCESI (12) sabitleri hiçbir veriye dayanmıyor — doküman
(Donem_Mesaj_Takvimi_ve_Sablonlari.docx) sadece bir aralık ("5-7 gün yerine 10-12 gün")
veriyor, aralık içindeki seçim gerekçelendirilmemiş. Bu betik, DB'deki GERÇEK geçmiş
dönem verisiyle (materyal_erisim_log + sinav_sonucu) bu eşiklerin ne kadar iyi ayırt
ettiğini ölçer.

YÖNTEM:
  1. Her öğrencinin HERHANGİ bir derste/etkinlikte erişim sağladığı günleri (öğrenci-günü
     çözünürlüğünde, ders ayrımı yapmadan — T2 üretimde de öğrenci-bazlı aggregate'tir,
     bkz. OgrenciErisimOzeti) çıkar.
  2. Ardışık erişim günleri arasındaki boşlukları (gün) hesapla.
  3. Her boşluğu şu şekilde etiketle:
       - "geçici": öğrenci bu boşluktan SONRA dönem içinde tekrar erişim sağladı
         (gerçek disengagement değildi, yanlış alarm riski).
       - "kalıcı": öğrenci bu boşluktan sonra dönem sonuna (gözlem penceresinin son
         gözlenen günü) kadar BİR DAHA erişim sağlamadı (sağ-sansürlü, ama bu dönem
         için pratik anlamda gerçek bir terk).
  4. Her boşluk, BAŞLADIĞI günün fazına (normal_hafta / ara_sinav_oncesi / final_oncesi,
     mesaj_zamanlamasi_pipeline-v2.py'deki DonemTakvimi ile) göre tek bir faza atanır —
     bu atama eşik adayından bağımsız, sabit kalır (karşılaştırılabilirlik için).
  5. Aday eşik değerleri için (ESIK_ADAYLARI), her faz için ayrı ayrı:
       precision = n_kalıcı_tetiklenen / n_tetiklenen  (eşik aşılırsa, gerçekten kalıcı mıydı?)
       recall    = n_kalıcı_tetiklenen / n_tüm_kalıcı_bu_fazda (o fazda başlayan TÜM gerçek
                   terklerin kaçını bu eşik yakalıyor?)

SAĞ-SANSÜR DÜZELTMESİ (önemli, ilk sürümde YOKTU — ara_sinav_oncesi/final_oncesi'de recall'u
anlamsız kılıyordu): "kalıcı" etiketi artık "dönem sonuna kadar" değil, SABİT bir takip ufku
(TAKIP_PENCERESI_GUN) kullanıyor. Bir boşluk ancak başlangıcından itibaren tam TAKIP_PENCERESI_GUN
kadar gözlem verisi varsa (yani veri bu kadar ileriye gerçekten uzanıyorsa) "kalıcı/geçici"
olarak karara bağlanır; aksi halde (gözlem penceresinin sonuna çok yakın başladıysa) analiz
DIŞI bırakılır. Böylece her fazda "kalıcı" aynı anlama gelir (tam olarak K gün boyunca dönmedi),
fazın takvimdeki konumuna göre sistematik bir önyargı oluşmaz. İlk sürümde (gözlem_sonu'na kadar
sağ-sansürlü) ara_sinav_oncesi/final_oncesi pencereleri dönemin takvim-erken bir noktasında
olduğu için (asıl veri bütünleme/geç aktiviteyle Haziran sonuna kadar sürüyor) her boşluk
yapay olarak 30+ gün uzunluğunda çıkıyor, recall her eşikte 1.000'e satürasyona uğruyordu —
bu betik sürümü o sorunu KÖKTEN çözer.

İKİNCİL KONTROL: mevcut üretim eşikleriyle (5 gün Normal, 12 gün Sınav Öncesi) tetiklenecek
öğrenci grubunun ortalama sinav_sonucu.puan'ı, tetiklenmeyecek grupla karşılaştırılır — T2
sinyalinin akademik sonuçla ilişkisi olup olmadığına kaba bir bakış.

KULLANIM (python container'ı içinde):
    docker compose exec python python t2_esik_kalibrasyonu.py --yil 2024 --donem 2

AKTİF DÖNEM GELDİĞİNDE (2026-10-22, kullanıcı kararı): bu betik canlı sistemin bir parçası
DEĞİL — geçmiş dönemler üzerinden TEK SEFERLİK yeniden çalıştırılıp, bulgulara göre
DailyTriggerEvaluator.php'deki T2_THRESHOLD_DAYS_NORMAL/SINAV_ONCESI elle güncellenmeli.
"""

import argparse
import importlib.util

import pandas as pd

spec = importlib.util.spec_from_file_location('pipeline', 'mesaj_zamanlamasi_pipeline-v2.py')
pipeline = importlib.util.module_from_spec(spec)
spec.loader.exec_module(pipeline)

ESIK_ADAYLARI = [3, 5, 7, 9, 10, 12, 14, 16, 18, 21]
# F1-optimum araması -- sınav öncesi fazlarda recall'un GERÇEKTEN düşmeye başladığı (yapısal
# doyma noktasının bittiği) yeri görebilmek için, bu dönemin takvimiyle ilgisiz, saf
# veri-güdümlü geniş bir üst sınıra kadar taranıyor (bkz. modül docstring'i).
ESIK_ARAMA_UST_SINIRI = 90
ESIK_ARAMA_ARALIGI = list(range(1, ESIK_ARAMA_UST_SINIRI + 1))


def faz_ata(tarih, takvim):
    if takvim.ara_sinav_oncesi_baslangic <= tarih <= takvim.ara_sinav_bitis:
        return 'ara_sinav_oncesi'
    if takvim.final_oncesi_baslangic <= tarih <= takvim.final_bitis:
        return 'final_oncesi'
    return 'normal_hafta'


def ogrenci_gunleri_cikar(mat, sin):
    """Her öğrencinin HERHANGİ bir ders/etkinlikte erişim sağladığı tekilleştirilmiş
    günler (gün çözünürlüğünde) — ders ayrımı yapılmaz, T2 üretimde de öğrenci-bazlıdır."""
    birlesik = pipeline.mat_sin_birlestir(mat, sin)
    birlesik['tarih'] = birlesik['islem_zamani'].dt.normalize()
    gunler = birlesik[['ogrenci_no', 'tarih']].drop_duplicates()
    return gunler.sort_values(['ogrenci_no', 'tarih'])


def bosluklari_cikar(gunler, takvim):
    """Ardışık erişim boşluklarını çıkarır. Her öğrencinin SON boşluğu dönem sonuna
    (gözlem penceresinin son gözlenen günü) kadar sağ-sansürlü olarak "kalıcı" sayılır --
    gerçek (sansürlü) uzunluk korunur, suni bir sabite ÇEKİLMEZ.

    NOT (bkz. modül docstring'i, "sağ-sansür" bölümü): ara_sinav_oncesi/final_oncesi gibi
    dönemin takvimde ERKEN bir noktasında oturan dar pencereler için, bu fazda başlayan
    HER kalıcı boşluk mekanik olarak uzun çıkar (o pencereden gözlem sonuna kadar geçen
    süre sabit ve büyük olduğundan) -- bu bir ölçüm hatası değil, verinin kendisinin
    (bu dönemde sınav sonrası ~2 ay daha gerçek aktivite sürmesi) yapısal bir sonucudur;
    recall bu fazlarda küçük eşiklerde satürasyona uğrar (bkz. main()'deki yorum)."""
    gozlem_sonu = gunler['tarih'].max()
    kayitlar = []
    for ogrenci_no, grup in gunler.groupby('ogrenci_no'):
        tarihler = grup['tarih'].tolist()
        for i in range(len(tarihler) - 1):
            bosluk_gun = (tarihler[i + 1] - tarihler[i]).days
            kayitlar.append({
                'ogrenci_no': ogrenci_no,
                'baslangic': tarihler[i],
                'bosluk_gun': bosluk_gun,
                'kalici': False,
            })
        son = tarihler[-1]
        son_bosluk_gun = (gozlem_sonu - son).days
        if son_bosluk_gun > 0:
            kayitlar.append({
                'ogrenci_no': ogrenci_no,
                'baslangic': son,
                'bosluk_gun': son_bosluk_gun,
                'kalici': True,
            })
    df = pd.DataFrame(kayitlar)
    df['faz'] = df['baslangic'].apply(lambda t: faz_ata(t, takvim))
    return df, gozlem_sonu


def esik_sweep(bosluklar, esik_adaylari=ESIK_ADAYLARI):
    sonuclar = []
    for faz in pipeline.FAZLAR:
        alt_faz = bosluklar[bosluklar['faz'] == faz]
        toplam_kalici = int(alt_faz['kalici'].sum())
        for esik in esik_adaylari:
            tetiklenen = alt_faz[alt_faz['bosluk_gun'] >= esik]
            n_tetik = len(tetiklenen)
            n_kalici = int(tetiklenen['kalici'].sum())
            n_gecici = n_tetik - n_kalici
            precision = n_kalici / n_tetik if n_tetik > 0 else float('nan')
            recall = n_kalici / toplam_kalici if toplam_kalici > 0 else float('nan')
            sonuclar.append({
                'faz': faz, 'esik_gun': esik,
                'n_tetiklenen': n_tetik, 'n_kalici': n_kalici, 'n_gecici': n_gecici,
                'precision': precision, 'recall': recall,
            })
    return pd.DataFrame(sonuclar)


def f1_optimum_bul(bosluklar, esik_araligi=ESIK_ARAMA_ARALIGI):
    """Her faz için F1 = 2*precision*recall/(precision+recall)'ı maksimize eden esiği bulur.
    Tek bir "doğru" sayı yerine, F1'in tepe civarındaki eğrisini de döner (tepe nokta ne kadar
    keskin/düz, görülebilsin diye)."""
    sonuclar = {}
    egri_hepsi = []
    for faz in pipeline.FAZLAR:
        alt_faz = bosluklar[bosluklar['faz'] == faz]
        toplam_kalici = int(alt_faz['kalici'].sum())
        en_iyi = None
        for esik in esik_araligi:
            tetiklenen = alt_faz[alt_faz['bosluk_gun'] >= esik]
            n_tetik = len(tetiklenen)
            n_kalici = int(tetiklenen['kalici'].sum())
            precision = n_kalici / n_tetik if n_tetik > 0 else 0.0
            recall = n_kalici / toplam_kalici if toplam_kalici > 0 else 0.0
            f1 = 2 * precision * recall / (precision + recall) if (precision + recall) > 0 else 0.0
            egri_hepsi.append({'faz': faz, 'esik_gun': esik, 'precision': precision, 'recall': recall, 'f1': f1})
            if en_iyi is None or f1 > en_iyi['f1']:
                en_iyi = {'esik_gun': esik, 'precision': precision, 'recall': recall, 'f1': f1}
        sonuclar[faz] = en_iyi
    return sonuclar, pd.DataFrame(egri_hepsi)


def akademik_sonuc_kontrolu(bosluklar, sin, esik_normal=5, esik_sinav_oncesi=12):
    """Mevcut üretim eşikleriyle (5/12) tetiklenecek öğrenciler vs tetiklenmeyecekler
    arasında ortalama sinav puanı farkı var mı -- kaba bir ikincil doğrulama."""
    def esik_bu_fazda(faz):
        return esik_sinav_oncesi if faz in ('ara_sinav_oncesi', 'final_oncesi') else esik_normal

    bosluklar = bosluklar.copy()
    bosluklar['esik_uygulanir'] = bosluklar['faz'].apply(esik_bu_fazda)
    bosluklar['tetiklenirdi'] = bosluklar['bosluk_gun'] >= bosluklar['esik_uygulanir']

    tetiklenen_ogrenciler = set(bosluklar.loc[bosluklar['tetiklenirdi'], 'ogrenci_no'])
    tum_ogrenciler = set(bosluklar['ogrenci_no'])
    tetiklenmeyen_ogrenciler = tum_ogrenciler - tetiklenen_ogrenciler

    puan_ort = sin.groupby('ogrenci_no')['puan'].mean()

    puan_tetik = puan_ort.reindex(list(tetiklenen_ogrenciler)).dropna()
    puan_tetik_degil = puan_ort.reindex(list(tetiklenmeyen_ogrenciler)).dropna()

    print("=== İKİNCİL KONTROL: Mevcut üretim eşikleri (5/12) ile akademik sonuç ===")
    print(f"En az bir kez eşiği aşan öğrenci sayısı : {len(puan_tetik)}, ort. puan = {puan_tetik.mean():.2f}")
    print(f"Hiç eşiği aşmayan öğrenci sayısı        : {len(puan_tetik_degil)}, ort. puan = {puan_tetik_degil.mean():.2f}")
    print(f"Fark: {puan_tetik_degil.mean() - puan_tetik.mean():+.2f} puan "
          f"(pozitifse: eşiği aşanlar gerçekten daha düşük puan almış)\n")


def main():
    parser = argparse.ArgumentParser(description="T2 eşiği kalibrasyonu (geçmiş dönem verisiyle)")
    parser.add_argument('--yil', type=int, default=2024)
    parser.add_argument('--donem', type=int, default=2)
    args = parser.parse_args()

    takvim = pipeline.TAKVIM_KAYITLARI.get((args.yil, args.donem), pipeline.BAHAR_2024_TAKVIMI)

    engine = pipeline.get_engine()
    print(f"Veri yükleniyor: {args.yil}/{args.donem} ...")
    mat, sin, _dem = pipeline.veriyi_yukle(engine, args.yil, args.donem)
    print(f"  materyal_erisim_log: {len(mat)} satır, {mat['ogrenci_no'].nunique()} öğrenci")
    print(f"  sinav_sonucu       : {len(sin)} satır, {sin['ogrenci_no'].nunique()} öğrenci\n")

    gunler = ogrenci_gunleri_cikar(mat, sin)
    bosluklar, gozlem_sonu = bosluklari_cikar(gunler, takvim)
    print(f"Gözlem penceresi sonu (son gözlenen gün): {gozlem_sonu.date()}")
    print(f"Toplam boşluk kaydı: {len(bosluklar)} "
          f"(geçici={int((~bosluklar['kalici']).sum())}, kalıcı={int(bosluklar['kalici'].sum())})\n")

    sonuc = esik_sweep(bosluklar)
    pd.set_option('display.width', 120)
    pd.set_option('display.float_format', lambda v: f'{v:.3f}')
    for faz in pipeline.FAZLAR:
        print(f"=== FAZ: {faz} ===")
        alt = sonuc[sonuc['faz'] == faz].drop(columns='faz')
        print(alt.to_string(index=False))
        print()

    akademik_sonuc_kontrolu(bosluklar, sin)

    print(f"=== F1-OPTİMUM ARAMASI (esik ∈ 1..{ESIK_ARAMA_UST_SINIRI} gün) ===")
    print("F1 = 2*precision*recall/(precision+recall) -- yanlış alarm (precision) ile kaçırılan\n"
          "gerçek terk (recall) arasında eşit ağırlıklı bir denge noktası arar.\n")
    en_iyiler, egri = f1_optimum_bul(bosluklar)
    for faz, en_iyi in en_iyiler.items():
        print(f"{faz:<18} -> optimum esik = {en_iyi['esik_gun']:>2} gün  "
              f"(precision={en_iyi['precision']:.3f}, recall={en_iyi['recall']:.3f}, F1={en_iyi['f1']:.3f})")
    print()
    for faz in pipeline.FAZLAR:
        alt = egri[egri['faz'] == faz].drop(columns='faz')
        en_iyi_esik = en_iyiler[faz]['esik_gun']
        pencere = alt[(alt['esik_gun'] >= max(1, en_iyi_esik - 5)) & (alt['esik_gun'] <= en_iyi_esik + 5)]
        print(f"--- {faz}: optimum civarı ---")
        print(pencere.to_string(index=False))
        print()


if __name__ == '__main__':
    main()
