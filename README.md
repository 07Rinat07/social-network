#### Автор проекта: **Rinat Sarmuldin** Email: [ura07srr@gmail.com](mailto:ura07srr@gmail.com)
# Solid Social Network SPA URALSK + IPTV/Radio
* IPTV/Radio (используются бесплатные листы самообновляемые и не нарушают юридических прав правообладателей итд.)

SPA-социальная сеть на `Laravel + Vue` с чатами, realtime, медиа, IPTV/радио и админ-панелью.

## Что актуально в текущей версии
- Чаты: realtime presence-статусы (кто онлайн на сайте/в чате) и индикатор набора текста.
- Главная: карусель медиа работает как плавный конвейер с несколькими карточками одновременно.
- Главная: в quick-блоке под "Лента в движении" показываются время и погода по городам (Нью-Йорк, Москва, Минск, Астана, Анкара, Уральск).
- Админка: у строки пользователя есть понятный статус сохранения (изменено/сохраняется/успех/ошибка).
- IPTV: при уходе со страницы/закрытии вкладки/выходе из аккаунта плеер и FFmpeg-сессия корректно останавливаются.
- Мультиязычность: поддержка `ru/en` с SEO-дружественными URL (`/ru/...`, `/en/...`) и переключателем языка в верхнем меню.

## Мультиязычность (SEO URL)
- Базовый формат URL:
  - русский: `/ru/...`
  - английский: `/en/...`
- Если открыть страницу без префикса языка (например `/users/index`), роутер автоматически выполнит redirect на локализованный URL.
- Текущий язык хранится в `localStorage` (`solid-social:locale`) и синхронизируется с префиксом в URL.
- Файлы переводов фронта:
  - `resources/js/i18n/messages/ru.js`
  - `resources/js/i18n/messages/en.js`
- Runtime-карта для legacy-строк (страницы, где ещё не заменили текст на `$t(...)`):
  - `resources/js/i18n/runtimeTextMap.js`
  - Поддерживаются точные фразы и regex-паттерны (`runtimeTextPatterns`).
- Точка подключения i18n:
  - `resources/js/i18n/index.js`
- Runtime-i18n в `resources/js/i18n/index.js` автоматически переводит текстовые узлы/placeholder/title/aria-label и сообщения `alert/confirm` при переключении на `/en/...`.
- В админке блок "Контент главной страницы" редактируется отдельно для `RU` и `EN`; обе версии сохраняются в `site_settings.key=home_page_content` в JSON-структуре `locales.ru / locales.en`.
- Примеры:
  - `http://127.0.0.1:8000/ru`
  - `http://127.0.0.1:8000/en`
  - `http://127.0.0.1:8000/en/users/login`
- Как добавить новый перевод:
  1. Добавьте ключ в `resources/js/i18n/messages/ru.js`.
  2. Добавьте тот же ключ в `resources/js/i18n/messages/en.js`.
  3. Используйте в Vue-шаблоне: `{{ $t('section.key') }}`.
  4. Если строка пока не в шаблоне Vue (legacy JS/DOM), добавьте её в `resources/js/i18n/runtimeTextMap.js`.

## Стек
- PHP 8.2+ (Docker: PHP 8.3 FPM)
- Laravel 10, Sanctum, Reverb
- Vue 3, Vue Router, Vite, Tailwind CSS 4
- MySQL (Docker: MySQL 8.4)

## Главный принцип
- Локальный режим: используйте только `.env` (из `.env.example`).
- Docker-режим: запускается из `docker-compose.yml`; сервисы используют `env_file` (`.env.docker.example`), а `.env` в контейнере создается автоматически при необходимости.
- Не смешивайте команды и переменные двух режимов.

---

## Локальный запуск (без Docker)

1. Установите зависимости:
   - `composer install`
   - `npm install`
2. Создайте `.env`:
   - Linux/macOS: `cp .env.example .env`
   - PowerShell: `Copy-Item .env.example .env`
   - CMD: `copy .env.example .env`
3. Запустите локальный сервер БД и создайте базу:
   - имя базы = `DB_DATABASE` (по умолчанию `laravel`)
4. Настройте БД в `.env`:
   - `MySQL`: `DB_CONNECTION=mysql`
   - `PostgreSQL`: `DB_CONNECTION=pgsql`
5. Сгенерируйте ключ и выполните миграции:
   - `php artisan key:generate`
   - `php artisan migrate --seed`
   - `php artisan storage:link`
6. Запустите 3 процесса:
   - `php artisan serve`
   - `npm run dev`
   - `php artisan reverb:start --host=0.0.0.0 --port=6001`
7. Откройте: `http://127.0.0.1:8000`

