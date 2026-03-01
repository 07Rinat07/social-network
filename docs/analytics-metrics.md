# Методика аналитики платформы

Документ фиксирует, откуда берутся данные аналитики в проекте, какие формулы используются, какие есть fallback-алгоритмы и как проверить цифры вручную.

Статус верификации на 1 марта 2026:
- `GET /api/admin/summary` и `GET /api/admin/dashboard/export` покрыты feature-тестами.
- Swagger/OpenAPI для аналитических endpoint-ов успешно генерируется и проверяется тестом `SwaggerDocumentationFeatureTest`.
- Полный локальный прогон: `php artisan test` -> `205 passed` (`1760 assertions`), `npm run test:js` -> `33 passed`.

## 1. Основные API и код

- `GET /api/admin/summary`
  - Контроллер: `app/Http/Controllers/AdminController.php`
  - Метод: `summary()`
- `GET /api/admin/dashboard`
  - Контроллер: `app/Http/Controllers/AdminController.php`
  - Метод: `dashboard()`
- `GET /api/admin/dashboard/export?format=xls|json`
  - Контроллер: `app/Http/Controllers/AdminController.php`
  - Метод: `exportDashboard()`
- Построение аналитического payload:
  - Сервис: `app/Services/AdminDashboardService.php`
  - Метод: `build()`
- Экспорт того же payload:
  - Сервис: `app/Services/AdminDashboardExportService.php`
  - Методы: `buildPayload()`, `toXls()`, `toJson()`
- Серверный heartbeat:
  - `POST /api/activity/heartbeat`
  - Контроллер: `app/Http/Controllers/ActivityHeartbeatController.php`
- Клиентские analytics events:
  - `POST /api/analytics/events`
  - Контроллер: `app/Http/Controllers/AnalyticsEventController.php`
  - Сервис записи: `app/Services/AnalyticsEventService.php`
  - Модель событий: `app/Models/AnalyticsEvent.php`
- Публичный client error intake:
  - `POST /api/client-errors`
  - Контроллер: `app/Http/Controllers/SiteErrorLogController.php`
- Админский diagnostics log:
  - `GET /api/admin/error-log`
  - `GET /api/admin/error-log/entries`
  - `GET /api/admin/error-log/export`
  - `GET /api/admin/error-log/download`
  - Сервис: `app/Services/SiteErrorLogService.php`

## 2. Таблицы-источники

### 2.1 Базовые бизнес-данные

- `users`
- `subscriber_followings`
- `posts`
- `post_images`
- `comments`
- `liked_posts`
- `post_views`
- `conversation_messages`
- `conversation_message_attachments`
- `feedback_messages`
- `user_blocks`
- `radio_favorites`
- `iptv_saved_channels`
- `iptv_saved_playlists`

### 2.2 Таблицы активности

- `user_activity_sessions`
  - Сырые heartbeat-сессии по feature.
- `user_activity_daily_stats`
  - Дневная агрегированная активность по пользователю и feature.
  - Используется для time-based аналитики.
- `analytics_events`
  - Лёгкие client-side события для `media`, `radio`, `iptv`.
  - Используется для media/video transport analytics, failure rate и mode split.

### 2.3 Operational diagnostics log

- `storage/logs/site-errors.log`
  - Append-only lifetime text log сайта.
  - Пишет `server_exception`, `client_error`, `analytics_failure`.
- `storage/logs/site-errors-archive/*`
  - Ротационные архивы активного лога по лимиту размера/возраста.
  - Архив может храниться как plain text или `.gz`, в зависимости от `SITE_ERROR_LOG_ARCHIVE_COMPRESS`.
- Источник данных:
  - `App\Services\SiteErrorLogService`
  - `App\Exceptions\Handler`
  - `App\Services\AnalyticsEventService`
  - `resources/js/utils/siteErrorReporter.js`

## 3. Общие правила расчёта

### 3.1 Период расчёта

- Основной метод: `App\Services\AdminDashboardService::build()`.
- Если передан только `year`, берётся весь выбранный год.
- Если переданы `date_from` и `date_to`, диапазон зажимается внутри выбранного года.
- `period.mode`:
  - `year` — весь год.
  - `custom_range` — пользовательский диапазон внутри года.

