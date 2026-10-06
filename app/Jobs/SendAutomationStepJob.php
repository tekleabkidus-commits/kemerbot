<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutomationDeliveryStatus;
use App\Enums\AutomationUserStatus;
use App\Models\AutomationStepDelivery;
use App\Services\Automations\AutomationStateAdvancer;
use App\Services\Bot\BotLocaleResolver;
use App\Services\Bot\BotMessageSender;
use App\Services\Bot\EmbeddedButtonsRenderer;
use App\Services\Bot\TokenRenderer;
use App\Services\ContactPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processes one automation step delivery (transactional outbox, HARDENING §4).
 * Claims the delivery row atomically (queued → sending), sends, and only then
 * advances the enrollment state. Retryable Telegram failures release the job
 * for a delayed retry; permanent failures are recorded and the journey moves
 * on (a blocked user's enrollment is cancelled) so one unreachable user never
 * stalls an automation.
 */
class SendAutomationStepJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120];

    public function __construct(public readonly int $deliveryId) {}

    public function handle(
        BotLocaleResolver $locale,
        TokenRenderer $tokens,
        EmbeddedButtonsRenderer $buttons,
        BotMessageSender $sender,
        AutomationStateAdvancer $advancer,
    ): void {
        $delivery = AutomationStepDelivery::query()
            ->with(['state.automation.steps', 'step.translations', 'step.mediaFile', 'user'])
            ->find($this->deliveryId);

        if ($delivery === null || $delivery->state === null) {
            return;
        }

        // Paused between claim and execution: put the delivery back so
        // recovery re-dispatches it after resume. Nothing sends while paused
        // (spec §4.10) and nothing is lost.
        if (! $delivery->state->automation->is_active) {
            AutomationStepDelivery::query()
                ->whereKey($delivery->id)
                ->where('status', AutomationDeliveryStatus::Queued->value)
                ->update(['status' => AutomationDeliveryStatus::Queued->value]);

            return;
        }

        // Atomic claim: only one worker may move queued → sending.
        $claimed = AutomationStepDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', AutomationDeliveryStatus::Queued->value)
            ->update([
                'status' => AutomationDeliveryStatus::Sending->value,
                'attempt_count' => DB::raw('attempt_count + 1'),
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            // Another worker owns it, or it already finished — re-dispatch no-op.
            return;
        }

        $delivery->refresh();

        if ($delivery->state->refresh()->status !== AutomationUserStatus::Active) {
            $this->failDelivery($delivery, 'journey no longer active');

            return;
        }
        $response = null;
        $step = $delivery->step;
        $user = $delivery->user;

        if ($step === null || $user === null) {
            DB::transaction(function () use ($delivery, $advancer) {
                $this->failDelivery($delivery, 'step or user no longer exists');
                $advancer->advancePast($delivery->state, $delivery->state->current_step_no);
            });

            return;
        }

        if ($user->blocked_bot) {
            DB::transaction(function () use ($delivery, $advancer) {
                $this->failDelivery($delivery, 'user has blocked the bot');
                $advancer->cancel($delivery->state);
            });

            return;
        }

        $conditions = $delivery->state->automation->trigger_config ?? [];
        if (($conditions['exit_on_conversion'] ?? false) && $user->converted_at ||
            ($conditions['exit_on_activity'] ?? false) && $user->last_active_at && $user->last_active_at->gt($delivery->state->triggered_at)) {
            DB::transaction(function () use ($delivery, $advancer) {
                $this->failDelivery($delivery, 'journey exit condition met');
                $advancer->cancel($delivery->state);
            });

            return;
        }
        $decision = app(ContactPolicy::class)->reserve($user, 'automation:'.$delivery->id, $conditions['topic'] ?? 'general');
        if ($decision === 'skip') {
            DB::transaction(function () use ($delivery, $advancer) {
                $this->failDelivery($delivery, 'user messaging preference');
                $advancer->cancel($delivery->state);
            });

            return;
        }
        if ($decision === 'wait') {
            $delivery->forceFill(['status' => AutomationDeliveryStatus::Queued, 'attempt_count' => max(0, $delivery->attempt_count - 1)])->save();

            return;
        }
        $lang = $locale->resolve($user);
        $translation = $locale->pickTranslation($step->translations, $lang);

        if ($translation === null && $step->media_file_id === null) {
            Log::warning('automation.step_has_no_content', ['step_id' => $step->id]);
            DB::transaction(function () use ($delivery, $advancer, $step) {
                $this->failDelivery($delivery, 'step has no content');
                $advancer->advancePast($delivery->state, $step->step_no);
            });

            return;
        }

        $text = $translation !== null ? $tokens->renderForUser($translation->text, $user) : null;

        $response = $sender->sendToUser($user, $text, $step->mediaFile, $buttons->render($step->buttons, $lang));

        if ($response->successful()) {
            DB::transaction(function () use ($delivery, $step, $advancer) {
                $step->newQuery()->whereKey($step->id)->update(['sent_count' => DB::raw('sent_count + 1')]);

                $delivery->forceFill([
                    'status' => AutomationDeliveryStatus::Sent,
                    'sent_at' => now(),
                ])->save();

                // Only NOW does the enrollment move forward (HARDENING §4).
                $advancer->advancePast($delivery->state, $step->step_no);
            });

            return;
        }

        if ($response->blockedByUser()) {
            DB::transaction(function () use ($delivery, $advancer, $response) {
                $this->failDelivery($delivery, 'blocked: '.(string) $response->description);
                $advancer->cancel($delivery->state);
            });

            return;
        }

        if ($response->retryable() && $delivery->attempt_count < $this->tries) {
            // Put the row back to queued and release the JOB for a delayed
            // retry — Laravel's backoff drives the schedule; the delivery row
            // stays durable in case the worker dies while waiting.
            $delivery->forceFill(['status' => AutomationDeliveryStatus::Queued])->save();

            $this->release($this->backoff[min($delivery->attempt_count, count($this->backoff)) - 1] ?? 60);

            return;
        }

        // Permanent (or retry-exhausted): record and move on — never stall
        // the journey on one failed message.
        DB::transaction(function () use ($delivery, $response, $step, $advancer) {
            DB::transaction(function () use ($delivery, $advancer, $step, $response) {
                $this->failDelivery($delivery, ($response->errorCode ?? 'network').': '.(string) $response->description);
                $advancer->advancePast($delivery->state, $step->step_no);
            });
        });
    }

    public function failed(): void
    {
        $delivery = AutomationStepDelivery::query()->with('state.automation.steps')->find($this->deliveryId);

        if ($delivery === null || in_array($delivery->status, [AutomationDeliveryStatus::Sent, AutomationDeliveryStatus::Failed], true)) {
            return;
        }

        DB::transaction(function () use ($delivery) {
            $delivery->forceFill([
                'status' => AutomationDeliveryStatus::Failed,
                'failed_at' => now(),
                'last_error' => 'job exhausted retries',
            ])->save();

            if ($delivery->state !== null) {
                app(AutomationStateAdvancer::class)->advancePast($delivery->state, $delivery->state->current_step_no);
            }
        });
    }

    private function failDelivery(AutomationStepDelivery $delivery, string $reason): void
    {
        $delivery->forceFill([
            'status' => AutomationDeliveryStatus::Failed,
            'failed_at' => now(),
            'last_error' => mb_substr($reason, 0, 490),
        ])->save();

        Log::info('automation.delivery_failed', [
            'delivery_id' => $delivery->id,
            'automation_step_id' => $delivery->automation_step_id,
            'user_id' => $delivery->user_id,
            'reason' => mb_substr($reason, 0, 200),
        ]);
    }
}
