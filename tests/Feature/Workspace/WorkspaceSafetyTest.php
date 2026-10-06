<?php

use App\Enums\BroadcastStatus;
use App\Filament\Pages\Reports;
use App\Filament\Resources\Broadcasts\Pages\CreateBroadcast;
use App\Filament\Resources\Broadcasts\Pages\EditBroadcast;
use App\Filament\Resources\Broadcasts\Pages\ListBroadcasts;
use App\Jobs\PrepareBroadcastJob;
use App\Jobs\SendBroadcastChunkJob;
use App\Jobs\SendDirectMessageJob;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Broadcast;
use App\Models\BroadcastTranslation;
use App\Models\ButtonClick;
use App\Models\MediaFile;
use App\Models\Poll;
use App\Models\PollInstance;
use App\Models\User;
use App\Services\Bot\BotMessageSender;
use App\Services\Bot\PollService;
use App\Services\Broadcasts\AudienceQuery;
use App\Services\Broadcasts\AudienceSnapshot;
use App\Services\Broadcasts\BroadcastChunkSender;
use App\Services\Broadcasts\BroadcastLifecycle;
use App\Services\Broadcasts\CampaignValidator;
use App\Services\Broadcasts\InvalidBroadcastTransition;
use App\Services\ContactPolicy;
use App\Services\DirectMessages;
use App\Services\MediaPayload;
use App\Services\SettingsService;
use Carbon\Carbon;
use Database\Seeders\AdminSeeder;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function workspaceCampaign(array $attributes = []): Broadcast
{
    $campaign = Broadcast::factory()->create($attributes);
    BroadcastTranslation::factory()->for($campaign)->create(['text' => 'Hello {first_name}']);

    return $campaign;
}

it('fences a reclaimed recipient against stale worker outcomes', function () {
    User::factory()->create();
    $campaign = workspaceCampaign();
    $ledger = app(AudienceSnapshot::class);
    $ledger->build($campaign);
    $old = $ledger->claimBatch($campaign->id, 'old', 1)[0];
    DB::table('broadcast_recipients')->where('id', $old['id'])->update(['claimed_at' => now()->subHour()]);
    $fresh = $ledger->claimBatch($campaign->id, 'new', 1)[0];
    expect($ledger->finish($campaign->id, $old, 'sent'))->toBeFalse()
        ->and($ledger->finish($campaign->id, $fresh, 'sent'))->toBeTrue()
        ->and($ledger->finish($campaign->id, $fresh, 'sent'))->toBeFalse()
        ->and($campaign->refresh()->sent)->toBe(1);
});

it('rolls back both an outcome and its counter together', function () {
    User::factory()->create();
    $campaign = workspaceCampaign();
    $ledger = app(AudienceSnapshot::class);
    $ledger->build($campaign);
    $entry = $ledger->claimBatch($campaign->id, 'worker', 1)[0];
    try {
        DB::transaction(function () use ($ledger, $campaign, $entry) {
            $ledger->finish($campaign->id, $entry, 'sent');
            throw new RuntimeException('simulated transaction failure');
        });
    } catch (RuntimeException $e) {
    }
    expect($campaign->refresh()->sent)->toBe(0)
        ->and(DB::table('broadcast_recipients')->where('id', $entry['id'])->value('status'))->toBe('processing');
});

it('leaves retryable recipients waiting until their backoff expires', function () {
    User::factory()->create();
    $campaign = workspaceCampaign();
    $ledger = app(AudienceSnapshot::class);
    $ledger->build($campaign);
    $entry = $ledger->claimBatch($campaign->id, 'worker', 1)[0];
    $ledger->requeueForRetry($campaign->id, $entry);
    expect($ledger->claimBatch($campaign->id, 'early', 1))->toBe([])
        ->and($ledger->outstanding($campaign->id))->toBe(1);
    $this->travel(10)->seconds();
    expect($ledger->claimBatch($campaign->id, 'later', 1)[0]['attempt'])->toBe(2);
});

