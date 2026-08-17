# KemerBot — Claude Code Kickoff Prompt
(Paste everything below this line into Claude Code, running inside ~/projects/kemerbot with docs/KEMERBOT-SPEC.md already in place.)

---

You are the principal architect and senior Laravel + Telegram-bot engineer building **KemerBot**, the KemerBet Telegram Bot Management System — a production marketing system, not a prototype. It must comfortably manage 100,000+ Telegram users.

## Source of truth

Read `docs/KEMERBOT-SPEC.md` completely before writing any code. It is Spec v2: the architectural audit has already been performed and its corrections are baked in (automation_user_states, broadcast_failures, telegram_messages, settings, poll_instances, /r/{code} click redirect, first-touch attribution, timezone rules, all "settled design decisions" in §4). **Do not re-open settled decisions and do not perform a fresh full audit.** Raise only genuine implementation blockers; everything else gets a standard production-safe default.

## Hard rules (also write these into CLAUDE.md in setup)

1. One automation engine. One /start payload parser. No per-recipient successful-delivery rows for broadcasts — only the documented exceptions in spec §3.
2. All timestamps UTC; display/schedule in Africa/Addis_Ababa.
3. Bot token + webhook secret live in env only — never in DB, UI, logs, or audit metadata.
4. Every user-facing bot string is EN + AM with fallback to EN through one BotLocaleResolver.
5. All Telegram HTTP goes through TelegramClient; all sends pass the global Redis TelegramRateLimiter (default 25 msg/s); honor 429 retry_after; 403 marks blocked_bot.
6. Server-side authorization with policies on every mutation; every admin mutation writes an audit log.
7. Tests are mandatory per spec §14, with a full Telegram fake — tests never call real Telegram.
8. Never silently skip security, tests, indexes, constraints, translations, or queue recovery. Never replace real functionality with hidden placeholders.

## How we work: BATCHES, not fragments

Build in exactly the 4 large batches defined in spec §15 — do not decompose into tiny feature-by-feature deliveries:

- **Setup (same session, before Batch 1):** git init; create `CLAUDE.md` (these hard rules + spec §4 settled decisions + this batch process), `docs/PROGRESS.md`, `docker-compose.yml` for local Postgres + Redis; then post a short plan (migration list + folder structure per spec §6 and the suggested domain layout) and continue straight into Batch 1 unless something truly blocks.
- **Batch 1 — Foundation & schema:** full Laravel 12 + Filament v4 install, ALL migrations (final schema — later batches add no tables), models, enums, relationships, factories, seeders, config, .env.example, Pest setup, model/constraint tests.
- **Batch 2 — Bot core:** TelegramClient + fake, rate limiter, webhook pipeline (secret + dedupe), /start + payload parser + attribution, membership service + join gate, locale resolver, menu rendering + callback protocol, keyword replies, welcome, blocked handling, inbound message capture, queues.
- **Batch 3 — Admin panel + broadcast engine:** Filament resources (menus tree editor, auto replies, users, settings, admins/roles/policies, audit log) + the complete broadcast engine (7-step wizard, lifecycle service, Redis audience snapshot, chunked sender, failures, counters, test send, scheduling, recurrence, cancellation, media file_id reuse, personalization, click tracking).
- **Batch 4 — Automations + growth + extras:** automation engine + user states + drips + re-engagement, tracking links + /r/{code}, referral-lite + leaderboard, dashboard + caching, match promo cards, native polls + poll_instances, Mini App button, user profiles + 1:1 messaging, UX polish (empty states, progressive validation), observability, OPERATIONS.md + README docs.

**At the end of every batch:** run the full test suite, update docs/PROGRESS.md, and give me a batch report — what was built, test results, key decisions, anything remaining — then **STOP and wait for my review** before starting the next batch. Within a batch, keep momentum: don't stop for approval on individual files or features.

## Batch acceptance bars

Use the "Accept when" criteria in spec §15 for each batch. Batch 4 ends with the production-readiness review from spec §15 (architecture, database/indexes, performance, security, reliability, Telegram behavior, UX, tests, deployment) — every category PASS or explained; no unresolved FAIL.

## Quality bar

Laravel 12 idioms; strict types where practical; enums, DTOs, actions, service classes, events, jobs, policies, Form Requests; the domain layout suggested in the spec (improve it if justified); no giant controllers or Filament resources; no business logic in views; no magic numbers; PostgreSQL types used intentionally (BIGINT for Telegram IDs, jsonb, real constraints + indexes from spec §6).

## Start now

1. Confirm you have read docs/KEMERBOT-SPEC.md in full (one line).
2. Do Setup.
3. Post the short plan.
4. Begin Batch 1.
