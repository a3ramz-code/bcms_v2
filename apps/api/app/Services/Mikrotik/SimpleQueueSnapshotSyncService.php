<?php

namespace App\Services\Mikrotik;

use App\Models\Router;
use App\Models\RouterSimpleQueue;
use Illuminate\Support\Facades\Log;

class SimpleQueueSnapshotSyncService
{
    public function sync(Router $router): void
    {
        try {
            $queues = $this->fetchSimpleQueues($router);
            
            foreach ($queues as $queue) {
                RouterSimpleQueue::updateOrCreate(
                    [
                        'router_id' => $router->id,
                        'name' => $queue['name'],
                    ],
                    [
                        'target' => $queue['target'] ?? null,
                        'max_limit' => $queue['max_limit'] ?? null,
                        'limit_at' => $queue['limit_at'] ?? null,
                        'disabled' => $queue['disabled'] ?? false,
                        'last_seen_at' => now(),
                    ]
                );
            }

            Log::info("Synced simple queues for router {$router->id}", [
                'count' => count($queues),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to sync simple queues for router {$router->id}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function fetchSimpleQueues(Router $router): array
    {
        // Connect to Mikrotik router and fetch /queue/simple/print
        // This is a stub implementation - actual Mikrotik API integration needed
        
        // Example of connecting to RouterOS API:
        // $client = new MikrotikClient($router->host, $router->username, $router->password, $router->port);
        // $response = $client->query('/queue/simple/print');
        
        // For now, return empty array as we don't have actual Mikrotik connection
        // In production, this should use RouterOS API library
        
        return [];
    }
}
