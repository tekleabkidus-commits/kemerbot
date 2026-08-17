# KemerBot — SunBet Telegram Bot Management System
## Detailed System Documentation (Spec v2 — authoritative)

**Internal project name:** KemerBot (repo `~/projects/kemerbot`)
**User-facing brand:** SunBet — all bot-visible names, welcome text, and links present as SunBet (site: sunbet.et).
**Status of this document:** This spec supersedes the earlier 14-table planning spec. The architectural audit has already been performed; its corrections (automation user state, failure log, message history, settings, poll correlation, click redirect, attribution rules) are baked in below. Do **not** redo a full audit — flag only genuine blockers.

---

## 1. What this system is

A standalone Laravel application containing:

1. The official **SunBet Telegram bot** (webhook mode) — menus, force channel join, keyword auto-replies, EN/AM bilingual.
2. A **Filament admin panel** for the marketing team — menu builder, broadcast center, automations, tracking links, users, analytics — operable by non-technical marketers with zero code deploys.
3. A **queue-based sending engine** safe at 100,000+ users, designed so scaling well beyond that does not require a rewrite.

**Standalone means standalone:** no sportsbook backend integration, no referral-platform integration, no SunBet account integration, no dependency on any other SunBet application. BotFather is used once (create bot, name, photo); everything else lives here.

---

## 2. Stack & environment

| Layer | Choice |
|---|---|
| Framework | Laravel 12, PHP 8.4 |
| Database | PostgreSQL (Laravel Cloud Serverless Postgres in production) |
| Queue / cache | Redis (Valkey on Laravel Cloud — Redis-protocol compatible) |
| Admin panel | Filament v4 |
| Telegram | Bot API, **webhook mode** (never polling in production) |
| Scheduler | Laravel Scheduler (cron every minute) |
| Tests | Pest |
| Deploy | Laravel Cloud (same pattern as the sunagent project) |
| Dev machine | WSL Ubuntu — PHP 8.4, Composer 2.10, Node 20, Docker Desktop (Postgres + Redis via docker compose for local dev) |

**Package policy:** Laravel-native wherever reasonable. Before adding any third-party package, justify: why Laravel can't do it, why the package is trustworthy/maintained, what happens if abandoned.

---

## 3. Architectural principles (non-negotiable)

1. **One automation engine.** Trigger → ordered steps → wait → send. Welcome drips, inactive re-engagement, and the recurring-broadcast scheduler all run through shared machinery. Never three separate schedulers.
2. **One `/start` payload parser.** A single code path handles source codes, `ref_<id>` codes, invalid, malformed, and unknown payloads. Never two parsing paths.
3. **No per-recipient successful-delivery rows for broadcasts.** A 100k-user broadcast must not create 100k rows. Counters live on the broadcast; only failures are logged per-user. Documented exceptions (allowed because correctness requires them): `automation_user_states`, `poll_instances`, `telegram_messages` (1:1 and inbound only, never mass-broadcast copies), `broadcast_failures`, `button_clicks`.

---

## 4. Settled design decisions (do not re-open; implement as stated)

