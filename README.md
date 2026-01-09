# BCMS v2 - Billing and Customer Management System

## Network Status Endpoint Implementation

This repository contains the implementation of a consolidated customer network status endpoint with mismatch detection based on expected billing policy, plus supporting snapshot sync for PPP profiles and simple queues.

## Features

### API Endpoint
- **GET** `/api/v1/subscriptions/{id}/network-status`
- Returns comprehensive network status including:
  - Subscription and router information
  - Latest provisioning details
  - Expected state based on billing policy
  - Mismatch detection flags
  - PPPoE session details (for PPPoE connections)
  - Static IP configuration (for static-ip connections)

### Database Tables
- `router_ppp_profiles` - Stores snapshots of PPP profiles from Mikrotik routers
- `router_simple_queues` - Stores snapshots of simple queues from Mikrotik routers

### Console Commands
- `php artisan bcms:sync-ppp-profiles [--router_id=]` - Sync PPP profiles from routers
- `php artisan bcms:sync-simple-queues [--router_id=]` - Sync simple queues from routers

Both commands are scheduled to run every 15 minutes.

### Authorization
- Endpoint is protected by `auth:sanctum` middleware
- Requires `subscriptions.read` permission

## Directory Structure

```
apps/api/
├── app/
│   ├── Console/
│   │   ├── Commands/
│   │   │   ├── SyncPppProfiles.php
│   │   │   └── SyncSimpleQueues.php
│   │   └── Kernel.php
│   ├── Http/
│   │   └── Controllers/
│   │       ├── Api/V1/
│   │       │   └── SubscriptionNetworkStatusController.php
│   │       └── Controller.php
│   ├── Models/
│   │   ├── Customer.php
│   │   ├── InternetService.php
│   │   ├── PppoeSession.php
│   │   ├── Provisioning.php
│   │   ├── Router.php
│   │   ├── RouterPppProfile.php
│   │   ├── RouterSimpleQueue.php
│   │   └── Subscription.php
│   └── Services/
│       ├── BillingEligibilityService.php
│       └── Mikrotik/
│           ├── PppProfileSnapshotSyncService.php
│           └── SimpleQueueSnapshotSyncService.php
├── database/
│   └── migrations/
│       ├── 2026_01_09_000001_create_router_ppp_profiles_table.php
│       └── 2026_01_09_000002_create_router_simple_queues_table.php
└── routes/
    ├── api.php
    └── console.php
```

## Expected State Policies

### Active
- PPPoE: secret enabled, rate_policy normal (uses internet_service.rate_limit)
- Static-IP: queue enabled, max_limit_policy normal (uses internet_service.rate_limit)

### Soft-Limit
- PPPoE: secret enabled, rate_policy soft_limit (uses internet_service.limit_at)
- Static-IP: queue enabled, max_limit_policy soft_limit (uses internet_service.limit_at)

### Suspend
- PPPoE: secret disabled
- Static-IP: queue enabled, max_limit_policy suspend (1k/1k)

## Mismatch Detection

The endpoint detects the following mismatches:
- PPPoE secret state mismatch (expected vs actual)
- PPPoE online while expected disabled
- PPPoE profile rate-limit mismatch
- Static-IP not configured when expected
- Static-IP queue max-limit mismatch

## Installation

1. Install dependencies:
   ```bash
   cd apps/api
   composer install
   ```

2. Run migrations:
   ```bash
   php artisan migrate
   ```

3. Set up scheduled tasks (add to crontab):
   ```
   * * * * * cd /path-to-project/apps/api && php artisan schedule:run >> /dev/null 2>&1
   ```

## Notes

- The Mikrotik sync services contain stub implementations and require actual RouterOS API integration
- Queue naming follows the pattern: `BCMS-{provisioning_id}-{subscription_id}`
- Session online status is computed using `router.sync_interval * 2` threshold
- The endpoint does not make real-time Mikrotik calls; it relies on snapshot data
