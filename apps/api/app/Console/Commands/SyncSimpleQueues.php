<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\Mikrotik\SimpleQueueSnapshotSyncService;
use Illuminate\Console\Command;

class SyncSimpleQueues extends Command
{
    protected $signature = 'bcms:sync-simple-queues {--router_id=}';
    protected $description = 'Sync simple queues from Mikrotik routers';

    public function handle(SimpleQueueSnapshotSyncService $syncService): int
    {
        $routerId = $this->option('router_id');

        if ($routerId) {
            $router = Router::find($routerId);
            if (!$router) {
                $this->error("Router {$routerId} not found");
                return self::FAILURE;
            }

            $this->info("Syncing simple queues for router {$router->name} (ID: {$router->id})");
            $syncService->sync($router);
            $this->info("Sync completed for router {$router->id}");
        } else {
            $routers = Router::all();
            $this->info("Syncing simple queues for " . $routers->count() . " routers");

            foreach ($routers as $router) {
                $this->info("Syncing router {$router->name} (ID: {$router->id})");
                try {
                    $syncService->sync($router);
                    $this->info("✓ Router {$router->id} synced successfully");
                } catch (\Exception $e) {
                    $this->error("✗ Failed to sync router {$router->id}: {$e->getMessage()}");
                }
            }

            $this->info("Sync completed for all routers");
        }

        return self::SUCCESS;
    }
}