1. **Timezone:** store all timestamps UTC. Display and schedule in `Africa/Addis_Ababa` via `APP_DISPLAY_TIMEZONE` env. "Every Saturday 09:00" means 09:00 Addis time.
2. **Welcome vs drip:** the editable welcome message is the `/start` reply. Welcome-drip automation steps must have `delay_hours > 0`. A new user never receives two instant welcomes.
3. **Recurring broadcasts** are `broadcasts` rows with a `recurrence` definition, executed by the shared scheduler service. The automation engine's triggers are `user_joined` and `inactive` only; recurring campaigns do NOT create automation records.
4. **`delay_hours` semantics:** delay since the **previous step** (step 0 relative to trigger time). Documented and tested.
5. **Attribution is first-touch and immutable.** `source` set only if empty; `referred_by_user_id` set only if empty, must reference an existing *different* user (self-referral blocked); a later `/start` never rewrites either.
6. **Click tracking is local.** `https://<app-domain>/r/{code}` increments the tracking link's click counter and 302-redirects to `t.me/<bot>?start=<code>`. No dependency on the external SunBet URL shortener (it can optionally sit in front later). No user identification during the anonymous pre-Telegram click.
7. **Secrets live in env/infrastructure only.** Bot token, webhook secret, encryption keys never appear in the admin UI, database, logs, or audit metadata. Settings screen shows "Telegram Bot: Connected ✓" only.
8. **Match promo card = Option A:** admin-uploaded image + structured, consistently formatted Telegram caption (teams, kickoff shown in Addis time, odds, CTA button). No server-side image generation.
9. **Keyword match priority:** exact match → longest `contains` match → lowest position. Input normalized (case, trim, Unicode NFC; Amharic-safe). Deterministic always.
10. **Automation pause/resume:** pausing freezes enrollments in place (no sends); resuming continues existing enrollments from where they stopped. Enrollments are not discarded.
11. **Blocked recovery:** any successful inbound update from a `blocked_bot` user clears the flag (they unblocked).
12. **Translation architecture:** relational `_translations` tables (`lang` = `en`|`am`) for the five content-bearing entities — menu items, keyword replies, broadcasts, broadcast buttons, automation steps. Settings-level texts (welcome message) and poll question/options use `jsonb` language maps (`{"en": …, "am": …}`). One `BotLocaleResolver` service performs user-language resolution with fallback to English; fallback logic exists in exactly one place.
13. **Media reuse:** every uploaded media file gets a `media_files` row; on first successful Telegram send the returned `file_id` is persisted and reused for all subsequent sends. Applies to broadcast, menu, keyword, automation, and welcome media.
14. **Counters:** sender jobs process users in chunks (~25–50) and apply **one atomic counter UPDATE per chunk**, not per message. `sent + blocked + failed ≤ queued` must always hold.

---

## 5. Modules

### 5.1 Bot core
- `/start`: create-or-update user (tg_chat_id unique; refresh first_name/username/language if changed), parse payload (Principle 2), apply attribution (Decision 5), fire `UserJoined` event for automations (new users only), check channel membership, then welcome + main menu or the join gate.
- **Force channel join:** non-members of the SunBet channel see per-language text + "Join channel" (URL button) + "I've joined ✅" (callback that re-checks). Membership handled by `TelegramMembershipService`: normalizes Telegram member states, updates `users.in_channel` + `channel_checked_at`, degrades gracefully on Telegram errors (fail-open with logged warning rather than locking all users out). Membership older than a configurable TTL is re-checked on next interaction. Setup docs must state the bot needs admin rights in the channel for reliable `getChatMember`.
- Menus render as inline keyboards in the user's language (Decision 12), Back button auto-added on submenus.
- `last_active_at` updated on every inbound interaction.
- **Keyword auto-replies** per Decision 9; per-language reply text, optional media + buttons; active flag.
- 403 on any send → `blocked_bot = true`, `blocked_at = now()`, excluded from all sends (Decision 11 for recovery).

### 5.2 Menu builder (admin)
Hierarchical tree editor: drag reorder, create, duplicate, activate/deactivate, delete (with confirmation), EN + AM labels, action selector. Actions: **reply** (text/photo, per-language), **submenu**, **external URL**, **Web App** (Mini App → sunbet.et). Validation prevents invalid configs: submenu requires children to activate, url requires valid URL, reply requires content, no circular parents. Live Telegram-style preview. Changes effective immediately.

### 5.3 Broadcast center (admin)
Seven-step wizard: **Type** (Standard / Match promo / Poll) → **Content** (EN tab, AM tab, media upload, Telegram-style preview, personalization preview, character-limit warnings) → **Buttons** (visual inline-keyboard builder: rows, drag, URL/callback, per-language labels) → **Audience** (filters with live estimated count + human-readable summary) → **Timing** (now / scheduled / recurring; Addis-time picker; past dates blocked) → **Test** (send to configured admin test recipients) → **Review & confirm**.

Audience filters: everyone · joined after/before · active last N days · inactive N days · language · source · channel status. Blocked users always excluded automatically. Filters stored as `jsonb`.

Personalization: `{first_name}` minimum, via a reusable token renderer; unknown tokens render safely; output escaped correctly for the chosen Telegram parse mode; extensible for future tokens.

Duplicate & resend from history. Cancellation per §7. Per-broadcast stats: queued / sent / blocked / failed + per-button clicks.

### 5.4 Broadcast engine (see §7 for deep spec)