it('fails safely rather than completing a campaign with a missing legacy snapshot', function () {
    $campaign = workspaceCampaign(['status' => 'sending', 'queued' => 10]);
    (new SendBroadcastChunkJob($campaign->id))->handle(app(AudienceSnapshot::class), app(BroadcastChunkSender::class), app(BroadcastLifecycle::class));
    expect($campaign->refresh()->status)->toBe(BroadcastStatus::Failed)
        ->and($campaign->failure_reason)->toContain('ledger is missing')->and(fakeTelegram()->nothingSent())->toBeTrue();
});

it('does not send to people who unsubscribe after preparation', function () {
    Queue::fake();
    $user = User::factory()->create();
    $campaign = workspaceCampaign();
    app(BroadcastLifecycle::class)->start($campaign);
    (new PrepareBroadcastJob($campaign->id))->handle(app(BroadcastLifecycle::class));
    $user->update(['marketing_subscribed' => false]);
    (new SendBroadcastChunkJob($campaign->id))->handle(app(AudienceSnapshot::class), app(BroadcastChunkSender::class), app(BroadcastLifecycle::class));
    expect(fakeTelegram()->nothingSent())->toBeTrue()->and($campaign->refresh()->skipped)->toBe(1)->and($campaign->failed)->toBe(0);
});

it('shares contact caps between campaigns and journeys while preserving a retry reservation', function () {
    $user = User::factory()->create(['daily_message_limit' => 1]);
    $policy = app(ContactPolicy::class);
    expect($policy->reserve($user, 'campaign:1'))->toBe('send')
        ->and($policy->reserve($user, 'journey:1'))->toBe('wait')
        ->and($policy->reserve($user, 'campaign:1'))->toBe('send');
    $user->update(['marketing_subscribed' => false]);
    expect($policy->reserve($user, 'campaign:1'))->toBe('skip');
});

it('honors quiet hours across midnight in Addis time', function () {
    app(SettingsService::class)->set('messaging.quiet_start', 22);
    app(SettingsService::class)->set('messaging.quiet_end', 7);
    $user = User::factory()->create();
    $this->travelTo(Carbon::parse('2026-10-06 23:00:00', 'Africa/Addis_Ababa'));
    expect(app(ContactPolicy::class)->reserve($user, 'night'))->toBe('wait');
    $this->travelTo(Carbon::parse('2026-10-07 08:00:00', 'Africa/Addis_Ababa'));
    expect(app(ContactPolicy::class)->reserve($user, 'morning'))->toBe('send');
});

it('respects topic choices', function () {
    $user = User::factory()->create(['topics' => ['news']]);
    expect(app(ContactPolicy::class)->reserve($user, 'offers:1', 'offers'))->toBe('skip')
        ->and(app(ContactPolicy::class)->reserve($user, 'news:1', 'news'))->toBe('send');
});

it('requires owner approval above the configured audience threshold', function () {
    Queue::fake();
    app(SettingsService::class)->set('campaigns.approval_threshold', 1);
    User::factory()->create();
    $campaign = workspaceCampaign();
    expect(fn () => app(BroadcastLifecycle::class)->start($campaign))->toThrow(InvalidBroadcastTransition::class);
    $campaign->update(['approved_at' => now(), 'approved_by' => Admin::factory()->owner()->create()->id]);
    app(BroadcastLifecycle::class)->start($campaign);
    expect($campaign->refresh()->status)->toBe(BroadcastStatus::Preparing);
});

it('validates the alternative message before a campaign can send', function () {
    $campaign = workspaceCampaign(['experiment' => ['text_b' => ['en' => str_repeat('W', 4100)]]]);
    expect(fn () => app(CampaignValidator::class)->validate($campaign))->toThrow(InvalidBroadcastTransition::class);
});

