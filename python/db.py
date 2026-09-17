"""MySQL veritabanına bağlantı için ortak yardımcı fonksiyonlar.

Bağlantı bilgileri, `python` servisine compose.yaml üzerinden enjekte edilen ortam
değişkenlerinden okunur (aynı MySQL container'ı için php servisinin kullandığı
kimlik bilgileriyle aynı) — bu dosyaya veya bir .env'e sabit değer yazılmaz.
"""

from __future__ import annotations

import os

import pymysql
import pymysql.cursors
from sqlalchemy import create_engine
from sqlalchemy.engine import Engine


def get_connection() -> pymysql.connections.Connection:
    """Ham bir PyMySQL bağlantısı döner (DB-API 2.0 uyumlu; `with` ile kullanılabilir).

    Satırlar dict olarak döner (`cursor.fetchone()["kolon_adi"]`).
    """
    return pymysql.connect(
        host=os.environ.get("MYSQL_HOST", "database"),
        port=int(os.environ.get("MYSQL_PORT", "3306")),
        user=os.environ.get("MYSQL_USER", "app"),
        password=os.environ.get("MYSQL_PASSWORD", "changeMe"),
        database=os.environ.get("MYSQL_DATABASE", "whatsapp_messenger"),
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
    )


def get_engine() -> Engine:
    """SQLAlchemy engine döner (örn. `pandas.read_sql(sorgu, get_engine())` ile kullanmak için)."""
    return create_engine(os.environ["DATABASE_URL"])