### 5.5 Automation engine (see §8 for deep spec)
Two triggers: `user_joined` (welcome drip) and `inactive` (`last_active_at ≤ now − N days`, default 14, with cooldown). Steps: wait + per-language content + optional buttons + optional media. Pause/resume per Decision 10. Per-step sent counters.

### 5.6 Growth & attribution
- **Tracking links:** code (validated: length, safe charset, unique, immutable once traffic exists) + friendly name + optional campaign description + active flag. System displays the deep link and the local `/r/{code}` short link. Dashboard columns: clicks, joins, conversion %. Renaming a campaign never rewrites attribution.
- **Referral-lite:** "Invite friends" menu action shows the user's personal `t.me/<bot>?start=ref_<user_id>` link with per-language share text. Rules: self-referral blocked, first referrer wins, one increment per referred user ever, repeated `/start` never re-increments, invalid/unknown ids ignored silently. Admin leaderboard: referrer, name, username, referral count, joined, last active. **No wallet, no rewards, no financial accounting.**

### 5.7 Users & 1:1 messaging (admin)
User list: search name/username/chat_id; filters: source, language, activity, blocked, channel membership. Profile page: identity (chat id, name, username, language) · attribution (source, referred by) · activity (joined, last active, blocked status, channel membership + last check) · engagement (clicks, referrals, automation history, polls answered) · conversation (recent inbound messages + admin replies from `telegram_messages`). Direct message: permission-checked, sent via the shared sender abstraction on the interactive queue, recorded in `telegram_messages`, audited. This is a light 1:1 tool — explicitly **not** a support-desk/Zendesk system.

### 5.8 Analytics dashboard
KPI cards: total users, new today, 7-day active, 30-day active, blocked. Charts: daily joins, blocked trend. Acquisition: source distribution, clicks→joins conversion, top referrers. Broadcasts: sent %, blocked %, failed %, click %, top campaigns. Automations: enrollments, step sends, completions. Polls: totals + option percentages. **No expensive live scans on every refresh** — cache aggregates (e.g., 5-minute cache) and/or maintain simple daily rollups.

### 5.9 Admins, roles, audit, settings
Roles: **Owner** (everything) · **Marketer** (dashboard, menus, keyword replies, users, broadcasts, automations, polls, tracking links — no admin management, no infrastructure settings) · **Viewer** (read-only). Enforced server-side with Laravel policies — never only hidden Filament buttons. Audit log (effectively immutable via UI): logins where practical, all create/edit/delete on content, broadcast lifecycle actions (created/test/scheduled/started/cancelled/duplicated), automation pause/resume, settings changes, direct messages, admin+role changes — actor, action, subject, timestamp, safe metadata only (never secrets). Settings (DB-backed, cached): channel ID + URL, default language, send rate, test recipient chat IDs, Web App URL, welcome message (jsonb lang map + media), feature toggles. Secrets stay in env (Decision 7).

---

## 6. Data model

Translation tables follow Decision 12. All Telegram IDs are `BIGINT`. Use `jsonb` (not `json`). Every table below states its reason for existing; FKs with explicit delete rules; timestamps on everything.

