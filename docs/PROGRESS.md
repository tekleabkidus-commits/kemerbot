# KemerBot — Progress Log

Spec: `docs/KEMERBOT-SPEC.md` (Spec v2, authoritative). Process: 4 batches per spec §15;
each batch ends with green tests, a batch report, and a stop for user review.

## Status

| Batch | Scope | Status |
|---|---|---|
| Setup | git, CLAUDE.md, docker-compose, plan | ✅ Done (2026-08-17) |
| 1 — Foundation & schema | Laravel 12 + Filament v4, all migrations, models, enums, factories, seeders, Pest | ✅ Done (2026-08-17) — 85 tests green |
| 2 — Bot core | TelegramClient + fake, webhook pipeline, /start + attribution, membership, menus, keywords | ✅ Done (2026-08-17) — 189 tests green |
| 3 — Admin panel + broadcast engine | Filament resources + 7-step wizard + lifecycle + sender | ✅ Done (2026-08-17) — 257 tests green |
| 4 — Automations + growth + extras | Automation engine, tracking links, referral-lite, dashboard, polls, docs | ✅ Done (2026-08-17) — 310 tests green |

## Log

### 2026-08-17 — Setup
- git init (branch `main`); `CLAUDE.md` written (principles, settled decisions, batch process, quality bar).
- `docker-compose.yml`: Postgres 17 (host port **5445**) + Redis 7 (host port **6381**) — 5432/6379/5433
  were taken by other local projects. Test DB `kemerbot_test` auto-created via init SQL.
- Environment caveat: host PHP is missing `intl`, `zip`, `gd`, `bcmath`, `redis` extensions (no sudo in
  session). Workarounds in place: `unzip` binary for composer, `predis` for Redis, composer
  `config.platform` entries for ext-intl/ext-zip. Everything runs (85 tests green, panel serves 200),
  but before Batch 3 the user should run:
  `sudo apt-get install -y php8.4-intl php8.4-zip php8.4-gd php8.4-bcmath php8.4-redis`
  and then remove the two `config.platform` lines from `composer.json`. Filament's number/date
  formatting (`NumberFormatter`) has no polyfill and will need real ext-intl.

### 2026-08-17 — Batch 1: Foundation & schema
- Laravel 12.66 + Filament v4 + predis + Pest 4 installed. Default auth points at `App\Models\Admin`
  (panel accounts); `users` is the bot audience per spec §6.
- **Final schema delivered — later batches add no tables.** 22 domain migrations + admins/framework:
  users, admins (+password_reset_tokens, sessions), audit_logs, settings, media_files, menu_items,
  menu_item_translations, keyword_replies, keyword_reply_translations, broadcasts,
  broadcast_translations, broadcast_buttons, broadcast_button_translations, broadcast_failures,
  automations, automation_steps, automation_step_translations, automation_user_states,
  telegram_messages, button_clicks, tracking_links, polls, poll_instances (+ jobs/failed_jobs/cache).
- All spec §6 critical indexes, FK delete rules, unique constraints, and CHECK constraints
  (lang ∈ {en,am}, role enum, non-negative broadcast counters) in place. BIGINT Telegram IDs; jsonb
  everywhere spec requires. `tg_poll_id` is varchar (Telegram poll ids are strings in the Bot API).
- 23 models with enum casts + relationships, 12 backed enums, 23 factories with states,
  AdminSeeder (owner, idempotent) + SettingsSeeder (bilingual welcome, non-clobbering).
- config/telegram.php (env-only secrets, send rate, chunk size, queues, TTLs); .env.example per §16;
  `app.display_timezone` = Africa/Addis_Ababa; storage timezone stays UTC.
- Key decisions (column-level, within spec):
  - `keyword_replies.buttons` jsonb added — §5.1 promises optional buttons on keyword replies but
    table 7 omitted a column; same embedded shape as `automation_steps.buttons`.
  - `sent+blocked+failed ≤ queued` enforced in service layer + tests (Batch 3), not as a DB CHECK,
    because spec §7's documented resume edge permits one re-sent chunk after worker death.
    Non-negativity IS a DB CHECK.
  - `polls.broadcast_id` unique (one poll definition per poll broadcast).
  - `created_by`/`admin_id` FKs are nullOnDelete so campaign history and audit trail survive
    admin deletion; audit_logs has created_at only (append-only).
