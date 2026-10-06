<?php

namespace App\Filament\Pages;

use App\Models\Broadcast;
use App\Services\AuditLogger;
use App\Services\Broadcasts\BroadcastLifecycle;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DeliveryCenter extends Page
{
    protected string $view = 'filament.pages.delivery-center';

    protected static ?string $navigationLabel = 'Delivery center';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected function getViewData(): array
    {
        return [
            'counts' => DB::table('broadcast_recipients')->selectRaw('status,count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'campaigns' => Broadcast::where(fn ($q) => $q->whereIn('status', ['failed', 'preparing', 'sending', 'paused'])->orWhere('failed', '>', 0))->latest()->limit(30)->get(),
            'inboxPending' => DB::table('telegram_updates')->where('status', 'pending')->count(),
            'inboxFailed' => DB::table('telegram_updates')->where('status', 'failed')->count(),
            'directMessages' => DB::table('direct_message_deliveries')->whereIn('status', ['pending', 'sending', 'failed'])->orderByDesc('created_at')->limit(20)->get(),
            'failedJobs' => DB::table('failed_jobs')->count(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('retry_updates')->label('Retry failed updates')->visible(fn () => auth()->user()->isOwner())->requiresConfirmation()->modalDescription('Retry quarantined updates after correcting the cause. A partial operation may already have reached Telegram.')->action(function () {
            abort_unless(auth()->user()->isOwner(), 403);
            DB::table('telegram_updates')->where('status', 'failed')->update(['status' => 'pending', 'attempts' => 0, 'updated_at' => now()]);
            app(AuditLogger::class)->log('workspace.updates_retried');
            Artisan::call('workspace:recover');
        }), Action::make('recover')->label('Check & recover')->icon('heroicon-o-arrow-path')->visible(fn () => auth()->user()->isOwner())->requiresConfirmation()->modalDescription('Recover interrupted work using the recorded delivery state. Messages already recorded as sent are excluded.')
            ->action(function () {
                abort_unless(auth()->user()->isOwner(), 403);
                Artisan::call('workspace:recover');
                Artisan::call('broadcasts:recover-stalled');
                app(AuditLogger::class)->log('workspace.recovered');
                Notification::make()->title('Recovery check complete')->success()->send();
            })];
    }

    public function retryFailed(int $id): void
    {
        abort_unless(auth()->user()->isOwner(), 403);
        $original = Broadcast::findOrFail($id);
        $ids = DB::table('broadcast_recipients')->where('broadcast_id', $id)->where('status', 'failed')->pluck('user_id')->all();
        if (! $ids) {
            Notification::make()->title('No failed recipients to retry')->warning()->send();

            return;
        }
        $copy = app(BroadcastLifecycle::class)->duplicate($original);
        $copy->update(['name' => ($original->name ?? 'Campaign').' · retry', 'audience_filter' => ['user_ids' => $ids]]);
        app(AuditLogger::class)->log('campaign.retry_draft', $copy, ['original' => $id, 'recipients' => count($ids)]);
        Notification::make()->title('Retry draft created')->body('Review and approve it in Campaigns before sending.')->success()->send();
    }
}
