<?php

declare(strict_types=1);

use App\Models\AutomationStepDelivery;
use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Schedule;

// Spec §2: Laravel Scheduler runs every minute in production (cron).
Schedule::command('workspace:recover')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('broadcasts:process-due')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('automations:run')->everyMinute()->withoutOverlapping()->onOneServer();

// HARDENING §3: revive chunk chains whose worker died (safe to re-dispatch —
// stream claims are atomic and finished recipients are skipped).
Schedule::command('broadcasts:recover-stalled')->everyMinute()->withoutOverlapping()->onOneServer();

// Retention: prune telegram_messages older than the configured window (spec §6 table 18).
Schedule::command('model:prune', ['--model' => [TelegramMessage::class, AutomationStepDelivery::class]])->daily();

// Retention: reclaim Redis snapshots for missing/terminal broadcasts (spec §7).
Schedule::command('broadcasts:sweep-orphans')->hourly();
