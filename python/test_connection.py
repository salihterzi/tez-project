"""MySQL bağlantısını hızlıca doğrulamak için:

    docker compose exec python python test_connection.py
"""

from __future__ import annotations

from db import get_connection


def main() -> None:
    with get_connection() as conn, conn.cursor() as cursor:
        cursor.execute("SELECT VERSION() AS version")
        row = cursor.fetchone()
        print(f"MySQL bağlantısı başarılı. Sunucu sürümü: {row['version']}")


if __name__ == "__main__":
    main()
