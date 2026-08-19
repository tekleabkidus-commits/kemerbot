# KemerBot Reliability Hardening — Master Work Order

> Governed by three standing amendments (already acknowledged):
> 1. Strict phase gating: P0 → stop, report, wait for go → P1 → stop, report, wait for go → hardening. No single-pass run.
> 2. Item 13 media disk: Laravel Cloud bucket (S3-compatible driver, auto-injected env vars) in production; local disk in development; env-driven disk selection; media handling must work against object storage (streams/temp files, not local paths).
> 3. Every "existing problem" below is a hypothesis: verify against the actual code first; where already handled, answer "already handled, here's where" with file:line instead of re-implementing.

You are acting as a **principal Laravel architect, distributed-systems engineer, Telegram Bot API specialist, PostgreSQL/Redis engineer, queue/reliability engineer, and senior security reviewer with 20+ years of production experience**.

You are working directly inside the existing **KemerBot Laravel repository**.

Your task is to **inspect the existing implementation first, understand how every affected module currently works, then implement the fixes below properly at architecture level**.

Do **not** patch symptoms with quick workarounds.

Do **not** rewrite unrelated working functionality.

Do **not** change existing product behavior unless required to solve one of these issues.

Do **not** remove existing features.

Do **not** create fake implementations, TODO placeholders, mocked production logic, or comments saying something should be implemented later.

The final code must be suitable for a production Telegram bot with **100,000+ users and potentially much larger scale**.

---

# 1. PROJECT CONTEXT

This application already includes:

- Laravel
- PostgreSQL
- Redis
- Laravel queues
- Filament admin
- Telegram Bot API webhook integration
- Broadcasts
- Scheduled broadcasts
- Recurring broadcasts
- Broadcast audience segmentation
- Automations
- Keyword replies
- Menus
- Mini Apps/WebApps
- Polls
- Referral/tracking links
- Analytics
- Direct messaging
- Audit logs
- English + Amharic
- Telegram media/file_id reuse
- Telegram webhook deduplication
- Feature settings
- Dedicated queues for broadcasts and automations
- Existing Pest test suite

You must preserve all currently working behavior.

---

# 2. FIRST: PERFORM A FULL CODE AUDIT

Before modifying anything, inspect all code related to:

- broadcasts
- audience snapshots
- queue workers
- automation execution
- automation state
- Telegram sending
- Telegram retry handling
- polls
- poll responses
- recurring broadcasts
- scheduler
- direct messages
- feature flags
- menus
- WebApp/Mini App actions
- application settings
- webhook processing
- webhook deduplication
- media uploads
- media storage
- Telegram `file_id`
- Redis
- PostgreSQL transactions
- Laravel queue configuration
- Redis queue configuration
- Laravel scheduler configuration
- deployment/runtime configuration
- existing tests

Do not assume filenames from this prompt are still exact.

Use the actual repository as the source of truth.

Create a short internal architecture map before making changes.

---

# 3. P0 — BROADCAST RECIPIENTS MUST NEVER BE SILENTLY LOST

## Existing problem

The current broadcast architecture appears to consume recipients from Redis using destructive operations similar to:

```php
Redis::lpop(...)
```

Recipients are therefore removed before successful Telegram delivery is safely recorded.

Failure scenario:

```text
pending recipients:
1
2
3
4
5
6

worker claims:
1
2
3

1,2,3 are removed from pending Redis list

worker crashes before sending them

new worker continues from:
4
5
6
```

Users 1–3 are now permanently skipped.

This is unacceptable.

---

# REQUIRED FIX

Redesign broadcast recipient delivery to provide **durable claim → processing → acknowledgement semantics**.

The architecture must ensure:

```text
recipient pending
    ↓
worker claims recipient
    ↓
recipient marked processing
    ↓
Telegram attempted
    ↓
delivery outcome recorded
    ↓
recipient acknowledged/finished
```

If the worker crashes:

```text
processing but unacknowledged
    ↓
becomes recoverable
    ↓
another worker processes it
```

Do not depend on destructive `LPOP` as the only state.

---

# RECOMMENDED APPROACH

Strongly evaluate:

## Redis Streams + Consumer Groups

Example architecture:

```text
XADD broadcast:{id}:recipients
XREADGROUP
Telegram send
XACK
```

Use:

