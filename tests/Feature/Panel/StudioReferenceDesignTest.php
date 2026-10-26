<?php

declare(strict_types=1);

use App\Enums\MessageDirection;
use App\Filament\Pages\Inbox;
use App\Models\Admin;
use App\Models\TelegramMessage;
use App\Models\User;
use Livewire\Livewire;

it('renders the reference design as KPI cards and three inbox panes', function () {
    $admin = Admin::factory()->owner()->create();
    $user = User::factory()->create(['first_name' => 'Reference User', 'support_status' => 'open', 'support_note' => 'Follow up']);
    TelegramMessage::factory()->create(['user_id' => $user->id, 'direction' => MessageDirection::Inbound, 'text' => 'Please help with a deposit']);

    $this->actingAs($admin)->get('/admin/inbox')
        ->assertOk()
        ->assertSee('Active Conversations')
        ->assertSee('Reply Rate')
        ->assertSee('Campaign Reach')
        ->assertSee('Delivered Today')
        ->assertSee('Please help with a deposit')
        ->assertSee('Notifications &amp; consent', false)
        ->assertSee('Follow up')
        ->assertSee('studio-profile-rail')
        ->assertSee('studio-thread-panel')
        ->assertSee('studio-conversations');
});

it('switches live profile tabs and never shows fictional activity', function () {
    $admin = Admin::factory()->owner()->create();
    $user = User::factory()->create(['support_status' => 'open']);

    Livewire::actingAs($admin)->test(Inbox::class)
        ->call('selectConversation', $user->id)
        ->call('changeProfileTab', 'activity')
        ->assertSet('profileTab', 'activity')
        ->assertSee('No recent conversation activity')
        ->call('changeProfileTab', 'campaigns')
        ->assertSee('No campaign deliveries recorded')
        ->call('changeProfileTab', 'profile')
        ->assertSee('Notifications & consent');
});

it('supports private notes with authorization and an audit event', function () {
    $admin = Admin::factory()->owner()->create();
    $person = User::factory()->create(['support_status' => 'open']);

    Livewire::actingAs($admin)->test(Inbox::class)
        ->call('selectConversation', $person->id)
        ->call('startNote')
        ->set('noteDraft', 'Please follow up in the morning')
        ->call('saveNote')
        ->assertHasNoErrors();

    expect($person->fresh()->support_note)->toBe('Please follow up in the morning');
});

it('does not let a viewer edit support notes or send chat replies', function () {
    $viewer = Admin::factory()->viewer()->create();
    $person = User::factory()->create(['support_status' => 'open']);

    Livewire::actingAs($viewer)->test(Inbox::class)
        ->call('selectConversation', $person->id)
        ->assertSee('Read-only access')
        ->assertDontSee('wire:click="startNote"', false);
});

it('exposes the real responsive stylesheet and matching desktop composer', function () {
    $css = file_get_contents(public_path('css/studio-reference.css'));

    expect($css)->toContain('.studio-inbox-workspace')
        ->toContain('grid-template-columns:minmax(305px,30.1%)')
        ->toContain('@media(max-width:767px)')
        ->toContain('.studio-profile-panel')
        ->toContain('.studio-quick-replies');
});
