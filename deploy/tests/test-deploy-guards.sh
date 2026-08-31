#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════════
# Tests statiques des garde-fous de deploy/deploy.sh (D01-D10)
# ═══════════════════════════════════════════════════════════════════════════════
# N'exécute JAMAIS deploy.sh en entier, ne touche à aucun serveur ni base de
# données réelle. Source deploy.sh (qui ne déclenche son pipeline que si
# BASH_SOURCE == $0, voir la fin du fichier) puis appelle chaque fonction de
# garde isolément, dans un sous-shell jetable, avec des fixtures locales.
#
# Usage : bash deploy/tests/test-deploy-guards.sh
# Exit 0 = tous les cas PASS, non-zero = au moins un FAIL.
# ═══════════════════════════════════════════════════════════════════════════════

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_SH="$SCRIPT_DIR/../deploy.sh"
TMP_ROOT="$(mktemp -d)"
trap 'rm -rf "$TMP_ROOT"' EXIT

PASS=0
FAIL=0
GREEN='\033[0;32m'; RED='\033[0;31m'; NC='\033[0m'

# expect_block CASE_ID DESCRIPTION -- COMMAND...
# Lance COMMAND dans un sous-shell isolé ; réussit si COMMAND sort en non-zero.
expect_block() {
    local id="$1" desc="$2"; shift 2
    if ( "$@" >"$TMP_ROOT/out.$id" 2>&1 ); then
        echo -e "${RED}[FAIL]${NC} $id — $desc (attendu : BLOCK, obtenu : exit 0)"
        FAIL=$((FAIL+1))
    else
        echo -e "${GREEN}[PASS]${NC} $id — $desc (BLOCK confirmé)"
        PASS=$((PASS+1))
    fi
}

# expect_pass CASE_ID DESCRIPTION -- COMMAND...
expect_pass() {
    local id="$1" desc="$2"; shift 2
    if ( "$@" >"$TMP_ROOT/out.$id" 2>&1 ); then
        echo -e "${GREEN}[PASS]${NC} $id — $desc (pass confirmé)"
        PASS=$((PASS+1))
    else
        echo -e "${RED}[FAIL]${NC} $id — $desc (attendu : pass, obtenu : BLOCK)"
        cat "$TMP_ROOT/out.$id" >&2
        FAIL=$((FAIL+1))
    fi
}

# Source deploy.sh dans le sous-shell courant (fonctions uniquement — le
# garde BASH_SOURCE==\$0 empêche main() de se lancer ici).
src() { source "$DEPLOY_SH"; }

echo "═══════════════════════════════════════"
echo " Tests garde-fous deploy.sh (D01-D10)"
echo "═══════════════════════════════════════"

# ── D01 — mauvaise branche → BLOCK ──────────────────────────────────────────
expect_block D01 "validate_branch refuse 'main'" \
    bash -c "source '$DEPLOY_SH'; validate_branch main"
expect_block D01b "validate_branch refuse 'integration/react-production-wave1'" \
    bash -c "source '$DEPLOY_SH'; validate_branch integration/react-production-wave1"

# ── D02 — EXPECTED_RELEASE_SHA manquant → BLOCK ─────────────────────────────
expect_block D02 "require_expected_sha refuse une valeur vide" \
    bash -c "source '$DEPLOY_SH'; require_expected_sha ''"
expect_block D02b "require_expected_sha refuse un SHA mal formé" \
    bash -c "source '$DEPLOY_SH'; require_expected_sha 'e81a964'"

# ── D03 — SHA mismatch → BLOCK ──────────────────────────────────────────────
SHA_A="e81a964beff116670e1365cd086f924c2d856018"
SHA_B="9befa7c13cafb4244ccf1b4d2bfaf0a39e601fe5"
expect_block D03 "compare_expected_sha bloque sur mismatch" \
    bash -c "source '$DEPLOY_SH'; compare_expected_sha '$SHA_A' '$SHA_B'"

# ── D04 — worktree sale → BLOCK ─────────────────────────────────────────────
DIRTY_REPO="$TMP_ROOT/dirty-repo"
mkdir -p "$DIRTY_REPO"
( cd "$DIRTY_REPO" && git init -q && git config user.email t@t.io && git config user.name t \
  && echo x > tracked.txt && git add tracked.txt && git commit -q -m init \
  && echo dirty >> tracked.txt )
expect_block D04 "check_dirty_tree bloque sur modification non commitée" \
    bash -c "source '$DEPLOY_SH'; cd '$DIRTY_REPO'; check_dirty_tree"

CLEAN_REPO="$TMP_ROOT/clean-repo"
mkdir -p "$CLEAN_REPO"
( cd "$CLEAN_REPO" && git init -q && git config user.email t@t.io && git config user.name t \
  && echo x > tracked.txt && git add tracked.txt && git commit -q -m init )
expect_pass D04b "check_dirty_tree passe sur arbre propre" \
    bash -c "source '$DEPLOY_SH'; cd '$CLEAN_REPO'; check_dirty_tree"