### 3.2 Reference moment

- Для метрик `DAU/WAU/MAU` и 30-дневных окон используется `referenceEnd`.
- Формула:
  - `referenceEnd = min(period_end, now())`
- Это исключает ситуацию, когда период уходит в будущее.

### 3.3 Приоритет heartbeat над fallback по действиям

- Если таблица `user_activity_daily_stats` существует и в периоде есть `seconds_total > 0`, дашборд переходит в режим `time_minutes`.
- Если heartbeat-данных нет, используются fallback-действия:
  - `posts`
  - `comments`
  - `liked_posts`
  - `post_views`
  - `conversation_messages`
  - `radio_favorites`
  - `iptv_saved_channels`
  - `iptv_saved_playlists`

### 3.4 Важное ограничение по качеству данных

- `analytics_events` отражают только те client-side события, которые реально были отправлены браузером.
- Если фронтенд событие не отправил, метрика не будет восстановлена по серверным логам автоматически.
- Поэтому `media/radio/IPTV failure rate`, `video completion`, `theater opens`, `fullscreen entries`, `mode split` зависят от корректной отправки событий.

## 4. Формулы по блокам

## 4.1 `/api/admin/summary`

Источник: `App\Http\Controllers\AdminController::summary()`

- `users = COUNT(users.id)`
- `admins = COUNT(users.id WHERE is_admin = true)`
- `posts = COUNT(posts.id)`
- `public_posts = COUNT(posts.id WHERE is_public = true)`
- `carousel_posts = COUNT(posts.id WHERE is_public = true AND show_in_carousel = true)`
- `comments = COUNT(comments.id)`
- `media = COUNT(post_images.id)`
- `likes = COUNT(liked_posts.id)`
- `feedback_new = COUNT(feedback_messages.id WHERE status = 'new')`
- `feedback_in_progress = COUNT(feedback_messages.id WHERE status = 'in_progress')`
- `feedback_resolved = COUNT(feedback_messages.id WHERE status = 'resolved')`
- `conversations = COUNT(conversations.id)`
- `messages = COUNT(conversation_messages.id)`
- `chat_attachments = COUNT(conversation_message_attachments.id)`
- `active_blocks = COUNT(user_blocks.id WHERE expires_at IS NULL OR expires_at > now())`

## 4.2 KPI блока `dashboard.kpis`

Источник: `App\Services\AdminDashboardService::build()`

- `users_total = COUNT(users.id)`
- `users_new_year = COUNT(users.id WHERE created_at in period)`
- `users_new_period = users_new_year`
- `subscriptions_total = COUNT(subscriber_followings.id)`
- `subscriptions_year = COUNT(subscriber_followings.id WHERE created_at in period)`
- `subscriptions_period = subscriptions_year`
- `subscriptions_previous_year = COUNT(subscriber_followings.id in previous comparable period)`
- `subscriptions_change_percent`
  - если `subscriptions_previous_year > 0`
  - `((subscriptions_year - subscriptions_previous_year) / subscriptions_previous_year) * 100`
  - иначе `null`
- `subscriptions_avg_month = subscriptions_year / period_months`
- `subscriptions_peak_month`
  - месяц с максимальным количеством подписок в периоде
- `tracked_minutes_year = SUM(user_activity_daily_stats.seconds_total in period) / 60`

## 4.3 Блок `dashboard.preference`

Источник: `App\Services\AdminDashboardService::build()`

### Режим `time_minutes`

- `social = SUM(seconds_total for feature='social') / 60`
- `chats = SUM(seconds_total for feature='chats') / 60`
- `radio = SUM(seconds_total for feature='radio') / 60`
- `iptv = SUM(seconds_total for feature='iptv') / 60`

### Fallback режим `actions`

- `social = posts + comments + likes + views`
- `chats = conversation_messages`
- `radio = radio_favorites`
- `iptv = iptv_saved_channels + iptv_saved_playlists`

### Общие формулы