| # | Table | Purpose · key columns |
|---|---|---|
| 1 | `users` | Bot audience. id, tg_chat_id (unique, bigint), first_name, username, language, source (nullable), referred_by_user_id (nullable FK users, null on delete), joined_at, last_active_at, blocked_bot bool, blocked_at, in_channel bool, channel_checked_at |
| 2 | `admins` | Panel accounts. name, email (unique), password, role enum(owner/marketer/viewer) |
| 3 | `audit_logs` | Accountability. admin_id FK, action, subject_type, subject_id, meta jsonb, created_at |
| 4 | `settings` | Runtime config. key (unique), value jsonb |
| 5 | `menu_items` | Menu tree. parent_id (self FK, cascade), position, action_type enum(reply/submenu/url/webapp), url, media_file_id FK, is_active |
| 6 | `menu_item_translations` | lang, label, reply_text; unique(menu_item_id, lang) |
| 7 | `keyword_replies` | Auto-replies. keywords jsonb array, match_type enum(exact/contains), position, media_file_id FK, is_active |
| 8 | `keyword_reply_translations` | lang, reply_text; unique(rule, lang) |
| 9 | `broadcasts` | Campaigns. type enum(standard/match_card/poll), status enum(draft/scheduled/preparing/sending/paused/completed/cancelled/failed), audience_filter jsonb, audience_snapshot_count, scheduled_at, recurrence jsonb nullable, template_fields jsonb (match-card data), created_by FK admins, queued/sent/blocked/failed ints default 0, started_at, finished_at, cancelled_at |
| 10 | `broadcast_translations` | lang, text, media_file_id FK; unique(broadcast, lang) |
| 11 | `broadcast_buttons` | row, position, kind enum(url/callback), url |
| 12 | `broadcast_button_translations` | lang, label; unique(button, lang) |
| 13 | `broadcast_failures` | Failures only (Principle 3). broadcast_id FK, user_id FK, tg_error_code, category enum(blocked/rate/network/invalid/other), sanitized_error, attempts, first_failed_at, last_failed_at; unique(broadcast, user) |
| 14 | `automations` | name, trigger enum(user_joined/inactive), trigger_config jsonb (e.g. inactive_days), cooldown_days, is_active |
| 15 | `automation_steps` | step_no, delay_hours, media_file_id FK, buttons jsonb (rows of {kind,url,label:{en,am}}) |
| 16 | `automation_step_translations` | lang, text; unique(step, lang) |
| 17 | `automation_user_states` | Durable per-user execution state (documented exception). automation_id FK, user_id FK, current_step_no, status enum(active/completed/cancelled/cooldown), triggered_at, last_step_sent_at, next_step_at, completed_at, cooldown_until, trigger_key; unique(automation_id, user_id, trigger_key); survives worker restarts |
| 18 | `telegram_messages` | Inbound + admin 1:1 only, never broadcast copies. user_id FK, tg_message_id bigint, direction enum(inbound/admin_outbound), type, text, media_meta jsonb, admin_id nullable FK, sent_at. Retention: prune > 90 days via scheduled job |
| 19 | `button_clicks` | Callback-button analytics. user_id, broadcast_id, button_id, clicked_at |
| 20 | `tracking_links` | code (unique, immutable), name, description, is_active, clicks_count, joins_count |
| 21 | `polls` | Parent poll. broadcast_id FK, question jsonb lang map, options jsonb lang map, answer_counts jsonb, is_anonymous bool |
| 22 | `poll_instances` | Documented exception: correlates each sent Telegram poll to its parent. poll_id FK, user_id FK, tg_poll_id (unique), sent_at. Minimal columns only |
| 23 | `media_files` | Upload + Telegram file_id reuse. path, tg_file_id nullable, kind enum(photo/video/animation), mime, size |

Plus framework tables: jobs, failed_jobs, cache, sessions.

**Critical indexes:** users(tg_chat_id) unique, users(language), users(source), users(joined_at), users(last_active_at), users(blocked_bot); broadcasts(status), broadcasts(scheduled_at), broadcasts(created_at); automation_user_states(automation_id,user_id), (status,next_step_at), (cooldown_until); button_clicks(broadcast_id), (user_id), (clicked_at); telegram_messages(user_id, sent_at); tracking_links(code) unique; poll_instances(tg_poll_id) unique; broadcast_failures(broadcast_id).

---

## 7. Broadcast engine — deep specification

**State machine:** `draft → scheduled → preparing → sending → completed`, exceptions `paused | cancelled | failed`. All transitions go through one domain service (`BroadcastLifecycle`) — never scattered status writes in controllers/resources. Invalid transitions throw.

**Audience snapshot (§ logically frozen at start):** on `preparing`, resolve the audience filter with a cursor query (never `User::all()`), push chat-id/user-id pairs into a Redis list/set keyed by broadcast, record `audience_snapshot_count` as `queued`. Deterministic chunking from Redis (e.g., LPOP batches of 25–50). Memory note to document: ~8–16 bytes per id ⇒ 100k ≈ ~2–4 MB, 1M ≈ ~20–40 MB with overhead — acceptable; cleanup: delete the key on completed/cancelled/failed + a sweeper for orphaned keys older than 48h.

**Sending:** chunk jobs on the `telegram-broadcast` queue pull batches, render per-user (locale + personalization), send via `TelegramClient` through the global `TelegramRateLimiter`, classify results (success / 403-blocked / 429-retry with `retry_after` / retryable network / permanent), retry retryables with exponential backoff up to configurable max attempts, write `broadcast_failures` for permanent failures, apply one atomic counter update per chunk (Decision 14).

