<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Console\Command;

class DispatchScheduledAnnouncements extends Command
{
    protected $signature = 'announcements:dispatch';

    protected $description = 'Send any scheduled broadcast announcements that are now due';

    public function handle(AnnouncementService $service): int
    {
        $due = Announcement::due()->get();

        foreach ($due as $announcement) {
            $service->dispatch($announcement);
        }

        $this->info("Dispatched {$due->count()} scheduled announcement(s).");

        return self::SUCCESS;
    }
}
