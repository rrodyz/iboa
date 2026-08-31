#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════════
# A3 ERP — Script de déploiement VPS (Zero-Downtime)
# ═══════════════════════════════════════════════════════════════════════════════
# Usage:
#   chmod +x deploy/deploy.sh
#   EXPECTED_RELEASE_SHA=<sha40> ./deploy/deploy.sh [--skip-composer] [--skip-npm]
#
#   EXPECTED_RELEASE_SHA est OBLIGATOIRE (fail-closed) — c'est l'opérateur qui
#   confirme, à chaque exécution, le commit exact qu'il veut voir en ligne.
#   Le script refuse de déployer si ce SHA ne correspond pas exactement à
#   origin/$DEPLOY_BRANCH au moment du fetch (protection contre un push
#   surprise sur la branche release entre la préparation et l'exécution).
#
#   DEPLOY_BRANCH (défaut : release/pilot-candidate) — seules les branches
#   `release/*` sont acceptées. main/master/develop/feature/*/integration/*
#   sont refusées explicitement : ce script déploie une release nommée, pas
#   "ce qui se trouve sur main" (voir PILOT-SERVER-PROVISIONING, PHASE 1-9).
#
#   Si des migrations sont en attente, le script s'arrête et affiche la liste
#   au lieu de les appliquer silencieusement. Pour confirmer explicitement
#   l'application après avoir relu la liste :
#     DEPLOY_MODE=apply-migrations EXPECTED_RELEASE_SHA=<sha40> ./deploy/deploy.sh
#
#   KNOWN_PENDING_MIGRATIONS (optionnel, défaut : la migration calendrier du
#   premier pilote) — liste blanche séparée par virgules des migrations
#   attendues en attente. Toute migration pending NON listée bloque le
#   déploiement même en DEPLOY_MODE=apply-migrations (une approbation donnée
#   pour une liste connue n'est pas une approbation pour une migration
#   surprise). Mettre à jour cette liste consciemment à chaque nouvelle
#   migration prévue pour un déploiement pilote.
#
# Prérequis VPS (voir docs/PILOT-SERVER-SPEC.md pour le détail justifié) :
#   - PHP 8.2+, Composer 2, Node (voir preflight_node — contrainte réelle de
#     Vite 7, pas un chiffre arbitraire), npm, Redis, MySQL 8/MariaDB équiv.,
#     mysqldump (utilisé en interne par spatie/laravel-backup)
#   - sudo configuré sans mot de passe pour php, composer, npm
#
# [PILOT-DEPLOY-02] Ce script ne lance JAMAIS migrate:fresh, db:wipe ni
# migrate:reset — uniquement migrate --force sur une base existante, et
# seulement après confirmation explicite + sauvegarde vérifiée.
#
# Ce fichier est conçu pour être sourcé par les tests de
# deploy/tests/test-deploy-guards.sh : toute la logique vit dans des
# fonctions, rien ne s'exécute au chargement — seul l'appel à main() tout en
# bas, protégé par un garde BASH_SOURCE, déclenche le pipeline réel.
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

DEPLOY_BRANCH="${DEPLOY_BRANCH:-release/pilot-candidate}"
EXPECTED_RELEASE_SHA="${EXPECTED_RELEASE_SHA:-}"
KNOWN_PENDING_MIGRATIONS="${KNOWN_PENDING_MIGRATIONS:-2026_08_29_090000_create_production_calendar_exceptions}"

# Contrainte Node réelle de la stack frontend (Vite 7), lue dans
# node_modules/vite/package.json → "engines": { "node": "^20.19.0 || >=22.12.0" }
# au moment de la rédaction de ce script. Revérifier après toute montée de
# version de Vite (npm ls vite).
NODE_MIN_20_MINOR=19
NODE_MIN_22_MINOR=12

for arg in "$@"; do
    case $arg in
        --skip-composer) SKIP_COMPOSER=true ;;
        --skip-npm)      SKIP_NPM=true ;;
    esac
done

# ── Garde-fous réutilisables (testés isolément par test-deploy-guards.sh) ──────

