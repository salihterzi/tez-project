#!/bin/sh
# Prod dağıtımı — sunucunun kendi kendine çekmesi.
#
# NİYE ÇEKME, NİYE WEBHOOK DEĞİL: kym.anadolu.edu.tr'ye internetten GELEN bağlantılar
# kurumsal güvenlik duvarında süzülüyor (2026-11-24'te doğrulandı). GitHub webhook'u ve
# Actions+SSH bu yüzden çalışmaz; sunucunun dışa bağlanıp değişikliği çekmesi gerekir.
#
# KULLANIM
#   sh deploy/deploy.sh          # değişiklik varsa dağıt
#   sh deploy/deploy.sh --force  # değişiklik olmasa da yeniden derle
#
# CRON (5 dakikada bir):
#   */5 * * * * cd /opt/proje && sh deploy/deploy.sh >> /opt/proje/var/deploy.log 2>&1
set -eu

cd "$(dirname "$0")/.."
PROJECT_DIR=$(pwd)
FORCE=${1:-}

# Üst üste binen çalıştırmaları engelle — derleme dakikalar sürebiliyor ve cron
# beklemez. flock dosyayı tutar, ikinci çalıştırma sessizce çıkar.
LOCK_FILE="$PROJECT_DIR/var/deploy.lock"
mkdir -p "$PROJECT_DIR/var"
if command -v flock >/dev/null 2>&1; then
	exec 9>"$LOCK_FILE"
	flock -n 9 || { echo "$(date '+%F %T') · önceki dağıtım sürüyor, atlandı"; exit 0; }
fi

log() { echo "$(date '+%F %T') · $*"; }

# Sunucuda elle düzenleme yapıldıysa SESSİZCE ezmek yerine dur. (Geçmişte sunucuda
# sed ile Dockerfile düzeltmesi önerilmişti; böyle bir değişiklik varsa görülmeli.)
if ! git diff --quiet || ! git diff --cached --quiet; then
	log "DURDU: sunucuda commit'lenmemiş değişiklik var. 'git status' ile bak."
	git status --short
	exit 1
fi

OLD_REV=$(git rev-parse HEAD)
git fetch --quiet origin main
NEW_REV=$(git rev-parse origin/main)

if [ "$OLD_REV" = "$NEW_REV" ] && [ "$FORCE" != "--force" ]; then
	log "değişiklik yok ($(echo "$OLD_REV" | cut -c1-7))"
	exit 0
fi

if [ "$OLD_REV" != "$NEW_REV" ]; then
	log "güncelleme: $(echo "$OLD_REV" | cut -c1-7) → $(echo "$NEW_REV" | cut -c1-7)"
	# --ff-only: birleştirme commit'i üretmez, ayrışma varsa hata verir.
	git merge --ff-only origin/main
	git --no-pager log --oneline "$OLD_REV..$NEW_REV" | sed 's/^/    /'
fi

# make prod-up önce derler, sonra değiştirir. Derleme patlarsa çalışan site
# ETKİLENMEZ — bu betiğin kesinti üretmemesi buna dayanıyor.
log "dağıtım başlıyor"
if make prod-up; then
	log "dağıtım TAMAM"
else
	log "dağıtım BAŞARISIZ — çalışan sürüm korundu"
	exit 1
fi
