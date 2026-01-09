<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Router;

class BillingEligibilityService
{
    public function internetServiceFor(Subscription $subscription, ?Router $router = null): ?object
    {
        // Return internet service with rate_limit and limit_at
        $internetService = $subscription->internetService;
        
        if (!$internetService) {
            return null;
        }

        return (object) [
            'id' => $internetService->id,
            'name' => $internetService->name,
            'rate_limit' => $internetService->rate_limit,
            'limit_at' => $internetService->limit_at,
        ];
    }
}