- Tests: **85 passed (184 assertions)** on real Postgres (kemerbot_test) — uniques, FK cascade/null
  rules, CHECK constraints, jsonb round-trips (incl. Amharic), enum casts, seeders, every factory,
  panel login (guest 200, guest redirect, authed admin 200).
- `migrate:fresh --seed` green; `/admin/login` serves HTTP 200; Pint clean.

### 2026-08-17 — Extensions chore
- Real `intl/zip/gd/bcmath/redis` extensions installed by user; composer `config.platform`
  overrides removed; `composer update` clean; switched `REDIS_CLIENT` to phpredis and
  removed the now-redundant `predis` package.

### 2026-08-17 — Batch 2: Bot core
- **Telegram layer** (`app/Services/Telegram/`): `TelegramClient` interface (the only Telegram
  HTTP doorway), `TelegramResponse` envelope with error classification (403 blocked / 429 +
  retry_after / retryable network / permanent), `HttpTelegramClient` (token scrubbed from all
  logs/errors; multipart upload for local media), `FakeTelegramClient` (full §14 fake: records
  calls, per-chat canned failures, membership control), `TelegramRateLimiter` (global Redis
  token bucket, env rate = hard ceiling, DB setting may lower it, 429 pauses the whole bucket
  — the only place throttle sleeps live), `TelegramMembershipService` (state normalization,
  TTL cache on user row, fail-open on Telegram errors).
- **Bot services** (`app/Services/Bot/`): `BotLocaleResolver` (THE one fallback point: user lang
  → en/am, translations rows, jsonb maps, and lang/-file UI strings), `TokenRenderer`
  ({first_name}, unknown tokens → empty), `UserService` (upsert/refresh, first-touch immutable
  attribution + joins_count, activity + blocked recovery), `KeywordMatcher` (NFC + casefold,
  exact → longest contains → lowest position), `MenuRenderer` (inline keyboards, auto Back,
  url/webapp buttons), `BotMessageSender` (rate limit → send → 429 pause+retry → 403 mark
  blocked → persist media file_id), `WelcomeService` (welcome = the /start reply, join gate),
  `InboundMessageRecorder` (telegram_messages capture), `SettingsService` (cached DB settings).
- **Webhook pipeline**: `VerifyTelegramWebhookSecret` (constant-time, rejects fast) → controller
  (safe parse, Redis SETNX dedupe by update_id, enqueue `ProcessTelegramUpdate` on
  telegram-interactive, always 200 after auth) → `TelegramWebhookProcessor` (message /
  callback_query / my_chat_member; poll types logged for Batch 4; unknown types logged+ignored).
- **Handlers**: `StartCommandHandler` (upsert → ONE `StartPayloadParser` → attribution →
  UserJoined for new users only → gate or welcome+menu; fully idempotent), `IncomingMessageHandler`
  (capture, gate, keyword replies; silent on no match), `CallbackQueryHandler` (validated
  `CallbackData` protocol menu:/join:check/bc:/invite:show; spinner ALWAYS answered; bc clicks
  recorded to button_clicks; invite:show defers to Batch 4).
- `UserJoined` event, `telegram:set-webhook` artisan command, `MenuItem::wouldCreateCycle`
  guard, CSRF exemption for the webhook route.
- Key decisions: unknown-but-sane /start codes are stored as `source` (marketing may use codes
  without tracking links) — joins_count only increments for active tracking links; malformed
  `ref_*` payloads are Invalid, never source codes; group-chat messages ignored; `my_chat_member`
  kicked/member updates flip blocked_bot immediately (cheaper than waiting for a failed send).
- Tests: **189 passed (387 assertions)** — webhook secret + dedupe + malformed payloads, full
  /start & attribution matrix, en/am/fallback, membership (member/non-member/error fail-open/
  TTL/re-check), menus (reply/submenu/url/webapp/back/inactive/hostile callbacks/cycles),
  keywords (exact/contains/priority/ties/Amharic NFC/fallback/silence/buttons), blocked
  (403→flag, skip sends, inbound recovery, my_chat_member), inbound capture, rate limiter
  (global cap, window, ceiling, pause, 429 retry), parser/callback/token unit tables.
