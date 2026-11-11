# -*- coding: utf-8 -*-
"""Tüm .docx dosyalarını kaynak koddan doğrulanmış olgulara karşı tarar.

Proje kökünden çalıştır:  python3 python/dokuman_denetimi.py

Her kural, kodda GERÇEKTEN değişmiş bir karara karşılık gelir. Bir eşleşme bulunması
çelişki demek DEĞİLDİR — tarihsel/düzeltme notları (MASUM deseni) ayıklanır, kalanlar
"ŞÜPHELİ" olarak listelenir ve elle incelenir.

Kurallar kodla birlikte GÜNCELLENMELİ: yeni bir karar alındığında eski hâlini arayan
bir desen eklenmeli, yoksa denetim o kararı hiç kontrol etmez.
"""
import re, sys, glob
from docx import Document

# Koddan doğrulanmış olgular (2026-11-11)
KURALLAR = [
 ('learning_goal eski alan adı',   r'learning_goal'),
 ('eski motivasyon seçeneği',      r'İyi bir not almak|Kariyerime katkı sağlamak|Zorunlu olduğu için alıyorum'),
 # 2026-11-11: desen genişletildi — "6 farklı şekilde" ifadesi eski desene takılmıyordu
 # ve Genel Bakış'ta bayat bir ton anlatımı bu yüzden TEMİZ raporlandı.
 ('6 persona / 6 ton',             r'(6|altı)\s+(persona|ton|farklı)|GY/GB|GB/YB|iletişim tarzı (tercih|seçim)'),
 ('Bilgi persona kodu',            r'\[(GB|YB|İB|IB)\]|persona.{0,12}\b(GB|YB|İB)\b'),
 ('T2 eski eşik 5/12',             r'(5|12)\s*g[üu]ne? gev|Normal.{0,24}5 gün|Sınav Öncesi.{0,20}12 gün'),
 ('T3 tek yarılanma',              r'bu[_ ]hafta.{0,6}<.{0,6}ge[çc]en[_ ]hafta.{0,14}0[.,]5\s*$|geçen haftanın yarısının altı'),
 ('T4 ön koşulsuz',                r'kümülatif\s*<\s*medyan\s*×\s*0,7\s*$'),
 ('cinsiyete bağlı ton',           r'cinsiyete bağlı|Cinsiyet\s*=\s*[KE]|cinsiyet.{0,18}(türetilir|eşlem)'),
 ('Tannen dayanak olarak',         r'Tannen'),
 ('"mesaj sıklığı" (yanıltıcı)',   r'mesaj sıklığ'),
 ('haftalık tavan ≠ 1',            r'haftada (en fazla )?([2-9]|\d\d)\s*mesaj'),
 ('eski şablon sayısı',            r'\b(192|238|244|254|384)\b'),
 ('tanitim_t0_v1',                 r'tanitim_t0_v1'),
 # 2026-11-11: "register" terimi "üslup" ile değiştirildi; yalnızca BİR KEZ
 # "dilbilimde register" biçiminde tanıtılması serbest. Başka her kullanım sapmadır.
 ('register terimi (üslup olmalı)', r'register(?!\s*olarak adlandırılır)'),
 # Üslup sabit bir yazım standardı; "eksen" diye anılması eski çerçeve
 ('üslup "eksen" diye anılıyor',   r'(üslup|register)[^.]{0,30}eksen|eksen[^.]{0,30}(üslup|register)'),
]
# masum bağlamlar (tarihsel not / düzeltme kaydı)
MASUM = re.compile(r'2026-11-(0[3-9]|1[0-9])|KALDIRILDI|kaldırıl|düştü|Kaldırılan|'
                   r'bayat|eski hâl|önceki hâl|geçersiz kıl|silindi|artık|yer almaz|'
                   r'DEĞİL|uygulanmadı|ÖNERİ|donmuş korpus|tarihsel', re.I)

for f in sorted(glob.glob('*.docx')):
    if f.startswith('~$'): continue
    try: d = Document(f)
    except Exception as e:
        print(f'{f}: AÇILAMADI ({e})'); continue
    parca = []
    for i,p in enumerate(d.paragraphs):
        if p.text.strip(): parca.append((f'p{i}', p.text))
    for ti,tb in enumerate(d.tables):
        for ri,r in enumerate(tb.rows):
            for ci,c in enumerate(r.cells):
                if c.text.strip(): parca.append((f't{ti}r{ri}c{ci}', c.text))
    bulgu = []
    for ad, dz in KURALLAR:
        for yer, t in parca:
            if re.search(dz, t, re.I | re.M):
                bulgu.append((ad, yer, t, bool(MASUM.search(t))))
    if not bulgu: 
        print(f'\n===== {f}  → TEMİZ'); continue
    supheli = [b for b in bulgu if not b[3]]
    print(f'\n===== {f}  → {len(bulgu)} eşleşme, {len(supheli)} ŞÜPHELİ')
    gor = set()
    for ad, yer, t, masum in bulgu:
        if masum or (ad, yer) in gor: continue
        gor.add((ad, yer))
        print(f'   ! [{ad}] {yer}: {t.replace(chr(10)," ")[:120]}')
