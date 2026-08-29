#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════════
# A3 ERP — Script de déploiement VPS (Zero-Downtime)
# ═══════════════════════════════════════════════════════════════════════════════
# Usage:
#   chmod +x deploy/deploy.sh
#   ./deploy/deploy.sh [--skip-composer] [--skip-npm]
#
#   Si des migrations sont en attente, le script s'arrête et affiche la liste
#   au lieu de les appliquer silencieusement. Pour confirmer explicitement
#   l'application après avoir relu la liste :
#     DEPLOY_MODE=apply-migrations ./deploy/deploy.sh
#
# Prérequis VPS:
#   - PHP 8.2+, Composer 2, Node 20+, npm, Redis, MySQL 8
#   - sudo configuré sans mot de passe pour php, composer, npm
#
# [PILOT-DEPLOY-02] Ce script ne lance JAMAIS migrate:fresh, db:wipe ni
# migrate:reset — uniquement migrate --force sur une base existante, et
# seulement après confirmation explicite + sauvegarde vérifiée.
# ═══════════════════════════════════════════════════════════════════════════════

set -euo pipefail

# ── Couleurs ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; NC='\033[0m'
info()    { echo -e "${BLUE}[INFO]${NC} $*"; }
success() { echo -e "${GREEN}[OK]${NC} $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC} $*"; }
error()   { echo -e "${RED}[ERROR]${NC} $*"; echo -e "${RED}[DEPLOYMENT]${NC} FAILED"; exit 1; }

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
info "1/13 Vérification de l'environnement..."
[ -f ".env" ] || error ".env introuvable — copier .env.production.example"
grep -q "APP_KEY=base64:" .env || error "APP_KEY vide — lancer: php artisan key:generate"
grep -q "APP_DEBUG=false" .env || warn "APP_DEBUG n'est pas false — vérifier .env"
grep -q "APP_ENV=production" .env || warn "APP_ENV n'est pas production"
success "Environnement OK"

# ── 2. Traçabilité de la release courante (pour rollback) ─────────────────────
info "2/13 Enregistrement de la release courante..."
mkdir -p storage/app/releases
if [ -f .git/HEAD ]; then
    PREVIOUS_RELEASE="$(git rev-parse HEAD)"
    echo "$PREVIOUS_RELEASE" > storage/app/releases/PREVIOUS_RELEASE
    info "Release avant déploiement : $PREVIOUS_RELEASE (enregistrée dans storage/app/releases/PREVIOUS_RELEASE)"
fi
success "Release précédente tracée"

# ── 3. Mode maintenance ───────────────────────────────────────────────────────
info "3/13 Activation du mode maintenance..."
php artisan down --retry=60 --secret="a3erp-$(date +%s)" 2>/dev/null || true
success "Mode maintenance activé"

# ── 4. Git pull ───────────────────────────────────────────────────────────────
info "4/13 Mise à jour du code (git pull)..."
git fetch origin main
git reset --hard origin/main
CURRENT_RELEASE="$(git rev-parse HEAD)"
echo "$CURRENT_RELEASE" > storage/app/releases/CURRENT_RELEASE
success "Code mis à jour — $(git log --oneline -1)"

# ── 5. Composer ───────────────────────────────────────────────────────────────
if [ "$SKIP_COMPOSER" = false ]; then
    info "5/13 Dépendances PHP (composer)..."
    # --classmap-authoritative évalué mais non activé par défaut : non testé
    # contre ce projet spécifique (packages avec autoload conditionnel type
    # laravel/pint, pest — risque de classe non trouvée en environnement dev
    # mélangé). À activer uniquement après un test dédié --no-dev complet.
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
    success "Composer OK"
else
    warn "5/13 Composer ignoré (--skip-composer)"
fi

# ── 6. NPM + Vite build ───────────────────────────────────────────────────────
if [ "$SKIP_NPM" = false ]; then
    info "6/13 Assets frontend (npm + Vite)..."
    info "Node : $(node --version 2>/dev/null || echo 'INTROUVABLE')"
    info "npm  : $(npm --version 2>/dev/null || echo 'INTROUVABLE')"
    command -v node >/dev/null 2>&1 || error "Node introuvable — build frontend impossible."
    npm ci --prefer-offline
    npm run build
    [ -f "public/build/manifest.json" ] || error "public/build/manifest.json absent après le build — déploiement refusé (assets non compilés)."
    success "Assets compilés (manifest.json présent)"
else
    warn "6/13 NPM ignoré (--skip-npm)"
    [ -f "public/build/manifest.json" ] || error "public/build/manifest.json absent et --skip-npm demandé — aucun build disponible."
fi

