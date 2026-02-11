# Solid Social Network SPA

SPA-социальная сеть на Laravel + Vue с чатами, realtime, медиа-контентом и админ-панелью.

## Что реализовано
- Современный SPA-интерфейс (главная, лента, профили, кабинет, чаты, админка).
- Пользовательский кабинет:
  - создание постов;
  - загрузка фото/видео;
  - лайки, комментарии, репосты, эмодзи.
  - настройка никнейма для отображения в постах/чатах;
  - загрузка и удаление личной аватарки.
  - страница «Мои обращения» со статусами feedback (`новое / в обработке / решено`).
  - регистрация с подтверждением email (до подтверждения доступен только экран верификации).
- Главная страница:
  - публичная медиа-карусель;
  - блоки популярных, самых просматриваемых и новых постов;
  - форма обратной связи в администрацию.
- Админ-панель:
  - управление пользователями и правами;
  - модерация постов, комментариев, feedback;
  - полный CRUD постов для администраторов: создание, редактирование и удаление любого поста (независимо от автора);
  - загрузка полного списка постов в админке без ограничения первой страницей;
  - контроль чатов и сообщений;
  - управление настройками сайта;
  - редактирование контента главной страницы (hero-блок и блок обратной связи);
  - выбор политики хранения медиа (локально/облако/выбор пользователя).
- Чаты:
  - общий чат;
  - личные диалоги;
  - отправка текста, голосовых, фото, видео, GIF;
  - отправка сообщения по `Ctrl+Enter` в поле ввода;
  - запись голосовых с индикатором уровня микрофона;
  - кнопка отмены записи до отправки;
  - лимит записи голосового: 5 минут (автостоп с добавлением в сообщение);
  - нормализация громкости голосового при формировании `wav`;
  - сохранение сообщений для оффлайн-пользователей и доставка после входа;
  - индикаторы непрочитанных сообщений (бейджи в чатах и в верхнем меню);
  - блокировки пользователей (временные/постоянные);
  - realtime через Reverb + Echo.
- Радио:
  - поиск интернет-радиостанций через Radio Browser;
  - встроенный плеер для прослушивания на сайте;
  - избранные станции пользователя (добавление/удаление/список).
- Обратная связь:
  - для зарегистрированных есть страница «Мои обращения»;
  - статус обращения (`новое / в обработке / решено`) обновляется в realtime без ручного refresh.

## Технологии
- PHP 8.2+ (в Docker используется PHP 8.3 FPM)
- Laravel 10
- Laravel Sanctum
- Laravel Reverb
- Vue 3
- Vue Router
- Vite
- Tailwind CSS 4
- Plyr (единый плеер для аудио/видео)
- MySQL 8.4 (в Docker)


## Локальный запуск (без Docker)
1. Установить зависимости:
   - `composer install`
   - `npm install`
2. Подготовить окружение:
   - Linux/macOS: `cp .env.example .env`
   - Windows PowerShell: `Copy-Item .env.example .env`
   - Windows CMD: `copy .env.example .env`
3. Сгенерировать ключ приложения:
   - `php artisan key:generate`
4. Выполнить миграции и сиды:
   - `php artisan migrate --seed`