# ── D05 — sauvegarde absente → détectée comme absente ───────────────────────
EMPTY_BACKUPS="$TMP_ROOT/empty-backups"
mkdir -p "$EMPTY_BACKUPS"
expect_block D05 "find_recent_backup ne trouve rien dans un dossier vide" \
    bash -c "source '$DEPLOY_SH'; out=\$(find_recent_backup '$EMPTY_BACKUPS'); [ -n \"\$out\" ]"

# ── D06 — sauvegarde périmée (>15min) → traitée comme absente ──────────────
STALE_BACKUPS="$TMP_ROOT/stale-backups"
mkdir -p "$STALE_BACKUPS"
dd if=/dev/zero of="$STALE_BACKUPS/old.zip" bs=1024 count=10 status=none
touch -d '-1 hour' "$STALE_BACKUPS/old.zip" 2>/dev/null || touch -t "$(date -d '-1 hour' +%Y%m%d%H%M 2>/dev/null || date -v-1H +%Y%m%d%H%M)" "$STALE_BACKUPS/old.zip" 2>/dev/null || true
expect_block D06 "find_recent_backup ignore une archive de plus de 15 minutes" \
    bash -c "source '$DEPLOY_SH'; out=\$(find_recent_backup '$STALE_BACKUPS'); [ -n \"\$out\" ]"

# ── D06b — sauvegarde fraîche mais anormalement petite → BLOCK ─────────────
TINY_BACKUP="$TMP_ROOT/tiny.zip"
echo -n "x" > "$TINY_BACKUP"
expect_block D06b "verify_backup_size refuse une archive < 1024 octets" \
    bash -c "source '$DEPLOY_SH'; verify_backup_size '$TINY_BACKUP'"

BIG_BACKUP="$TMP_ROOT/big.zip"
dd if=/dev/zero of="$BIG_BACKUP" bs=1024 count=10 status=none
expect_pass D06c "verify_backup_size accepte une archive > 1024 octets" \
    bash -c "source '$DEPLOY_SH'; verify_backup_size '$BIG_BACKUP'"

# ── D07 — mode migration absent → pas de migration ─────────────────────────
expect_block D07 "require_migration_mode_if_pending bloque sans DEPLOY_MODE=apply-migrations" \
    bash -c "source '$DEPLOY_SH'; require_migration_mode_if_pending true ''"
expect_pass D07b "require_migration_mode_if_pending passe avec DEPLOY_MODE=apply-migrations" \
    bash -c "source '$DEPLOY_SH'; require_migration_mode_if_pending true apply-migrations"
expect_pass D07c "require_migration_mode_if_pending passe si rien n'est pending" \
    bash -c "source '$DEPLOY_SH'; require_migration_mode_if_pending false ''"

# ── D08 — branche + SHA corrects → passe le préflight ───────────────────────
expect_pass D08 "validate_branch accepte release/pilot-candidate" \
    bash -c "source '$DEPLOY_SH'; validate_branch release/pilot-candidate"
expect_pass D08b "require_expected_sha + compare_expected_sha acceptent un SHA valide identique" \
    bash -c "source '$DEPLOY_SH'; require_expected_sha '$SHA_A' && compare_expected_sha '$SHA_A' '$SHA_A'"

# ── D09 — manifest de build absent → BLOCK ──────────────────────────────────
expect_block D09 "require_build_manifest bloque si manifest.json absent" \
    bash -c "source '$DEPLOY_SH'; require_build_manifest '$TMP_ROOT/does-not-exist/manifest.json'"
MANIFEST_DIR="$TMP_ROOT/build-ok"; mkdir -p "$MANIFEST_DIR"; echo '{}' > "$MANIFEST_DIR/manifest.json"
expect_pass D09b "require_build_manifest passe si manifest.json présent" \
    bash -c "source '$DEPLOY_SH'; require_build_manifest '$MANIFEST_DIR/manifest.json'"

# ── D10 — health check en échec → BLOCK ─────────────────────────────────────
FAILING_HC="$TMP_ROOT/health-check-fail.sh"
printf '#!/usr/bin/env bash\nexit 1\n' > "$FAILING_HC"; chmod +x "$FAILING_HC"
expect_block D10 "require_health_check_pass bloque si health-check.sh échoue" \
    bash -c "source '$DEPLOY_SH'; require_health_check_pass '$FAILING_HC'"

PASSING_HC="$TMP_ROOT/health-check-pass.sh"
printf '#!/usr/bin/env bash\nexit 0\n' > "$PASSING_HC"; chmod +x "$PASSING_HC"
expect_pass D10b "require_health_check_pass passe si health-check.sh réussit" \
    bash -c "source '$DEPLOY_SH'; require_health_check_pass '$PASSING_HC'"

echo "═══════════════════════════════════════"
echo "Résultat : $PASS passed, $FAIL failed"
if [ "$FAIL" -eq 0 ]; then
    echo -e "${GREEN}DEPLOY GUARDS TESTS : PASS${NC}"
    exit 0
else
    echo -e "${RED}DEPLOY GUARDS TESTS : FAIL${NC}"
    exit 1
fi
