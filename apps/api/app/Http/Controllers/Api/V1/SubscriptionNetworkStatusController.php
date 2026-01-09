<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\PppoeSession;
use App\Models\RouterPppProfile;
use App\Models\RouterSimpleQueue;
use App\Services\BillingEligibilityService;
use Illuminate\Http\JsonResponse;

class SubscriptionNetworkStatusController extends Controller
{
    public function __construct(
        private BillingEligibilityService $billingService
    ) {}

    public function show(int $id): JsonResponse
    {
        $subscription = Subscription::with(['latestProvisioning.router', 'internetService'])
            ->findOrFail($id);

        $provisioning = $subscription->latestProvisioning;
        $router = $provisioning?->router;

        // Build basic response structure
        $response = [
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
            ],
            'router' => null,
            'provisioning' => null,
            'expected_state' => $this->computeExpectedState($subscription),
            'mismatch_flags' => [
                'has_mismatch' => false,
                'reasons' => [],
            ],
        ];

        // Add router info if available
        if ($router) {
            $response['router'] = [
                'id' => $router->id,
                'name' => $router->name,
                'sync_interval' => $router->sync_interval,
            ];
        }

        // Add provisioning info if available
        if ($provisioning) {
            $response['provisioning'] = [
                'id' => $provisioning->id,
                'router_id' => $provisioning->router_id,
                'device_conn' => $provisioning->device_conn,
                'provisioning_status' => $provisioning->provisioning_status,
                'pppoe_name' => $provisioning->pppoe_name,
                'static_ip' => $provisioning->static_ip,
                'static_gateway' => $provisioning->static_gateway,
                'pppoe_secret_disabled' => $provisioning->pppoe_secret_disabled,
                'pppoe_secret_last_sync_at' => $provisioning->pppoe_secret_last_sync_at?->toIso8601String(),
                'provisioned_at' => $provisioning->provisioned_at?->toIso8601String(),
            ];

            // Add PPPoE block if applicable
            if ($provisioning->device_conn === 'pppoe' && $provisioning->pppoe_name) {
                $response['pppoe'] = $this->buildPppoeBlock($provisioning, $router);
            }

            // Add Static IP block if applicable
            if ($provisioning->device_conn === 'static-ip') {
                $response['static_ip'] = $this->buildStaticIpBlock($provisioning, $router);
            }

            // Compute mismatches
            $response['mismatch_flags'] = $this->computeMismatches(
                $subscription,
                $provisioning,
                $router,
                $response['expected_state'],
                $response['pppoe'] ?? null,
                $response['static_ip'] ?? null
            );
        }