5. Настроить почту для подтверждения email:
   - для разработки можно оставить `MAIL_MAILER=log` (ссылка придёт в `storage/logs/laravel.log`);
   - для реальной отправки укажите SMTP-параметры (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`).
6. Создать storage-ссылку:
   - `php artisan storage:link`
7. Запустить приложение (3 процесса):
   - `php artisan serve`
   - `npm run dev`
   - `php artisan reverb:start --host=0.0.0.0 --port=6001`

## Docker (Windows / Linux / macOS)
Конфигурация проверена: сборка, запуск, миграции и тесты выполняются успешно.
`web` и `websocket` стартуют только после `app (php-fpm)` в состоянии `healthy`.
`web` также ждёт `frontend-build` (Node), который собирает актуальные `public/build/*`.
На первом запуске `app` может инициализироваться 30-90 секунд (composer/autoload).

### Быстрый старт
1. Подготовить `.env`:
   - Linux/macOS: `cp .env.docker.example .env`
   - Windows PowerShell: `Copy-Item .env.docker.example .env`
   - Windows CMD: `copy .env.docker.example .env`
2. Запустить сервисы:
   - `docker compose up -d --build`
   - этап `frontend-build` может занять 1-3 минуты (установка npm-зависимостей + Vite build).
3. Миграции применяются автоматически при старте `app`-контейнера.
   - при необходимости вручную: `docker compose exec app php artisan migrate --seed`
4. Открыть проект:
   - `http://localhost:8080`

### Дополнительно
- Vite dev server в Docker:
  - `docker compose --profile dev up -d node`
- Тесты в Docker:
  - `docker compose --profile test run --rm test`
- Примечание по Docker Compose:
  - предупреждение `No services to build` при `docker compose run ...` допустимо, если образы уже собраны.
- Полный перезапуск с пересборкой:
  - `docker compose down && docker compose up -d --build`
- Остановка:
  - `docker compose down`
- Полный сброс с БД:
  - `docker compose down -v`

## Realtime (Reverb + Echo)
### Локально
- WebSocket: `ws://127.0.0.1:6001`
- Нужны корректные переменные в `.env`:
  - `BROADCAST_DRIVER=pusher`
  - `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`
  - `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`

### В Docker
- WebSocket endpoint: `ws://localhost:6001`
- Сервис `websocket` поднимается автоматически в `docker compose up -d`.

## Тесты и контроль качества
- Все тесты:
  - `php artisan test`
- Только feature:
  - `php artisan test --testsuite=Feature`
- Production build фронтенда:
  - `npm run build`

## Интеграция радио
- По умолчанию используется каталог `https://all.api.radio-browser.info`.
- Переменная окружения:
  - `RADIO_BROWSER_BASE_URL=https://all.api.radio-browser.info`
- Эндпоинты:
  - `GET /api/radio/stations` — поиск станций;
  - `GET /api/radio/favorites` — список избранных;
  - `POST /api/radio/favorites` — добавить/обновить избранную станцию;
  - `DELETE /api/radio/favorites/{stationUuid}` — удалить из избранного.

## Тестовые аккаунты (сидер)
- Админ: `admin@example.com` / `password`
- Пользователи:
  - `user1@example.com` / `password`
  - `user2@example.com` / `password`
  - `user3@example.com` / `password`
  - `user4@example.com` / `password`
  - `user5@example.com` / `password`

## Лимиты загрузки медиа
- API-валидация: до 200MB на файл.
- Docker настроен для больших файлов:
  - Nginx `client_max_body_size=256m`
  - PHP `upload_max_filesize=256M`
  - PHP `post_max_size=256M`
- Если запускаете без Docker, синхронизируйте лимиты в вашем `php.ini` и веб-сервере.

## Диагностика проблем
- Очистить кеши Laravel:
  - `php artisan optimize:clear`
- Если фронт пустой после обновления:
  - пересобрать docker-сборку фронта: `docker compose run --rm frontend-build`
  - сделать hard refresh в браузере (`Ctrl+F5`)
- Если голосовое в чате исчезает после `Стоп`:
  - проверить, что `frontend-build` завершился успешно: `docker compose logs --tail=200 frontend-build`
  - пересобрать фронт и перезапустить web: `docker compose run --rm frontend-build && docker compose restart web`
  - в Chrome открыть DevTools -> Network и убедиться, что грузится свежий файл `Chats-*.js` (без старого хеша из кеша).
- Если запись голосового не стартует:
  - проверить разрешение микрофона для `http://localhost:8080` в Chrome Site settings
  - убедиться, что в системе не занят микрофон другим приложением
- Проверить контейнеры:
  - `docker compose ps`
  - `docker compose logs --tail=100 app`
  - `docker compose logs --tail=100 web`
- Если видите `502 Bad Gateway`:
  - дождаться статуса `healthy` у `app` в `docker compose ps`
  - проверить, что `php-fpm` готов в логах `docker compose logs --tail=100 app`
- Если в форме feedback кнопка зависает на "Отправка...":
  - убедиться, что `DB_HOST` соответствует режиму запуска:
    - локально обычно `127.0.0.1`
    - в Docker обычно `db`
  - проверить, что задан `DB_CONNECT_TIMEOUT=5` (или меньше) для быстрого fail-fast при недоступной БД
