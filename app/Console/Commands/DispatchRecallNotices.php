<?php

namespace App\Console\Commands;

use App\Services\RetentionService;
use Illuminate\Console\Command;

class DispatchRecallNotices extends Command
{
    protected $signature = 'recall:dispatch';

    protected $description = 'Send any due recall / care-gap outreach notices';

    public function handle(RetentionService $retention): int
    {
        $sent = $retention->sendDueNotices();

        $this->info("Sent {$sent} recall/care-gap notice(s).");

        return self::SUCCESS;
    }
}