**Delivery semantics — honest:** no false "exactly once" claim. Guarantees implemented: broadcast-level Redis lock (no two workers prepare the same broadcast), idempotent chunk consumption (LPOP is atomic), send-button double-click prevention (idempotency key on the confirm action), safe resume after worker restart (remaining Redis ids are simply consumed by the next worker). **Documented residual edge:** Telegram accepts a message and the worker dies before the chunk counter persists ⇒ up to one chunk of messages may be counted as unsent or re-sent once on resume. Stated in docs; not hidden.

**Cancellation:** stop queueing, in-flight jobs check a cancellation flag per batch, already-accepted messages cannot be recalled, counters stay correct, status `cancelled`, UI shows "Sent before cancellation: X".

**Scheduling & recurrence:** scheduler (every minute) promotes due `scheduled` broadcasts and materializes the next occurrence for `recurrence` definitions (Decision 3), all in Addis display time / UTC storage.

---

## 8. Automation engine — deep specification

**Enrollment:** `UserJoined` event → enroll in active `user_joined` automations (step 0 scheduled at trigger + its delay; Decision 2 forbids instant step 0 for welcome drips). A scheduler pass finds users matching `inactive` triggers who have no active/cooldown state (or whose `cooldown_until` passed) and enrolls them.

**Execution:** scheduler claims due `automation_user_states` rows (`status=active`, `next_step_at ≤ now`) with row-level locking / atomic claim, dispatches step-send jobs on the `automation` queue, then advances `current_step_no` + `next_step_at` (delay from previous step, Decision 4) or marks `completed` and sets `cooldown_until = now + cooldown_days`.

**Guarantees:** unique(automation, user, trigger_key) prevents duplicate enrollment; a user never re-enters the same automation before cooldown expiry; paused automations send nothing and preserve `next_step_at`; resume continues (Decision 10); worker restarts lose nothing (state is in Postgres); step sends respect the global rate limiter; frequency behavior tested.

---

## 9. Webhook & callback protocol

**Endpoint:** `POST /telegram/webhook`. Pipeline: verify `X-Telegram-Bot-Api-Secret-Token` against env secret → reject invalid fast → parse safely → dedupe by `update_id` (Redis SETNX, short TTL) → route to handler → return 200 quickly. Slow work (automation triggers, membership checks beyond cache, media processing) goes to events/jobs — never in the HTTP request. Supported update types: `message`, `callback_query`, `poll`, `poll_answer`, membership updates if used; unknown types logged (structured, secret-free) and ignored without crashing.

**Callback protocol (never trust arbitrary strings):** `menu:{id}` · `join:check` · `bc:{broadcast_id}:{button_id}` (click tracking) · `invite:show`. Every handler validates action, entity existence + active status, user access, payload length; always `answerCallbackQuery` so Telegram spinners never hang.

**`/start` flow:** webhook → StartCommandHandler → StartPayloadParser → UserService (attribution, profile refresh, activity) → MembershipService → gate or welcome + menu → `UserJoined` (new users only). Repeated `/start` is fully idempotent: no duplicate users, no attribution rewrites, no duplicate referral increments, no re-enrollment.

---

## 10. Telegram client, rate limiting, queues

**`TelegramClient`** is the only place HTTP calls to Telegram happen. Typed methods: sendText, sendPhoto, sendVideo, sendAnimation, sendPoll, answerCallbackQuery, getChatMember, editMessage*, setWebhook, getWebhookInfo. Consistent response envelope; error classification lives here. Higher-level services: `BotMessageSender`, `BroadcastSender`, `TelegramMembershipService`, `PollService`, `TelegramWebhookProcessor`. No Telegram HTTP from controllers, Filament resources, models, or ad-hoc job code.

**`TelegramRateLimiter`:** Redis token bucket, global across all workers (5 workers on one server still collectively respect the cap), default 25 msg/s via `TELEGRAM_SEND_RATE`, honors `retry_after` by pausing the bucket, no scattered `sleep()`.

