# KemerBot — SunBet Telegram Bot Management System

A standalone Laravel 12 application containing the official **SunBet** Telegram bot
(webhook mode, EN/AM bilingual), a **Filament v4 admin panel** for the marketing team,
and a **queue-based sending engine** safe at 100,000+ users.

Spec: `docs/KEMERBOT-SPEC.md` (authoritative) · Standing rules: `CLAUDE.md` ·
Runbook: `docs/OPERATIONS.md` · Build log: `docs/PROGRESS.md`

## Stack

Laravel 12 · PHP 8.4 · PostgreSQL · Redis · Filament v4 · Pest.
Deployed on Laravel Cloud (Serverless Postgres + Valkey).

## Local development

```bash
composer install
cp .env.example .env && php artisan key:generate
docker compose up -d                  # Postgres :5445, Redis :6381 (test DB auto-created)
php artisan migrate:fresh --seed      # owner login: owner@sunbet.et / password (change via SEED_ADMIN_*)
php artisan serve                     # panel at http://localhost:8000/admin
php artisan queue:work redis --queue=telegram-interactive,telegram-broadcast,automation,default
php artisan schedule:work             # scheduler (broadcasts, automations, retention)
```

Tests (never call real Telegram — a full fake is bound in tests):

```bash
./vendor/bin/pest
```

## Telegram & channel setup (spec §17)

1. **BotFather**: create the bot, grab the token, set name/photo/description.
2. **Env**: set `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME` (no @), and a random
   `TELEGRAM_WEBHOOK_SECRET`. Secrets live in env only — never in the DB or panel.
3. **Channel**: add the bot as an **admin** of the SunBet channel (required for reliable
   `getChatMember` membership checks), then put the channel ID + URL into
   Administration → Settings.
4. **Webhook**: `php artisan telegram:set-webhook` (uses `APP_URL`; pass a tunnel URL in
   dev, e.g. `cloudflared tunnel --url http://localhost:8000` then
   `php artisan telegram:set-webhook https://<tunnel-host>`). The command registers the
   secret token and the `allowed_updates` list (message, callback_query, poll,
   poll_answer, my_chat_member) and prints `getWebhookInfo`.

## Architecture in one paragraph

All Telegram HTTP flows through one `TelegramClient`; every send passes a global Redis
token bucket (25 msg/s default, env ceiling, DB-tunable below it). The webhook verifies
its secret, dedupes by `update_id`, and enqueues onto `telegram-interactive` — interactive
traffic never waits behind a broadcast. Broadcasts freeze their audience into a Redis
snapshot and self-chaining chunk jobs consume it with atomic LPOPs (one counter UPDATE
per chunk; failures-only per-user rows). One automation engine (trigger → steps → wait →
send) powers welcome drips and re-engagement with per-row compare-and-swap claims;
recurring broadcasts are template rows materialized by the shared scheduler. Everything
user-facing resolves EN/AM through one `BotLocaleResolver`. All timestamps are stored
UTC and displayed/scheduled in Africa/Addis_Ababa.

## Queues & scheduler (production)

| Worker | Queues |
|---|---|
| interactive (≥1) | `telegram-interactive` — /start replies, menus, callbacks, 1:1 |
| broadcast (1) | `telegram-broadcast` — snapshot prepare + chunk chain |
| automation (≥1) | `automation` — drip/re-engagement step sends |
| default (1) | `default` |

Cron: `* * * * * php artisan schedule:run` — drives `broadcasts:process-due` and
`automations:run` (every minute), `broadcasts:sweep-orphans` (hourly), and the
`telegram_messages` 90-day prune (daily).

## Roles

**Owner** — everything · **Marketer** — content, broadcasts, automations, audience,
polls, tracking links (no admin management, no infrastructure settings) · **Viewer** —
read-only. Enforced server-side with policies; every admin mutation is audited.
