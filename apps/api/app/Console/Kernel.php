<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sync PPP profiles every 15 minutes
        $schedule->command('bcms:sync-ppp-profiles')
            ->everyFifteenMinutes()
            ->withoutOverlapping();

        // Sync simple queues every 15 minutes
        $schedule->command('bcms:sync-simple-queues')
            ->everyFifteenMinutes()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
