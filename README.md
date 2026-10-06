# KemerBot — KemerBet Telegram Bot Management System

A standalone Laravel 12 application containing the official **KemerBet** Telegram bot
(webhook mode, EN/AM bilingual), a **Filament v4 admin panel** for the marketing team,
and a **durable queue-based sending engine**. Capacity must be measured against your deployment.

Current release: [Workspace upgrade](docs/WORKSPACE-UPGRADE.md) · [Installation and upgrade guide](docs/UPGRADE-GUIDE.md) · [Verification and previews](docs/VERIFICATION.md).
The original spec, hardening notes, and build log are historical references; the durable delivery design in this release supersedes their Redis-only snapshot and failures-only storage descriptions.

## Stack

Laravel 12 · PHP 8.4.1+ · PostgreSQL · Redis/Valkey · Filament v4 · Pest.
This source has been verified locally; live deployment and Telegram integration are separate steps.

## Local development

```bash
composer install
cp .env.example .env && php artisan key:generate
docker compose up -d                  # Postgres :5445, Redis :6381 (test DB auto-created)
php artisan migrate:fresh --seed      # owner login: owner@kemerbet.co / password (change via SEED_ADMIN_*)
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
3. **Channel**: add the bot as an **admin** of the KemerBet channel (required for reliable
   `getChatMember` membership checks), then put the channel ID + URL into
   Settings.
4. **Webhook**: `php artisan telegram:set-webhook` (uses `APP_URL`; pass a tunnel URL in
   dev, e.g. `cloudflared tunnel --url http://localhost:8000` then
   `php artisan telegram:set-webhook https://<tunnel-host>`). The command registers the
   secret token and the `allowed_updates` list (message, callback_query, poll,
   poll_answer, my_chat_member) and prints `getWebhookInfo`.

## Architecture in one paragraph

All Telegram HTTP flows through one client and a shared Redis pacer with per-chat spacing.
Webhook requests authenticate and commit updates to a PostgreSQL inbox before dispatch.
Campaigns freeze audiences into a durable recipient ledger. Row leases, fencing tokens,
and transactional outcomes/counters make recorded sends idempotent and interrupted work recoverable.
Automations and personal replies use durable delivery records. Separate queue workers keep
interactive requests moving while campaigns run. Topic preferences, unsubscribe controls,
quiet hours, and daily contact reservations apply to campaigns and journeys.
Telegram itself has no message-send idempotency key: a timeout or a crash after Telegram
accepts a message but before the database commits can still cause an ambiguous duplicate.

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