- consumer groups
- pending entries
- acknowledgement
- stale consumer recovery
- retry/reclaim logic

Potentially use:

```text
XAUTOCLAIM
```

or equivalent safe recovery mechanism.

If Redis Streams are inappropriate for some concrete reason in this project, implement another robust pattern such as:

```text
pending
processing
completed
```

with atomic Redis Lua operations or database-backed recipient delivery rows.

Whatever solution you choose must provide crash recovery.

---

# REQUIRED DELIVERY GUARANTEE

Target semantics:

```text
at-least-once processing
+
idempotent delivery bookkeeping
```

Perfect exactly-once Telegram delivery cannot generally be guaranteed across network failures, so design properly around that reality.

Avoid silent recipient loss as the primary guarantee.

---

# BROADCAST RECIPIENT STATES

Consider explicit states such as:

```text
PENDING
PROCESSING
SENT
BLOCKED
FAILED_RETRYABLE
FAILED_PERMANENT
```

Persist enough information to diagnose failures.

Include:

- telegram_user_id
- broadcast_id
- attempt count
- last attempted at
- delivered at
- failure code
- failure reason
- final state

Do not create unnecessary database bloat if Redis Streams can serve the operational queue appropriately, but maintain durable summary/audit information.

---

# FAILURE CLASSIFICATION

Telegram errors must be classified.

Examples:

```text
403 bot blocked by user
    → permanent blocked

400 invalid request
    → normally permanent

429 rate limited
    → retryable
    → respect retry_after

500/502/503
    → retryable

network timeout
    → retryable

connection reset
    → retryable
```

---

# BROADCAST COMPLETION

A broadcast must only become:

```text
COMPLETED
```

when there are no:

```text
pending
processing
retryable
```

recipients remaining.

A crashed worker must not accidentally cause the campaign to appear complete.

---

# ADD RECOVERY JOB

Implement a recovery mechanism for stale processing recipients.

For example:

```text
every minute:
find deliveries claimed longer than threshold
reclaim them
```

Make timeout configurable.

---

# 4. P0 — AUTOMATION STATE MUST NOT ADVANCE BEFORE SAFE MESSAGE PROCESSING

## Existing problem

Automation state appears to advance before the corresponding message job is safely processed.

Bad sequence:

```text
automation step claimed
↓
state advances to next step
↓
message job dispatched
```

Crash between state update and queue dispatch:

```text
step considered complete
but message never sent
```

---

# REQUIRED FIX

Redesign automation execution around explicit step execution lifecycle.

For example:

```text
READY
↓
CLAIMED
↓
QUEUED
↓
SENDING
↓
SENT
↓
ADVANCE TO NEXT STEP
```

The next automation step must not become eligible prematurely.

---

# TRANSACTIONAL DESIGN

Use safe transactional behavior.

Strongly consider a database-backed execution record:

```text
automation_user_steps
```

or equivalent.

Possible fields:

```text
id
automation_id
automation_step_id
telegram_user_id
state
attempt_count
available_at
claimed_at
queued_at
sent_at
completed_at
failed_at
last_error
created_at
updated_at
```

Do not duplicate existing models unnecessarily if an existing state table can be extended cleanly.

---

# OUTBOX PATTERN

Evaluate using a transactional outbox for automation dispatch.

Ideal pattern:

```text
DB transaction:
    claim automation step
    create delivery/outbox record
COMMIT

queue worker:
    process outbox/delivery
```

This eliminates the state-change-before-dispatch crash window.

---

# STATE ADVANCEMENT

Advance the automation only after successful processing according to defined semantics.

For example:

```text
message sent successfully
↓
mark current step SENT
↓
calculate wait delay
↓
schedule next step
```

Permanent delivery errors should have clearly defined behavior.

Example:

```text
user blocked bot
→ mark user unreachable
→ stop/pause that user's automation execution
```

Do not let a single unreachable user break the automation globally.

---

# 5. P0 — REDESIGN POLL AGGREGATION FOR LARGE SCALE

## Existing problem

The current architecture may keep per-Telegram-poll counts and aggregate using:

```php
Redis::hgetall(...)
```

across potentially every poll instance.

At 100,000 recipients this can turn each poll response into roughly:

```text
O(number of recipients)
```

processing.

That architecture must be replaced.

---

# REQUIRED TARGET

