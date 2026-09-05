# WhatsApp Webhook — Çok Turlu Konuşma Oturumu Test Rehberi

Bu dosya, Faz 1 (webhook + durumlu konuşma) kurulumunun uçtan uca nasıl test
edileceğini sırayla anlatır. Her adımı tamamlamadan sonrakine geçme.

## Ortam özeti

| Bileşen | Değer |
|---|---|
| Web (nginx konteyneri) | `http://localhost:8080` |
| MySQL — host'tan | `127.0.0.1:3307` (kullanıcı `app` / `ChangeMe`, root `ChangeMeRoot`) |
| MySQL — `php` konteyneri içinden | `database:3306` |
| Veritabanı | `whatsapp_messenger` |
| Symfony komutları | `docker compose exec -u 1000:1000 php php bin/console <komut>` (yerel PHP 8.2 < gerekli 8.4) |
| Webhook route | `GET` + `POST` `/webhook/whatsapp` |
| ngrok sabit domain | `unreined-amorally-idella.ngrok-free.dev` → `localhost:8080` |
| ngrok inceleme arayüzü | `http://localhost:4040` |

---

## Adım 0 — `.env.local`'i doldur

`.env.local` git'e girmez; şu an placeholder'lar boş. Bir verify token üret:

```bash
uuidgen   # çıktıyı WHATSAPP_VERIFY_TOKEN'a yapıştır
```

`.env.local` içinde şu satırlar dolu olmalı:

```dotenv
WHATSAPP_VERIFY_TOKEN=<uuidgen çıktısı>
META_APP_SECRET=<Meta App Dashboard > Settings > Basic > App Secret>
WHATSAPP_ACCESS_TOKEN=<Meta > Business Settings > System Users > kalıcı token>
WHATSAPP_PHONE_NUMBER_ID=<Meta > WhatsApp > API Setup > Phone number ID>
OPENAI_API_KEY=<platform.openai.com > API keys>
OPENAI_MODEL=gpt-4o-mini
```

> **Dosya adı `.env.local` — başında nokta var.** `env.local` (noktasız) Symfony
> tarafından okunmaz; değerler uygulamaya ulaşmaz.

> **`WHATSAPP_ACCESS_TOKEN`:** API Setup sayfasındaki token 24 saatlik geçici
> token. Sürekli test için Meta → Business Settings → System Users'dan
> `whatsapp_business_messaging` + `whatsapp_business_management` izinli kalıcı
> token üret.