### Важно для локального realtime (чаты online/typing)
- Для presence/whisper (онлайн-статусы и индикатор набора текста) **обязательно** держать запущенным Reverb:
  - `php artisan reverb:start --host=0.0.0.0 --port=6001`
- Если Reverb не запущен, чат откроется, но realtime-обновления `online/typing` работать не будут.
---

## Docker запуск

1. Запустите:
   - `docker compose up -d --build`
2. Проверьте статус:
   - `docker compose ps`
   - в списке должен быть сервис `websocket` в статусе `Up`
3. Дополнительно проверьте процесс Reverb:
   - `docker compose top websocket`
   - ожидаемый процесс: `php artisan reverb:start --host=0.0.0.0 --port=6001`
4. Откройте:
   - `http://localhost:8080`

Примечание по IPTV:
- В Docker-образе `app` `ffmpeg` уже установлен.
- Для стабильной работы в compose зафиксированы переменные:
  - `IPTV_FFMPEG_BIN=/usr/bin/ffmpeg`

### Полезные Docker-команды
- Миграции: `docker compose exec app php artisan migrate --seed`
- Тесты: `docker compose --profile test run --rm test`
- Логи: `docker compose logs --tail=100 app`
- Остановка: `docker compose down`

### Docker порты и БД по умолчанию
- Приложение: `http://localhost:8080`
- Vite dev server (профиль `dev`): `5173`
- Reverb websocket: `6001`
- MySQL с хоста: `127.0.0.1:3307`
- MySQL внутри Docker-сети: `db:3306` (`social_network` / `social` / `social`)

---

## Тесты
- Все тесты: `php artisan test`
- Feature: `php artisan test --testsuite=Feature`
- Виджет времени/погоды главной: `php artisan test tests/Feature/SiteSettingsAndDiscoveryFeatureTest.php`
- Broadcast/channels: `php artisan test tests/Feature/BroadcastChannelsFeatureTest.php`
- Чаты: `php artisan test tests/Feature/ChatFeatureTest.php`
- Сборка фронта: `npm run build`
- Быстрый check перед коммитом:
  - `php artisan test`
  - `npm run build`
- Docker check:
  - `docker compose --profile test run --rm test`
  - `docker compose run --rm frontend-build`

---

## IPTV и FFmpeg
- Для совместимого режима IPTV нужен `ffmpeg`.
- Локально укажите путь при необходимости:
  - `IPTV_FFMPEG_BIN=ffmpeg`
  - пример Windows: `IPTV_FFMPEG_BIN=C:\ffmpeg\bin\ffmpeg.exe`
- Проверка capability API (требуется авторизация и подтвержденный email): `GET /api/iptv/transcode/capabilities`
- При выходе из страницы IPTV поток и transcode-сессия должны останавливаться автоматически.

---

## Виджет времени и погоды (главная)
- API: `GET /api/site/world-overview?locale=ru|en`
- Источник погоды: `https://api.open-meteo.com/v1/forecast` (серверный запрос, без CORS-ограничений браузера).
- Время считается на клиенте по timezone каждого города и обновляется каждую секунду.
- Погодные данные кэшируются на сервере на `5` минут и автообновляются на клиенте.

---

## Тестовые аккаунты
- `admin@example.com` / `password`
- `user1@example.com` / `password`
- `user2@example.com` / `password`
- `user3@example.com` / `password`
- `user4@example.com` / `password`
- `user5@example.com` / `password`

---

## Частые проблемы

### `SQLSTATE[HY000] [2002] Connection refused`
- Приложение не видит БД по `DB_HOST`/`DB_PORT`.
- Убедитесь, что локальная служба БД запущена.
- В локальном режиме проверьте реальный порт вашей локальной БД (`3306`/`5432`/другой).
- В Docker режиме MySQL доступен на `127.0.0.1:3307`.

### `Access denied`
- Неверные `DB_USERNAME`/`DB_PASSWORD` в активном env-файле.

### Пустая страница / сломанный фронт
- Локально: `npm run dev` должен быть запущен.
- Docker: пересоберите фронт `docker compose run --rm frontend-build`.
- Сделайте hard refresh (`Ctrl+F5`).

### `403` на `/api/broadcasting/auth` (чаты, presence, typing)
- Проверьте, что пользователь авторизован (cookie-сессия активна).
- Проверьте соответствие домена/порта в `APP_URL` и фактического URL в браузере.
- Убедитесь, что realtime сервер запущен:
  - локально: `php artisan reverb:start --host=0.0.0.0 --port=6001`

### Битая аватарка или файлы из `/storage` (локально)
- Такое бывает после запуска в Docker, если симлинк `public/storage` остался с docker-путём.
- Исправление:
  - `php -r "if (is_link('public/storage')) unlink('public/storage');"`
  - `php artisan storage:link`
