<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\AutomationStepTranslation;
use App\Models\AutomationUserState;
use App\Models\Broadcast;
use App\Models\BroadcastButton;
use App\Models\BroadcastButtonTranslation;
use App\Models\BroadcastFailure;
use App\Models\BroadcastTranslation;
use App\Models\ButtonClick;
use App\Models\KeywordReply;
use App\Models\KeywordReplyTranslation;
use App\Models\MediaFile;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\Poll;
use App\Models\PollInstance;
use App\Models\Setting;
use App\Models\TelegramMessage;
use App\Models\TrackingLink;
use App\Models\User;

it('has a working factory for every model', function (string $model) {
    $instance = $model::factory()->create();

    expect($instance->exists)->toBeTrue()
        ->and($model::query()->count())->toBeGreaterThanOrEqual(1);
})->with([
    Admin::class,
    AuditLog::class,
    Automation::class,
    AutomationStep::class,
    AutomationStepTranslation::class,
    AutomationUserState::class,
    Broadcast::class,
    BroadcastButton::class,
    BroadcastButtonTranslation::class,
    BroadcastFailure::class,
    BroadcastTranslation::class,
    ButtonClick::class,
    KeywordReply::class,
    KeywordReplyTranslation::class,
    MediaFile::class,
    MenuItem::class,
    MenuItemTranslation::class,
    Poll::class,
    PollInstance::class,
    Setting::class,
    TelegramMessage::class,
    TrackingLink::class,
    User::class,
]);