        return response()->json($response);
    }

    private function computeExpectedState(Subscription $subscription): array
    {
        $status = $subscription->status;

        switch ($status) {
            case 'active':
                return [
                    'status' => 'active',
                    'pppoe_secret_enabled' => true,
                    'rate_policy' => 'normal',
                    'static_queue_enabled' => true,
                    'max_limit_policy' => 'normal',
                ];

            case 'soft_limit':
                return [
                    'status' => 'soft_limit',
                    'pppoe_secret_enabled' => true,
                    'rate_policy' => 'soft_limit',
                    'static_queue_enabled' => true,
                    'max_limit_policy' => 'soft_limit',
                ];

            case 'suspend':
                return [
                    'status' => 'suspend',
                    'pppoe_secret_enabled' => false,
                    'rate_policy' => null,
                    'static_queue_enabled' => true,
                    'max_limit_policy' => 'suspend',
                ];

            default:
                return [
                    'status' => $status,
                    'policy' => 'unknown',
                    'note' => 'Status not mapped to a defined policy',
                ];
        }
    }

    private function buildPppoeBlock($provisioning, $router): array
    {
        $block = [
            'secret' => [
                'enabled' => !$provisioning->pppoe_secret_disabled,
                'disabled' => $provisioning->pppoe_secret_disabled,
                'last_sync_at' => $provisioning->pppoe_secret_last_sync_at?->toIso8601String(),
            ],
            'session' => null,
            'profile_snapshot' => null,
        ];

        // Get PPPoE session
        if ($router && $provisioning->pppoe_name) {
            $session = PppoeSession::where('router_id', $router->id)
                ->where('name', $provisioning->pppoe_name)
                ->first();

            if ($session) {
                $isOnline = false;
                if ($session->last_seen_at && $router->sync_interval) {
                    $threshold = now()->subSeconds($router->sync_interval * 2);
                    $isOnline = $session->last_seen_at->isAfter($threshold);
                }

                $block['session'] = [
                    'online' => $isOnline,
                    'address' => $session->address,
                    'caller_id' => $session->caller_id,
                    'uptime' => $session->uptime,
                    'bytes_in' => $session->bytes_in,
                    'bytes_out' => $session->bytes_out,
                    'last_seen_at' => $session->last_seen_at?->toIso8601String(),
                ];
            }
        }

        // Get profile snapshot
        if ($router && $provisioning->pppoe_name) {
            // Extract profile name from pppoe_name (assuming format like "username@profile")
            // or use a default profile lookup strategy
            $profile = RouterPppProfile::where('router_id', $router->id)
                ->orderBy('last_seen_at', 'desc')
                ->first();

            if ($profile) {
                $block['profile_snapshot'] = [
                    'name' => $profile->name,
                    'rate_limit' => $profile->rate_limit,
                    'last_seen_at' => $profile->last_seen_at?->toIso8601String(),
                ];
            }
        }

        return $block;
    }

    private function buildStaticIpBlock($provisioning, $router): array
    {
        $block = [
            'ip' => $provisioning->static_ip,
            'gateway' => $provisioning->static_gateway,
            'configured' => !empty($provisioning->static_ip),
            'queue_snapshot' => null,
        ];

        // Get queue snapshot
        if ($router && $provisioning->id && $provisioning->subscription_id) {
            $queueName = "BCMS-{$provisioning->id}-{$provisioning->subscription_id}";
            $queue = RouterSimpleQueue::where('router_id', $router->id)
                ->where('name', $queueName)
                ->first();

            if ($queue) {
                $block['queue_snapshot'] = [
                    'name' => $queue->name,
                    'target' => $queue->target,
                    'max_limit' => $queue->max_limit,
                    'limit_at' => $queue->limit_at,
                    'disabled' => $queue->disabled,
                    'last_seen_at' => $queue->last_seen_at?->toIso8601String(),
                ];
            }
        }

        return $block;
    }

    private function computeMismatches(
        Subscription $subscription,
        $provisioning,
        $router,
        array $expectedState,
        ?array $pppoeBlock,
        ?array $staticIpBlock
    ): array {
        $reasons = [];

        // Only compute mismatches if we have a known expected state
        if (isset($expectedState['policy']) && $expectedState['policy'] === 'unknown') {
            return ['has_mismatch' => false, 'reasons' => []];
        }

        // PPPoE mismatches
        if ($provisioning->device_conn === 'pppoe' && $pppoeBlock) {
            // Secret state mismatch
            $expectedEnabled = $expectedState['pppoe_secret_enabled'] ?? null;
            $actualEnabled = $pppoeBlock['secret']['enabled'] ?? null;

            if ($expectedEnabled !== null && $actualEnabled !== null && $expectedEnabled !== $actualEnabled) {
                $reasons[] = [
                    'type' => 'pppoe_secret_state_mismatch',
                    'message' => "PPPoE secret should be " . ($expectedEnabled ? 'enabled' : 'disabled') . " but is " . ($actualEnabled ? 'enabled' : 'disabled'),
                    'expected' => $expectedEnabled,
                    'actual' => $actualEnabled,
                ];
            }

            // Session online while expected disabled
            if ($expectedEnabled === false && isset($pppoeBlock['session']['online']) && $pppoeBlock['session']['online']) {
                $reasons[] = [
                    'type' => 'pppoe_online_while_expected_disabled',
                    'message' => 'PPPoE session is online but secret is expected to be disabled',
                ];
            }

            // Profile rate limit mismatch
            if (isset($expectedState['rate_policy']) && $expectedState['rate_policy'] !== null) {
                $internetService = $this->billingService->internetServiceFor($subscription, $router);
                $expectedRateLimit = null;

                if ($expectedState['rate_policy'] === 'normal' && $internetService) {
                    $expectedRateLimit = $internetService->rate_limit;
                } elseif ($expectedState['rate_policy'] === 'soft_limit' && $internetService) {
                    $expectedRateLimit = $internetService->limit_at;
                }

                $actualRateLimit = $pppoeBlock['profile_snapshot']['rate_limit'] ?? null;

                if ($expectedRateLimit && $actualRateLimit && $expectedRateLimit !== $actualRateLimit) {
                    $reasons[] = [
                        'type' => 'pppoe_profile_rate_limit_mismatch',
                        'message' => "PPPoE profile rate limit mismatch",
                        'expected' => $expectedRateLimit,
                        'actual' => $actualRateLimit,
                    ];
                }
            }
        }

        // Static IP mismatches
        if ($provisioning->device_conn === 'static-ip' && $staticIpBlock) {
            // Not configured when expected
            if (in_array($expectedState['status'] ?? null, ['active', 'soft_limit', 'suspend'])) {
                if (!$staticIpBlock['configured']) {
                    $reasons[] = [
                        'type' => 'static_ip_not_configured',
                        'message' => 'Static IP is not configured but subscription requires it',
                    ];
                }
            }

            // Queue max-limit mismatch
            if (isset($expectedState['max_limit_policy']) && $staticIpBlock['queue_snapshot']) {
                $internetService = $this->billingService->internetServiceFor($subscription, $router);
                $expectedMaxLimit = null;

                switch ($expectedState['max_limit_policy']) {
                    case 'normal':
                        $expectedMaxLimit = $internetService->rate_limit ?? null;
                        break;
                    case 'soft_limit':
                        $expectedMaxLimit = $internetService->limit_at ?? null;
                        break;
                    case 'suspend':
                        $expectedMaxLimit = '1k/1k';
                        break;
                }

                $actualMaxLimit = $staticIpBlock['queue_snapshot']['max_limit'] ?? null;

                if ($expectedMaxLimit && $actualMaxLimit && $expectedMaxLimit !== $actualMaxLimit) {
                    $reasons[] = [
                        'type' => 'static_ip_queue_max_limit_mismatch',
                        'message' => 'Static IP queue max-limit mismatch',
                        'expected' => $expectedMaxLimit,
                        'actual' => $actualMaxLimit,
                    ];
                }
            }
        }

        return [
            'has_mismatch' => count($reasons) > 0,
            'reasons' => $reasons,
        ];
    }
}
