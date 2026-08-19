<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Automations\AutomationEnroller;
use App\Services\Automations\AutomationRunner;
use Illuminate\Console\Command;

class RunAutomations extends Command
{
    protected $signature = 'automations:run';

    protected $description = 'Enroll inactive-trigger users and dispatch due automation steps';

    public function handle(AutomationEnroller $enroller, AutomationRunner $runner): int
    {
        $enrolled = $enroller->enrollInactive();
        $dispatched = $runner->runDue();
        $recovered = $runner->recoverStalled();

        $this->info("Inactive enrollments: {$enrolled}, steps dispatched: {$dispatched}, deliveries recovered: {$recovered}");

        return self::SUCCESS;
    }
}
