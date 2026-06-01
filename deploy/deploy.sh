#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════════
# A3 ERP — Script de déploiement VPS (Zero-Downtime)
# ═══════════════════════════════════════════════════════════════════════════════
# Usage:
#   chmod +x deploy/deploy.sh
#   ./deploy/deploy.sh [--skip-composer] [--skip-npm]
#
# Prérequis VPS:
#   - PHP 8.2+, Composer 2, Node 20+, npm, Redis, MySQL 8
#   - sudo configuré sans mot de passe pour php, composer, npm
# ═══════════════════════════════════════════════════════════════════════════════

set -euo pipefail

# ── Couleurs ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; NC='\033[0m'
info()    { echo -e "${BLUE}[INFO]${NC} $*"; }
success() { echo -e "${GREEN}[OK]${NC} $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC} $*"; }
error()   { echo -e "${RED}[ERROR]${NC} $*"; exit 1; }

# ── Configuration ─────────────────────────────────────────────────────────────
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SKIP_COMPOSER=false
SKIP_NPM=false

for arg in "$@"; do
    case $arg in
        --skip-composer) SKIP_COMPOSER=true ;;
        --skip-npm)      SKIP_NPM=true ;;
    esac
done

info "═══════════════════════════════════════"
info " Déploiement A3 ERP — $(date '+%Y-%m-%d %H:%M:%S')"
info " Répertoire : $APP_DIR"
info "═══════════════════════════════════════"

cd "$APP_DIR" || error "Impossible d'accéder à $APP_DIR"

# ── 1. Vérification .env ──────────────────────────────────────────────────────
info "1/10 Vérification de l'environnement..."
[ -f ".env" ] || error ".env introuvable — copier .env.production.example"
grep -q "APP_KEY=base64:" .env || error "APP_KEY vide — lancer: php artisan key:generate"
grep -q "APP_DEBUG=false" .env || warn "APP_DEBUG n'est pas false — vérifier .env"
grep -q "APP_ENV=production" .env || warn "APP_ENV n'est pas production"
success "Environnement OK"

# ── 2. Mode maintenance ───────────────────────────────────────────────────────
info "2/10 Activation du mode maintenance..."
php artisan down --retry=60 --secret="a3erp-$(date +%s)" 2>/dev/null || true
success "Mode maintenance activé"

# ── 3. Git pull ───────────────────────────────────────────────────────────────
info "3/10 Mise à jour du code (git pull)..."
git fetch origin main
git reset --hard origin/main
success "Code mis à jour — $(git log --oneline -1)"

# ── 4. Composer ───────────────────────────────────────────────────────────────
if [ "$SKIP_COMPOSER" = false ]; then
    info "4/10 Dépendances PHP (composer)..."
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
    success "Composer OK"
else
    warn "4/10 Composer ignoré (--skip-composer)"
fi

# ── 5. NPM + Vite build ───────────────────────────────────────────────────────
if [ "$SKIP_NPM" = false ]; then
    info "5/10 Assets frontend (npm + Vite)..."
    npm ci --prefer-offline
    npm run build
    success "Assets compilés"
else
    warn "5/10 NPM ignoré (--skip-npm)"
fi

# ── 6. Migrations ─────────────────────────────────────────────────────────────
info "6/10 Migrations base de données..."
php artisan migrate --force
success "Migrations exécutées"

# ── 7. Caches Laravel ─────────────────────────────────────────────────────────
info "7/10 Reconstruction des caches Laravel..."
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
success "Caches reconstruits"

# ── 8. Storage symlink ────────────────────────────────────────────────────────
info "8/10 Lien symbolique storage..."
php artisan storage:link --force 2>/dev/null || true
success "Storage lié"

# ── 9. Permissions ────────────────────────────────────────────────────────────
info "9/10 Permissions fichiers..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
success "Permissions appliquées"

# ── 10. Restart services ──────────────────────────────────────────────────────
info "10/10 Redémarrage des services..."
# PHP-FPM
sudo systemctl reload php8.2-fpm 2>/dev/null || sudo service php8.2-fpm reload 2>/dev/null || true
# Queue workers via Supervisor
sudo supervisorctl restart "a3erp-worker:*" 2>/dev/null || true
success "Services redémarrés"

# ── Sortie du mode maintenance ────────────────────────────────────────────────
php artisan up
success "Mode maintenance désactivé"

info "═══════════════════════════════════════"
success " Déploiement terminé ! $(date '+%H:%M:%S')"
info " URL : $(grep APP_URL .env | cut -d= -f2)"
info "═══════════════════════════════════════"