it('keeps experiment assignments stable and never queues holdout people', function () {
    User::factory()->count(120)->create();
    $campaign = workspaceCampaign(['experiment' => ['holdout_percent' => 30, 'text_b' => ['en' => 'Another message']]]);
    $ledger = app(AudienceSnapshot::class);
    $count = $ledger->build($campaign);
    $rows = DB::table('broadcast_recipients')->where('broadcast_id', $campaign->id)->get();
    expect($rows)->toHaveCount(120)->and($rows->where('status', 'holdout')->count())->toBeGreaterThan(0)
        ->and($count)->toBe(120 - $rows->where('status', 'holdout')->count());
    Redis::connection()->flushdb();
    expect($ledger->build($campaign))->toBe($count)
        ->and(DB::table('broadcast_recipients')->where('broadcast_id', $campaign->id)->get()->toJson())->toBe($rows->toJson());
});

it('supports preference commands without silently resubscribing on start', function () {
    $user = User::factory()->create();
    postWebhook(telegramMessageUpdate($user->tg_chat_id, '/stop'))->assertOk();
    postWebhook(telegramMessageUpdate($user->tg_chat_id, '/start'))->assertOk();
    expect($user->refresh()->marketing_subscribed)->toBeFalse();
    postWebhook(telegramMessageUpdate($user->tg_chat_id, '/subscribe'))->assertOk();
    postWebhook(telegramMessageUpdate($user->tg_chat_id, '/language am'))->assertOk();
    postWebhook(telegramMessageUpdate($user->tg_chat_id, '/frequency 2'))->assertOk();
    expect($user->refresh()->marketing_subscribed)->toBeTrue()->and($user->preferred_language)->toBe('am')->and($user->daily_message_limit)->toBe(2);
});

it('matches the preferred audience language and describes targeted retries', function () {
    $user = User::factory()->create(['language' => 'en', 'preferred_language' => 'am']);
    expect(app(AudienceQuery::class)->estimatedCount(['language' => 'am']))->toBe(1)
        ->and(app(AudienceQuery::class)->describe(['user_ids' => [$user->id]]))->toContain('1 selected people');
});

it('ignores older poll updates and keeps totals consistent after Redis loss', function () {
    $poll = Poll::factory()->create();
    $instance = PollInstance::factory()->for($poll)->create(['tg_poll_id' => 'ordered']);
    $service = app(PollService::class);
    $service->ingestPollUpdate(['id' => 'ordered', 'options' => [['voter_count' => 2]]], 100);
    Redis::connection()->flushdb();
    $service->ingestPollUpdate(['id' => 'ordered', 'options' => [['voter_count' => 1]]], 99);
    expect($poll->refresh()->answer_counts)->toEqual([2])->and($instance->refresh()->last_update_id)->toBe(100);
});

it('stores authenticator secrets encrypted and hides them from profile serialization', function () {
    $admin = Admin::factory()->owner()->create();
    $admin->saveAppAuthenticationSecret('TESTAUTHSECRET');
    expect(DB::table('admins')->where('id', $admin->id)->value('app_authentication_secret'))->not->toBe('TESTAUTHSECRET')
        ->and($admin->refresh()->getAppAuthenticationSecret())->toBe('TESTAUTHSECRET')
        ->and(array_key_exists('app_authentication_secret', $admin->toArray()))->toBeFalse();
});

it('recovers committed webhook updates after queue dispatch failure', function () {
    Queue::shouldReceive('connection')->andThrow(new RuntimeException('queue unavailable'));
    $payload = telegramMessageUpdate(12345000, '/stop', updateId: 850001);
    postWebhook($payload)->assertOk();
    expect(DB::table('telegram_updates')->where('update_id', 850001)->value('status'))->toBe('pending');
});

it('quarantines repeatedly failing webhook updates instead of retrying forever', function () {
    Queue::fake();
    DB::table('telegram_updates')->insert(['update_id' => 850002, 'payload' => json_encode(['update_id' => 850002]), 'status' => 'pending', 'attempts' => 10, 'created_at' => now(), 'updated_at' => now()]);
    $this->artisan('workspace:recover')->assertSuccessful();
    expect(DB::table('telegram_updates')->where('update_id', 850002)->value('status'))->toBe('failed');
});