# PHASE 5/9 — n'accepte que des branches release/* ; refuse explicitement
# main/master/develop/feature/*/integration/* même si DEPLOY_BRANCH est
# surchargé par erreur.
validate_branch() {
    local branch="$1"
    case "$branch" in
        main|master|develop|feature/*|integration/*)
            error "BRANCHE REFUSÉE : '$branch' — ce script ne déploie que release/*. La release officielle est release/pilot-candidate."
            ;;
        release/*)
            success "Branche cible autorisée : $branch"
            ;;
        *)
            error "BRANCHE REFUSÉE : '$branch' ne correspond à aucun motif autorisé (attendu : release/*)."
            ;;
    esac
}

# PHASE 6/7 — fail-closed : sans EXPECTED_RELEASE_SHA fourni explicitement par
# l'opérateur, le script ne déploie rien.
require_expected_sha() {
    local sha="$1"
    [ -n "$sha" ] || error "EXPECTED_RELEASE_SHA manquant. Fournir explicitement : EXPECTED_RELEASE_SHA=<sha40> $0"
    [[ "$sha" =~ ^[0-9a-f]{40}$ ]] || error "EXPECTED_RELEASE_SHA invalide (attendu : 40 caractères hexadécimaux) : '$sha'"
}

# PHASE 8 — fetch uniquement la branche cible, jamais main.
resolve_remote_sha() {
    local branch="$1"
    git fetch origin "$branch" >&2
    git rev-parse "origin/$branch"
}

# PHASE 6 — compare le SHA distant résolu à celui attendu par l'opérateur.
compare_expected_sha() {
    local expected="$1" resolved="$2"
    if [ "$expected" != "$resolved" ]; then
        error "RELEASE SHA MISMATCH — attendu $expected, origin/$DEPLOY_BRANCH pointe sur $resolved. Le script s'arrête : quelqu'un a poussé sur la branche release depuis la préparation du déploiement."
    fi
    success "SHA distant vérifié : $resolved"
}

# PHASE 10 — ne jamais écraser silencieusement du travail local avec reset --hard.
check_dirty_tree() {
    local dirty
    dirty="$(git status --porcelain)"
    if [ -n "$dirty" ]; then
        echo "$dirty" >&2
        error "WORKTREE DIRTY — modifications locales détectées sur le serveur. reset --hard refusé tant que l'arbre n'est pas propre (voir la liste ci-dessus)."
    fi
    success "Arbre de travail propre"
}

# PHASE 20/21 — préflight PHP : version + extensions réellement requises par
# composer.lock (pas une checklist Laravel générique).
preflight_php() {
    command -v php >/dev/null 2>&1 || error "PHP introuvable."
    info "PHP : $(php -r 'echo PHP_VERSION;')"
    if command -v composer >/dev/null 2>&1; then
        composer check-platform-reqs || error "composer check-platform-reqs a échoué — plateforme PHP incompatible avec composer.lock. Voir le détail ci-dessus."
    else
        error "Composer introuvable — impossible de vérifier la compatibilité de plateforme."
    fi
    success "Préflight PHP OK"
}

# PHASE 18/19 — préflight Node : contrainte réelle de Vite 7, pas un chiffre
# choisi arbitrairement.
preflight_node() {
    command -v node >/dev/null 2>&1 || error "Node introuvable — build frontend impossible."
    command -v npm  >/dev/null 2>&1 || error "npm introuvable — build frontend impossible."
    local ver major minor
    ver="$(node --version)"
    ver="${ver#v}"
    major="${ver%%.*}"
    minor="${ver#*.}"; minor="${minor%%.*}"
    info "Node : v$ver ($(npm --version) npm)"
    if { [ "$major" -eq 20 ] && [ "$minor" -ge "$NODE_MIN_20_MINOR" ]; } \
        || [ "$major" -gt 22 ] \
        || { [ "$major" -eq 22 ] && [ "$minor" -ge "$NODE_MIN_22_MINOR" ]; }; then
        success "Node compatible (Vite 7 exige ^20.$NODE_MIN_20_MINOR.0 || >=22.$NODE_MIN_22_MINOR.0)"
    else
        error "Node v$ver incompatible avec Vite 7 (exige ^20.$NODE_MIN_20_MINOR.0 || >=22.$NODE_MIN_22_MINOR.0)."
    fi
}

# PHASE 22 — mysqldump est utilisé en interne par spatie/laravel-backup
# (Spatie\DbDumper\Databases\MySql) : sans lui, backup:run échoue à l'étape
# de sauvegarde, après le mode maintenance. Autant l'échouer tôt.
preflight_mysql_client() {
    command -v mysqldump >/dev/null 2>&1 || error "mysqldump introuvable — requis par spatie/laravel-backup pour la sauvegarde pré-migration."
    command -v mysql >/dev/null 2>&1 || warn "mysql (client CLI) introuvable — nécessaire pour une restauration manuelle (deploy/ROLLBACK.md), pas pour le déploiement lui-même."
    success "Client MySQL présent"
}

# PHASE 15 — affiche les migrations en attente et bloque si une migration
# imprévue apparaît, même en DEPLOY_MODE=apply-migrations.
check_pending_migrations_allowlist() {
    local pending allowed_csv="$1" name found
    pending="$(php artisan migrate:status --pending 2>/dev/null | grep -oE '[0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_[A-Za-z0-9_]+' || true)"
    [ -n "$pending" ] || { success "Aucune migration en attente"; return 0; }
    info "Migrations en attente :"
    echo "$pending" >&2
    while IFS= read -r name; do
        [ -n "$name" ] || continue
        found=false
        IFS=',' read -ra allowed_arr <<< "$allowed_csv"
        for a in "${allowed_arr[@]}"; do
            [ "$name" = "$a" ] && found=true && break
        done
        if [ "$found" = false ]; then
            error "MIGRATION INATTENDUE : '$name' n'est pas dans KNOWN_PENDING_MIGRATIONS ('$allowed_csv'). Relire la liste, puis mettre à jour KNOWN_PENDING_MIGRATIONS explicitement si elle est légitime."
        fi
    done <<< "$pending"
    warn "Migration(s) en attente, toutes dans la liste blanche connue"
}

# PHASE 14 — le mode migration explicite reste requis dès qu'il y a du
# pending, quel que soit le nom de la migration.
require_migration_mode_if_pending() {
    local has_pending="$1" mode="$2"
    [ "$has_pending" = "true" ] && [ "$mode" != "apply-migrations" ] && return 1
    return 0
}

# PHASE 7 — détection d'une sauvegarde exploitable (< 15 min, non vide).
# Séparée du déclenchement de backup:run pour rester testable sans Laravel.
find_recent_backup() {
    local backups_dir="$1"
    find "$backups_dir" -name '*.zip' -newermt '-15 minutes' 2>/dev/null | sort -r | head -1
}

verify_backup_size() {
    local path="$1" size
    [ -n "$path" ] && [ -f "$path" ] || return 1
    size="$(stat -c%s "$path" 2>/dev/null || stat -f%z "$path" 2>/dev/null || echo 0)"
    [ "$size" -gt 1024 ]
}

# PHASE 18/6 — le build est refusé sans manifest, que --skip-npm ait été
# utilisé ou non.
require_build_manifest() {
    local manifest="$1"
    [ -f "$manifest" ] || error "public/build/manifest.json absent — déploiement refusé (assets non compilés)."
    success "Build manifest présent : $manifest"
}

# PHASE 47 — health-check.sh doit bloquer le déploiement s'il échoue.
require_health_check_pass() {
    local script="$1"
    if [ -x "$script" ]; then
        "$script" || error "health-check.sh en FAIL — voir le détail ci-dessus. Consulter deploy/ROLLBACK.md."
    else
        warn "deploy/health-check.sh absent ou non exécutable — health check ignoré (ne devrait pas arriver)."
    fi
}

# ── Pipeline principal ──────────────────────────────────────────────────────────
main() {
info "═══════════════════════════════════════"
info " Déploiement A3 ERP — $(date '+%Y-%m-%d %H:%M:%S')"
info " Répertoire : $APP_DIR"
info " Branche cible : $DEPLOY_BRANCH"
info "═══════════════════════════════════════"

cd "$APP_DIR" || error "Impossible d'accéder à $APP_DIR"

# ── 1/16. Vérification .env ──────────────────────────────────────────────────
info "1/16 Vérification de l'environnement..."
[ -f ".env" ] || error ".env introuvable — copier .env.production.example"
grep -q "APP_KEY=base64:" .env || error "APP_KEY vide — lancer: php artisan key:generate"
grep -q "APP_DEBUG=false" .env || warn "APP_DEBUG n'est pas false — vérifier .env"
grep -q "APP_ENV=production" .env || warn "APP_ENV n'est pas production"
preflight_php
preflight_node
preflight_mysql_client
success "Environnement OK"

# ── 2/16. Branche autorisée ──────────────────────────────────────────────────
info "2/16 Validation de la branche cible..."
validate_branch "$DEPLOY_BRANCH"

# ── 3/16. SHA attendu ─────────────────────────────────────────────────────────
info "3/16 Validation du SHA attendu..."
require_expected_sha "$EXPECTED_RELEASE_SHA"

# ── 4/16. Résolution + comparaison du SHA distant ────────────────────────────
info "4/16 Résolution du SHA distant (fetch $DEPLOY_BRANCH uniquement)..."
REMOTE_SHA="$(resolve_remote_sha "$DEPLOY_BRANCH")"
compare_expected_sha "$EXPECTED_RELEASE_SHA" "$REMOTE_SHA"

# ── 5/16. Arbre de travail propre ────────────────────────────────────────────
info "5/16 Vérification de l'arbre de travail..."
check_dirty_tree

# ── 6/16. Traçabilité de la release courante (pour rollback) ────────────────
info "6/16 Enregistrement de la release courante..."
mkdir -p storage/app/releases
if [ -f .git/HEAD ]; then
    PREVIOUS_RELEASE="$(git rev-parse HEAD)"
    echo "$PREVIOUS_RELEASE" > storage/app/releases/PREVIOUS_RELEASE
    info "Release avant déploiement : $PREVIOUS_RELEASE (enregistrée dans storage/app/releases/PREVIOUS_RELEASE)"
fi
success "Release précédente tracée"

# ── 7/16. Sauvegarde vérifiée AVANT toute action destructive ────────────────
# Principe : NO VERIFIED BACKUP = NO DATABASE MIGRATION.
info "7/16 Sauvegarde de sécurité pré-déploiement..."
mkdir -p storage/app/backups
RECENT_BACKUP="$(find_recent_backup storage/app/backups)"
if [ -z "$RECENT_BACKUP" ]; then
    info "Aucune sauvegarde de moins de 15 minutes — déclenchement de backup:run..."
    php artisan backup:run || error "backup:run a échoué — NO VERIFIED BACKUP = NO DATABASE MIGRATION. Déploiement arrêté avant toute migration."
    RECENT_BACKUP="$(find_recent_backup storage/app/backups)"
fi
[ -n "$RECENT_BACKUP" ] || error "NO VERIFIED BACKUP = NO DATABASE MIGRATION. Aucune archive trouvée après backup:run."
BACKUP_SIZE="$(stat -c%s "$RECENT_BACKUP" 2>/dev/null || stat -f%z "$RECENT_BACKUP" 2>/dev/null || echo 0)"
verify_backup_size "$RECENT_BACKUP" || error "Sauvegarde trouvée ($RECENT_BACKUP) mais anormalement petite (${BACKUP_SIZE} octets) — refus de migrer."
BACKUP_CHECKSUM="$(sha256sum "$RECENT_BACKUP" 2>/dev/null | cut -d' ' -f1 || shasum -a 256 "$RECENT_BACKUP" | cut -d' ' -f1)"
info "Sauvegarde vérifiée :"
info "  fichier   : $RECENT_BACKUP"
info "  taille    : ${BACKUP_SIZE} octets"
info "  sha256    : $BACKUP_CHECKSUM"
info "  horodatage: $(date -r "$RECENT_BACKUP" '+%Y-%m-%d %H:%M:%S' 2>/dev/null || stat -f '%Sm' "$RECENT_BACKUP")"
success "Sauvegarde vérifiée et exploitable"

# ── 8/16. Mode maintenance ────────────────────────────────────────────────────
info "8/16 Activation du mode maintenance..."
php artisan down --retry=60 --secret="a3erp-$(date +%s)" 2>/dev/null || true
success "Mode maintenance activé"

# ── 9/16. Checkout de la release approuvée (branche + SHA déjà vérifiés) ────
info "9/16 Mise à jour du code — $DEPLOY_BRANCH @ $EXPECTED_RELEASE_SHA..."
git reset --hard "$EXPECTED_RELEASE_SHA"
CURRENT_RELEASE="$(git rev-parse HEAD)"
[ "$CURRENT_RELEASE" = "$EXPECTED_RELEASE_SHA" ] || error "Checkout incohérent : HEAD=$CURRENT_RELEASE attendu $EXPECTED_RELEASE_SHA."
echo "$CURRENT_RELEASE" > storage/app/releases/CURRENT_RELEASE
success "Code mis à jour — $(git log --oneline -1)"

# ── 10/16. Composer ───────────────────────────────────────────────────────────
if [ "$SKIP_COMPOSER" = false ]; then
    info "10/16 Dépendances PHP (composer)..."
    # --classmap-authoritative évalué mais non activé par défaut : non testé
    # contre ce projet spécifique (packages avec autoload conditionnel type
    # laravel/pint, pest — risque de classe non trouvée en environnement dev
    # mélangé). À activer uniquement après un test dédié --no-dev complet.
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
    success "Composer OK"
else
    warn "10/16 Composer ignoré (--skip-composer)"
fi

# ── 11/16. Migration gate — inspection AVANT exécution ───────────────────────
info "11/16 Vérification des migrations en attente..."
php artisan migrate:status || warn "migrate:status a retourné un code d'erreur"
check_pending_migrations_allowlist "$KNOWN_PENDING_MIGRATIONS"

if php artisan a3:check-pending-migrations; then
    success "Aucune migration en attente — rien à appliquer"
else
    warn "Migration(s) en attente détectée(s) (liste ci-dessus)"
    require_migration_mode_if_pending "true" "${DEPLOY_MODE:-}" \
        || error "Migrations en attente mais DEPLOY_MODE != apply-migrations. Relire la liste ci-dessus puis relancer explicitement avec : DEPLOY_MODE=apply-migrations EXPECTED_RELEASE_SHA=$EXPECTED_RELEASE_SHA $0"
    info "DEPLOY_MODE=apply-migrations confirmé — application des migrations..."
    php artisan migrate --force || error "Migration échouée — restaurer depuis $RECENT_BACKUP (sha256:$BACKUP_CHECKSUM) si nécessaire. Voir deploy/ROLLBACK.md."
    php artisan migrate:status
    php artisan a3:check-pending-migrations || error "Post-check : migration(s) toujours en attente après migrate --force. deployment = FAILED."
    success "Migrations appliquées et vérifiées"
fi

# ── 12/16. NPM + Vite build ───────────────────────────────────────────────────
if [ "$SKIP_NPM" = false ]; then
    info "12/16 Assets frontend (npm + Vite)..."
    npm ci --prefer-offline
    npm run build
    require_build_manifest "public/build/manifest.json"
else
    warn "12/16 NPM ignoré (--skip-npm)"
    require_build_manifest "public/build/manifest.json"
fi

# ── 13/16. Caches Laravel ─────────────────────────────────────────────────────
info "13/16 Reconstruction des caches Laravel..."
php artisan cache:clear || error "cache:clear a échoué"
php artisan config:cache || error "config:cache a échoué"
php artisan route:cache || error "route:cache a échoué"
php artisan view:cache || error "view:cache a échoué"
php artisan event:cache || warn "event:cache a échoué (non bloquant — vérifier compatibilité si listeners à closures)"
success "Caches reconstruits"

# ── 14/16. Storage : répertoires + symlink + permissions ────────────────────
info "14/16 Storage, permissions et services..."
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
         storage/app/backups storage/app/private storage/logs bootstrap/cache
php artisan storage:link --force 2>/dev/null || true
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
sudo systemctl reload php8.2-fpm 2>/dev/null || sudo service php8.2-fpm reload 2>/dev/null || true
sudo supervisorctl restart "a3erp:*" 2>/dev/null || true
success "Storage, permissions et services OK"

# ── 15/16. Health check ───────────────────────────────────────────────────────
info "15/16 Health check post-déploiement..."
require_health_check_pass "$APP_DIR/deploy/health-check.sh"

# ── 16/16. Sortie du mode maintenance ─────────────────────────────────────────
info "16/16 Sortie du mode maintenance..."
php artisan up
success "Mode maintenance désactivé"

info "═══════════════════════════════════════"
success " Déploiement terminé ! $(date '+%H:%M:%S')"
info " Release : $CURRENT_RELEASE ($DEPLOY_BRANCH)"
info " URL : $(grep APP_URL .env | cut -d= -f2)"
info " Exécuter deploy/smoke-test.sh <url> puis le contrôle manuel des pages authentifiées (voir smoke-test.sh)."
info "═══════════════════════════════════════"
}

# Ne lance le pipeline que si le script est exécuté directement — un `source`
# (utilisé par les tests) ne déclenche que les définitions ci-dessus.
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
