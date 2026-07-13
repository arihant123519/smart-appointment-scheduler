<?php

namespace App\Console\Commands;

use App\Services\RetentionService;
use Illuminate\Console\Command;

class DispatchReviewRequests extends Command
{
    protected $signature = 'reviews:request-dispatch';

    protected $description = 'Send a review request for every completed visit that finished at least 2 hours ago';

    public function handle(RetentionService $retention): int
    {
        $sent = $retention->sendReviewRequests();

        $this->info("Sent {$sent} review request(s).");

        return self::SUCCESS;
    }
}
