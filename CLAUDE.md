# KemerBot — Standing Rules (read before any change)

KemerBot is the KemerBet Telegram Bot Management System — a production marketing system for
100,000+ Telegram users. Source of truth: `docs/KEMERBOT-SPEC.md` (Spec v2, authoritative).
Do **not** re-open settled decisions or re-audit the architecture; raise only genuine blockers.

Internal name **KemerBot**; every user-facing string is branded **KemerBet** (kemerbet.co).

## Architectural principles (non-negotiable)

1. **One automation engine.** Welcome drips, inactive re-engagement, and recurring-broadcast
   scheduling share one trigger → steps → wait → send machinery. Never parallel schedulers.
2. **One `/start` payload parser.** A single code path for source codes, `ref_<id>`, invalid,
   malformed, and unknown payloads.
3. **No per-recipient successful-delivery rows for broadcasts.** Counters live on the broadcast;
   only failures are logged per user. Documented exceptions only: `automation_user_states`,
   `poll_instances`, `telegram_messages` (1:1 + inbound only), `broadcast_failures`, `button_clicks`.

## Hard rules

1. All timestamps stored UTC; display/schedule in `Africa/Addis_Ababa` (`APP_DISPLAY_TIMEZONE`).
2. Bot token + webhook secret live in env only — never in DB, UI, logs, or audit metadata.
   Settings screen shows "Telegram Bot: Connected ✓" only.
3. Every user-facing bot string is EN + AM with fallback to EN through one `BotLocaleResolver`.
   Fallback logic exists in exactly one place.
4. All Telegram HTTP goes through `TelegramClient`; all sends pass the global Redis
   `TelegramRateLimiter` (default 25 msg/s, `TELEGRAM_SEND_RATE`); honor 429 `retry_after`;
   403 marks `blocked_bot` (+ recovery on any successful inbound update).
5. Server-side authorization with policies on every mutation; every admin mutation writes an
   audit log (actor, action, subject, safe metadata — never secrets).
6. Tests are mandatory per spec §14, with a full Telegram fake — tests never call real Telegram.
7. Never silently skip security, tests, indexes, constraints, translations, or queue recovery.
   Never replace real functionality with hidden placeholders.

## Settled design decisions (spec §4 — implement as stated, do not re-open)

1. UTC storage; Addis Ababa display/scheduling. "Every Saturday 09:00" = 09:00 Addis time.
2. The editable welcome message is the `/start` reply; welcome-drip steps require
   `delay_hours > 0`. A new user never gets two instant welcomes.
3. Recurring broadcasts are `broadcasts` rows with a `recurrence` definition run by the shared
   scheduler. Automation triggers are `user_joined` and `inactive` only.
4. `delay_hours` = delay since the **previous step** (step 0 relative to trigger time).
5. Attribution is first-touch and immutable: `source` set only if empty; `referred_by_user_id`
   set only if empty, must reference an existing *different* user; later `/start` never rewrites.
6. Click tracking is local: `/r/{code}` increments the counter and 302-redirects to
   `t.me/<bot>?start=<code>` only. No external shortener dependency; no open redirects.
7. Secrets in env/infrastructure only (see hard rule 2).
8. Match promo card = admin-uploaded image + structured caption (teams, kickoff in Addis time,
   odds, CTA). No server-side image generation.
9. Keyword match priority: exact → longest `contains` → lowest position. Input normalized
   (case, trim, Unicode NFC; Amharic-safe). Deterministic always.
10. Automation pause freezes enrollments in place; resume continues them. Never discarded.
11. Blocked recovery: any successful inbound update from a `blocked_bot` user clears the flag.
12. Translations: relational `_translations` tables (`lang` = `en`|`am`) for menu items, keyword
    replies, broadcasts, broadcast buttons, automation steps. Settings-level texts and poll
    question/options use `jsonb` language maps.
13. Media reuse: `media_files` row per upload; persist Telegram `file_id` on first successful
    send and reuse everywhere.
14. Sender jobs chunk users (~25–50) with **one atomic counter UPDATE per chunk**;
    `sent + blocked + failed ≤ queued` must always hold.

## Build process — BATCHES, not fragments

Build in the 4 batches of spec §15 (1 Foundation/schema · 2 Bot core · 3 Admin panel +
broadcast engine · 4 Automations/growth/extras). Within a batch keep momentum — no per-file
approvals. At the end of every batch: full test suite → update `docs/PROGRESS.md` → batch
report (built, tests, decisions, remaining) → **stop and wait for user review**.
Batch 1 delivered the final schema — later batches add no tables.

## Quality bar

Laravel 12 idioms; strict types where practical; enums, DTOs, actions, service classes, events,
jobs, policies, Form Requests; domain-oriented layout under `app/`; no giant controllers or
Filament resources; no business logic in views; no magic numbers; PostgreSQL used intentionally
(BIGINT Telegram IDs, `jsonb`, real constraints + indexes per spec §6). Package policy:
Laravel-native wherever reasonable; justify any third-party addition.

## Local dev

Docker Desktop provides Postgres + Redis: `docker compose up -d`. Queue/cache/session on Redis
(phpredis client). Tests: `php artisan test` (Pest). DB for tests: `kemerbot_test` (same container).
