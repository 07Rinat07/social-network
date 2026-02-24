# Technical Review Report (EN)

Date: February 24, 2026  
Project: `social-network`  
Stack: Laravel 10, Vue 3, Reverb, MySQL, Docker, FFmpeg

## 1. Report Purpose

This document is intended for:

- senior-level code review,
- technical interview discussions,
- architectural defense of the project as a product-grade pet project.

It focuses on engineering quality: architecture, security, trade-offs, scalability, and technical debt.

## 2. System Overview

`Solid Social Network` is a SPA social platform that combines:

- social feed (posts, comments, likes, reposts, view counters),
- realtime chat (global/direct/archive with attachments and reactions),
- radio module (search, stream proxy, favorites),
- IPTV module (playlist import + direct/proxy/transcode/relay playback),
- admin panel (moderation + platform settings),
- RU/EN localization and SEO routing.

Architectural intent: keep a modular monolith and isolate domains/services well enough to scale without immediate migration to microservices.

## 3. Architecture and Key Decisions

## 3.1 Architectural Style

- Backend: modular monolith (Laravel).
- Frontend: Vue SPA with route-level guards.
- Realtime: event-driven broadcasting via Laravel Reverb.

## 3.2 Key Engineering Decisions (ADR-level)

1. Stateful auth via Sanctum cookies instead of JWT.
Reason: simpler SPA integration in trusted first-party context, CSRF-first model, lower token lifecycle complexity.

2. Reverb for websocket transport.
Reason: native Laravel broadcasting integration and lower operational overhead than a custom WS service.

3. Multiple IPTV playback modes.
Reason: practical compatibility across unstable streams and CORS constraints:
- `Direct`: lowest server load.
- `Proxy`: URL rewriting + CORS workaround.
- `Transcode`: FFmpeg normalization for unstable/incompatible streams.
- `Relay`: near-pass-through FFmpeg mode.

4. Dynamic media storage policy.
Reason: product flexibility (`server_local`, `cloud`, `user_choice`) and gradual cloud migration.

## 4. Backend Layer Map

Entry points:

- `routes/api.php`
- `routes/web.php`
- `routes/channels.php`

Core middleware/security:

- `app/Http/Kernel.php`
- `app/Http/Middleware/EnsureUserIsAdmin.php`
- `app/Providers/RouteServiceProvider.php`
- `app/Providers/BroadcastServiceProvider.php`

Domain controllers:

- Feed/User: `PostController`, `PostImageController`, `UserController`, `MediaController`
- Chat: `ChatController`
- Radio/IPTV: `RadioController`, `IptvController`
- Admin/Settings: `AdminController`, `SiteSettingController`

Service layer:

- `PostService`
- `SiteSettingService`
- `WorldOverviewService`
- `RadioBrowserService`
- `IptvPlaylistService`
- `IptvProxyService`
- `IptvTranscodeService`

## 5. Critical Request Flows

## 5.1 SPA Authentication Flow

1. Client requests `/sanctum/csrf-cookie`.
2. Login request creates `laravel_session`.
3. API requests are sent with credentials.
4. Axios interceptor retries failed state-changing requests on HTTP 419 after CSRF refresh.

Where:

- `resources/js/bootstrap.js`
- `config/sanctum.php`
- `config/session.php`

## 5.2 Post + Media Flow

1. File upload to `/api/post_media` or `/api/post_images`.
2. Disk resolution through `SiteSettingService`.
3. Post creation with `media_ids`.
4. `PostService::attachMedia` enforces owner-only orphan media attachment.

Security outcome: no foreign media attachment across users (covered by tests).

## 5.3 Chat Message + Realtime Flow

1. Conversation access check.
2. Payload validation.
3. Message and attachment persistence.
4. Read-state/unread logic update.
5. Broadcast event (`ConversationMessageSent`).
6. Client state sync through Echo subscription.

Where:

- `ChatController`
- `app/Events/ConversationMessageSent.php`
- `routes/channels.php`
- `resources/js/components/widgets/PersistentChatWidget.vue`

## 5.4 IPTV Flow

Direct:

- Client plays source URL directly.

Proxy:

- Server session, remote fetch, playlist URL rewrite, segment proxying.

Transcode/Relay:

- FFmpeg process generates HLS playlist and segments with TTL/session controls.

Where:

- `IptvController`
- `IptvPlaylistService`
- `IptvProxyService`
- `IptvTranscodeService`

## 6. Frontend Architecture

Initialization:

- `resources/js/app.js` mounts app + i18n + router.
- `resources/views/welcome.blade.php` serves base SEO/meta shell.

Router:

- Localized route prefix: `/:locale(ru|en)?`
- Guarded routes for auth/guest/verified/admin.
- Canonical/hreflang/robots updates in router lifecycle.

Realtime:

- `window.Echo` initialized in `resources/js/bootstrap.js`.
- Broadcast auth endpoint: `/api/broadcasting/auth`.

Large components:

- `resources/js/components/widgets/PersistentChatWidget.vue`
- `resources/js/components/widgets/PersistentRadioWidget.vue`
- `resources/js/components/IptvPlayer.vue`

## 7. Data Model and Constraints

Core entities:

- Social: `users`, `posts`, `post_images`, `comments`, `liked_posts`, `subscriber_followings`, `post_views`
- Chat: `conversations`, `conversation_participants`, `conversation_messages`, `conversation_message_attachments`, `conversation_message_reactions`, `conversation_mood_statuses`, `user_chat_settings`, `chat_archives`
- Platform/media: `site_settings`, `feedback_messages`, `radio_favorites`, `iptv_seeds`, `iptv_saved_playlists`, `iptv_saved_channels`

Important constraints:

- `liked_posts` unique (`user_id`, `post_id`)
- `post_views` unique (`post_id`, `user_id`, `viewed_on`)
- `conversation_participants` unique (`conversation_id`, `user_id`)
- `conversation_message_reactions` unique (`conversation_message_id`, `user_id`, `emoji`)

Engineering impact: strong relational consistency with predictable read/write patterns.

## 8. Security Deep Dive

## 8.1 Practical Threat Model

- XSS via text fields and encoded markup.
- IDOR on media and chat attachments.
- SSRF via external playlist/stream URLs.
- Unauthorized websocket channel access.
- Admin API privilege escalation.
- CSRF/session collision between environments.

## 8.2 Implemented Controls

XSS:

- `NoUnsafeMarkup` blocks HTML/script patterns, inline handlers, unsafe protocols, and control characters.

IDOR:

- Media endpoints enforce ownership/public/admin access rules.
- Chat attachment delivery requires conversation access.

SSRF:

- `IptvPlaylistService::validateExternalUrl` allows only `http/https`.
- Blocks localhost, private/reserved ranges, and unsafe playlist lines.

Authorization:

- `auth:sanctum` + `verified` for main protected API.
- `admin` middleware for admin namespaces.

Broadcast security:

- Explicit channel authorization in `routes/channels.php`.
- Direct chat channels are denied if active user block exists.

CSRF/cookies:

- Environment-specific cookie names (`SESSION_COOKIE`, `XSRF_COOKIE`).
- Auto CSRF refresh flow in frontend interceptor.

Traffic protection:

- API rate limit set to 60 req/min.
- Verification notification throttle.

## 8.3 Residual Risks / Hardening Opportunities

High:

- No dedicated per-endpoint abuse control for CPU-heavy IPTV operations.

Medium:

- No malware scanning stage for uploaded files.
- Reverb `allowed_origins` should be tightened in production.

Medium/Low:

- At scale, Redis-backed queue/cache/session is required for predictable latency.

## 9. Performance and Scalability

Current hotspots:

- `ChatController` is very large and does too many responsibilities.
- FFmpeg transcode paths are CPU intensive.
- Feed/chat read paths may require additional caching as user volume grows.

What is already done well:

- TTL/session limits for proxy/transcode/relay.
- Reasonable pagination caps and validation limits.
- Indexed filters for feed/chat retrieval.

Scalability plan:

Phase 1:

- Move cache/session/queue to Redis.
- Add dedicated throttles for heavy streaming endpoints.

Phase 2:

- Move FFmpeg orchestration into worker jobs.
- Add lock-based cleanup and operational scheduling.