Poll update handling should approach:

```text
O(1)
```

per update.

---

# REQUIRED DESIGN

Telegram may produce updates containing the latest poll counts.

Maintain the previous counts for that individual Telegram poll instance.

For example:

```text
poll_instance:{telegram_poll_id}
previous_option_0 = 100
previous_option_1 = 50
```

New Telegram update:

```text
option_0 = 103
option_1 = 51
```

Compute:

```text
delta option_0 = +3
delta option_1 = +1
```

Atomically increment aggregate campaign/poll totals:

```text
campaign_poll:{poll_id}:totals
```

Result:

```text
O(number of options)
```

not:

```text
O(number of recipients)
```

---

# ATOMICITY

Use an atomic mechanism such as:

- Redis Lua script
- Redis transaction where safe
- PostgreSQL atomic increment
- another proven atomic design

Concurrent poll updates must not corrupt totals.

---

# CLEANUP

Every poll-specific temporary Redis key must have:

```text
TTL
```

or explicit cleanup.

Do not leave unlimited:

```text
poll:{id}:*
```

keys forever.

Define appropriate retention.

---

# POLL DATA MODEL

Keep campaign-level analytics such as:

```text
total poll recipients
total voters where detectable
votes per option
vote percentage
delivery count
poll closed/open state
```

Preserve existing admin reports.

Do not scan every recipient whenever the report page loads.

---

# 6. P0 — RECURRING BROADCASTS MUST BE ATOMIC

## Existing problem

Two scheduler processes may see the same recurring broadcast as due and both create a child campaign.

Example:

```text
scheduler A reads recurring campaign #10
scheduler B reads recurring campaign #10

A creates child #11
B creates child #12

both send
```

Same campaign is delivered twice.

---

# REQUIRED FIX

Make recurring campaign advancement atomic.

Use one of:

```text
SELECT ... FOR UPDATE
```

inside a transaction,

or an atomic Redis/database lock,

or another production-grade distributed locking method.

Prefer database transactional locking for persistent schedule state.

---

# REQUIRED BEHAVIOR

Within one transaction:

```text
lock parent recurring campaign
↓
verify still due
↓
calculate occurrence identifier
↓
ensure occurrence hasn't already been created
↓
create child
↓
advance parent's next scheduled occurrence
↓
commit
```

Only then dispatch the child broadcast.

---

# OCCURRENCE IDEMPOTENCY

Add a deterministic uniqueness constraint.

Example:

```text
(parent_broadcast_id, scheduled_occurrence_at)
```

must be unique.

This creates database-level protection even if application locking fails.

---

# 7. P1 — FIX DIRECT-MESSAGE RETRIES

Current behavior appears to have Laravel job retries such as:

```php
$tries = 3;
$backoff = [5, 30];
```

but Telegram failures may return normally instead of throwing/releasing the job.

Therefore Laravel considers the job successful.

Fix this.

---

# CENTRAL TELEGRAM ERROR CLASSIFICATION

Do not implement different retry logic independently in:

- direct messages
- broadcasts
- automation steps

Create or improve a central Telegram delivery service/error classifier.

Possible result object:

```php
TelegramDeliveryResult
```

containing:

```text
successful
retryable
permanent
blocked
rateLimited
retryAfter
telegramErrorCode
description
exception
```

---

# RETRY POLICY

Example:

```text
403 bot blocked
→ permanent
→ do not retry

400 invalid request
→ permanent

429
→ retry
→ use Telegram retry_after

5xx
→ retry

network exception
→ retry

timeout
→ retry
```

Use Laravel job release/retry behavior correctly.

Do not create retry storms.

Add jitter if helpful.

---

# DIRECT MESSAGE AUDIT

Change semantics where necessary.

Instead of logging:

```text
direct_message.sent
```

when merely queued, use:

```text
direct_message.queued
```

Then after Telegram success:

```text
direct_message.sent
```

or:

```text
direct_message.delivered
```

On permanent failure:

```text
direct_message.failed
```

Preserve backward compatibility where practical.

---

# 8. P1 — POLL FEATURE TOGGLE MUST ACTUALLY WORK

There is a Poll feature setting in admin.

It must be enforced server-side.

When disabled:

```text
feature_polls = false
```

users/admins must not be able to:

- create new poll broadcasts
- schedule poll broadcasts
- duplicate a poll broadcast into a sendable campaign without warning
- trigger poll-specific bot behavior
- bypass restrictions through crafted HTTP requests

Existing historical poll reports should remain viewable unless product behavior explicitly says otherwise.

---

# UI BEHAVIOR

When disabled:

- hide or disable Poll creation option
- show a clear explanation
- enforce backend validation regardless of UI

Never depend only on Filament visibility.

---

# 9. P1 — MINI APP FEATURE TOGGLE MUST ACTUALLY WORK

When:

```text
feature_mini_app = false
```

the system must prevent active Mini App/WebApp actions from being exposed to users.

Apply enforcement to:

- menu creation
- menu editing
- Telegram keyboard rendering
- inline/reply buttons as applicable
- any other WebApp entry point

Existing historical/configuration data should not necessarily be deleted.

The feature should become usable again if re-enabled.

---

# 10. P1 — CONNECT `webapp.url` TO WEBAPP MENU ACTIONS

There is apparently a global setting:

```text
webapp.url
```

but WebApp menu items may currently store unrelated independent URLs.

Audit current intended behavior.

Implement a clean model.

Preferred design:

```text
global webapp.url
```

is the default canonical Mini App URL.

Menu WebApp actions should either:

### Option A — preferred if only one Mini App exists

Always use:

```text
settings.webapp.url
```

or:

### Option B

Allow:

```text
Use global Mini App URL
```

with optional:

```text
Custom URL override
```

If using an override, make this explicit in the admin UI.

Do not leave two independent configuration systems whose relationship is unclear.

---

# URL SECURITY

WebApp URLs must use safe validation.

Production should normally require:

```text
https://
```

unless there is an explicit development exception.

Reject malformed URLs.

---

# 11. P1 — FIX WEBHOOK DEDUPLICATION MESSAGE-LOSS WINDOW

## Existing unsafe sequence

Potential current behavior:

```text
receive Telegram update
↓
mark update_id as processed in Redis
↓
dispatch queue job
```

If queue dispatch fails after Redis marking:

```text
update is marked processed
but processing job does not exist
```

Telegram retries:

```text
dedupe rejects it
```

The update disappears.

---

# REQUIRED FIX

Design durable webhook ingestion.

Strongly consider:

```text
telegram_updates
```

table.

Fields could include:

```text
id
telegram_update_id UNIQUE
payload JSONB
status
received_at
processing_started_at
processed_at
attempt_count
last_error
```

Webhook:

```text
BEGIN
INSERT telegram update ON CONFLICT DO NOTHING
COMMIT
dispatch processing
```

If queue dispatch fails:

the durable Telegram update still exists and can be recovered.

---

# ALTERNATIVE

A transactional outbox/inbox approach is also acceptable.

The key requirements:

- no duplicate processing
- no lost update between dedupe and dispatch
- recoverable after worker/Redis/queue outage

---

# UPDATE IDEMPOTENCY

Enforce database uniqueness:

```text
UNIQUE telegram_update_id
```

Do not rely solely on a short Redis TTL.

Redis can remain a fast-path cache if useful, but not the only durable source.

---

# RECOVERY

Create a scheduled recovery command/job for:

```text
received/unprocessed updates
```

older than a safe threshold.

---

# 12. P1 — PREVENT SCHEDULER OVERLAP ACROSS SERVERS

Inspect:

```text
routes/console.php
Console Kernel
scheduled commands
deployment configuration
```

Apply appropriate Laravel protections such as:

```php
->withoutOverlapping()
```

and, if the deployment can run scheduler on multiple instances:

```php
->onOneServer()
```

where supported and correctly configured.

Do not blindly add these everywhere.

Determine which scheduled tasks require:

- local overlap protection
- distributed single-server execution
- database-level idempotency anyway

Important financial/delivery state must not depend purely on scheduler locks.

Scheduler locks are an additional defense, not the only defense.

---

# 13. P1 — FIX MEDIA STORAGE FOR MULTI-SERVER/DISTRIBUTED WORKERS

Audit all media behavior.

Current local storage may resemble:

```text
storage/app/private/media
```

This is unsafe if:

```text
web server A receives upload
worker B processes campaign
```

and they do not share a filesystem.

---

# REQUIRED ARCHITECTURE

Support shared object storage using Laravel Filesystem.

Recommended:

