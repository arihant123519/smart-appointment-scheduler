<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Flows\FlowEngine;
use Illuminate\Console\Command;

class SweepStaleConversations extends Command
{
    protected $signature = 'flows:sweep-timeouts';

    protected $description = 'Mark WhatsApp conversation flows that have gone quiet as timed out and alert the front desk';

    public function handle(FlowEngine $engine): int
    {
        $hours = max(1, (int) Setting::get('whatsapp.flow_timeout_hours', 24));
        $count = $engine->sweepTimeouts($hours);

        $this->info("Timed out {$count} stale WhatsApp conversation(s).");

        return self::SUCCESS;
    }
}