**Queues:** `telegram-interactive` (menus, /start replies, callbacks, 1:1 — highest priority) · `telegram-broadcast` · `automation` · `default`. Interactive traffic must never wait behind a 100k blast. Worker config (Laravel Cloud + local): documented workers per queue, tries, timeout, backoff, failed-job strategy.

---

## 11. Security

HTTPS-only in production · webhook secret verification · CSRF · server-side authorization on every mutation (policies, not hidden buttons) · secure sessions + hashed passwords + login throttling · Filament 2FA if reasonable · validation everywhere (Form Requests) · uploads: MIME + size validation, safe filenames, isolated storage disk · URL validation + open-redirect protection on `/r/{code}` (only redirect to the bot deep link) · safe callback parsing (§9) · DB constraints as last line · mass-assignment protection · secret redaction in logs · `APP_DEBUG=false` in production · least-privilege DB user · Redis reachable only from app infrastructure · audit log immutable via UI.

---

## 12. Admin panel — navigation, UX, validation

**Navigation:** Overview → Dashboard · Engagement → Broadcasts, Automations, Polls · Bot Content → Menus, Auto Replies · Audience → Users, Tracking Links · Administration → Admins, Audit Logs, Settings. Appropriate icons; responsive; information-dense but clean; destructive actions obvious + confirmed; clear success/error feedback; minimal modal clutter. This must feel like a professional marketing app, not a generic CRUD scaffold.

**Empty states with actions:** e.g. Broadcasts — "Create your first Telegram campaign"; Tracking Links — "Create a tracking link to measure where new Telegram users come from"; Automations — "Build a welcome or re-engagement journey."

**Progressive validation (never fail only at final Send):** invalid URL immediately · missing AM shows fallback-to-English warning (not a block) · poll needs ≥2 options immediately · empty audience warning with live count · past schedule date blocked · unsupported media blocked · character limits warned in the composer.

**Dashboard layout:** KPI cards top; then growth chart, activity chart, source breakdown, broadcast performance, recent campaigns, top referrers, system health (queue depth, current broadcast, last webhook OK, last Telegram error). Don't overload the first screen.

---

## 13. Observability

Structured logging (secret-free) for: webhook errors, Telegram API failures, broadcast lifecycle, batch failures, automation scheduler errors, Redis/queue failures, poll processing errors. Admin-visible health: queue/broadcast health, failed broadcast count, running broadcasts, last successful webhook, last Telegram API error. Marketers never see stack traces.

---

## 14. Testing requirements (Pest; Telegram fully faked — tests never hit real Telegram)

- **/start:** new user · existing user · valid source · unknown source · referral · self-referral · repeated referral · attribution immutability.
- **Language:** en · am · fallback.
- **Membership:** member · non-member · Telegram error (fail-open).
- **Menus:** reply · submenu · url · webapp · inactive item · back · circular-parent prevention.
- **Keywords:** exact · contains · overlapping priority · Amharic input · no match.
- **Broadcasts:** every audience filter · snapshot freezing · test send · send · scheduled · recurring materialization · cancellation · double-Send prevention · personalization (incl. unknown token) · buttons + click tracking · 403 → blocked · 429 → retry_after respected · retry/backoff · counter consistency (`sent+blocked+failed ≤ queued`) · resume after simulated worker death.
- **Automation:** enrollment · delay-from-previous-step · pause preserves state · resume continues · cooldown blocks re-entry · inactive trigger query · completion.
- **Security:** invalid webhook secret rejected · viewer mutation blocked · marketer blocked from owner settings · malicious callback payloads · invalid upload.
- **Polls:** send · poll_answer/poll update ingestion via poll_instances · aggregation.

---

## 15. Build plan — 4 batches (batch → tests green → report → stop for review)

**Before Batch 1 (setup, same session):** initialize repo; create `CLAUDE.md` (the standing rules: 3 principles, settled decisions list, batch process, quality bars, "no shortcuts"), `docs/PROGRESS.md`, and copy this spec to `docs/KEMERBOT-SPEC.md` if not already there; docker-compose for local Postgres + Redis; brief written plan (migration list + folder structure). No lengthy re-audit.

**Batch 1 — Foundation & schema.** Laravel 12 skeleton, Filament v4 + minimal deps installed, ALL migrations from §6 (final schema up front — later batches add no tables), all models + enums + relationships + factories, admin seeder, settings seeder, config/telegram.php, .env.example (§16), Pest setup, model/constraint tests. *Accept when:* `migrate:fresh --seed` green, model tests green, panel login works.

