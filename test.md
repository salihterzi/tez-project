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

---

## Adım 0 — `.env.local`'i doldur

`.env.local` git'e girmez; şu an placeholder'lar boş. Bir verify token üret:

```bash
uuidgen   # çıktıyı WHATSAPP_VERIFY_TOKEN'a yapıştır
```

`.env.local` içinde şu 5 satır dolu olmalı:

```dotenv
WHATSAPP_VERIFY_TOKEN=<uuidgen çıktısı>
META_APP_SECRET=<Meta App Dashboard > Settings > Basic > App Secret>
WHATSAPP_ACCESS_TOKEN=<Meta > WhatsApp > API Setup > access token>
WHATSAPP_PHONE_NUMBER_ID=<Meta > WhatsApp > API Setup > Phone number ID>
OPENAI_API_KEY=<platform.openai.com > API keys>
```

> `DATABASE_URL` compose dosyalarında tanımlı değil; kaynak `.env` (docker
> varsayılanı) + `.env.local` (senin değerin). `.env.local`'de hazır, dokunmana gerek yok.

Yeniden yükle:

```bash
docker compose up -d
docker compose exec -u 1000:1000 php php bin/console cache:clear
```

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

ngrok kurulu değilse kur (https://ngrok.com/download veya `snap install ngrok`), sonra:

```bash
ngrok http 8080
```

Çıktıdaki `https://xxxx.ngrok-free.app` adresini kopyala. **Tünel açık kalmalı.**

Doğrulama (challenge testi, bu sefer ngrok üzerinden):

```bash
curl -i "https://xxxx.ngrok-free.app/webhook/whatsapp?hub_mode=subscribe&hub_verify_token=TOKEN&hub_challenge=test123"
```

---

## Adım 4 — Meta Dashboard'da webhook'u bağla

Meta App Dashboard → **WhatsApp → Configuration → Webhook → Edit**:

| Alan | Değer |
|---|---|
| Callback URL | `https://xxxx.ngrok-free.app/webhook/whatsapp` |
| Verify token | `.env.local`'deki `WHATSAPP_VERIFY_TOKEN` ile **birebir aynı** |

**"Verify and save"** → yeşil onay gelmeli.

Sonra aynı ekranda **Webhook fields** listesinde **`messages`** satırında **Subscribe**.

---

## Adım 5 — Gerçek mesaj gönder (1. tur)

Kendi kişisel WhatsApp numaranfrom, Meta'daki işletme test numarasına bir metin yaz:
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
| Meta "verify" başarısız | `WHATSAPP_VERIFY_TOKEN` birebir eşleşiyor mu; `cache:clear` çalıştı mı; ngrok URL'si doğru mu |
| Webhook 200 dönüyor ama yanıt gelmiyor | `docker compose logs php` — OpenAI (`OPENAI_API_KEY`) veya WhatsApp (`WHATSAPP_ACCESS_TOKEN`, 24 saat penceresi) hatası |
| DB'ye yazılmıyor | `docker compose exec -u 1000:1000 php php bin/console doctrine:schema:validate` |
| `turn_count` artmıyor | Aynı `wamid` ile tekrar mesaj gelmiş olabilir (idempotency); Meta gerçek mesajda her seferinde yeni ID üretir |
| Metin dışı mesaj (resim/ses) | Bilinçli olarak yok sayılır, 200 döner — `WhatsAppWebhookController` içinde `type !== 'text'` kontrolü |
