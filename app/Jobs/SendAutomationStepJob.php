<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AutomationStep;
use App\Models\AutomationUserState;
use App\Models\User;
use App\Services\Bot\BotLocaleResolver;
use App\Services\Bot\BotMessageSender;
use App\Services\Bot\EmbeddedButtonsRenderer;
use App\Services\Bot\TokenRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sends one already-claimed automation step to one user on the automation
 * queue (spec §8). Respects the global rate limiter via BotMessageSender,
 * skips blocked users, and bumps the per-step sent counter (spec §5.5).
 */
class SendAutomationStepJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(
        public readonly int $stateId,
        public readonly int $stepId,
        public readonly int $userId,
    ) {}

    public function handle(
        BotLocaleResolver $locale,
        TokenRenderer $tokens,
        EmbeddedButtonsRenderer $buttons,
        BotMessageSender $sender,
    ): void {
        $state = AutomationUserState::query()->with('automation')->find($this->stateId);
        $step = AutomationStep::query()->with(['translations', 'mediaFile'])->find($this->stepId);
        $user = User::query()->find($this->userId);

        if ($state === null || $step === null || $user === null) {
            return;
        }

        // Paused between claim and execution (spec §4.10: paused sends nothing).
        if (! $state->automation->is_active) {
            Log::info('automation.step_skipped_paused', ['state_id' => $state->id, 'step_id' => $step->id]);

            return;
        }

        if ($user->blocked_bot) {
            return;
        }

        $lang = $locale->resolve($user);
        $translation = $locale->pickTranslation($step->translations, $lang);

        if ($translation === null && $step->media_file_id === null) {
            Log::warning('automation.step_has_no_content', ['step_id' => $step->id]);

            return;
        }

        $text = $translation !== null ? $tokens->renderForUser($translation->text, $user) : null;

        $response = $sender->sendToUser($user, $text, $step->mediaFile, $buttons->render($step->buttons, $lang));

        if ($response->successful()) {
            AutomationStep::query()->whereKey($step->id)->update([
                'sent_count' => DB::raw('sent_count + 1'),
            ]);
        }
    }
}