- `total_actions = social + chats + radio + iptv`
- `share(feature) = value(feature) / total_actions * 100`
- `leader_key = feature with max(value)`
- `method = 'time_minutes' | 'actions'`

## 4.4 Блок `dashboard.engagement`

Источник: `App\Services\AdminDashboardService::build()`

30-дневное окно строится относительно `referenceEnd`.

- `active_users_30d = unique(social ∪ chats ∪ radio ∪ iptv users in last 30 days)`
- `creators_30d = COUNT(DISTINCT posts.user_id where created_at >= cutoff)`
- `chatters_30d = COUNT(DISTINCT conversation_messages.user_id where created_at >= cutoff)`
- `new_users_30d = COUNT(users.id where created_at >= cutoff)`
- `social_active_users_30d`
  - heartbeat: `COUNT(DISTINCT user_id in user_activity_daily_stats where feature='social' and activity_date >= cutoff)`
  - fallback: unique users from `posts/comments/liked_posts/post_views`
- `chat_active_users_30d`
  - heartbeat or fallback from `conversation_messages`
- `radio_active_users_30d`
  - heartbeat or fallback from `radio_favorites`
- `iptv_active_users_30d`
  - heartbeat or fallback from `iptv_saved_channels + iptv_saved_playlists`

## 4.5 Блок `dashboard.retention`

Источник: `App\Services\AdminDashboardService::buildRetentionAnalytics()`

- `DAU = COUNT(unique active users between referenceEnd startOfDay and endOfDay)`
- `WAU = COUNT(unique active users between referenceEnd - 6 days and referenceEnd)`
- `MAU = COUNT(unique active users between referenceEnd - 29 days and referenceEnd)`
- `stickiness_percent = DAU / MAU * 100`
- `new_active_users_30d = COUNT(users from MAU set with created_at in last 30 days)`
- `returning_users_30d = MAU - new_active_users_30d`

### Определение active user

- При наличии heartbeat:
  - `user_activity_daily_stats.seconds_total > 0`
- Иначе fallback:
  - любая активность в `posts/comments/liked_posts/post_views/conversation_messages/radio_favorites/iptv_saved_channels/iptv_saved_playlists`

### Cohorts

Источник: `App\Services\AdminDashboardService::buildRetentionCohorts()`

Для каждого пользователя, созданного в периоде:

- `cohort month = month(users.created_at)`
- `new_users += 1`
- окно удержания:
  - `window_start = registered_at + 1 day`
  - `window_end = registered_at + 30 days`
- пользователь считается retained, если в окне есть хотя бы один activity day
- `retention_percent = retained_users / new_users * 100`
- `partial = true`, если полное 30-дневное окно ещё не закрыто к `referenceEnd`

## 4.6 Блок `dashboard.content`

Источник: `App\Services\AdminDashboardService::buildContentAnalytics()`

- `posts_total = COUNT(posts.id in period)`
- `public_posts = COUNT(posts.id WHERE is_public = true in period)`
- `private_posts = posts_total - public_posts`
- `carousel_posts = COUNT(posts.id WHERE show_in_carousel = true in period)`
- `likes_total = COUNT(liked_posts.id in period)`
- `comments_total = COUNT(comments.id in period)`
- `views_total = COUNT(post_views.id in period)`
- `reposts_total = COUNT(posts.id WHERE reposted_id IS NOT NULL in period)`
- `engagement_total = likes_total + comments_total + reposts_total`
- `engagement_per_post = engagement_total / posts_total`
- `avg_views_per_post = views_total / posts_total`
- `view_to_engagement_rate_percent = engagement_total / views_total * 100`

### Топ постов

Для каждого поста:

- `engagement_score = likes_count + comments_count + reposts_count`
- сортировка:
  - `engagement_score desc`
  - `views_count desc`
  - `posts.id desc`

### Топ авторов

Для каждого автора:

- `posts_count = COUNT(posts.id)`
- `views_count = SUM(post views over author's posts)`
- `engagement_total = SUM(likes + comments + reposts over author's posts)`
- `engagement_per_post = engagement_total / posts_count`

## 4.7 Блок `dashboard.chats`

