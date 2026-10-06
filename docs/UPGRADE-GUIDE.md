# Installation and upgrade guide

## Requirements

PHP 8.4.1 or newer with PostgreSQL PDO, mbstring, intl, curl, fileinfo, openssl, and the usual Laravel extensions; Composer; PostgreSQL; Redis or Valkey; Node.js 22.12+ (Node 24 also verified). Predis is supplied, so a PHP Redis extension is optional. All application workers must see the same media disk.

## Fresh installation

1. Extract the source, run `composer install`, then `npm ci` and `npm run build`.
2. Copy `.env.example` to `.env`, generate `APP_KEY` with `php artisan key:generate`, and set your application URL, database, Redis, and Telegram credentials. Use `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, and `SESSION_SECURE_COOKIE=true` for a live office.
3. Choose explicit `SEED_ADMIN_EMAIL` and a unique `SEED_ADMIN_PASSWORD` of at least sixteen characters before seeding a shared environment. The local-only example login must never be used as a live credential.
4. Run `php artisan migrate --force` and, for a fresh database only, `php artisan db:seed --force`.
5. Serve Laravel from `public/`; give the application writable storage/cache directories. Configure shared local storage or `FILESYSTEM_DISK=s3` and bucket credentials. Use `php artisan storage:link` when appropriate for your chosen disk.
6. Run the queue workers and scheduler described below. In Settings, configure the channel, welcome content, test recipients, quiet hours, daily cap, and approval threshold.
7. Complete authenticator enrollment on the administrator profile. Outside local/testing it is required.
8. Register the webhook with `php artisan telegram:set-webhook`, then test with a small designated staging audience before starting a real campaign.

## Upgrade an existing installation

1. Back up PostgreSQL, Redis, media, and `APP_KEY`. Keep the key unchanged: authenticator secrets rely on it.
2. Finish existing campaigns before switching the sender architecture. If that is impossible, cancel/pause and reconcile their known outcomes before creating a new draft. This release does not import old in-flight Redis audience streams. Legacy active campaigns without a ledger will fail visibly; rebuilding their whole audience could duplicate earlier sends.
3. Put the office in maintenance/read-only mode and stop old workers and scheduler. Preserve Redis poll state until migration is complete.
4. Install the locked dependencies, build assets, and run all four new workspace migrations with `php artisan migrate --force`. They add data; they do not rebuild the existing database. Do not run `migrate:fresh` on an existing installation.
5. Run `php artisan polls:migrate-aggregation-state` before receiving new poll updates. It imports old per-poll hashes and available per-instance Redis previous counts into PostgreSQL. If old Redis counts have already expired while aggregate totals are nonzero, reconcile those historical polls manually; missing history cannot be reconstructed safely.
6. Configure shared media storage and queue timeouts. Clear/rebuild Laravel caches using your normal deployment process; restart workers on the new code.
7. Run `php artisan workspace:recover` and `php artisan broadcasts:recover-stalled`. Review Delivery center, then test login/MFA, one opted-in campaign, unsubscribe, and one conversion event on staging.
8. Leave maintenance mode only after these checks pass. Use backup restoration and the prior application release as the rollback plan if necessary; dropping durable delivery tables would erase their new history.

## Workers and scheduler

Use separate supervised workers for interactive, campaign, and automation queues so campaigns do not delay support:

```sh
php artisan queue:work redis --queue=telegram-interactive --timeout=120 --tries=3
php artisan queue:work redis --queue=telegram-broadcast --timeout=600 --tries=3
php artisan queue:work redis --queue=automation --timeout=120 --tries=5
```

Set `REDIS_QUEUE_RETRY_AFTER=660` or higher; it must exceed the longest worker timeout. The default campaign claim lease is 720 seconds, with stalled-chain recovery after 780 seconds. Use Linux process supervision in production so job timeouts can interrupt workers reliably.

Run `php artisan schedule:run` every minute from cron (or your hosting scheduler). Use one shared Redis cache across nodes for single-server and overlap locks. `workspace:recover` recovers the webhook inbox and personal outbox; campaigns and journeys also have their scheduled recovery commands.

Database queues need a corresponding `DB_QUEUE_RETRY_AFTER` above 600 seconds; Redis is the intended production configuration. Avoid `sync` queues for production campaigns.

## Conversion integration

Set a separate random `CONVERSION_API_KEY` of at least 32 characters. Your business backend sends an HTTPS POST to `/integrations/conversions` with an Authorization bearer token and JSON:

```json
{
  "external_id": "your-unique-event-id",
  "tg_chat_id": 123456789,
  "event": "registration",
  "value": 0,
  "currency": "ETB",
  "occurred_at": "2026-10-06T10:00:00Z",
  "broadcast_id": 42
}
```

Allowed events are `registration`, `deposit`, and `purchase`; currencies are ETB, USD, and EUR. `broadcast_id` is optional; when supplied, the person must belong to its stored audience, including a holdout cohort. Repeating the same event ID/data returns `created:false`; conflicting reuse returns HTTP 409. Unauthorized requests return 401. Future-dated events are rejected. Keep this secret out of browser code and bot messages.

## Verification

The test suite uses a separate database `kemerbot_test` and Redis database 15. Default local test ports are PostgreSQL 5445 and Redis 6381, matching the included local Compose file. The isolated test cluster uses dummy local credentials only. Run `vendor/bin/pest`; Telegram is faked throughout.

For release checks run `composer validate --strict`, `composer audit`, `npm audit`, `npm run build`, and `vendor/bin/pint --test --dirty`. Dependency advisories change; run the audits during each deployment.

The packaged archive excludes local environment files, installed dependencies, database contents, and logs. Install dependencies from its lockfiles. Screenshots and the verification report describe the local build only.
