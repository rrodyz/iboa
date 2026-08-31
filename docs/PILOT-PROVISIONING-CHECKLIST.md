# Checklist de provisionnement — serveur pilote A3 ERP

À exécuter dès qu'une machine cible réelle est désignée. Voir
[PILOT-SERVER-SPEC.md](PILOT-SERVER-SPEC.md) pour le détail justifié de
chaque exigence. Aucune valeur secrète dans ce document.

## 1 — Système

- [ ] OS provisionné (Linux Debian/Ubuntu LTS recommandé — voir
      `docs/PILOT-ENVIRONMENT.md` §B)
- [ ] Compte de service dédié non privilégié créé, propriétaire des fichiers
      de l'app (pas un compte interactif partagé)
- [ ] Firewall configuré (80/443 ouverts publiquement, 3306/6379 fermés au
      public — accès DB/Redis local ou via réseau privé uniquement)
- [ ] Chemin projet hors document root public (seul `public/` exposé au
      serveur HTTP — voir `deploy/nginx.conf` `root /var/www/iboa/public`)

## 2 — DNS / HTTPS

- [ ] Domaine ou sous-domaine dédié pointé vers le serveur
- [ ] Certificat HTTPS valide obtenu (Let's Encrypt) pour ce domaine
- [ ] `deploy/nginx.conf` copié et `<ERP_DOMAIN>` remplacé par le domaine réel
- [ ] Redirection HTTP→HTTPS vérifiée

## 3 — PHP

- [ ] PHP 8.2+ installé
- [ ] Extensions installées : `dom`, `fileinfo`, `filter`, `gd`, `hash`,
      `iconv`, `json`, `libxml`, `openssl`, `pcre`, `phar`, `reflection`,
      `session`, `simplexml`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`,
      `zip`, `zlib`, `mbstring` (natif), `pdo_mysql`, `mysqli`
- [ ] `php-fpm` configuré et démarré (socket attendu par
      `deploy/nginx.conf` : `/var/run/php/php8.2-fpm.sock`)
- [ ] `deploy/php-opcache.ini` appliqué

## 4 — Composer

- [ ] Composer 2.x installé
- [ ] `composer check-platform-reqs` passe contre `composer.lock` du projet

## 5 — Base de données

- [ ] MySQL 8 (ou MariaDB équivalent) installé, `utf8mb4`/`utf8mb4_unicode_ci`,
      `sql_mode` strict
- [ ] Base de données créée, utilisateur dédié avec privilèges limités au
      strict nécessaire (pas root applicatif)
- [ ] `mysqldump` et `mysql` (client CLI) installés — requis par
      `spatie/laravel-backup` et par la procédure de restauration manuelle
- [ ] Stratégie de données pilote tranchée par le commanditaire (base vide /
      copie assainie / copie réelle — voir PILOT-SERVER-SPEC.md, section
      "Stratégie de données pilote")

## 6 — Redis

- [ ] Redis installé et démarré
- [ ] Accessible uniquement en local ou réseau privé (pas exposé publiquement)

## 7 — Node / npm (si build sur cible)

- [ ] Node dans la plage `^20.19.0 || >=22.12.0` (contrainte Vite 7 réelle —
      voir PILOT-SERVER-SPEC.md)
- [ ] npm installé
- [ ] Alternative : livrer `public/build/` déjà compilé depuis un poste CI/dev
      et ne pas installer Node sur le serveur

## 8 — Supervisor (queue workers)

- [ ] Supervisor installé
- [ ] `deploy/supervisor.conf` copié dans `/etc/supervisor/conf.d/a3erp.conf`
- [ ] Chemins vérifiés/adaptés (`/home/votre-domaine.com/public_html/artisan`
      → chemin réel du déploiement)
- [ ] `supervisorctl reread && supervisorctl update` exécuté
- [ ] Workers démarrés et `supervisorctl status` vérifié (2 programmes :
      `a3erp-worker` ×2, `a3erp-pdf-worker` ×1)

## 9 — Cron

- [ ] `deploy/cron.txt` installé pour l'utilisateur de service
      (`crontab -e`), chemin `artisan` adapté
- [ ] Une seule entrée cron applicative confirmée (pas de doublon des tâches
      déjà gérées par `schedule:run`, voir `deploy/SCHEDULER-NOTES.md`)

## 10 — Git

- [ ] Dépôt cloné, remote `origin` configuré
- [ ] Accès en lecture à `origin/release/pilot-candidate` vérifié
- [ ] Clé/déploiement Git (deploy key ou équivalent) configurée avec accès
      minimal (lecture seule si possible)

## 11 — `.env`

Voir `.env.production.example` pour le fichier complet. Checklist de
présence des catégories, **aucune valeur secrète ici** :

- [ ] `APP_ENV`, `APP_KEY` (généré via `php artisan key:generate --force`),
      `APP_DEBUG=false`, `APP_URL` (domaine réel, pas `127.0.0.1`)
- [ ] `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`,
      `DB_PASSWORD`
- [ ] `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`,
      `REDIS_DB`, `REDIS_CACHE_DB`
- [ ] `QUEUE_CONNECTION=redis`
- [ ] `MAIL_MAILER` — recommandé `log` ou SMTP sandbox tant que les
      destinataires/données du pilote ne sont pas validés (voir PHASE 26)
- [ ] `BACKUP_ARCHIVE_PASSWORD`, `BACKUP_NOTIFICATION_EMAIL`,
      `BACKUP_REMOTE_ENABLED` (+ `AWS_*` si activé)
- [ ] `SECURITY_MAKER_CHECKER=true` (obligatoire — `audit:business` échoue
      en CRITIQUE sinon)

## 12 — Stockage / permissions

- [ ] `storage/`, `bootstrap/cache/` créés et inscriptibles par le compte de
      service
- [ ] `php artisan storage:link` exécuté

## 13 — Sauvegarde

- [ ] `php artisan backup:run` testé manuellement une première fois hors
      pipeline de déploiement
- [ ] `deploy/README-BACKUP-REMOTE.md` appliqué si sauvegarde distante
      souhaitée dès le pilote
- [ ] `deploy/ROLLBACK.md` lu et compris par l'opérateur qui exécutera le
      premier déploiement

## 14 — Déploiement

- [ ] `deploy/deploy.sh` rendu exécutable (`chmod +x`)
- [ ] Première exécution planifiée avec `EXPECTED_RELEASE_SHA` explicite et
      `DEPLOY_BRANCH=release/pilot-candidate` (valeur par défaut du script)
- [ ] `deploy/health-check.sh` et `deploy/smoke-test.sh` lus par l'opérateur

## 15 — Branch protection (GitHub)

- [ ] `release/pilot-candidate` protégée (force-push désactivé, suppression
      désactivée) — voir recommandation dans le rapport final de cette
      mission, non appliquée automatiquement