Источник: `App\Services\AdminDashboardService::buildChatAnalytics()`

- `messages_total = COUNT(conversation_messages.id in period)`
- `active_chatters = COUNT(DISTINCT conversation_messages.user_id in period)`
- `attachments_total = COUNT(conversation_message_attachments.id in period)`
- `attachment_breakdown = COUNT(*) GROUP BY type`

### Reply time

Считаются только direct conversations.

Алгоритм:

1. Берём сообщения direct-диалогов в периоде.
2. Идём по сообщениям по времени внутри каждого диалога.
3. Если текущее сообщение отправил другой пользователь, чем предыдущее:
   - `reply_minutes = diff(previous.created_at, current.created_at)`
4. Берём только значения `0 <= reply_minutes <= 10080`.

Формулы:

- `avg_reply_minutes = SUM(reply_minutes) / samples_count`
- `median_reply_minutes = median(reply_minutes)`

## 4.8 Блок `dashboard.media`

Источник: `App\Services\AdminDashboardService::buildMediaAnalytics()`

### Успешные загрузки

- `post_media_uploads = COUNT(post_images.id in period)`
- `chat_attachments_uploads = COUNT(conversation_message_attachments.id in period)`
- `uploads_total = post_media_uploads + chat_attachments_uploads`
- `images_uploaded = count(type='image')`
- `videos_uploaded = count(type='video')`
- `avg_upload_size_kb = SUM(size bytes) / uploads_total / 1024`

### Ошибки загрузки

- `failed_uploads = COUNT(analytics_events where event_name='media_upload_failed')`
- `upload_failure_rate_percent = failed_uploads / (uploads_total + failed_uploads) * 100`

### Видео-поведение

- `video_sessions = COUNT(analytics_events where event_name='video_session')`
- `video_completed_sessions = COUNT(video_session where context.completed = true)`
- `video_completion_rate_percent = video_completed_sessions / video_sessions * 100`
- `video_watch_seconds = SUM(duration_seconds for video_session)`
- `avg_video_completion_percent = AVG(metric_value for video_session)`
- `theater_opens = COUNT(video_theater_open)`
- `fullscreen_entries = COUNT(video_fullscreen_enter)`

## 4.9 Блок `dashboard.radio`

Источник: `App\Services\AdminDashboardService::buildRadioAnalytics()`

- `active_users_period = COUNT(unique radio users in period)`
- `favorite_additions_period = COUNT(radio_favorites.id in period)`
- `sessions_started = COUNT(analytics_events where event_name='radio_play_started')`
- `failures_total = COUNT(analytics_events where event_name='radio_play_failed')`
- `failure_rate_percent = failures_total / (sessions_started + failures_total) * 100`
- `top_stations`
  - группировка по `entity_key/entity_id`
  - label берётся из `context.station_name`
  - сортировка по count desc

## 4.10 Блок `dashboard.iptv`

Источник: `App\Services\AdminDashboardService::buildIptvAnalytics()`

### Базовые значения

- `active_users_period = COUNT(unique iptv users in period)`
- `saved_channels_period = COUNT(iptv_saved_channels.id in period)`
- `saved_playlists_period = COUNT(iptv_saved_playlists.id in period)`

### Sessions and failures

Собираются события:

- `iptv_direct_started`
- `iptv_direct_failed`
- `iptv_proxy_started`
- `iptv_proxy_failed`
- `iptv_relay_started`
- `iptv_relay_failed`
- `iptv_ffmpeg_started`
- `iptv_ffmpeg_failed`

Формулы:

- `sessions_started = sum(started across all modes)`
- `failures_total = sum(failed across all modes)`
- `failure_rate_percent = failures_total / (sessions_started + failures_total) * 100`

### Mode split

Для каждого режима `direct/proxy/relay/ffmpeg`:

- `started = COUNT(start event)`
- `failed = COUNT(fail event)`
- `share = started / sessions_started * 100`

### Top channels

- Источник: started events всех режимов
- группировка по `entity_key/entity_id`
- label берётся из `context.channel_name`

## 4.11 Блок `dashboard.errors_and_moderation`

