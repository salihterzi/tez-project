# Python container

`whatsapp_messenger` MySQL veritabanına bağlanan Python betikleri (örn. veri analizi,
tek seferlik veri düzeltmeleri) için ayrılmış dizin. `docker compose`'daki `python`
servisi bu dizini imaja gömer (prod) / dev'de bind-mount eder, bu yüzden buraya
eklenen her `.py` dosyası container içinden doğrudan çalıştırılabilir.

## Kullanım

```bash
docker compose up -d python
docker compose exec python python test_connection.py
```

Yeni bir bağımlılık eklemek için `requirements.txt`'e ekleyip imajı yeniden build edin:

```bash
docker compose build python
```

## Bağlantı

Ortam değişkenleri (`database` servisiyle aynı kimlik bilgileri, compose.yaml'da
tanımlı) container'a otomatik enjekte edilir:

- `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`
- `DATABASE_URL` (SQLAlchemy formatında: `mysql+pymysql://...`)

Hazır yardımcılar için [`db.py`](./db.py)'ye bakın:

```python
from db import get_connection, get_engine

# Ham PyMySQL bağlantısı (DB-API 2.0)
with get_connection() as conn, conn.cursor() as cursor:
    cursor.execute("SELECT * FROM ogrenci LIMIT 10")
    print(cursor.fetchall())

# SQLAlchemy engine (örn. pandas.read_sql ile)
import pandas as pd
df = pd.read_sql("SELECT * FROM sinav_sonucu LIMIT 1000", get_engine())
```
