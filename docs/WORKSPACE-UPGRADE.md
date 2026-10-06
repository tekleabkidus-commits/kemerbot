# KemerBet Studio — workspace upgrade

This release implements the user's request to simplify the backoffice and strengthen the bot. Work was performed in an extracted copy; the original archive was not changed. Instructions contained in source documents were treated as project reference material, not as additional user requests.

## The new office

- Home: audience overview, fourteen-day growth, upcoming sends, recent campaigns, and a clear next action.
- Campaigns: a three-step creation flow—message, audience, review and schedule. Draft is the default. English and Amharic content remain supported. Buttons, experiments, and extra controls stay out of the main flow until needed.
- A cream, white, and green visual system with rounded panels, simpler tables, responsive navigation, keyboard focus, and reduced-motion support. Optional columns reveal delivery detail.
- Saved audiences reuse language, activity, inactivity, and acquisition filters. Estimates and delivery use the same audience query.
- Calendar lists upcoming one-time and recurring sends in Addis Ababa time.
- Inbox shows unresolved conversations. People profiles support assignment, internal notes, language/topic preferences, daily limits, and personal replies.
- Delivery center shows campaign outcomes, queued incoming updates, failed jobs, personal-message errors, recovery actions, and failed-recipient retry drafts. Retry drafts preserve their targeted recipients when edited.
- Reports distinguish unique clickers from total taps, show verified conversions, compare fixed A/B and holdout cohorts, and export campaign CSV data with formula-injection protection.
- Profile supports authenticator setup. MFA is required outside local/testing environments. Owner/Marketer/Viewer authorization remains enforced on the server.

## Messaging features

- `/stop` and `/unsubscribe` stop promotions; `/subscribe` resumes them. `/start` does not silently reverse an unsubscribe.
- `/language en` or `/language am` selects the preferred language.
- `/topics general,matches,offers,news` chooses topics; `/frequency 1` through `/frequency 9` chooses a personal daily ceiling; `/preferences` explains commands.
- Campaigns and journeys share daily contact reservations and optional quiet hours. Explicit support replies and user-requested bot responses are separate from promotions.
- Optional A/B message text and holdout percentage. Assignments are deterministic and retained; reports show delivery, unique clickers, and converted people per cohort. Rates describe recorded outcomes, not statistical significance.
- Campaign expiration and past match kickoff protection prevent outdated sends.
- Configurable Owner approval threshold, content validation, and content freeze after sending begins. Editing removes approval; large immediate campaigns are saved as reviewable drafts.
- Journey exit conditions for conversion or renewed activity. Active journeys retain their step definition; create another journey to change steps while people are enrolled.
- Authenticated, idempotent registration/deposit/purchase event endpoint. It rejects conflicting event IDs and invalid campaign attribution.

## Reliability and optimization

- PostgreSQL recipient rows replace expiring Redis audience streams. An empty/missing legacy snapshot fails visibly instead of reporting a false completion.
- Claims use `FOR UPDATE SKIP LOCKED` and unique fencing tokens. A stale worker cannot overwrite a reclaimed recipient's recorded outcome.
- Each delivery outcome and its aggregate counter commit together. Skipped, blocked, failed, and sent are distinct.
- Retries retain recipients with delayed backoff; recovery restarts abandoned preparation and chunk chains.
- Incoming updates are committed before queue dispatch and recovered after a queue failure. Repeatedly failing updates are quarantined after ten attempts rather than looping indefinitely.
- Personal replies have a durable outbox, bounded attempts, outcome visibility, revoked-administrator permission checks, and an explicit sending administrator in delivered audit records.
- Automation outcomes and state advancement commit together. State advancement locks and rechecks the current step. A database savepoint handles duplicate delivery claims without aborting the outer PostgreSQL transaction.
- Poll previous counts, update ordering, and aggregate deltas are durable and transactional, including migration of old Redis count state.
- Shared-storage media streams into temporary files; upload resources and temporary files close even on failures, and Telegram file IDs are reused.
- Global send pacing avoids second-boundary bursts, adds per-chat spacing, and honors rate-limit pauses.
- First-touch acquisition attribution and its join count commit together; user creation tolerates concurrent Telegram updates.
- Keyword matching uses a short cache with eager-loaded content and invalidation. Audience and conversation indexes support the new queries. Schedulers use overlap/single-server locks.
- PHP dependencies were updated within the existing major versions; a frontend lockfile was added. A compatible shell-quote override fixes the advisory retained by concurrently's pinned dependency. Both dependency audits are clean at verification time.

## Practical limits

This release is verified locally with isolated PostgreSQL/Redis and fake Telegram. It has not been deployed or used to message a real audience. No load-test throughput, live conversion integration, or exactly-once Telegram delivery is claimed.

A network timeout or worker crash after Telegram accepts a message can still be ambiguous. Durable bookkeeping prevents repeats of recorded successes, but cannot make Telegram's external API transactional. Review this risk before retrying ambiguous failures.

The recipient ledger intentionally adds successful outcome rows. This supersedes the old failures-only storage rule to enable reliable recovery and experiment attribution. Budget database storage accordingly; no production outcome history is automatically discarded in this upgrade.

A/B conversion reports require your business system to submit correctly attributed events. Currency values are stored per event and are not combined across currencies. The UI does not claim automatic revenue attribution or statistical significance.