Источник: `App\Services\AdminDashboardService::buildErrorsAndModeration()`

- `media_upload_failures = COUNT(media_upload_failed events)`
- `radio_failures = COUNT(radio_play_failed events)`
- `iptv_failures = COUNT(all iptv *_failed events)`
- `total_tracked_failures = media_upload_failures + radio_failures + iptv_failures`
- `active_blocks_total = COUNT(user_blocks WHERE expires_at IS NULL OR expires_at > now())`
- `feedback_new_total = COUNT(feedback_messages WHERE status='new')`
- `feedback_in_progress_total = COUNT(feedback_messages WHERE status='in_progress')`
- `feedback_resolved_total = COUNT(feedback_messages WHERE status='resolved')`
- `feedback_created_period = COUNT(feedback_messages WHERE created_at in period)`

Важно:

- этот блок показывает агрегированные счётчики по выбранному периоду и текущему состоянию БД;
- lifetime `site-errors.log` не является источником этих чисел, а служит отдельным diagnostics-слоем для расшифровки конкретных инцидентов;
- в text log попадают сырые записи server/client/analytics failures, поэтому он используется для расследования "что, где, почему и когда произошло", а не для периодных KPI.

## 5. Выгрузка в Excel и JSON

Экспорт использует тот же аналитический payload, что и экран админки.

Источник:

- `App\Services\AdminDashboardExportService::buildPayload()`
- `App\Services\AdminDashboardExportService::toXls()`
- `App\Services\AdminDashboardExportService::toJson()`

Следствие:

- цифры на экране и в выгрузке должны совпадать;
- если есть расхождение, искать его нужно не в формулах Excel, а в моменте построения payload или в данных БД.

## 6. Что важно объяснять заказчику и ревьюеру

- summary counters — это прямые counts из БД;
- dashboard — это агрегированная аналитика по выбранному периоду;
- `time_minutes` появляется только при наличии heartbeat-агрегатов;
- если heartbeat нет, система автоматически откатывается к action-based оценке;
- media/radio/IPTV transport metrics строятся по `analytics_events`, а не по server access logs;
- lifetime error log и его архивы нужны для операционной диагностики, а не для пересчёта dashboard KPI;
- retention cohorts могут быть `partial`, если окно 30 дней ещё не завершено;
- demo/seed-данные могут создавать искусственные пики по месяцам и непропорционально высокие значения в коротком периоде.

## 7. Быстрая ручная проверка

### 7.1 Summary

```bash
php artisan tinker --execute="dump(app(\App\Http\Controllers\AdminController::class)->summary()->getData(true)['data']);"
```

### 7.2 Dashboard payload

```bash
php artisan tinker --execute="dump(app(\App\Services\AdminDashboardService::class)->build(now()->year));"
```

### 7.3 Проверка heartbeat-минут

```bash
php artisan tinker --execute="dump(DB::table('user_activity_daily_stats')->sum('seconds_total'));"
```

Формула:

- `minutes = seconds_total / 60`

### 7.4 Проверка client analytics events

```bash
php artisan tinker --execute="dump(DB::table('analytics_events')->selectRaw('event_name, COUNT(*) as total')->groupBy('event_name')->pluck('total','event_name')->all());"
```

### 7.5 Проверка diagnostics log

```bash
php artisan tinker --execute="dump(app(\App\Services\SiteErrorLogService::class)->preview());"
php artisan tinker --execute="dump(app(\App\Services\SiteErrorLogService::class)->listEntries('500', 'client_error', 1, 20));"
```

## 8. Связанные документы

- `README.md` — обзор проекта и основные API.
- `DEPLOY.md` — инструкции развертывания и smoke-check.
- `app/OpenApi/OpenApiSpec.php` — Swagger/OpenAPI аннотации.
- `tests/Feature/AdminAndChatFeatureTest.php` — регрессионные тесты аналитики.
- `tests/Feature/SiteErrorLogFeatureTest.php` — регрессионные тесты lifetime error log, фильтров, export и archive rotation.
- `tests/Feature/SwaggerDocumentationFeatureTest.php` — проверка генерации Swagger.
