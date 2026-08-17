<?php

declare(strict_types=1);

use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Schedule;

// Spec §2: Laravel Scheduler runs every minute in production (cron).
Schedule::command('broadcasts:process-due')->everyMinute();

// Retention: prune telegram_messages older than the configured window (spec §6 table 18).
Schedule::command('model:prune', ['--model' => [TelegramMessage::class]])->daily();
