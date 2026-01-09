<?php

namespace App\Services\Mikrotik;

use App\Models\Router;
use App\Models\RouterPppProfile;
use Illuminate\Support\Facades\Log;

class PppProfileSnapshotSyncService
{
    public function sync(Router $router): void
    {
        try {
            $profiles = $this->fetchPppProfiles($router);
            
            foreach ($profiles as $profile) {
                RouterPppProfile::updateOrCreate(
                    [
                        'router_id' => $router->id,
                        'name' => $profile['name'],
                    ],
                    [
                        'rate_limit' => $profile['rate_limit'] ?? null,
                        'last_seen_at' => now(),
                    ]
                );
            }

            // Mark profiles not seen in this sync as stale (optional)
            // Could delete or mark old profiles that weren't in this sync
            
            Log::info("Synced PPP profiles for router {$router->id}", [
                'count' => count($profiles),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to sync PPP profiles for router {$router->id}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function fetchPppProfiles(Router $router): array
    {
        // Connect to Mikrotik router and fetch /ppp/profile/print
        // This is a stub implementation - actual Mikrotik API integration needed
        
        // Example of connecting to RouterOS API:
        // $client = new MikrotikClient($router->host, $router->username, $router->password, $router->port);
        // $response = $client->query('/ppp/profile/print');
        
        // For now, return empty array as we don't have actual Mikrotik connection
        // In production, this should use RouterOS API library
        
        return [];
    }
}
