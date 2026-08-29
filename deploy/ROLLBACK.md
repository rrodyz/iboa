# Procédure de rollback

Aucune automatisation aveugle (`git reset --hard` déclenché seul n'est
JAMAIS suffisant sur un serveur réel). Chaque étape ci-dessous exige une
décision humaine explicite.

## Prérequis

`deploy/deploy.sh` enregistre systématiquement, avant chaque déploiement :

- `storage/app/releases/PREVIOUS_RELEASE` — le hash de la release AVANT le déploiement en cours
- `storage/app/releases/CURRENT_RELEASE` — le hash de la release venant d'être déployée

Consulter ces deux fichiers pour identifier précisément où revenir :

```
cat storage/app/releases/PREVIOUS_RELEASE
cat storage/app/releases/CURRENT_RELEASE
```

## Décision

Un rollback se déclenche seulement après un déploiement qui a échoué de
façon visible (health-check.sh en FAIL, smoke test en échec, erreurs 500
en production) — jamais de manière préventive ou automatique.

## 1 — Code

```bash
php artisan down --retry=60
git fetch origin
git reset --hard "$(cat storage/app/releases/PREVIOUS_RELEASE)"
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
npm ci --prefer-offline && npm run build
```

## 2 — Base de données

**Ne jamais supposer qu'une migration peut être annulée sans risque**
(`migrate:rollback` peut échouer silencieusement ou perdre des données selon
la migration). Le backup complet pré-migration reste la seule protection
fiable :

```bash
# Récupérer le backup vérifié par deploy.sh juste avant la migration
# (voir la sortie de deploy.sh, section "Sauvegarde vérifiée" — fichier +
# sha256 affichés à l'écran et dans les logs du déploiement).
php artisan backup:list

# Restauration MANUELLE — jamais automatisée dans un script :
# 1. Vérifier le checksum sha256 de l'archive contre celui affiché au
#    moment du backup.
# 2. Décompresser et restaurer la base via le dump SQL inclus dans
#    l'archive spatie/laravel-backup (mysql < dump.sql sur une base
#    vidée au préalable — jamais sur la base en cours sans confirmation).
# 3. Ne restaurer storage/app QUE si des fichiers ont été perdus/corrompus
#    par le déploiement — pas systématiquement (risque d'écraser des
#    documents créés entre le backup et l'incident).
```

## 3 — Storage

Restaurer uniquement si nécessaire (voir ci-dessus), depuis la même
archive de sauvegarde.

## 4 — Caches et services

```bash
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
sudo systemctl reload php8.2-fpm
sudo supervisorctl restart "a3erp:*"
php artisan up
```

## 5 — Revalidation

```bash
./deploy/health-check.sh
./deploy/smoke-test.sh https://<votre-domaine>
```

Ne considérer le rollback terminé que si `health-check.sh` sort en PASS.
