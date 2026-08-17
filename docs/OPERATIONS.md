# KemerBot — Operations Runbook (spec §18)

Audience: whoever operates the production deployment. Marketers never need this file.

## Broadcast stuck / recovery

Symptoms: broadcast sits in `preparing`/`sending`, counters not moving.

1. Check workers: `php artisan queue:monitor redis:telegram-broadcast` (or Laravel Cloud
   worker dashboard) and the dashboard's System health widget (queue depths).
2. If the worker died mid-run, the remaining audience is still in Redis
   (`broadcast:{id}:audience`). Restart the worker, then re-dispatch the chain:
   `php artisan tinker --execute="App\Jobs\SendBroadcastChunkJob::dispatch(<id>)->onQueue('telegram-broadcast');"`
   The next worker simply consumes what remains — safe by design (atomic LPOP).
3. Documented residual edge (spec §7): if a worker died between Telegram accepting a
   message and the chunk counter persisting, up to one chunk may be counted as unsent or
   re-sent once on resume. `sent + blocked + failed ≤ queued` still holds.
4. A broadcast stuck in `preparing` with a dead prepare job can be cancelled from the
   panel and duplicated; its snapshot is cleaned on cancel (plus the hourly sweeper and
   a 48h key TTL as backstops).

## Retrying failed queue jobs

```bash
php artisan queue:failed          # inspect
php artisan queue:retry all       # or a specific id
php artisan queue:flush           # only after triage
```

Automation step jobs and DM jobs retry themselves (tries=3, backoff). A step job that
ultimately fails skips that step for that user — enrollment state has already advanced
(at-most-once per step, documented).

## Cancelling a broadcast

Panel → Broadcasts → Cancel (allowed from scheduled/preparing/sending/paused).
Queueing stops at the next chunk boundary; messages already accepted by Telegram cannot
be recalled. The row shows "Sent before cancellation: X". Redis snapshot is deleted.

## Pausing automations

Panel → Automations → Pause. Enrollments freeze in place (`next_step_at` preserved,
nothing sends); Resume continues each user exactly where they stopped. Pause/resume are
audited. Overdue steps send on the first scheduler tick after resume.

## Rotating the bot token

1. Get the new token from BotFather (`/revoke` then `/token`).
2. Update `TELEGRAM_BOT_TOKEN` in the environment (Laravel Cloud env editor), redeploy /
   restart workers.
3. Re-register the webhook: `php artisan telegram:set-webhook`.
4. Consider rotating `TELEGRAM_WEBHOOK_SECRET` at the same time (same two steps).
   Nothing in the DB or panel stores either value.

## Changing the force-join channel

1. Add the bot as an **admin** of the new channel.
2. Administration → Settings → update channel ID + URL (Owner only; audited).
3. Membership caches on user rows re-check within the TTL
   (`TELEGRAM_MEMBERSHIP_TTL_MINUTES`, default 60) or on the next "I've joined ✅" tap.

## Redis restart behavior

- **Queues**: pending jobs are in Redis — a restart with persistence (default compose
  volume / Laravel Cloud managed Redis) keeps them; without persistence they are lost
  and the scheduler re-materializes recurring/scheduled work on the next tick.
- **Broadcast snapshots**: lost snapshot = broadcast stalls; cancel + duplicate it
  (counters stay honest). Automation state is in Postgres — nothing is lost.
- **Rate limiter / dedupe keys**: self-healing (short TTLs).
- **Poll per-instance counts**: lost counts re-converge as Telegram sends further poll
  updates; totals may read low until then (analytics-grade, documented).

## Deploy during a broadcast

Chunk jobs are small (~25 users, seconds each). A rolling worker restart between chunks
is safe: the chain resumes from Redis. The residual edge above applies to a worker
killed mid-chunk. For zero-doubt sends, deploy outside broadcast windows or pause the
broadcast first (panel → Pause, deploy, Resume).

## Laravel Cloud checklist

- **Postgres**: Serverless Postgres; run `php artisan migrate --force` on deploy.
- **Redis**: managed Valkey; set `REDIS_*` env; `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`.
- **Env secrets**: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, `APP_KEY`, DB creds —
  environment manager only.
- **Workers**: four worker pools per the README table; sizes: interactive ≥1,
  broadcast exactly 1 (the rate limiter is the throughput governor; more workers add
  nothing but contention), automation ≥1.
- **Scheduler**: enable the every-minute scheduler.
- **After first deploy**: seed (`php artisan db:seed --force`), change the owner
  password, set channel settings, run `php artisan telegram:set-webhook`.

## Health surface

Dashboard → System health: queue depths, running/failed broadcasts, last webhook OK,
last Telegram API error (secret-free). Logs are structured and secret-free; the bot
token is scrubbed from every error path.
