#### Автор проекта: **Rinat Sarmuldin** Email: [ura07srr@gmail.com](mailto:ura07srr@gmail.com)
# Solid Social Network SPA URALSK + IPTV/Radio

SPA-социальная сеть на `Laravel + Vue` с чатами, realtime, медиа, IPTV/радио и админ-панелью.

## Стек
- PHP 8.2+ (Docker: PHP 8.3 FPM)
- Laravel 10, Sanctum, Reverb
- Vue 3, Vue Router, Vite, Tailwind CSS 4
- MySQL (Docker: MySQL 8.4)

## Главный принцип
- Локальный режим: используйте только `.env` (из `.env.example`).
- Docker-режим: запускается из `docker-compose.yml` без обязательного env-файла.
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

### Быстрая проверка БД (Windows)
- MySQL порт: `Test-NetConnection 127.0.0.1 -Port 3306`
- PostgreSQL порт: `Test-NetConnection 127.0.0.1 -Port 5432`
- Если у вас нестандартный порт (например `3307`), укажите его в `DB_PORT`.

---

## Docker запуск

1. Запустите:
   - `docker compose up -d --build`
2. Проверьте статус:
   - `docker compose ps`
3. Откройте:
   - `http://localhost:8080`

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
- Сборка фронта: `npm run build`

---

## IPTV и FFmpeg
- Для совместимого режима IPTV нужен `ffmpeg`.
- Локально укажите путь при необходимости:
  - `IPTV_FFMPEG_BIN=ffmpeg`
  - пример Windows: `IPTV_FFMPEG_BIN=C:\ffmpeg\bin\ffmpeg.exe`
- Проверка capability API: `GET /api/iptv/transcode/capabilities`

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