- Amazon S3
- Cloudflare R2
- DigitalOcean Spaces
- another S3-compatible store

Use configuration rather than hardcoding one vendor.

Example:

```env
MEDIA_DISK=s3
```

or equivalent.

---

# REQUIREMENTS

Media code must use:

```php
Storage::disk(...)
```

not direct local filesystem paths wherever possible.

Preserve local storage support for development.

Example:

```env
MEDIA_DISK=local
```

development

```env
MEDIA_DISK=s3
```

production

---

# TELEGRAM FILE ID

Continue using Telegram:

```text
file_id
```

after initial upload whenever appropriate.

If media already has a reusable Telegram `file_id`, the original object may not need to be repeatedly downloaded/uploaded.

Do not break existing file ID reuse.

---

# MEDIA CLEANUP

Add safe lifecycle cleanup where appropriate.

Never delete media still referenced by:

- broadcasts
- automation steps
- menus
- keyword replies

---

# 14. DO NOT INTRODUCE NEW SINGLE POINTS OF FAILURE

For every change ask:

```text
What happens if Redis restarts?
What happens if PostgreSQL remains available but Redis is unavailable?
What happens if a queue worker dies?
What happens if two workers process simultaneously?
What happens if Telegram returns 429?
What happens if Telegram times out after receiving our request?
What happens if the app process dies between DB commit and queue dispatch?
What happens if two schedulers execute simultaneously?
```

Design around those failure modes.

---

# 15. DATABASE MIGRATIONS

If schema changes are needed:

Create proper Laravel migrations.

Requirements:

- safe for production rollout
- appropriate indexes
- foreign keys
- unique constraints
- no destructive data loss
- rollback where reasonably possible
- PostgreSQL-compatible
- avoid full-table locks when avoidable
- use JSONB where appropriate

Indexes should support actual queries.

Example likely indexes:

```text
broadcast_id + status
automation_id + user_id + status
telegram_update_id UNIQUE
parent_broadcast_id + occurrence_at UNIQUE
available_at + status
```

Choose based on actual implementation.

---

# 16. CONCURRENCY

Use concurrency-safe operations.

Do not rely on patterns like:

```php
if (!$record) {
    create();
}
```

without uniqueness protection.

Use:

- transactions
- row locking
- unique indexes
- atomic updates
- Redis Lua
- distributed locks

appropriately.

Database constraints must act as the final defense where possible.

---

# 17. OBSERVABILITY

Add structured logging for the critical new flows.

Useful context:

```text
broadcast_id
recipient_id
telegram_user_id
automation_id
automation_step_id
telegram_update_id
attempt
worker
failure_type
telegram_error_code
retry_after
```

Never log:

- bot token
- webhook secret
- passwords
- sensitive credentials

---

# 18. METRICS / SYSTEM HEALTH

Where the existing System Health dashboard allows it, expose useful counts for:

```text
broadcast pending
broadcast processing
broadcast retrying
broadcast permanently failed

automation ready
automation processing
automation retrying
automation failed

Telegram webhook backlog

stale claims

poll aggregation errors

scheduler lock failures
```

Avoid building a huge new monitoring system if one doesn't exist.

Integrate with existing patterns.

---

# 19. ADMIN RECOVERY ACTIONS

Where useful and consistent with the existing Filament architecture, provide safe admin operations such as:

```text
Retry failed recipients
Retry stuck broadcast
Retry failed direct message
Recover stuck automation execution
```

Never allow an action that can blindly duplicate delivery to the entire audience without confirmation/protection.

---

# 20. TESTING — MANDATORY

Do not consider the task complete until tests cover the failure conditions.

Review and update existing Pest tests.

Add tests for all major changes.

---

# BROADCAST TESTS

Test:

```text
worker crash after claiming recipients
claimed recipients become recoverable

successful send gets acknowledged

permanent Telegram failure ends correctly

429 gets retried

network error gets retried

campaign doesn't finish while processing recipients remain

two workers cannot incorrectly process claim bookkeeping

broadcast pause/resume remains functional
```

---

# AUTOMATION TESTS

Test:

```text
state does not advance before safe execution

job dispatch failure does not lose step

worker crash can be recovered

successful step advances

429 retries

network errors retry

blocked user doesn't retry forever

same step cannot be processed concurrently twice
```

---

