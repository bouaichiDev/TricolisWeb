# Déploiement VPS de Tricolis

Ce dépôt contient deux applications déployées ensemble :

- `frontend/` (React/Vite) vers `https://tricolis.bouaichibadr.com` ;
- Laravel (racine du dépôt) vers `https://tricolisba.bouaichibadr.com`.

Le workflow `.github/workflows/deploy-production.yml` construit le frontend,
synchronise les deux applications puis exécute uniquement les migrations qui
n'ont pas encore été appliquées. Le fichier `.env` et le dossier `storage` du
backend restent sur le VPS et ne sont jamais écrasés par GitHub Actions.

## 1. Préparer MySQL

Générer un mot de passe fort :

```bash
openssl rand -hex 24
```

Ouvrir MySQL ou MariaDB :

```bash
sudo mysql
```

Remplacer `MOT_DE_PASSE_FORT` puis exécuter :

```sql
CREATE DATABASE `tricolis`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'tricolis_user'@'127.0.0.1'
    IDENTIFIED BY 'MOT_DE_PASSE_FORT';

GRANT ALL PRIVILEGES ON `tricolis`.*
    TO 'tricolis_user'@'127.0.0.1';

FLUSH PRIVILEGES;
EXIT;
```

## 2. Préparer les dossiers et l'environnement Laravel

```bash
sudo mkdir -p /var/www/tricolis /var/www/tricolisba
sudo chown -R badradmin:www-data /var/www/tricolis /var/www/tricolisba
sudo chmod 2755 /var/www/tricolis /var/www/tricolisba

mkdir -p /var/www/tricolisba/storage/framework/{cache,sessions,views}
mkdir -p /var/www/tricolisba/storage/logs
mkdir -p /var/www/tricolisba/bootstrap/cache
chmod -R ug+rwX /var/www/tricolisba/storage /var/www/tricolisba/bootstrap/cache
```

Créer `/var/www/tricolisba/.env` avec au minimum :

```dotenv
APP_NAME="Tricolis Web"
APP_ENV=production
APP_KEY=base64:REMPLACER_PAR_UNE_CLE
APP_DEBUG=false
APP_URL=https://tricolisba.bouaichibadr.com

APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_FAKER_LOCALE=fr_FR

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tricolis
DB_USERNAME=tricolis_user
DB_PASSWORD=MOT_DE_PASSE_FORT

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@bouaichibadr.com"
MAIL_FROM_NAME="${APP_NAME}"

LOG_VIEWER_ENABLED=false
LOG_VIEWER_ALLOWED_EMAILS=
```

Pour générer `APP_KEY` sans le projet déjà installé :

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

## 3. Configurer Nginx

Frontend, fichier `/etc/nginx/sites-available/tricolis.bouaichibadr.com` :

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name tricolis.bouaichibadr.com;

    root /var/www/tricolis;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~* \.(?:css|js|jpg|jpeg|png|gif|svg|webp|ico|woff2?)$ {
        expires 7d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
}
```

Backend, fichier `/etc/nginx/sites-available/tricolisba.bouaichibadr.com` :

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name tricolisba.bouaichibadr.com;

    root /var/www/tricolisba/public;
    index index.php;
    charset utf-8;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ ^/index\.php(/|$) {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ \.php$ {
        return 404;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Activer les deux sites :

```bash
sudo ln -s /etc/nginx/sites-available/tricolis.bouaichibadr.com /etc/nginx/sites-enabled/tricolis.bouaichibadr.com
sudo ln -s /etc/nginx/sites-available/tricolisba.bouaichibadr.com /etc/nginx/sites-enabled/tricolisba.bouaichibadr.com
sudo nginx -t
sudo systemctl reload nginx
```

Si un lien existe déjà, ne pas le recréer. Vérifier avec :

```bash
ls -l /etc/nginx/sites-enabled/
```

## 4. Configurer GitHub Actions

Dans `Settings > Secrets and variables > Actions`, ajouter :

| Secret | Valeur |
|---|---|
| `VPS_HOST` | IP publique du VPS |
| `VPS_USER` | `badradmin` |
| `VPS_PORT` | `22`, sauf port SSH personnalisé |
| `VPS_SSH_KEY` | clé SSH privée dédiée au déploiement |
| `VPS_KNOWN_HOSTS` | empreinte SSH vérifiée du VPS |

La clé publique associée à `VPS_SSH_KEY` doit être présente dans
`/home/badradmin/.ssh/authorized_keys`.

Pour préparer `VPS_KNOWN_HOSTS`, exécuter depuis une machine fiable :

```bash
ssh-keyscan -p 22 -H IP_DU_VPS
```

Comparer l'empreinte obtenue avec `ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub`
sur le VPS avant de l'ajouter au secret.

Une fusion dans `main` déclenche ensuite le déploiement. Il est aussi possible
de le lancer manuellement dans l'onglet `Actions`.

### Seeders en production

Les seeders ne sont pas exécutés automatiquement pendant un déploiement. Laravel
mémorise les migrations appliquées dans sa table `migrations`, mais ne possède
pas de mécanisme équivalent pour mémoriser les seeders déjà exécutés.

Lors de la création initiale d'une base vide, exécuter une seule fois :

```bash
cd /var/www/tricolisba
php artisan db:seed --class=ProductionSeeder --force
```

Pour de nouvelles données obligatoires en production, préférer une migration de
données : elle ne sera exécutée qu'une fois. Si un nouveau seeder indépendant
est créé, l'exécuter explicitement une seule fois :

```bash
php artisan db:seed --class=NomDuNouveauSeeder --force
```

Ne jamais relancer `ProductionSeeder` sur une base déjà initialisée sans avoir
vérifié que tous ses seeders sont idempotents.

## 5. Installer le worker et le scheduler

La file utilise la base de données. Installer Supervisor :

```bash
sudo apt update
sudo apt install -y supervisor
sudo nano /etc/supervisor/conf.d/tricolis-worker.conf
```

Configuration :

```ini
[program:tricolis-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/tricolisba/artisan queue:work --sleep=3 --tries=3 --timeout=120
directory=/var/www/tricolisba
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=badradmin
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/tricolisba/storage/logs/worker.log
stopwaitsecs=3600
```

Activer le worker :

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status tricolis-worker:*
```

Ajouter au `crontab -e` de `badradmin` :

```cron
* * * * * cd /var/www/tricolisba && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

## 6. DNS et HTTPS

Dans Cloudflare, les deux enregistrements `A` pointent vers la même IP du VPS :

- `tricolis` ;
- `tricolisba`.

Les laisser temporairement en `DNS only`, puis installer les certificats :

```bash
sudo certbot --nginx \
  -d tricolis.bouaichibadr.com \
  -d tricolisba.bouaichibadr.com
```

Après vérification HTTPS, remettre les deux enregistrements en `Proxied` et
utiliser le mode Cloudflare `Full (strict)`.

## 7. Vérifications

```bash
curl -I https://tricolis.bouaichibadr.com
curl -I https://tricolisba.bouaichibadr.com/up
curl -I https://tricolisba.bouaichibadr.com/docs/api

cd /var/www/tricolisba
php artisan migrate:status
php artisan schedule:list
php artisan about --only=environment
```

Ne pas exécuter `php artisan migrate:fresh --seed` en production : le seeder
général contient volontairement des comptes et données de démonstration.
