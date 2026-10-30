# Arşiv — mesaj senaryosu belgelerinin eski sürümleri

26 Ekim 2026'da arşive alındı. **Hiçbiri güncel değildir**; güncel kaynaklar:

- `Mesaj_Senaryolari_Akademik.docx` — makale bölümü biçiminde, numaralı (360 mesaj)
- `templates.docx` — çalışma belgesi, BÖLÜM 0–6 (360 mesaj)

Mesaj metinlerinin tek doğru kaynağı koddur: `config/whatsapp/templates.json`
(proaktif şablonlar) ve `src/Service/EngagementFlowMessages.php` +
`src/Service/TriggerMessageCatalog.php` (reaktif metinler).

| Dosya | Mesaj | Neden arşivde |
|---|---|---|
| `Tez_Eki_Mesaj_Senaryolari.docx` | 360 | İçerik `Mesaj_Senaryolari_Akademik.docx` ile aynı; E.1–E.7 numaralandırması kullanıyordu |
| `templates-yedek-20261026-akademik-oncesi.docx` | 360 | Akademik dil düzenlemesi öncesi yedek |
| `templates-yedek-20261026-ek-akislar.docx` | 294 | Günlük Kontrol kanalı eklenmeden önceki yedek |
| `templates-yedek-20261026-metin-senkron.docx` | 294 | Metinler kodla senkronlanmadan önceki yedek; T2 Normal butonları, 72 saat mesajı ve `{sınav}` ekleri hatalı |
| `templates-yedek-20261025.docx` | 288 | **Paketi bozuk, açılmıyor** (`customXml/item1.xml` eksik) |
| `templates-v2-ARSIV.docx` | 234 | 3 Ekim ara sürümü; Bölüm 4 (Sınav Günü) kişiselleştirme kararından önceki tasarımı yansıtıyor |
| `templates.txt` | 162 | docx'e geçildikten sonra kullanım dışı kaldı |

## TEZ PROJESİ.docx (26 Ekim 2026'da arşive alındı)

Adına rağmen tez ana metni DEĞİLDİR. İçeriği yalnızca mesaj senaryosudur (BÖLÜM 0–5);
giriş, literatür taraması, yöntem, bulgular, tartışma veya sonuç bölümü içermez.
BÖLÜM 6 (Günlük Kontrol kanalı) da eksiktir.

`templates.docx`'in 26 Ekim öncesi haline karşılık gelir: 288 mesaj taşır (güncel 360) ve
içindeki 36 metin senkron öncesi HATALI sürümlerdir — örneğin T2 Normal'de "Seni şu an en çok
ne zorluyor?" sorusu yer alır; kod ise "Şu an nasıl gidiyor?" sorusunu ve farklı butonları
kullanır. Bu nedenle içindeki metinler referans alınmamalıdır.