# POLL TESTS

Test:

```text
poll delta correctly increments totals

concurrent updates do not corrupt totals

duplicate update doesn't double count

Redis temporary state expires

100,000 recipient architecture does not require scanning 100,000 keys per vote
```

You do not necessarily need to literally create 100,000 records in every CI test.

But write tests/benchmarks proving the algorithm is not linear across recipients.

---

# RECURRING BROADCAST TESTS

Test concurrency.

Simulate two attempts to process the same due occurrence.

Expected:

```text
exactly one child occurrence created
```

Database uniqueness must enforce this.

---

# FEATURE FLAG TESTS

Poll disabled:

```text
UI unavailable
backend rejects poll creation/send
```

Mini App disabled:

```text
UI unavailable
backend prevents active WebApp rendering
```

---

# WEBHOOK TESTS

Test:

```text
duplicate update_id processed once

queue dispatch failure does not lose durable update

recovery can process pending update

same update arriving concurrently produces one durable record
```

---

# DIRECT MESSAGE TESTS

Test:

```text
403 → no retry
429 → retry
500 → retry
network timeout → retry
successful delivery → correct audit event
queued delivery → correct audit event
permanent failure → failure event
```

---

# MEDIA TESTS

Test against:

```text
local disk
fake S3/shared disk
Telegram file_id reuse
```

---

# 21. RUN FULL VALIDATION

After implementation run every available validation command.

At minimum, where supported:

```bash
php artisan test
```

or:

```bash
./vendor/bin/pest
```

Also:

```bash
php artisan migrate --pretend
```

where useful.

Run formatting/static analysis tools if present:

```bash
./vendor/bin/pint
phpstan
larastan
```

Do not install random tooling unless appropriate.

Inspect:

```text
composer.json
package.json
```

and use the project's actual existing scripts.

---

# 22. DO NOT HIDE FAILURES

If existing tests fail before your changes:

identify whether they were already broken.

After modifications:

report clearly:

```text
existing failure
new failure
fixed failure
```

Do not disable tests to make CI green.

Do not weaken assertions simply to pass.

---

# 23. PERFORMANCE REVIEW

After fixing the issues, review whether the design can reasonably support:

```text
100,000 Telegram users
500,000 users
1,000,000 users
```

Specifically inspect:

- Redis memory growth
- Redis key count
- Redis blocking commands
- database query count
- queue depth
- Telegram rate limits
- scheduler load
- audience snapshot creation
- poll update complexity
- webhook throughput
- indexes
- N+1 queries

Do not promise unsupported numbers.

Explain bottlenecks realistically.

---

# 24. REDIS RULES

Remove problematic production patterns where encountered in these modules, especially:

```php
Redis::keys(...)
```

Use:

```text
SCAN
```

or tracked indexes/sets instead.

Avoid blocking Redis commands on large keyspaces.

Ensure temporary operational keys have TTL where appropriate.

---

# 25. BACKWARD COMPATIBILITY

Existing:

- broadcasts
- automation definitions
- menus
- users
- tracking links
- referral data
- poll reports
- media
- settings

must continue working after migration.

Do not require marketers to rebuild their campaigns.

If a data migration is necessary, implement it safely.

---

# 26. DOCUMENTATION

Update project documentation after the code works.

Document:

```text
new broadcast delivery semantics
broadcast recovery behavior
automation execution semantics
Telegram retry rules
poll aggregation architecture
recurring campaign locking
webhook inbox architecture
media disk configuration
new environment variables
scheduler requirements
worker requirements
Redis requirements
```

Update:

```text
.env.example
README
deployment documentation
```

where appropriate.

---

# 27. IMPLEMENTATION PRIORITY

Follow this exact priority.

## Phase 1 — P0

1. Broadcast delivery reliability
2. Automation delivery/state reliability
3. Poll aggregation redesign
4. Recurring broadcast atomicity

Do not proceed casually to cosmetic issues while these remain broken.

---

## Phase 2 — P1

5. Central Telegram retry classification
6. Direct-message retries
7. Poll feature toggle enforcement
8. Mini App feature toggle enforcement
9. Connect `webapp.url`
10. Durable webhook ingestion
11. Scheduler overlap protection
12. Shared media storage

---

## Phase 3 — Hardening

13. Redis cleanup
14. Recovery commands
15. metrics/logging
16. documentation
17. load/concurrency review