# ── 7. Sauvegarde vérifiée AVANT toute migration ──────────────────────────────
# Principe : NO VERIFIED BACKUP = NO DATABASE MIGRATION.
info "7/13 Sauvegarde de sécurité pré-migration..."
mkdir -p storage/app/backups
RECENT_BACKUP="$(find storage/app/backups -name '*.zip' -newermt '-15 minutes' 2>/dev/null | sort -r | head -1)"
if [ -z "$RECENT_BACKUP" ]; then
    info "Aucune sauvegarde de moins de 15 minutes — déclenchement de backup:run..."
    php artisan backup:run || error "backup:run a échoué — NO VERIFIED BACKUP = NO DATABASE MIGRATION. Déploiement arrêté avant toute migration."
    RECENT_BACKUP="$(find storage/app/backups -name '*.zip' -newermt '-15 minutes' 2>/dev/null | sort -r | head -1)"
fi
[ -n "$RECENT_BACKUP" ] || error "NO VERIFIED BACKUP = NO DATABASE MIGRATION. Aucune archive trouvée après backup:run."
BACKUP_SIZE="$(stat -c%s "$RECENT_BACKUP" 2>/dev/null || stat -f%z "$RECENT_BACKUP" 2>/dev/null || echo 0)"
[ "$BACKUP_SIZE" -gt 1024 ] || error "Sauvegarde trouvée ($RECENT_BACKUP) mais anormalement petite (${BACKUP_SIZE} octets) — refus de migrer."
BACKUP_CHECKSUM="$(sha256sum "$RECENT_BACKUP" 2>/dev/null | cut -d' ' -f1 || shasum -a 256 "$RECENT_BACKUP" | cut -d' ' -f1)"
info "Sauvegarde vérifiée :"
info "  fichier   : $RECENT_BACKUP"
info "  taille    : ${BACKUP_SIZE} octets"
info "  sha256    : $BACKUP_CHECKSUM"
info "  horodatage: $(date -r "$RECENT_BACKUP" '+%Y-%m-%d %H:%M:%S' 2>/dev/null || stat -f '%Sm' "$RECENT_BACKUP")"
success "Sauvegarde vérifiée et exploitable"

# ── 8. Migration gate — inspection AVANT exécution ────────────────────────────
info "8/13 Vérification des migrations en attente..."
php artisan migrate:status || warn "migrate:status a retourné un code d'erreur"

if php artisan a3:check-pending-migrations; then
    success "Aucune migration en attente — rien à appliquer"
else
    warn "Migration(s) en attente détectée(s) (liste ci-dessus)"
    if [ "${DEPLOY_MODE:-}" != "apply-migrations" ]; then
        error "Migrations en attente mais DEPLOY_MODE != apply-migrations. Relire la liste ci-dessus puis relancer explicitement avec : DEPLOY_MODE=apply-migrations ./deploy/deploy.sh"
    fi
    info "DEPLOY_MODE=apply-migrations confirmé — application des migrations..."
    php artisan migrate --force || error "Migration échouée — restaurer depuis $RECENT_BACKUP (sha256:$BACKUP_CHECKSUM) si nécessaire. Voir deploy/ROLLBACK.md."
    php artisan migrate:status
    php artisan a3:check-pending-migrations || error "Post-check : migration(s) toujours en attente après migrate --force. deployment = FAILED."
    success "Migrations appliquées et vérifiées"
fi

# ── 9. Caches Laravel ──────────────────────────────────────────────────────────
info "9/13 Reconstruction des caches Laravel..."
php artisan cache:clear || error "cache:clear a échoué"
php artisan config:cache || error "config:cache a échoué"
php artisan route:cache || error "route:cache a échoué"
php artisan view:cache || error "view:cache a échoué"
php artisan event:cache || warn "event:cache a échoué (non bloquant — vérifier compatibilité si listeners à closures)"
success "Caches reconstruits"

# ── 10. Storage : répertoires + symlink ────────────────────────────────────────
info "10/13 Répertoires storage et lien symbolique..."
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
         storage/app/backups storage/app/private storage/logs bootstrap/cache
php artisan storage:link --force 2>/dev/null || true
success "Storage prêt"

# ── 11. Permissions (jamais 777) ────────────────────────────────────────────────
info "11/13 Permissions fichiers..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
success "Permissions appliquées"

# ── 12. Restart services ────────────────────────────────────────────────────────
info "12/13 Redémarrage des services..."
sudo systemctl reload php8.2-fpm 2>/dev/null || sudo service php8.2-fpm reload 2>/dev/null || true
sudo supervisorctl restart "a3erp:*" 2>/dev/null || true
success "Services redémarrés"

# ── 13. Sortie du mode maintenance ──────────────────────────────────────────────
info "13/13 Sortie du mode maintenance..."
php artisan up
success "Mode maintenance désactivé"

info "═══════════════════════════════════════"
success " Déploiement terminé ! $(date '+%H:%M:%S')"
info " Release : $CURRENT_RELEASE"
info " URL : $(grep APP_URL .env | cut -d= -f2)"
info " Exécuter deploy/health-check.sh pour valider le déploiement."
info "═══════════════════════════════════════"
