# Spécification serveur pilote A3 ERP

[PILOT-SERVER-PROVISIONING] Aucun serveur n'existe encore (confirmé par
[docs/PILOT-ENVIRONMENT.md](PILOT-ENVIRONMENT.md), 2026-08-28). Ce document
sert de spécification exploitable pour provisionner la machine cible —
chaque valeur est justifiée par un besoin réel du projet, pas choisie au
hasard.

## Services requis

| Service | Requis ? | Pourquoi |
|---|---|---|
| PHP 8.2+ | OUI | `composer.json` : `"php": "^8.2"` |
| Extensions PHP | OUI (liste précise ci-dessous) | dérivées de `composer.lock` via `composer check-platform-reqs`, pas d'une checklist Laravel générique |
| MySQL 8 / MariaDB équivalent | OUI | `.env.production.example` : `DB_CONNECTION=mysql` ; migrations testées sous MySQL 8.4.3 |
| mysqldump / mysql (client CLI) | OUI | utilisé en interne par `spatie/laravel-backup` (`Spatie\DbDumper\Databases\MySql`) pour `backup:run`, et par la procédure de restauration manuelle (`deploy/ROLLBACK.md`) |
| Redis | OUI si le pilote doit être fonctionnel | `.env.production.example` fixe `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis` — sans Redis, les 5 `Jobs` (PDF, email facture, relance, SMS, paiement externe) ne s'exécutent jamais |
| Supervisor (ou équivalent systemd) | OUI si Redis/queue actif | `deploy/supervisor.conf` définit 2 workers (`a3erp-worker` défaut, `a3erp-pdf-worker` dédié PDF) |
| Cron | OUI | 12 tâches planifiées dans `routes/console.php` (backup, audits, factures, alertes — voir `deploy/cron.txt`), déclenchées par une seule entrée `* * * * * php artisan schedule:run` |
| Node + npm | OPTIONNEL sur le serveur | uniquement si le build (`npm run build`) est fait sur cible ; sinon livrer `public/build/` déjà compilé (voir `docs/PILOT-ENVIRONMENT.md` §B) |
| Composer 2 | OUI | déploiement des dépendances PHP (`composer install --no-dev`) |
| HTTPS (Let's Encrypt suffisant) | OUI avant tout accès utilisateur réel | `.env.production.example` : `SESSION_SECURE_COOKIE=true`, HSTS déjà codé dans `deploy/nginx.conf` — cassent les connexions sans certificat valide |

## Extensions PHP réellement requises

Confirmées par `composer check-platform-reqs` sur `composer.lock` (pas une
liste générique) : `ext-dom`, `ext-fileinfo`, `ext-filter`, `ext-gd`,
`ext-hash`, `ext-iconv`, `ext-json`, `ext-libxml`, `ext-openssl`, `ext-pcre`,
`ext-phar`, `ext-reflection`, `ext-session`, `ext-simplexml`,
`ext-tokenizer`, `ext-xml`, `ext-xmlreader`, `ext-xmlwriter`, `ext-zip`,
`ext-zlib`. `ext-ctype`/`ext-mbstring` sont satisfaites par des polyfills
Symfony dans `composer.lock` mais les vraies extensions natives sont
recommandées en production (perf). `ext-pdo`/`ext-mysqli`/`ext-pdo_mysql`
non listées par Composer (pas une dépendance déclarée du projet) mais
requises par Laravel pour la connexion MySQL — à vérifier explicitement au
provisioning (`deploy/deploy.sh` → `preflight_php()` appelle
`composer check-platform-reqs`, qui ne couvre pas les extensions runtime
hors composer.lock ; le health check vérifie la connexion DB séparément).

## Node — contrainte réelle, pas un choix arbitraire

`node_modules/vite/package.json` → `"engines": { "node": "^20.19.0 || >=22.12.0" }`
(Vite 7.0.7, utilisé par ce projet). C'est la contrainte la plus stricte de
la chaîne frontend (`@vitejs/plugin-react` exige seulement `^14.18.0 || >=16.0.0`,
React exige `>=0.10.0`). Le poste de développement utilise Node v22.16.0
(satisfait `>=22.12.0`). `package.json` n'a pas de champ `engines` — c'est
maintenant vérifié par `deploy/deploy.sh` → `preflight_node()` au lieu d'être
supposé. Node 21.x est explicitement hors de cette plage (ni `^20.19.0` ni
`>=22.12.0`) — refusé par le préflight s'il était rencontré.

## Dimensionnement

Le pilote n'est pas la production finale (charge attendue : quelques
utilisateurs, données réelles limitées). Deux profils :

| Ressource | MINIMUM PILOTE | RECOMMANDÉ PILOTE | Justification |
|---|---|---|---|
| CPU | 2 vCPU | 4 vCPU | PHP-FPM + MySQL + Redis + 2 workers queue en même temps ; la génération PDF (dompdf, `a3erp-pdf-worker` dédié) est CPU-bound par pics |
| RAM | 2 Go | 4 Go | `docs/PILOT-ENVIRONMENT.md` §B recommande déjà 4 Go minimum ; en dessous, le poste de dev a montré des symptômes de tension mémoire avec un usage bien plus lourd (plusieurs worktrees + suites de test en parallèle, non représentatif d'un pilote, mais 2 Go est un plancher risqué avec MySQL + Redis + PHP-FPM + 2 workers simultanés) |
| Stockage | 20 Go | 40 Go+ | code + `vendor/` + `node_modules/` (si build sur cible) + rétention sauvegardes (`config/backup.php` : nettoyage jusqu'à 2 ans en cumul mensuel/annuel) — prévoir large marge dès le pilote pour ne pas avoir à redimensionner sous charge |

Pas de surdimensionnement arbitraire : les colonnes MINIMUM/RECOMMANDÉ
correspondent aux services listés ci-dessus, pas à une marge de sécurité
générique.

## Architecture base de données

Recommandation, non tranchée ici (aucune décision métier prise) :

- **DB locale au serveur pilote** — plus simple à opérer pour un premier
  pilote limité, latence minimale, mais couplée à la disponibilité du
  serveur (mitigé par `deploy/README-BACKUP-REMOTE.md` : sauvegarde
  seconde destination S3-compatible).
- **DB managée séparée** — meilleure résilience/isolation, complexité et
  coût plus élevés, pertinent surtout à partir de la production réelle.

Pour un premier pilote : DB locale au serveur + sauvegarde distante activée
(`BACKUP_REMOTE_ENABLED=true`) est un compromis raisonnable. Décision finale
laissée à l'opérateur/au commanditaire.

## Domaine et HTTPS

Aucun domaine réel n'existe. Besoin exprimé : un sous-domaine ou domaine
dédié (`pilot.example`, illustratif — aucun domaine réel inventé ici),
`APP_URL` doit être l'URL réelle (jamais `127.0.0.1`). Certificat HTTPS
valide (Let's Encrypt suffit) obligatoire avant tout accès utilisateur réel
— voir `.env.production.example` (`SESSION_SECURE_COOKIE=true`) et
`deploy/nginx.conf` (HSTS, redirection HTTP→HTTPS).

```
PILOT DNS:
TO PROVISION
```

## Stratégie de données pilote

Décision métier non prise dans cette mission :

```
PILOT DATA STRATEGY:
TO DECIDE
```

Options : base vide, copie assainie du dev, copie opérationnelle réelle.
Chaque option change le profil de risque (RGPD/confidentialité pour une
copie réelle, réalisme réduit pour une base vide) — à trancher par le
commanditaire, pas par ce script ni cette mission.
