# Deploy (Ubuntu + Nginx + PHP-FPM + Supervisor)

Инструкция для VPS/облака (не shared-hosting).  
Для `shared hosting` IPTV-транскодирование может не работать из-за ограничений на фоновые процессы.

## 1. Подготовка сервера

```bash
sudo apt update
sudo apt install -y nginx mysql-server ffmpeg unzip git curl supervisor software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl
```

Установите Composer:

```bash
cd /tmp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
```

## 2. Код и зависимости

```bash
sudo mkdir -p /var/www/social-network
sudo chown -R $USER:$USER /var/www/social-network
cd /var/www/social-network

git clone <URL_ВАШЕГО_РЕПО> .
composer install --no-dev --optimize-autoloader
```

Сборка фронта (если на сервере есть Node):

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
npm ci
npm run build
```

Если билдите локально/в CI, просто загрузите `public/build`.

## 3. База данных

```bash
sudo mysql -e "CREATE DATABASE social_network CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'social'@'localhost' IDENTIFIED BY 'strong_password_here';"
sudo mysql -e "GRANT ALL PRIVILEGES ON social_network.* TO 'social'@'localhost'; FLUSH PRIVILEGES;"
```

## 4. .env и Laravel

```bash
cd /var/www/social-network
cp .env.example .env
php artisan key:generate
```

Минимум, что нужно выставить в `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=social_network
DB_USERNAME=social
DB_PASSWORD=strong_password_here

BROADCAST_DRIVER=pusher
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
CACHE_DRIVER=database

IPTV_FFMPEG_BIN=/usr/bin/ffmpeg

REVERB_APP_ID=prod-app-id
REVERB_APP_KEY=prod-app-key
REVERB_APP_SECRET=prod-app-secret
REVERB_HOST=your-domain.com
REVERB_PORT=80
REVERB_SCHEME=http
REVERB_PATH=
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=6001

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
VITE_REVERB_PATH="${REVERB_PATH}"
```

Если у вас HTTPS, переключите:
- `APP_URL=https://your-domain.com`
- `REVERB_PORT=443`
- `REVERB_SCHEME=https`

Дальше:

```bash
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan l5-swagger:generate
```

Если нужен демо-набор пользователей и контента (например, для стенда), запустите отдельно:

```bash
php artisan db:seed --class=UserSeeder --force
php artisan db:seed --class=DemoSocialContentSeeder --force
```

`DemoSocialContentSeeder` заполняет проект тестовыми постами/комментариями/лайками/подписками и placeholder-изображениями.
По умолчанию используются локально сгенерированные placeholder-изображения (без внешней сети).
Если нужны внешние фото из `loremflickr.com`, задайте `DEMO_SEED_USE_REMOTE_IMAGES=1` в `.env`.

Если нужно раздать текущее радио-избранное админов всем не-админам (одноразовый сценарий):

```bash
php artisan radio:distribute-admin-favorites --dry-run
php artisan radio:distribute-admin-favorites
```

Команда идемпотентна (`insertOrIgnore`) и безопасна для повторного запуска.

## 5. Права

```bash
sudo chown -R www-data:www-data /var/www/social-network
sudo find /var/www/social-network -type f -exec chmod 644 {} \;
sudo find /var/www/social-network -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/social-network/storage /var/www/social-network/bootstrap/cache
```

## 6. Nginx

Создайте `/etc/nginx/sites-available/social-network`:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/social-network/public;
    index index.php index.html;

    client_max_body_size 200M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Reverb WebSocket proxy
    location /app {
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_pass http://127.0.0.1:6001;
    }

    # Reverb HTTP API (events)
    location /apps {
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_pass http://127.0.0.1:6001;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Если сайт стоит за CDN, ingress или внешним балансировщиком, выставьте лимит запроса не ниже `200 MB` и там тоже. Иначе большие видео для постов будут отбрасываться до Laravel-валидации.

Включите сайт:

```bash
sudo ln -s /etc/nginx/sites-available/social-network /etc/nginx/sites-enabled/social-network
sudo nginx -t
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm
```

## 7. Supervisor (Reverb обязательно)

Создайте `/etc/supervisor/conf.d/social-network.conf`:

```ini
[program:social-network-reverb]
command=php /var/www/social-network/artisan reverb:start --host=0.0.0.0 --port=6001
directory=/var/www/social-network
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/social-network-reverb.log
```

Примените:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

### Очереди (опционально)

Нужно только если вы реально используете фоновые jobs и ставите `QUEUE_CONNECTION=database` или `redis`.

Пример для `database`:

```bash
cd /var/www/social-network
php artisan queue:table
php artisan migrate --force
```

`php artisan queue:table` запускается один раз (когда миграции jobs ещё нет в репозитории).

Добавьте отдельный блок в `/etc/supervisor/conf.d/social-network.conf`:

```ini
[program:social-network-queue]
command=php /var/www/social-network/artisan queue:work --sleep=1 --tries=3 --max-time=3600
directory=/var/www/social-network
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/social-network-queue.log
```

И примените:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

## 8. Быстрые проверки

```bash
which ffmpeg
ffmpeg -version
php artisan about
php artisan route:list | grep broadcasting
php artisan route:list | grep activity/heartbeat
php artisan route:list | grep admin/dashboard
php artisan route:list | grep admin/dashboard/export
sudo supervisorctl status
```

Опциональная smoke-проверка API документации:

```bash
curl -I http://your-domain.com/api/documentation
curl -I http://your-domain.com/docs/api-docs.json
curl -I "http://your-domain.com/api/admin/dashboard/export?format=json&date_from=2026-01-01&date_to=2026-01-31"
```

Проверьте в браузере:
- логин/регистрация;
- чаты realtime (онлайн/typing);
- IPTV;
- создание поста с видео, очередь загрузки и отображение ошибок;
- скачивание `mkv` и воспроизведение файлов, которые браузер реально поддерживает по кодекам.

Дополнительно для виджета времени/погоды на главной:
- сервер должен иметь исходящий HTTPS-доступ к `api.open-meteo.com:443`;
- при блокировке исходящего трафика сайт продолжит работу, но в виджете будет статус "Нет данных о погоде".

Дополнительно для seed-изображений демо-контента:
- внешняя сеть для `loremflickr.com` не обязательна (по умолчанию сидер работает офлайн);
- если включён `DEMO_SEED_USE_REMOTE_IMAGES=1`, откройте исходящий HTTPS-доступ к `loremflickr.com:443`.

## 9. Обновление проекта

Перед production-обновлением прогоните проверки в CI, staging или локально с dev-зависимостями:

```bash
composer install
npm ci
php artisan test
npm run test:js
npm run build
composer audit
npm audit
```

На production-сервере выполняйте только сам rollout:

```bash
cd /var/www/social-network
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan l5-swagger:generate
sudo supervisorctl restart social-network-reverb
sudo systemctl reload nginx
```

## 10. Docker-вариант (альтернатива)

Если деплой через Docker:

```bash
docker compose up -d --build
docker compose ps
```

В проекте уже предусмотрен `ffmpeg` в контейнере и переменные:
- `IPTV_FFMPEG_BIN=/usr/bin/ffmpeg`
