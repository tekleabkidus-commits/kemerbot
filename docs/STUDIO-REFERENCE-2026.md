# KemerBet Audience Studio — Reference UI implementation (2026-10-27)

## Source of design truth

The user-approved 2026 desktop Inbox screenshot and full-platform showcase are the visual references for this release. The application is not a marketing collage: figures, names, message history and preferences must be actual database records. This creates content-dependent visual differences from a fixed mockup.

## Implemented in code

- Shared compact white sidebar / top toolbar, KemerBet green & mint tokens, modern cards, buttons, inputs, badges, validation states, modals, data tables, responsive spacing.
- Home: 4 data-backed KPIs, real delivery and joins chart, quick actions, conversations and campaign performance.
- Inbox: 4 KPI cards; three-column conversation list, chat and CRM inspector; responsive independent views on narrow screens; real Telegram chat history, reply composer, quick reply drafts, notes, consent indicators, activity and delivery history; staff permissions enforced by existing roles.
- People/CRM: native searchable/filterable Filament table, quick profile preview and full conversation profile.
- Campaigns: filter tabs, real draft preview, five-step create wizard. The existing Telegram sender, media validation, consent rules and state transitions are unchanged.
- Automations: actual saved trigger/wait/message sequence on visual canvas, with original validated editor for changes.
- Reports: real audience/delivery trends, campaign results and experiment cohort table.
- Calendar: functional month navigation and scheduled entries in Addis Ababa time.
- Settings, Delivery Center, tracking links, polls, menus, keyword replies, saved audiences, staff administration: common design system applied to their actual Filament forms and tables.
- Mobile: bottom Home / Inbox / People / Calendar / More navigation; More opens the rest of the live platform links. Touch targets and scrollable layouts.

## Explicitly not represented as functional until a supported backend exists

- WhatsApp, SMS, email sending; only the Telegram integration currently exists.
- Read receipts: Telegram Bot API does not provide verified read receipts for private bot messages.
- Inbox file upload/send: attachment icon is disabled until safe durable media-outbox and shared object storage support are implemented.
- Branching A/B conditional automation editor: actual automation currently supports ordered wait/send steps; visual preview does not promise unsupported branches.
- Billing / Pro plan functionality: omitted to avoid fake upgrade flows.
- Static image values such as 98% reply rate, 12.4K reach, or 8,932 delivered: replaced with real metrics.

## Deploy safely

1. Review the GitHub diff on \`main\` and create a deployment backup/rollback point.
2. Run \`composer install --no-interaction\` if dependencies need refreshing; no new Composer dependencies were introduced.
3. Check syntax: \`find app tests database/migrations -name '*.php' -print0 | xargs -0 -n1 php -l\`.
4. Run \`php artisan view:cache\` to catch Blade compilation errors, then run the Pest feature tests against the project's isolated PostgreSQL/Redis testing environment.
5. Deploy to a preview environment first; compare screenshots at 1648x927, 1440x900, 1024x768, 390x844 and 375x812.
6. Confirm every list's search, filters and actions; five campaign wizard steps; Inbox chat, profile tabs, note permissions and real send acknowledgements. Also verify latency with a larger dataset.
7. Promote to production only after these checks. The UI commit itself does not require schema migrations.

## Verification status

GitHub push success confirms the code is stored on the main branch, **not** successful rendering on Laravel Cloud. The GitHub-hosted test attempts immediately reported failure with empty job step data; remote logs were not retrievable in this session, so neither a successful test suite nor a precise pixel match is claimed. Tests remain checked in under \`tests/Feature/Panel\`, ready to run in a configured Laravel test environment.