> **`OPENAI_API_KEY`:** hesapta kredi olmalı (https://platform.openai.com/settings/organization/billing).
> Kredi biterse istek `429 insufficient_quota` döner ve yanıt üretilemez.

> `DATABASE_URL` compose dosyalarında tanımlı değil; kaynak `.env` (docker
> varsayılanı) + `.env.local` (senin değerin). `.env.local`'de hazır, dokunmana gerek yok.

Yeniden yükle:

```bash
docker compose up -d
docker compose exec -u 1000:1000 php php bin/console cache:clear
docker compose restart php
```

Değerlerin konteynere ulaştığını doğrula:

```bash
docker compose exec php php bin/console debug:dotenv | grep -E "WHATSAPP_|META_|OPENAI_"
```

---

## Adım 0.1 — Dış servis kimlik bilgilerini tek tek doğrula

**OpenAI** (JSON içinde `choices` dönmeli, `error` değil):

```bash
KEY=$(grep '^OPENAI_API_KEY=' .env.local | cut -d= -f2)
curl -s https://api.openai.com/v1/chat/completions \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini","messages":[{"role":"user","content":"ping"}]}'
```

**WhatsApp** (`display_phone_number` dönmeli, `error` değil):

```bash
TOKEN=$(grep '^WHATSAPP_ACCESS_TOKEN=' .env.local | cut -d= -f2)
PNID=$(grep '^WHATSAPP_PHONE_NUMBER_ID=' .env.local | cut -d= -f2)
curl -s "https://graph.facebook.com/v20.0/$PNID?fields=display_phone_number,verified_name&access_token=$TOKEN"
```

İkisi de temiz dönmeden sonraki adımlara geçme.

---

## Adım 1 — Lokal duman testi (Meta'sız, ngrok'suz)

`TOKEN` yerine `.env.local`'e yazdığın `WHATSAPP_VERIFY_TOKEN` değerini koy.

```bash
# Doğru token → gövdede sadece "test123", HTTP 200
curl -i "http://localhost:8080/webhook/whatsapp?hub_mode=subscribe&hub_verify_token=TOKEN&hub_challenge=test123"

# Yanlış token → HTTP 403 "Forbidden"
curl -i "http://localhost:8080/webhook/whatsapp?hub_mode=subscribe&hub_verify_token=yanlis&hub_challenge=test123"
```

İlk komut `200 OK` + gövdede `test123` döndürüyorsa doğrulama tarafı hazır.

### (Opsiyonel) Mesaj işleme akışını Meta olmadan dene

Sahte bir Meta payload'ı POST et:

```bash
curl -i -X POST http://localhost:8080/webhook/whatsapp \
  -H 'Content-Type: application/json' \
  -d '{"entry":[{"changes":[{"value":{"messages":[{"from":"905551112233","id":"wamid.TEST001","type":"text","text":{"body":"merhaba"}}]}}]}]}'
```

Beklenen: `{"status":"ok"}` 200.
- `OPENAI_API_KEY` doluysa gerçek AI yanıtı üretilir ve DB'ye yazılır.
- Geçerli `WHATSAPP_ACCESS_TOKEN` yoksa WhatsApp gönderimi log'a hata düşer ama
  DB kayıtları yine oluşur.

Kontrol için Adım 6'daki SQL'i çalıştır. Test verisini temizle:

```bash
docker compose exec database mysql -uroot -pChangeMeRoot whatsapp_messenger \
  -e "DELETE FROM conversation_message; DELETE FROM conversation_session;"
```

---

## Adım 2 — Log'ları canlı izle

Ayrı bir terminal aç, testlerin geri kalanı boyunca açık bıraksın:

```bash
docker compose logs -f php
```

---

## Adım 3 — ngrok tüneli

ngrok kurulu (Homebrew) ve authtoken yapılandırılmış. Sabit domain ile başlat:

```bash
ngrok http --url=unreined-amorally-idella.ngrok-free.dev 8080
```

**Tünel açık kalmalı.** Gelen istekleri canlı izleme: `http://localhost:4040`.

Doğrulama (challenge testi, bu sefer ngrok üzerinden):

```bash
curl -i "https://unreined-amorally-idella.ngrok-free.dev/webhook/whatsapp?hub_mode=subscribe&hub_verify_token=TOKEN&hub_challenge=test123"
```

---

## Adım 4 — Meta Dashboard'da webhook'u bağla

Meta App Dashboard → **WhatsApp → Configuration → Webhook → Edit**:

| Alan | Değer |
|---|---|
| Callback URL | `https://unreined-amorally-idella.ngrok-free.dev/webhook/whatsapp` |
| Verify token | `.env.local`'deki `WHATSAPP_VERIFY_TOKEN` ile **birebir aynı** |

**"Verify and save"** → yeşil onay gelmeli.

Sonra aynı ekranda **Webhook fields** listesinde **`messages`** satırında **Subscribe**.

Ayrıca **WhatsApp → API Setup → "To"** altında kendi WhatsApp numaranı alıcı olarak
ekle ve gelen OTP ile doğrula (ücretsiz test modunda en fazla 5 alıcı).

---

## Adım 5 — Gerçek mesaj gönder (1. tur)

Kendi kişisel WhatsApp'ından, Meta'daki işletme test numarasına ("From") bir metin yaz:
**`merhaba`**

Log terminalinde: gelen POST → DB flush → OpenAI çağrısı → WhatsApp gönderimi.

---

## Adım 6 — Veritabanını doğrula

```bash
docker compose exec database mysql -uroot -pChangeMeRoot whatsapp_messenger -e "
SELECT id, phone_number, status, turn_count, max_turns FROM conversation_session;
SELECT id, session_id, role, LEFT(content,50) content, whatsapp_message_id FROM conversation_message ORDER BY id;"
```

**Beklenen 1. tur sonrası:**
- `conversation_session`: 1 satır — `status=active`, `turn_count=1`, `max_turns=5`
- `conversation_message`: 2 satır
  - `role=user` → `whatsapp_message_id` dolu (`wamid...`)
  - `role=assistant` → `whatsapp_message_id` NULL

---

## Adım 7 — Telefonda yanıtı kontrol et

İşletme numarasından Türkçe, kısa bir AI yanıtı gelmeli.

---

## Adım 8 — Turları tekrarla (2 → 5)

Aynı numaradan 4 mesaj daha gönder. Her mesajdan sonra Adım 6'daki SQL'i çalıştır:

| Tur | `turn_count` | `status`    | mesaj sayısı |
|-----|--------------|-------------|--------------|
| 2   | 2            | active      | 4            |
| 3   | 3            | active      | 6            |
| 4   | 4            | active      | 8            |
| 5   | 5            | completed   | 10           |

5. turda:
- `status` → `completed`
- Telefona gelen 5. yanıtın **sonunda** şu ek olmalı:
  > Bu oturum burada sona erdi, tekrar mesaj yazarsan yeni bir oturum başlar.

---

## Adım 9 — Yeni oturum açıldığını doğrula

Oturum kapandıktan sonra aynı numaradan 1 mesaj daha gönder, sonra:

```bash
docker compose exec database mysql -uroot -pChangeMeRoot whatsapp_messenger -e "
SELECT id, phone_number, status, turn_count FROM conversation_session ORDER BY id;"
```

**Beklenen:** 2 satır — id=1 `completed` (turn_count=5), **id=2 `active` (turn_count=1)**.

---

## Adım 10 (opsiyonel) — Idempotency / retry koruması

Adım 1'deki opsiyonel POST komutunu **aynı `wamid.TEST001` ID'siyle iki kez**
çalıştır. İkinci seferde:
- Log'da: `mesaj zaten işlenmiş, atlanıyor`
- DB'ye ikinci kez yazılmaz
- Yine `{"status":"ok"}` 200 döner

---

## Sorun giderme

| Belirti | Bakılacak yer |
|---|---|
| `502 Bad Gateway` | php-fpm sağlıklı değil. `docker compose logs php` — genelde DB erişimi (`app` kullanıcısının `whatsapp_messenger` yetkisi) ya da `.env.local` |
| Meta "verify" başarısız | `WHATSAPP_VERIFY_TOKEN` birebir eşleşiyor mu; `cache:clear` çalıştı mı; ngrok URL'si doğru mu |
| Webhook 200 dönüyor ama yanıt gelmiyor | `docker compose logs php` — OpenAI (`OPENAI_API_KEY`) veya WhatsApp (`WHATSAPP_ACCESS_TOKEN`, 24 saat penceresi) hatası |
| Log: "OpenAI boş yanıt döndü" | OpenAI `429` — kredi bitmiş (`insufficient_quota`) veya model adı geçersiz. Kredi ekle, `OPENAI_MODEL=gpt-4o-mini` |
| Log: "yanıt gönderilemedi" veya `OAuthException code 190` | `WHATSAPP_ACCESS_TOKEN` süresi dolmuş (24 saatlik token) veya alıcı "To" listesinde doğrulanmamış |
| DB'ye yazılmıyor | `docker compose exec -u 1000:1000 php php bin/console doctrine:schema:validate` |
| `turn_count` artmıyor | Aynı `wamid` ile tekrar mesaj gelmiş olabilir (idempotency); Meta gerçek mesajda her seferinde yeni ID üretir |
| Metin dışı mesaj (resim/ses) | Bilinçli olarak yok sayılır, 200 döner — `WhatsAppWebhookController` içinde `type !== 'text'` kontrolü |

## Faydalı komutlar

```bash
docker compose ps                                              # konteyner durumu
docker compose logs -f php                                     # canlı log
docker compose exec php php bin/console debug:router | grep whatsapp
docker compose exec php php bin/console debug:dotenv
docker compose exec -u 1000:1000 php php bin/console doctrine:migrations:status

# CLI'dan mesaj gönderme (24 saat penceresi açıksa serbest metin)
docker compose exec php php bin/console app:send-message 905XXXXXXXXX "merhaba"
docker compose exec php php bin/console app:send-message 905XXXXXXXXX x --template=hello_world
docker compose exec php php bin/console app:send-message 905XXXXXXXXX "kısa moral mesajı" --generate
```
