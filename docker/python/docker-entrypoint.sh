#!/bin/sh
set -e

# Cron ortam değişkenlerini miras almaz; konteynerin env'ini dosyaya dökmek şart.
# (2026-11-11: python veri hattı cron'a bağlandı.)
: > /app/cron.env
for d in TZ MYSQL_HOST MYSQL_PORT MYSQL_DATABASE MYSQL_USER MYSQL_PASSWORD DATABASE_URL; do
	eval "v=\${$d:-}"
	[ -n "$v" ] && printf 'export %s=%s\n' "$d" "$(printf '%s' "$v" | sed "s/'/'\\\\''/g; s/^/'/; s/\$/'/")" >> /app/cron.env
done
mkdir -p /app/var/log

if [ "$1" = "cron" ]; then
	echo "[entrypoint] TZ=${TZ:-(boş)} · $(date '+%Y-%m-%d %H:%M:%S %Z') · cron başlatılıyor"
	exec cron -f -L 2
fi
exec "$@"
