# Skill : erp-auditor

Auditeur général de l'ERP IBOA. Ce skill déclenche une inspection complète et
systématique du projet avant livraison, mise en production ou sprint review.

## Contexte projet

- **Framework** : Laravel 12 / PHP 8.2
- **Base** : MySQL 8 via Laragon (`iboa_erp`)
- **Chemin local** : `C:/laragon/www/iboa`
- **URL locale** : `http://127.0.0.1/iboa/public`
- **Repo** : `rrodyz/iboa` (branch `main`)
- **Modules** : Ventes · Achats · Stock · Comptabilité SYSCOHADA · RH/Paie · Trésorerie · CRM

## Ce que fait ce skill

Lance un audit en 8 axes. Pour chaque axe, produit un tableau `✅ OK | ⚠️ Warning | ❌ Bloquant`.

### Axe 1 — Schéma base de données
```bash
php check_tables.php          # tables vs modèles
php artisan migrate:status    # migrations fantômes / en attente
```
- Toutes les tables des modèles existent ?
- Migrations fantômes (DB mais pas sur disque) ?
- Colonnes `$fillable` présentes physiquement ?

### Axe 2 — Routes & contrôleurs
```bash
php artisan route:list --json
```
- Routes sans middleware `auth` ou `permission` exposées ?
- Contrôleurs référencés mais absents ?
- Routes dupliquées ?

### Axe 3 — Permissions Spatie
```bash
php artisan tinker --execute="echo \App\Models\Permission::count();"
```
- Chaque route protégée a-t-elle une permission déclarée ?
- `authorize()` ou `middleware('permission:x')` présent sur chaque groupe sensible ?

### Axe 4 — Qualité du code
```bash
find app -name "*.php" | xargs grep -l "dd(\|dump(\|var_dump("
grep -r "TODO\|FIXME\|HACK\|XXX" app --include="*.php"
grep -r "Company::firstOrFail\|Company::first()" app --include="*.php"
```
- Résidus de debug (`dd`, `dump`) ?
- TODO/FIXME non résolus ?
- Appels directs `Company::firstOrFail()` à remplacer par `currentCompany()` ?

### Axe 5 — Performances
```bash
php artisan route:cache && php artisan config:cache && php artisan view:cache
```
- Caches applicatifs actifs ?
- Requêtes N+1 détectables statiquement dans les contrôleurs ?
- Index manquants sur colonnes `WHERE company_id`, `status`, `created_at` ?

### Axe 6 — Sécurité
- `.env` versionné dans git ? (`git log --all -- .env`)
- `APP_DEBUG=false` en production ?
- `APP_ENV=production` ?
- Headers de sécurité via SecurityHeaders middleware ?

### Axe 7 — Assets & front-end
```bash
ls public/hot          # Vite dev server actif = ❌ production
ls public/build        # build existe = ✅
```
- Fichier `public/hot` absent (Vite dev server off) ?
- `public/build/manifest.json` présent ?

### Axe 8 — Tests & CI
```bash
php artisan test --stop-on-failure
```
- Tests passent ?
- Couverture minimale des cas critiques (paiement, stock, paie) ?

## Format de réponse

```
## Rapport d'audit ERP IBOA — {date}

| Axe | Statut | Détail |
|-----|--------|--------|
| Schéma DB | ✅/⚠️/❌ | ... |
| Routes | ✅/⚠️/❌ | ... |
...

### Actions requises (par priorité)
1. ❌ [BLOQUANT] ...
2. ⚠️ [IMPORTANT] ...
3. ℹ️ [MINEUR] ...
```

## Règles d'exécution

- Toujours vérifier via les outils (Bash, Read, Grep) — ne jamais répondre de mémoire.
- Si un axe génère une erreur PHP, noter l'erreur et continuer les autres axes.
- Proposer les corrections après le rapport complet, pas pendant.
