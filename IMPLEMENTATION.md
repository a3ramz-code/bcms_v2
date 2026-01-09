# Implementation Summary

## Overview
This implementation provides a consolidated customer network status endpoint with mismatch detection based on expected billing policy, along with supporting infrastructure for syncing Mikrotik router snapshots.

## Key Components

### 1. Database Schema

#### router_ppp_profiles
Stores snapshots of PPP profiles from Mikrotik routers:
- `router_id`: Foreign key to routers table
- `name`: Profile name
- `rate_limit`: Rate limit configuration (e.g., "10M/10M")
- `last_seen_at`: Last time this profile was seen during sync

#### router_simple_queues  
Stores snapshots of simple queues from Mikrotik routers:
- `router_id`: Foreign key to routers table
- `name`: Queue name (format: BCMS-{provisioning_id}-{subscription_id})
- `target`: Target IP address
- `max_limit`: Maximum bandwidth limit
- `limit_at`: Guaranteed bandwidth limit
- `disabled`: Whether queue is disabled
- `last_seen_at`: Last time this queue was seen during sync

### 2. Models

All models follow Laravel conventions with proper relationships:
- `RouterPppProfile`: Represents PPP profile snapshots
- `RouterSimpleQueue`: Represents simple queue snapshots
- `Subscription`: Customer subscription with status tracking
- `Provisioning`: Network provisioning records
- `Router`: Mikrotik router configuration
- `PppoeSession`: Active PPPoE sessions
- `InternetService`: Service plans with rate limits

### 3. Services

#### BillingEligibilityService
Resolves the appropriate internet service for a subscription, returning:
- Service ID and name
- `rate_limit`: Used for active subscriptions
- `limit_at`: Used for soft-limited subscriptions

#### PppProfileSnapshotSyncService
Syncs PPP profiles from Mikrotik routers:
- Connects to router via RouterOS API
- Fetches `/ppp/profile/print`
- Updates local database with profile snapshots
- Records `rate_limit` for each profile

#### SimpleQueueSnapshotSyncService
Syncs simple queues from Mikrotik routers:
- Connects to router via RouterOS API
- Fetches `/queue/simple/print`
- Updates local database with queue snapshots
- Records target, max_limit, limit_at, and disabled status

### 4. Console Commands

#### bcms:sync-ppp-profiles
```bash
php artisan bcms:sync-ppp-profiles [--router_id=123]
```
- Syncs PPP profiles from specified router or all routers
- Scheduled to run every 15 minutes
- Uses `withoutOverlapping()` to prevent concurrent runs

#### bcms:sync-simple-queues
```bash
php artisan bcms:sync-simple-queues [--router_id=123]
```
- Syncs simple queues from specified router or all routers
- Scheduled to run every 15 minutes
- Uses `withoutOverlapping()` to prevent concurrent runs

### 5. API Endpoint

#### GET /api/v1/subscriptions/{id}/network-status

**Authentication**: Requires `auth:sanctum` middleware
**Authorization**: Requires `subscriptions.read` permission

**Response Structure**:
```json
{
  "subscription": {
    "id": 1,
    "status": "active"
  },
  "router": {
    "id": 1,
    "name": "Router-1",
    "sync_interval": 60
  },
  "provisioning": {
    "id": 1,
    "router_id": 1,
    "device_conn": "pppoe",
    "provisioning_status": "active",
    "pppoe_name": "user123",
    "static_ip": null,
    "static_gateway": null,
    "pppoe_secret_disabled": false,
    "pppoe_secret_last_sync_at": "2026-01-09T08:00:00+00:00",
    "provisioned_at": "2026-01-01T00:00:00+00:00"
  },
  "expected_state": {
    "status": "active",
    "pppoe_secret_enabled": true,
    "rate_policy": "normal",
    "static_queue_enabled": true,
    "max_limit_policy": "normal"
  },
  "mismatch_flags": {
    "has_mismatch": false,
    "reasons": []
  },
  "pppoe": {
    "secret": {
      "enabled": true,
      "disabled": false,
      "last_sync_at": "2026-01-09T08:00:00+00:00"
    },
    "session": {
      "online": true,
      "address": "10.0.0.123",
      "caller_id": "00:11:22:33:44:55",
      "uptime": "1d2h30m",
      "bytes_in": 1000000,
      "bytes_out": 500000,
      "last_seen_at": "2026-01-09T08:15:00+00:00"
    },
    "profile_snapshot": {
      "name": "default",
      "rate_limit": "10M/10M",
      "last_seen_at": "2026-01-09T08:00:00+00:00"
    }
  }
}
```

### 6. Expected State Logic

The endpoint computes expected state based on subscription status:

#### Active Status
- PPPoE: Secret should be enabled, rate policy uses `internet_service.rate_limit`
- Static-IP: Queue should be enabled, max_limit policy uses `internet_service.rate_limit`

#### Soft-Limit Status
- PPPoE: Secret should be enabled, rate policy uses `internet_service.limit_at`
- Static-IP: Queue should be enabled, max_limit policy uses `internet_service.limit_at`

#### Suspend Status
- PPPoE: Secret should be disabled
- Static-IP: Queue should be enabled with max_limit of "1k/1k"

### 7. Mismatch Detection

The endpoint detects the following types of mismatches:

#### PPPoE Mismatches
1. **Secret State Mismatch**: Expected enabled/disabled state doesn't match actual
2. **Online While Disabled**: Session is online but secret should be disabled
3. **Profile Rate Limit Mismatch**: Profile rate_limit doesn't match expected value

#### Static-IP Mismatches
1. **Not Configured**: Static IP not configured when subscription requires it
2. **Queue Max-Limit Mismatch**: Queue max_limit doesn't match expected value

### 8. Session Online Detection

A PPPoE session is considered online if:
- `last_seen_at` is within `router.sync_interval * 2` seconds from current time
- This accounts for sync delays and ensures accurate online status

### 9. Queue Naming Convention

Simple queues are named using the pattern:
```
BCMS-{provisioning_id}-{subscription_id}
```

This allows the endpoint to look up the correct queue for each subscription.

## Implementation Notes

### Stub Implementation
The Mikrotik sync services contain stub implementations. In production, they should:
1. Use a RouterOS API library (e.g., `routeros-api-php`)
2. Handle connection errors gracefully
3. Implement proper logging
4. Support authentication methods (username/password, API keys)

### No Real-Time Calls
The network status endpoint deliberately avoids making real-time calls to Mikrotik routers. Instead, it relies on:
- Snapshot data from scheduled syncs (every 15 minutes)
- Existing `pppoe_sessions` table data
- Provisioning table state

This design ensures:
- Fast API response times
- Reduced load on Mikrotik routers
- Resilience to router connectivity issues

### Migration Dates
All migrations are dated `2026_01_09_*` as specified in requirements.

### Code Style
The implementation follows Laravel conventions:
- PSR-4 autoloading
- Type hints and return types
- Property promotion in constructors (PHP 8+)
- Eloquent relationships and query builder
- Resource controllers
- Command signatures with options

## Testing Recommendations

1. **Unit Tests**: Test expected state logic and mismatch detection independently
2. **Integration Tests**: Test the full endpoint with mock data
3. **Command Tests**: Verify Artisan commands work with various options
4. **Migration Tests**: Ensure migrations run successfully

## Future Enhancements

1. Implement actual Mikrotik API integration
2. Add caching layer for network status responses
3. Implement webhook notifications for detected mismatches
4. Add historical tracking of mismatch events
5. Support additional connection types beyond PPPoE and Static-IP
6. Add rate limiting to API endpoint
7. Implement detailed audit logging