it('accepts authenticated conversion events once and rejects conflicting reuse', function () {
    config()->set('telegram.conversion_api_key', str_repeat('k', 40));
    $user = User::factory()->create();
    $data = ['external_id' => 'conversion-1', 'tg_chat_id' => $user->tg_chat_id, 'event' => 'registration', 'occurred_at' => now()->subMinute()->toIso8601String()];
    $headers = ['Authorization' => 'Bearer '.str_repeat('k', 40)];
    $this->postJson('/integrations/conversions', $data)->assertUnauthorized();
    $this->postJson('/integrations/conversions', $data, $headers)->assertOk()->assertJson(['created' => true]);
    $this->postJson('/integrations/conversions', $data, $headers)->assertOk()->assertJson(['created' => false]);
    $this->postJson('/integrations/conversions', [...$data, 'event' => 'deposit'], $headers)->assertStatus(409);
    expect(DB::table('conversion_events')->count())->toBe(1)->and($user->refresh()->converted_at)->not->toBeNull();
});

it('rejects conversion attribution to a campaign that did not include the person', function () {
    config()->set('telegram.conversion_api_key', str_repeat('k', 40));
    $user = User::factory()->create();
    $campaign = workspaceCampaign();
    $this->postJson('/integrations/conversions', ['external_id' => 'invalid-attribution', 'tg_chat_id' => $user->tg_chat_id, 'event' => 'registration', 'broadcast_id' => $campaign->id, 'occurred_at' => now()->subMinute()->toIso8601String()], ['Authorization' => 'Bearer '.str_repeat('k', 40)])->assertStatus(422);
});

it('renders the new workspace pages for viewers without exposing owner recovery actions', function () {
    $this->actingAs(Admin::factory()->viewer()->create());
    foreach (['calendar', 'reports', 'inbox', 'delivery-center', 'segments'] as $page) {
        $this->get('/admin/'.$page)->assertOk();
    }
    $this->get('/admin/delivery-center')->assertDontSee('Check &amp; recover', false);
});

it('does not resend a direct message after its durable outcome is recorded', function () {
    $user = User::factory()->create();
    $admin = Admin::factory()->marketer()->create();
    $job = new SendDirectMessageJob($user->id, $admin->id, 'Personal support reply');
    $job->handle(app(BotMessageSender::class));
    $job->handle(app(BotMessageSender::class));
    expect(fakeTelegram()->sentTo($user->tg_chat_id))->toHaveCount(1)
        ->and(DB::table('direct_message_deliveries')->value('status'))->toBe('sent')
        ->and(AuditLog::where('action', 'direct_message.delivered')->exists())->toBeTrue();
});

it('recovers a personal reply committed before the queue accepted it', function () {
    Queue::fake();
    $user = User::factory()->create();
    $admin = Admin::factory()->marketer()->create();
    $id = app(DirectMessages::class)->queue($user->id, $admin->id, 'Durable personal reply');
    expect(DB::table('direct_message_deliveries')->where('id', $id)->value('status'))->toBe('pending');
    $this->artisan('workspace:recover')->assertSuccessful();
    Queue::assertPushed(SendDirectMessageJob::class, fn ($job) => $job->deliveryId === $id);
});

it('preserves targeted retry recipients when their message is edited', function () {
    $this->actingAs(Admin::factory()->marketer()->create());
    $target = User::factory()->create();
    User::factory()->create();
    $campaign = workspaceCampaign(['audience_filter' => ['user_ids' => [$target->id]], 'approved_at' => now()]);
    Livewire::test(EditBroadcast::class, ['record' => $campaign->id])
        ->fillForm(['text_en' => 'Corrected content', 'timing_mode' => 'draft', 'aud_user_ids' => null])->call('save')->assertHasNoFormErrors();
    expect($campaign->refresh()->audience_filter['user_ids'])->toBe([$target->id])
        ->and($campaign->approved_at)->toBeNull()->and($campaign->translations()->first()->text)->toBe('Corrected content');
});

