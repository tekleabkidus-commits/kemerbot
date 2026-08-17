<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserJoined;
use App\Services\Automations\AutomationEnroller;

class EnrollUserInAutomations
{
    public function __construct(private readonly AutomationEnroller $enroller) {}

    public function handle(UserJoined $event): void
    {
        $this->enroller->enrollUserJoined($event->user);
    }
}
