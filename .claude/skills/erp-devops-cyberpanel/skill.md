# Skill : erp-devops-cyberpanel

Expert déploiement et administration de l'ERP IBOA sur serveur Linux avec CyberPanel.
Intervient sur : déploiement, mise à jour, sauvegardes, SSL, DNS, performances serveur,
monitoring, sécurité OS.

## Stack serveur cible

- **Panel** : CyberPanel (OpenLiteSpeed)
- **OS** : Ubuntu 22.04 LTS ou AlmaLinux 8+
- **Web server** : OpenLiteSpeed (config via CyberPanel)
- **PHP** : PHP 8.2 (via LSPHP 8.2)
- **DB** : MySQL 8.0
- **Cache** : Redis (optionnel, recommandé pour sessions + cache Laravel)
- **Domaine** : à configurer (ex: erp.oametal.bf)

## Structure des répertoires (CyberPanel)

```
/home/{domain}/
├── public_html/          ← Racine web (= public/ de Laravel)
├── {domain}/             ← Fichiers Laravel HORS du web root
│   ├── app/
│   ├── config/
│   ├── .env
│   └── ...
└── logs/
```

> **Sécurité** : Les fichiers Laravel (`.env`, `app/`, `config/`) doivent être
> **hors** du `public_html`. Seul `public/` est exposé.

## Déploiement initial

### 1. Créer le site dans CyberPanel
```
CyberPanel → Websites → Create Website
- Domain: erp.oametal.bf
- PHP: LSPHP 8.2
- SSL: Let's Encrypt (après DNS)
```

### 2. Cloner le repo
```bash
cd /home/erp.oametal.bf/
git clone https://github.com/rrodyz/iboa.git app
```

### 3. Configurer public_html → public/
```bash
# Option 1 : Symlink
rm -rf public_html
ln -s /home/erp.oametal.bf/app/public /home/erp.oametal.bf/public_html

# Option 2 : Modifier le document root dans CyberPanel
# Websites → List Websites → Manage → vHost Conf → DocumentRoot
```

### 4. Permissions
```bash
chown -R nobody:nobody /home/erp.oametal.bf/app
chmod -R 755 /home/erp.oametal.bf/app
chmod -R 775 /home/erp.oametal.bf/app/storage
chmod -R 775 /home/erp.oametal.bf/app/bootstrap/cache
```

### 5. Installer les dépendances
```bash
cd /home/erp.oametal.bf/app
composer install --optimize-autoloader --no-dev
cp .env.example .env
# Éditer .env avec les vraies valeurs
php artisan key:generate
```

### 6. Base de données
```bash
# Créer la DB dans CyberPanel → Databases → Create Database
# Puis :
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder  # si existant
```

### 7. Optimisations production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
npm run build  # ou copier public/build depuis local
```

## Mise à jour (git pull)

```bash
cd /home/erp.oametal.bf/app

# 1. Mettre en maintenance
php artisan down --retry=60 --secret="token-secret"

# 2. Pull
git pull origin main

# 3. Dépendances
composer install --optimize-autoloader --no-dev

# 4. Migrations
php artisan migrate --force

# 5. Reconstruire caches
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Revenir en ligne
php artisan up
```

## Configuration `.env` production

```dotenv
APP_NAME="IBOA ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.oametal.bf

APP_TIMEZONE=Africa/Ouagadougou
APP_LOCALE=fr

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iboa_erp
DB_USERNAME=iboa_user
DB_PASSWORD=MotDePasseForte123!

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.oametal.bf
MAIL_PORT=587
MAIL_USERNAME=erp@oametal.bf
MAIL_PASSWORD=xxxxxx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=erp@oametal.bf
MAIL_FROM_NAME="IBOA ERP"

LOG_CHANNEL=daily
LOG_LEVEL=error
```

## Configuration OpenLiteSpeed (CyberPanel)

### Rewrite rules (dans vHost Config)
```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

### PHP ini recommandés (via CyberPanel → PHP → Edit PHP Configs)
```ini
upload_max_filesize = 20M
post_max_size = 20M
max_execution_time = 120
memory_limit = 256M
```

## Sauvegardes automatiques

### Backup DB quotidien (cron)
```bash
# Dans CyberPanel → Cron Jobs ou crontab -e
0 2 * * * mysqldump -u iboa_user -pMotDePasseForte123! iboa_erp | gzip > /backup/iboa_$(date +\%Y\%m\%d).sql.gz
# Garder 30 jours
find /backup -name "iboa_*.sql.gz" -mtime +30 -delete
```

### Backup fichiers (hebdomadaire)
```bash
0 3 * * 0 tar -czf /backup/iboa_files_$(date +\%Y\%m\%d).tar.gz /home/erp.oametal.bf/app
```

### Backup CyberPanel natif
```
CyberPanel → Backup → Create Backup
```

## SSL et HTTPS

```bash
# Let's Encrypt via CyberPanel
CyberPanel → SSL → Issue SSL → Let's Encrypt

# Vérifier expiration
certbot certificates

# Forcer HTTPS dans .htaccess ou rewrite OLS
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

## Monitoring

### Vérification rapide
```bash
# Espace disque
df -h

# Mémoire
free -h

# Charge CPU
top -bn1 | head -20

# Logs erreurs Laravel
tail -100 /home/erp.oametal.bf/app/storage/logs/laravel.log | grep -E "ERROR|CRITICAL"

# Logs OLS
tail -100 /usr/local/lsws/logs/error.log
```

### Workers de queue (si utilisé)
```bash
# Démarrer le worker
php artisan queue:work --daemon --sleep=3 --tries=3 &

# Via Supervisor (recommandé)
# /etc/supervisor/conf.d/iboa-worker.conf
[program:iboa-worker]
command=php /home/erp.oametal.bf/app/artisan queue:work --sleep=3 --tries=3
user=nobody
autostart=true
autorestart=true
```

## Checklist déploiement

```
□ DNS A record configuré et propagé
□ SSL Let's Encrypt actif (HTTPS)
□ public_html → public/ symlink ou document root configuré
□ .env production complet (pas de APP_DEBUG=true)
□ php artisan key:generate exécuté
□ Migrations exécutées avec --force
□ Caches Laravel compilés (config, route, view)
□ Composer --no-dev --optimize-autoloader
□ public/hot absent (Vite dev server off)
□ Permissions storage/ et bootstrap/cache/ à 775
□ Backup DB configuré
□ Log rotation configuré
□ Monitoring uptime (UptimeRobot, etc.)
```

## Commandes de diagnostic rapide

```bash
# Santé de l'app
curl -I https://erp.oametal.bf/up

# Vérifier la config Laravel
php artisan about

# Tester la connexion DB
php artisan tinker --execute="DB::select('SELECT 1');"

# Vérifier les permissions
ls -la storage/ bootstrap/cache/
```
