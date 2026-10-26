<?php

declare(strict_types=1);

use App\Enums\MessageDirection;
use App\Filament\Pages\Inbox;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Jobs\SendDirectMessageJob;
use App\Models\Admin;
use App\Models\TelegramMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('shows a real conversation and links the contact name to the profile', function () {
    $admin = Admin::factory()->owner()->create();
    $user = User::factory()->create([
        'first_name' => 'Example Person',
        'username' => 'exampleperson',
        'support_status' => 'open',
    ]);

    TelegramMessage::factory()->create([
        'user_id' => $user->id,
        'direction' => MessageDirection::Inbound,
        'text' => 'My latest real message',
    ]);

    $this->actingAs($admin)
        ->get('/admin/inbox')
        ->assertOk()
        ->assertSee('Example Person')
        ->assertSee('My latest real message')
        ->assertSee('/admin/users/'.$user->id, false);
});

it('switches the inbox conversation without redirecting to the profile', function () {
    $admin = Admin::factory()->owner()->create();
    $first = User::factory()->create(['first_name' => 'First', 'support_status' => 'open']);
    $second = User::factory()->create(['first_name' => 'Second', 'support_status' => 'open']);

    TelegramMessage::factory()->create(['user_id' => $first->id, 'text' => 'first conversation']);
    TelegramMessage::factory()->create(['user_id' => $second->id, 'text' => 'second conversation']);

    Livewire::actingAs($admin)
        ->test(Inbox::class)
        ->call('selectConversation', $second->id)
        ->assertSet('selectedUserId', $second->id)
        ->assertSee('second conversation');
});

it('queues a support reply and waits for confirmed Telegram delivery before showing it as sent', function () {
    Queue::fake();

    $admin = Admin::factory()->owner()->create();
    $user = User::factory()->create(['support_status' => 'open']);

    Livewire::actingAs($admin)
        ->test(Inbox::class)
        ->call('selectConversation', $user->id)
        ->set('reply', 'Your request has been received')
        ->call('submitReply')
        ->assertHasNoErrors()
        ->assertSet('reply', '');

    $this->assertDatabaseHas('direct_message_deliveries', [
        'user_id' => $user->id,
        'admin_id' => $admin->id,
        'text' => 'Your request has been received',
        'status' => 'pending',
    ]);

    expect(TelegramMessage::query()->where('user_id', $user->id)->count())->toBe(0);
    Queue::assertPushed(SendDirectMessageJob::class);
});

it('renders the profile and shared chat layout, including actual messages', function () {
    $admin = Admin::factory()->owner()->create();
    $user = User::factory()->create([
        'first_name' => 'Aster',
        'support_status' => 'open',
        'support_note' => 'Call after confirmation.',
    ]);

    TelegramMessage::factory()->create([
        'user_id' => $user->id,
        'text' => 'Hello from the user',
    ]);

    $this->actingAs($admin)
        ->get('/admin/users/'.$user->id)
        ->assertOk()
        ->assertSee('Hello from the user')
        ->assertSee('Call after confirmation.')
        ->assertSee('Type a message');
});

it('does not expose the reply composer to a viewer', function () {
    $viewer = Admin::factory()->viewer()->create();
    User::factory()->create(['support_status' => 'open']);

    $this->actingAs($viewer)
        ->get('/admin/inbox')
        ->assertOk()
        ->assertSee('Read-only access')
        ->assertDontSee('wire:submit="submitReply"', false);
});