---

# 28. IMPORTANT ARCHITECTURAL PRINCIPLES

Follow these throughout the implementation:

```text
Never mark work complete before the side effect is safely accounted for.

Never remove a queue item before it can be recovered.

Never depend solely on a UI feature toggle.

Never use Redis alone for critical permanent idempotency when durable DB state is appropriate.

Never assume only one worker exists.

Never assume only one scheduler exists.

Never assume Telegram requests always give a definitive success/failure result.

Never assume local disk is shared across application instances.

Never scan all campaign recipients to process one poll vote.

Never solve concurrency only with application-level `if` statements.

Never silently swallow retryable Telegram failures.
```

---

# 29. DO NOT OVERENGINEER

Although reliability is critical, keep the implementation consistent with the existing Laravel architecture.

Do not introduce:

- Kafka
- RabbitMQ
- Kubernetes
- microservices
- event sourcing
- new programming languages

unless there is an extraordinary reason already present in the project.

Laravel + PostgreSQL + Redis should be sufficient.

---

# 30. REQUIRED FINAL REPORT

When finished, provide me a detailed report with these sections.

## A. Files changed

List every important file modified or added.

Example:

```text
app/...
database/migrations/...
tests/Feature/...
config/...
```

---

## B. Architecture changes

Explain each P0/P1 change.

Show:

```text
BEFORE
```

and:

```text
AFTER
```

for the critical workflows.

---

## C. Broadcast guarantee

State exactly what guarantee now exists.

For example:

```text
at-least-once processing
```

and explain remaining unavoidable Telegram ambiguity.

---

## D. Automation guarantee

Explain when a step is considered:

```text
claimed
queued
sent
completed
```

---

## E. Poll complexity

State previous estimated complexity and new complexity.

Expected target:

```text
Before: O(recipients) per relevant update
After: O(options) / approximately O(1)
```

---

## F. Concurrency protection

Explain protection against:

- duplicate recurring broadcasts
- duplicate Telegram updates
- duplicate automation claims
- duplicate recipient claims
- multiple scheduler instances

---

## G. Retry matrix

Give me a table like:

| Failure | Retry? | Behavior |
|---|---:|---|
| Telegram 400 | No | Permanent failure |
| Telegram 403 | No | Mark blocked |
| Telegram 429 | Yes | Respect retry_after |
| Telegram 5xx | Yes | Backoff |
| Network timeout | Yes | Backoff |
| Connection error | Yes | Backoff |

Adjust according to actual Telegram API behavior found in the existing implementation.

---

## H. Database changes

Explain:

- migrations
- new tables
- new columns
- indexes
- uniqueness constraints

---

## I. Redis changes

Explain:

- Streams if used
- consumer groups
- retry/recovery
- TTL
- removed `KEYS`
- new key patterns

---

## J. Media architecture

Explain how:

```text
local development
```

and:

```text
distributed production storage
```

now work.

---

## K. Tests

Tell me:

```text
tests before
tests after
number passed
number failed
```

List important new concurrency/reliability tests.

---

## L. Remaining risks

Do not tell me everything is perfect.

Identify realistic remaining risks.

---

## M. Production deployment instructions

Give exact deployment requirements such as:

```text
migration command
queue restart
scheduler requirements
new environment variables
Redis requirements
object storage variables
rollback considerations
```

---

# 31. EXECUTION MODE

You are working in Claude Terminal.

You have permission to:

- inspect the entire repository
- edit code
- create migrations
- create tests
- update configuration
- update documentation
- execute safe development/test commands
- inspect Git diff

Do not merely explain what should be changed.

**Implement the fixes.**

Do not stop after the audit.

Continue until:

```text
P0 issues are fixed
P1 issues are fixed
tests are added
tests are run
documentation is updated
final diff has been reviewed
```

(Amended: subject to the phase gates at the top of this document — stop for review after each phase.)

Before finishing, inspect your own diff as a senior code reviewer and look specifically for:

```text
race conditions
silent failure
duplicate delivery
message loss
N+1 queries
Redis blocking operations
missing DB indexes
missing uniqueness constraints
incorrect retry behavior
deadlocks
unbounded Redis growth
unbounded table growth
migration safety
backward compatibility
security regressions
```

Fix anything you find before reporting completion.
