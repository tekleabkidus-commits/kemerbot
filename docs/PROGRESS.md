# KemerBot — Progress Log

Spec: `docs/KEMERBOT-SPEC.md` (Spec v2, authoritative). Process: 4 batches per spec §15;
each batch ends with green tests, a batch report, and a stop for user review.

## Status

| Batch | Scope | Status |
|---|---|---|
| Setup | git, CLAUDE.md, docker-compose, plan | ✅ Done (2026-08-17) |
| 1 — Foundation & schema | Laravel 12 + Filament v4, all migrations, models, enums, factories, seeders, Pest | ✅ Done (2026-08-17) — 85 tests green |
| 2 — Bot core | TelegramClient + fake, webhook pipeline, /start + attribution, membership, menus, keywords | Pending |
| 3 — Admin panel + broadcast engine | Filament resources + 7-step wizard + lifecycle + sender | Pending |
| 4 — Automations + growth + extras | Automation engine, tracking links, referral-lite, dashboard, polls, docs | Pending |

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