- Local dev workers: `php artisan queue:work redis --queue=telegram-interactive,telegram-broadcast,automation,default`
  (one command; production splits per-queue workers — documented fully in Batch 4 OPERATIONS.md).
  Webhook registration: `php artisan telegram:set-webhook [tunnel-url]`.

### 2026-08-17 — Pre-Batch-3 fixes
- `TrackingLink::CODE_PATTERN` is the single code-validation rule, shared by the /start parser
  (and Batch 3+ admin validation). Tests assert `allowed_updates` ⊇ every update type the
  webhook processor handles (with a source-sync guard).
- **Carried forward:** live webhook tunnel test (Batch 2 manual acceptance) — pending user run:
  `.env` bot credentials → tunnel → `php artisan serve` + `queue:work` → `telegram:set-webhook <url>`.

### 2026-08-17 — Batch 3: Admin panel + broadcast engine
- **Broadcast engine** (`app/Services/Broadcasts/`): `BroadcastLifecycle` — sole owner of the
  status machine (atomic DB claim = double-send guard; invalid transitions throw); `AudienceQuery`
  (all §5.3 filters, blocked always excluded, same code powers wizard live-count + snapshot);
  `AudienceSnapshot` (Redis list of userId:chatId, cursor-fed, atomic LPOP chunks, retry re-queue
  with attempts hash, cleanup on terminal states); `BroadcastChunkSender` (render → global rate
  limiter → classify: 403 blocked+flag / 429 pause+retry / retryable re-queued to tail up to max
  attempts / permanent → broadcast_failures upsert; ONE atomic counter UPDATE per chunk);
  `BroadcastRenderer` (per-user locale + {tokens} + click-tracked bc: keyboards + match-card
  caption with Addis kickoff); `BroadcastTestSender` (EN render, [TEST] prefix, no counters);
  `BroadcastScheduler` + `NextOccurrenceCalculator` (Addis-time daily/weekly/monthly with month-end
  clamping; recurring parents act as templates: due tick clones a one-off child + re-arms parent);
  jobs `PrepareBroadcastJob` / self-chaining `SendBroadcastChunkJob` on telegram-broadcast queue;
  `broadcasts:process-due` every minute + daily telegram_messages pruning (Prunable).
- **Panel** (Filament v4): navigation groups per §12; `BroadcastResource` with the 7-step wizard
  (Type→Content→Buttons→Audience→Timing→Test→Review) incl. live audience estimate + summary,
  EN preview, in-wizard test send from unsaved state, Addis-time pickers with past dates blocked,
  progressive validation; table lifecycle actions (Send w/ double-click guard, Test, Cancel with
  sent-before-cancel, Duplicate); Menus (level-scoped drag reorder, cycle validation, submenu-
  needs-children rule, Telegram-style preview modal); Auto Replies (tabs EN/AM, media, url
  buttons); Users (search + filters + basic profile); Admins; read-only Audit Log; Settings page
  ("Telegram Bot: Connected ✓", owner/marketer key split enforced server-side).
- **AuthZ + audit**: policies for every resource (Owner/Marketer/Viewer per §5.9), enforced
  server-side; one `AuditsAdminMutations` observer (admin-context only — bot/scheduler traffic
  is never audited) + explicit lifecycle/settings audit events; `AuditLogger` strips
  password/token/secret keys from metadata.
- Key decisions:
  - Retryable-send backoff is positional (re-queue to snapshot tail + attempts hash in Redis)
    instead of sleeping workers — honors "retry with backoff up to max attempts" without stalling
    the pipeline; 429 handled separately by pausing the global bucket.
  - 403s during a send get a `broadcast_failures` row (category blocked) AND the blocked counter.
  - Recurring = template parent + materialized one-off children (stats live on children).
  - Poll type visible in the wizard but disabled with an honest "arrives next batch" note.
  - Broadcast edit is locked once sending starts (draft/scheduled only).
