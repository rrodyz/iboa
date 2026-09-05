# Sauvegarde hors-serveur — configuration et comportement

## Pourquoi

`storage/app/backups` (disque `backups`, local) reste un point de défaillance
unique : si le serveur est perdu (panne disque, incident hébergeur,
compromission), la sauvegarde disparaît avec les données qu'elle protège.

## Mécanisme

Réutilise le support **multi-disques natif** de `spatie/laravel-backup` —
aucun système de sauvegarde parallèle. `config/backup.php` écrit désormais
sur `['backups', <disque distant>]` quand `BACKUP_REMOTE_ENABLED=true`,
au lieu de `['backups']` seul. Même filtre appliqué à `monitor_backups`.

Le disque distant réutilise le disque `s3` déjà déclaré dans
`config/filesystems.php` (stub Laravel standard, jamais modifié) —
compatible avec tout stockage S3-compatible (AWS S3, OVH, Scaleway,
Backblaze B2...) via `AWS_ENDPOINT`.

## Activation

Dans `.env` (voir `.env.production.example`) :

```
BACKUP_REMOTE_ENABLED=true
BACKUP_REMOTE_DISK=s3
AWS_ACCESS_KEY_ID=<clé_access>
AWS_SECRET_ACCESS_KEY=<clé_secrète>
AWS_DEFAULT_REGION=<region>
AWS_BUCKET=<bucket>
AWS_ENDPOINT=<https://endpoint_s3_compatible>
AWS_USE_PATH_STYLE_ENDPOINT=true
```

Puis vérifier :

```
php artisan config:clear
php artisan backup:run
php artisan backup:list
```

`backup:list` doit montrer la sauvegarde présente sur les DEUX disques.

## Comportement en cas d'échec — matrice

| Local | Distant | Comportement attendu | Action requise |
|---|---|---|---|
| OK | OK | Sauvegarde exploitable | Aucune — cas nominal |
| OK | FAIL | `backup:run` peut sortir en succès partiel (le package Spatie ne fait pas échouer la commande si au moins un disque a réussi) — **mais `backup:monitor` doit le détecter** puisque `monitor_backups.disks` inclut désormais le disque distant, et lèvera `UnHealthyBackupWasFound` sur ce disque précis | **Alerte** (email via `BACKUP_NOTIFICATION_EMAIL`), pas un blocage immédiat du service applicatif — mais **doit bloquer toute migration** planifiée tant que non résolu (voir `deploy.sh`, la porte de migration exige une copie locale fraîche, indépendamment du distant) |
| FAIL | (n'importe) | Aucune sauvegarde exploitable | **Bloquant** — `deploy.sh` doit refuser toute migration (`NO VERIFIED BACKUP = NO DATABASE MIGRATION`) |

## Principe retenu pour le déploiement

La porte de migration (`deploy.sh`) ne vérifie **que la copie locale
récente** avant `migrate --force` — c'est la condition minimale suffisante
pour restaurer immédiatement en cas d'échec de migration. La copie distante
est une protection contre la perte du serveur, pas une condition bloquante
du déploiement lui-même (un déploiement ne doit pas échouer parce qu'un
opérateur S3 est temporairement indisponible, alors que la restauration
locale reste possible). Une copie distante manquante ou en échec reste
signalée par `backup:monitor` (email), à traiter séparément.

## Test de restauration

Aucune restauration automatique n'est scriptée ici (risque). Procédure
manuelle documentée dans `deploy/ROLLBACK.md`.

Toute restauration (y compris un test de restauration sur un serveur qui
sera remis en service) se termine par la séquence `cache:clear` →
`permission:cache-reset` → restart des workers → `a3:audit-security` avant
`php artisan up` (voir ROLLBACK.md §2 — [R1] cache Spatie obsolète après restore).
