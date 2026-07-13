<?php

namespace App\Console\Commands;

use App\Services\PaymentService;
use Illuminate\Console\Command;

class ReleaseAbandonedDeposits extends Command
{
    protected $signature = 'payments:release-abandoned';

    protected $description = 'Release appointments whose required deposit was never completed, freeing the slot';

    public function handle(PaymentService $payments): int
    {
        $released = $payments->releaseAbandoned();

        $this->info("Released {$released} appointment(s) with an abandoned deposit.");

        return self::SUCCESS;
    }
}