**Batch 2 — Bot core.** TelegramClient + fake, RateLimiter, webhook pipeline + secret + dedupe, StartCommandHandler + StartPayloadParser + attribution, MembershipService + join gate, BotLocaleResolver, menu rendering + callback protocol, keyword replies, welcome message, blocked handling + recovery, telegram_messages inbound capture, queues wired. *Accept when:* all §14 bot-side tests green; manually: real bot responds with menus + join gate on dev via webhook tunnel.

**Batch 3 — Admin panel + broadcast engine.** Filament resources: Menus (tree editor), Auto Replies, Users (list, basic profile), Settings, Admins + roles + policies, Audit log; broadcast wizard (7 steps) + BroadcastLifecycle + snapshot + sender + failures + counters + test send + scheduling + cancellation + media file_id reuse + personalization + click tracking. *Accept when:* §14 broadcast + security tests green; a real test broadcast with buttons reaches admin test recipients.

**Batch 4 — Automations, growth, extras.** Automation engine + user states + welcome drip + re-engagement + recurring execution; tracking links + `/r/{code}`; referral-lite + leaderboard; dashboard + caching; match promo cards; native polls + poll_instances; Mini App button; full user profiles + 1:1 messaging; empty states + progressive validation polish; observability surface; ops documentation (§17–18). *Accept when:* full suite green; production-readiness review (architecture, DB/indexes, performance, security, reliability, Telegram behavior, UX, tests, deployment) has no FAIL.

**Per-batch process:** plan files → implement → run tests continuously → self-review (correctness, authorization, security, performance, i18n, errors, observability) → update `docs/PROGRESS.md` → batch report (built, tests, decisions, remaining) → **stop for user review before next batch.** Never placeholder-and-hide; never skip tests/indexes/constraints/translations inside the current batch.

---

## 16. Environment variables (`.env.example`)

```
APP_NAME=KemerBot
APP_ENV=local
APP_DEBUG=true
APP_URL=
APP_DISPLAY_TIMEZONE=Africa/Addis_Ababa

DB_CONNECTION=pgsql  DB_HOST= DB_PORT=5432 DB_DATABASE=kemerbot DB_USERNAME= DB_PASSWORD=
REDIS_HOST= REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_STORE=redis

TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=
TELEGRAM_WEBHOOK_SECRET=
TELEGRAM_SEND_RATE=25
```

Channel ID/URL, Web App URL, test recipients, welcome content = DB settings (admin-editable). Real secrets never committed.

---

## 17. Telegram & channel setup (document in README)

BotFather: create bot → token → set name/photo/description. Channel: add bot as **admin** of the SunBet channel (required for reliable membership checks) → capture channel ID. Webhook: `setWebhook` with secret token + required `allowed_updates` (message, callback_query, poll, poll_answer); verification via `getWebhookInfo`; local dev via tunnel (e.g., `cloudflared`/`ngrok`).

## 18. Operations runbook (document in `docs/OPERATIONS.md`)

Broadcast stuck/recovery · retrying failed queues · cancelling a broadcast · pausing automations · rotating the bot token (env change + setWebhook) · changing the channel · Redis restart behavior · deploy-during-broadcast behavior · Laravel Cloud: Postgres, Redis, env secrets, worker + scheduler configuration, migrations on deploy.

---

## 19. Deferred features (schema-readiness honestly classified)

| Feature | Readiness |
|---|---|
| QR per tracking link | A — no migration (generate from existing code) |
| Ban list | A — `users` gains nothing; implement as `is_banned` boolean → small migration, trivial (call it B-lite) |
| A/B broadcast testing | B — will need variant tables; deliberately not pre-built |
| Approval workflow | B — needs approval states/columns; deliberately not pre-built |
| Live online-agents button | A — menu URL/webapp action already covers it (points at sunagent) |
| Multi-bot support | B — deliberate decision: NOT adding bot_id ownership columns today; accept a future migration rather than building multi-tenancy now |

---

*Spec v2 · 2026-08-17 · Internal name KemerBot, public brand SunBet · Supersedes the 14-table planning spec · Audit corrections incorporated — build from this document.*