Phase 3:

- Horizontal Reverb scaling with Redis backplane.
- CDN/object storage lifecycle strategy for media.

## 10. DevOps and Operability

Local:

- Standard Laravel + Vite setup from `.env.example`.

Docker:

- Multi-service `docker-compose.yml`: `app`, `web`, `websocket`, `db`, `frontend-build`.
- Entrypoint performs bootstrap, migrations, FFmpeg validation, storage link checks.

Production:

- Deployment guide in `DEPLOY.md` (Nginx + PHP-FPM + Supervisor + Reverb process).

Recommended improvements:

- Centralized logging and correlation IDs.
- Metrics for API latency, websocket load, FFmpeg session count, and error budgets.
- Backup and restore runbook validation.

## 11. Testing Strategy

Current approach:

- Feature-focused black-box tests around API behavior and access rules.

Key suites:

- `tests/Feature/ApiSecurityRefactorTest.php`
- `tests/Feature/ChatFeatureTest.php`
- `tests/Feature/BroadcastChannelsFeatureTest.php`
- `tests/Feature/IptvFeatureTest.php`
- `tests/Feature/RadioFeatureTest.php`
- `tests/Feature/AdminPanelFeatureTest.php`
- `tests/Feature/SiteSettingsAndDiscoveryFeatureTest.php`

Strengths:

- Strong negative-case coverage for authorization and validation.
- Security-sensitive paths are actively tested (XSS, SSRF, channel auth).
- Streaming error scenarios are covered.

Gaps:

- No full E2E browser automation suite yet.
- No formal load/stress benchmark profile in repository.

## 12. Code Quality and Technical Debt

Strengths:

- Clear domain separation and practical service layer usage.
- Security-aware input and media handling.
- Good infra reproducibility and deployment clarity.
- Meaningful integration-level tests.

Debt:

- `ChatController` complexity and size.
- Inconsistent response envelope patterns across domains.
- Some domain workflows should be extracted into dedicated actions/services.

## 13. Refactoring Roadmap (Senior-Level)

P0:

- Split `ChatController` into focused controllers:
  - conversations
  - messages
  - archives
  - mood status
- Introduce policy-based authorization for core aggregates.

P1:

- Standardize API response contracts and error mapping.
- Introduce explicit DTO/transformer boundaries.
- Apply fine-grained throttling for media/stream routes.

P2:

- Move IPTV session orchestration to job-based subsystem.
- Add measurable performance benchmarks and SLO-aligned reporting.

## 14. Interview Talking Points

1. Why Sanctum over JWT:
- Better fit for first-party SPA with cookie + CSRF model and simpler token lifecycle.

2. How IPTV security is enforced:
- URL scheme/host validation, private-range blocking, unsafe playlist line rejection.

3. Why multiple playback modes exist:
- Operational compatibility strategy, not feature duplication.

4. How chat authorization is protected:
- API checks + channel auth + block-aware direct channel denial.

5. How to scale this architecture:
- Redis-backed state, queued heavy tasks, horizontal realtime, media CDN strategy.

## 15. Reviewer Checklist

Security:

- [ ] Verify media/attachment access isolation.
- [ ] Verify SSRF rejection for localhost/private IP playlist sources.
- [ ] Verify admin route denial for non-admin users.

Correctness:

- [ ] Verify direct chat idempotency.
- [ ] Verify per-day post view deduplication.
- [ ] Verify IPTV session expiration/cleanup behavior.

Realtime:

- [ ] Verify `/api/broadcasting/auth` behavior for private/presence channels.
- [ ] Verify direct channel denial when user blocking is active.

Operations:

- [ ] Validate deployment path from `DEPLOY.md`.
- [ ] Validate Reverb supervision and restart behavior.

## Conclusion

This is a technically credible fullstack project with real product-like complexity:

- multi-domain architecture,
- practical security controls,
- working realtime and streaming subsystems,
- meaningful feature-level test coverage,
- reproducible local/docker/deploy workflows.

For strong middle/fullstack interviews, the project is already solid.  
For senior positioning, the next step is structural decomposition of chat domain, stronger observability, and formal performance engineering.