it('saves large immediate campaigns as reviewable drafts', function () {
    $this->actingAs(Admin::factory()->marketer()->create());
    app(SettingsService::class)->set('campaigns.approval_threshold', 1);
    User::factory()->create();
    Livewire::test(CreateBroadcast::class)
        ->fillForm(['name' => 'Needs approval', 'type' => 'standard', 'text_en' => 'Ready for review', 'timing_mode' => 'now'])->call('create')->assertHasNoFormErrors();
    expect(Broadcast::where('name', 'Needs approval')->first()->status)->toBe(BroadcastStatus::Draft)
        ->and(fakeTelegram()->nothingSent())->toBeTrue();
});

it('removes a shared-storage media temporary file even when sending throws', function () {
    Storage::fake();
    $media = MediaFile::factory()->create();
    Storage::put($media->path, 'shared media fixture');
    $temporary = null;
    try {
        app(MediaPayload::class)->withFile($media, function ($path) use (&$temporary) {
            $temporary = $path;
            expect(file_get_contents($path))->toBe('shared media fixture');
            throw new RuntimeException('send interrupted');
        });
    } catch (RuntimeException $e) {
    }
    expect($temporary)->not->toBeNull()->and(file_exists($temporary))->toBeFalse();
});

it('reports unique experiment clickers and conversions without multiplying repeated events', function () {
    $campaign = workspaceCampaign(['experiment' => ['text_b' => ['en' => 'Alternative']]]);
    $user = User::factory()->create();
    app(AudienceSnapshot::class)->build($campaign);
    DB::table('broadcast_recipients')->where('broadcast_id', $campaign->id)->update(['variant' => 'a', 'status' => 'sent']);
    ButtonClick::factory()->count(3)->create(['broadcast_id' => $campaign->id, 'user_id' => $user->id]);
    foreach (['one', 'two'] as $event) {
        DB::table('conversion_events')->insert(['external_id' => $event, 'user_id' => $user->id, 'broadcast_id' => $campaign->id, 'event' => 'purchase', 'value' => 1, 'currency' => 'ETB', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }
    $data = (new ReflectionMethod(Reports::class, 'getViewData'))->invoke(new Reports);
    expect((int) $data['experiments'][0]->audience)->toBe(1)->and((int) $data['experiments'][0]->clickers)->toBe(1)
        ->and((int) $data['experiments'][0]->converted)->toBe(1)->and((int) $data['clicks'][$campaign->id]->taps)->toBe(3);
});

it('approves campaign content through the owner action without persisting transient rendering state', function () {
    $admin = Admin::factory()->owner()->create();
    $this->actingAs($admin);
    $campaign = workspaceCampaign(['experiment' => ['text_b' => ['en' => 'Another version']]]);
    Livewire::test(ListBroadcasts::class)
        ->callTableAction('approve', $campaign)->assertHasNoActionErrors();
    expect($campaign->refresh()->approved_at)->not->toBeNull()->and($campaign->approved_by)->toBe($admin->id);
});

it('seeds the local example owner when optional seed variables are blank', function () {
    Env::getRepository()->set('SEED_ADMIN_EMAIL', '');
    Env::getRepository()->set('SEED_ADMIN_PASSWORD', '');
    try {
        $this->seed(AdminSeeder::class);
        $admin = Admin::where('email', 'owner@kemerbet.co')->first();
        expect($admin)->not->toBeNull()->and(Hash::check('password', $admin->password))->toBeTrue();
    } finally {
        Env::getRepository()->clear('SEED_ADMIN_EMAIL');
        Env::getRepository()->clear('SEED_ADMIN_PASSWORD');
    }
});

it('stops a queued personal reply when its administrator is removed', function () {
    Queue::fake();
    $user = User::factory()->create();
    $admin = Admin::factory()->marketer()->create();
    $id = app(DirectMessages::class)->queue($user->id, $admin->id, 'Pending support reply');
    $admin->delete();
    (new SendDirectMessageJob($user->id, $admin->id, 'Pending support reply', $id))->handle(app(BotMessageSender::class));
    expect(fakeTelegram()->nothingSent())->toBeTrue()->and(DB::table('direct_message_deliveries')->where('id', $id)->value('status'))->toBe('failed');
});