- Tests: **257 passed (614 assertions)** — every audience filter; snapshot freezing; chunked
  end-to-end sends; 403/429/network/invalid classification; retry-then-succeed and
  retry-exhausted; counter invariant; resume after simulated worker death; cancellation
  mid-send; double-send prevention; empty audience; media file_id reuse across a broadcast;
  scheduling (past blocked, due promotion, recurring materialization with Addis→UTC checks,
  calculator matrix); renderer (locale fallback, tokens, buttons, match card); test send;
  click tracking incl. hostile payloads; full panel smoke (all pages render, role gating);
  Livewire wizard end-to-end (send now / scheduled / recurring / validation); audit coverage.

### 2026-08-17 — Batch 4: Automations, growth, extras
- **Automation engine**: `AutomationEnroller` (UserJoined listener → welcome drips with
  constant trigger_key = once ever; scheduler pass enrolls inactive users with date-scoped
  keys, ≥1-day effective cooldown, never while active/cooldown), `AutomationRunner`
  (per-row compare-and-swap claim: one UPDATE guarded on id+status+current_step_no+
  next_step_at advances the state — a racing tick's CAS matches zero rows, proven by test),
  `SendAutomationStepJob` (localized, tokened, embedded url buttons, global rate limiter,
  per-step sent_count, blocked-skip, paused-skip). Pause freezes/resume continues (§4.10).
- **Growth**: `/r/{code}` redirect (route-constraint + CODE_PATTERN, active links only,
  Location built solely from config bot username + validated code — open-redirect-proof by
  construction, hostile-code test matrix); TrackingLink resource (code immutable once
  traffic, server-side); invite menu action (constraint migration) + invite:show handler
  (personal ref link, EN/AM share text, feature toggle) + TopReferrers leaderboard widget.
- **Polls**: wizard poll type live (question/options EN/AM, 2–10, anonymity); chunk sender
  sends localized native polls and records poll_instances; ingestion: `poll` updates are
  the single counting source (authoritative per-instance counts in a Redis hash, summed
  into polls.answer_counts — retraction-safe, no double counting), `poll_answer` marks
  voter activity/blocked-recovery only; read-only Polls resource with results/percentages.
- **Dashboard**: `DashboardMetrics` (5-min cache; Addis-day bucketing with zero-fill) +
  widgets: KPIs, daily joins & blocked trend lines, source bars (single validated hues
  #d97706/#4f46e5, light+dark checks pass), broadcast performance, engagement, recent
  campaigns, top referrers, SystemHealth (live Redis: queue depths, running/failed
  broadcasts, last webhook OK, last TG API error — breadcrumbs written by webhook + client).
  Cold load ≈ 12 indexed aggregate queries; warm loads zero SQL.
- **Profiles + 1:1**: full user profile (engagement, automation journeys, polls received,
  conversation view) + Send message action → `SendDirectMessageJob` on telegram-interactive,
  recorded in telegram_messages, audited.
- **Retention**: telegram_messages Prunable (90d, daily), snapshot 48h TTL backstop at
  build + `broadcasts:sweep-orphans` hourly (terminal/missing broadcasts).
- **Schema fixes (column/constraint level, flagged)**: `automation_steps.sent_count`
  (spec §5.5 required per-step counters; table 15 omitted it) and `invite` added to the
  menu action check constraint (spec §5.6 names it a menu action). No new tables.
- Settings audits now record old→new values (safe by design: no secrets in settings).
- Docs: README (setup, tunnel webhook, worker/scheduler table) + docs/OPERATIONS.md (§18
  runbook: stuck broadcasts, retries, cancellation, pause, token rotation, channel change,
  Redis restart, deploy-during-broadcast, Laravel Cloud checklist).
- Known deviations (documented): per-user "polls answered" not stored (schema-minimal
  poll_instances) — profile shows polls received; anonymous-poll per-instance counts live
  in Redis (lost counts re-converge from later updates); automation step is at-most-once
  after claim (scheduler death between claim and dispatch skips one step — OPERATIONS.md).
- Tests: **310 passed (747 assertions)**.
- Carried forward: live webhook tunnel test (needs real bot token; instructions in README).
