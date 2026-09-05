# Yapılanlar — 2026-09-05/06

WhatsApp Cloud API + OpenAI webhook akışını sıfırdan çalışır hale getirme oturumu.
Sonuç: **uçtan uca çalışıyor** — outbound açılış mesajı → öğrenci cevabı → webhook →
OpenAI bağlamlı yanıt → WhatsApp'a geri gönderim, çok turlu.

---

## 1. `502 Bad Gateway` — uygulama açılmıyordu

**Belirti:** nginx → php-fpm'e bağlanamıyor (`connect() failed (111: Connection refused)`),
php konteyneri sürekli "health: starting".

**Kök neden:** php entrypoint DB beklerken `Access denied for user 'app'@'%' to
database 'whatsapp_messenger'` ile takılıyordu. MySQL volume ilk kez 30 Ağustos'ta
`MYSQL_DATABASE=app` ile initialize olmuştu; 2 Eylül'de `compose.override.yaml`'a
`DATABASE_URL` override'ı eklenip hedef `whatsapp_messenger`'a çevrildi ama bu
veritabanı MySQL'de hiç oluşturulmadı (env değişkeni yalnızca boş volume'de çalışır).

**Çözüm (canlı MySQL'de):**
```sql
CREATE DATABASE whatsapp_messenger CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON whatsapp_messenger.* TO 'app'@'%';
FLUSH PRIVILEGES;
```
php konteyneri restart → migration'lar çalıştı → fpm ayağa kalktı → site `200`.

**Kalıcı düzeltme (dosyalar):**
- `compose.yaml`: `MYSQL_DATABASE` varsayılanı `app` → `whatsapp_messenger`
  (hem `database` hem `php` servisinde). Volume sıfırlansa bile doğru kurulur.
- `compose.override.yaml`: artık gereksiz `DATABASE_URL` override satırı kaldırıldı
  (tek doğru kaynak `compose.yaml`).

---

## 2. `.env.local` dosya adı yanlıştı

**Belirti:** `debug:dotenv` içinde `WHATSAPP_*`, `META_*`, `OPENAI_*` hepsi boş;
webhook doğrulaması ve mesaj gönderimi imkânsız.

**Kök neden:** Gizli değerler `env.local` (noktasız) dosyasındaydı. Symfony yalnızca
`.env.local` (başında nokta) okur.

**Çözüm:** `git mv env.local .env.local`. `.gitignore` zaten `/.env.local`'i yok
sayıyor. cache:clear + restart sonrası tüm değerler yüklendi.

---

## 3. OpenAI modeli geçersizdi + kredi yoktu

- `OPENAI_MODEL=gpt-5.6-luna` (geçersiz) → `.env.local`'de `gpt-4o-mini` yapıldı.
  Sebep: kod `/v1/chat/completions` + `messages` kullanıyor; `gpt-4o-mini` bununla
  uyumlu, çok ucuz, test için yeterli.
- OpenAI hesabında kredi yoktu (`insufficient_quota` / `credit_balance_exhausted`).
  Kullanıcı kredi ekledi → doğrulandı (`gpt-4o-mini` → "Tamam.").

---

## 4. WhatsApp access token kısa ömürlüydü

**Belirti:** `OAuthException code 190 — Session has expired`.

**Adımlar:**
- İlk alınan token `type: USER`, **~1 saat** ömürlü (Graph Explorer / API Setup token'ı).
- 60 günlük uzun ömürlü token'a çevrildi:
  ```
  GET /oauth/access_token?grant_type=fb_exchange_token
      &client_id=1089961696864700&client_secret=<META_APP_SECRET>
      &fb_exchange_token=<kısa_token>
  ```
- `.env.local` güncellendi. Doğrulandı: test numarası `+1 555-204-6394` ("Test Number"),
  **son kullanma 2026-11-05**.

**Öneri (yapılmadı):** Business Settings → System Users → "Never" expiration ile
kalıcı token üret, bir daha uğraşma.

---

## 5. Yeni: Outbound başlatma endpoint'i

**Dosyalar:**
- `src/Controller/OutboundController.php` — **yeni**
- `src/Conversation/ConversationPrompt.php` — **yeni** (ortak system prompt)
- `src/Controller/WhatsAppWebhookController.php` — `SYSTEM_PROMPT` sabiti
  `ConversationPrompt::SYSTEM`'e taşındı (webhook + outbound aynı personayı kullanır)

**Endpoint:** `GET|POST /outbound/start`

| Parametre | Zorunlu | Varsayılan | Açıklama |
|---|---|---|---|
| `to` | ✅ | — | Ülke kodlu, yalnızca rakam: `905455743041` |
| `fresh` | — | — | `1` → mevcut aktif oturumu kapat, yenisini aç |
| `template` | — | `hello_world` | Gönderilecek onaylı şablon adı |
| `lang` | — | `en_US` | Şablon dil kodu |
| `mode` | — | (şablon) | `ai` → şablon yerine OpenAI serbest metni (yalnız 24s pencere açıkken iletilir) |
| `prompt`, `system` | — | — | `mode=ai` için AI talimatı / persona |

**Neden şablon:** WhatsApp'ta konuşmayı işletme başlatıyorsa ilk mesaj **onaylı
template** olmak zorunda. Serbest metin (AI üretimi) yalnızca öğrenci son 24 saatte
yazmışsa iletilir; aksi halde Meta `accepted` der ama mesajı düşürür. Bu yüzden
açılış `hello_world` şablonuyla yapılır; AI turları öğrencinin ilk yanıtından
sonra webhook üzerinden devreye girer.

**Kullanım:**
```bash
curl -X POST "http://localhost:8080/outbound/start?to=905455743041&fresh=1"
# → kişiye hello_world gider, o cevap yazınca AI sohbeti sürdürür (5 tur, sonra kapanır)
```

---

## 6. ⭐ Webhook hiç tetiklenmiyordu — WABA aboneliği eksik

**Belirti:** Endpoint `status: ok` + `wamid` dönüyor, Meta sandbox "Send" çalışıyor,
ama ne outbound mesaj telefona ulaşıyor ne de öğrenci cevabı webhook'a düşüyor.
ngrok kayıtlarında Meta'dan **sıfır POST** (statuses bildirimi bile yok).

**Doğrulananlar (hepsi sağlamdı):**
- App seviyesi webhook aboneliği aktif, `messages`, callback URL doğru
- ngrok tüneli açık, dışarıdan `POST /webhook/whatsapp` → uygulamaya ulaşıyor (200)

**Kök neden:** Webhook 2 katmanlı. App seviyesi tamamdı ama **WABA seviyesi** eksikti.
`GET /1035700795897614/subscribed_apps` → WABA yalnızca Meta'nın dahili test app'ine
(*"WA DevX Webhook Events 1P App"*, `2202427980234937`) aboneydi; gerçek app
(`1089961696864700`) **listede yoktu**. Bu yüzden sandbox çalışıyor, kendi
webhook'umuz boş kalıyordu.

**Çözüm:**
```bash
TOKEN=$(grep '^WHATSAPP_ACCESS_TOKEN=' .env.local | cut -d= -f2)
curl -X POST "https://graph.facebook.com/v20.0/1035700795897614/subscribed_apps?access_token=$TOKEN"
# → {"success": true}
```
Hemen ardından Meta webhook POST'ları akmaya başladı (statuses + gelen mesajlar).

---

## 7. Uçtan uca test — BAŞARILI

`session #9`, gerçek WhatsApp + gerçek OpenAI:

```
👤 "Selammmm"                → 🤖 "Selam! Nasılsın? Sana nasıl yardımcı olabilirim? 😊"
👤 "İyiyim sen nasılsın"     → 🤖 "Ben de iyiyim, teşekkür ederim! Bugün seni desteklemek için buradayım..."
👤 "Güzel gidiyor..."        → 🤖 (bağlamlı yanıt)
```
Log akışı: `webhook_receive` → `api.openai.com 200` → `graph.facebook.com/.../messages 200`.

---

## Değişen / eklenen dosyalar

| Dosya | Durum |
|---|---|
| `compose.yaml` | değişti — `MYSQL_DATABASE` varsayılanı `whatsapp_messenger` |
| `compose.override.yaml` | değişti — gereksiz `DATABASE_URL` override kaldırıldı |
| `env.local` → `.env.local` | yeniden adlandırıldı + `OPENAI_MODEL`, `WHATSAPP_ACCESS_TOKEN` güncellendi (git dışı) |
| `src/Controller/OutboundController.php` | **yeni** |
| `src/Conversation/ConversationPrompt.php` | **yeni** |
| `src/Controller/WhatsAppWebhookController.php` | değişti — ortak system prompt |
| `test.md` | güncellendi — outbound endpoint, WABA adımı, kimlik doğrulama, sorun giderme |
| `DONE.md` | **yeni** — bu dosya |

## Ortam durumu (canlı, dosyada olmayan)

- MySQL: `whatsapp_messenger` DB'si + `app` kullanıcısına grant elle verildi
  (volume sıfırlanırsa `compose.yaml` varsayılanı devralır)
- WABA `1035700795897614` → app `1089961696864700` subscribed_apps üzerinden abone
- ngrok: `https://unreined-amorally-idella.ngrok-free.dev` → `localhost:8080` (sabit domain, açık kalmalı)
- WhatsApp long-lived token son kullanma: **2026-11-05**

## Sıradaki adımlar (öneri)

1. WhatsApp için kalıcı **System User token** üret (60 günde bir yenilemeyi bırak).
2. Webhook'u Symfony Messenger ile asenkron yap (şu an senkron; OpenAI yavaşlarsa
   Meta timeout + retry).
3. Metin dışı mesaj tipleri (image/audio/document) desteği.
4. `mode=ai` açılış için `{{1}}` parametreli onaylı özel şablon.
