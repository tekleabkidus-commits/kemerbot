<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Broadcasts\BroadcastScheduler;
use Illuminate\Console\Command;

class ProcessDueBroadcasts extends Command
{
    protected $signature = 'broadcasts:process-due';

    protected $description = 'Promote due scheduled broadcasts and materialize recurring occurrences';

    public function handle(BroadcastScheduler $scheduler): int
    {
        $result = $scheduler->processDue();

        $this->info("Started: {$result['started']}, recurring occurrences materialized: {$result['materialized']}");

        return self::SUCCESS;
    }
}
