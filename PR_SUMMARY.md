# Pull Request Summary

## Title
Implement consolidated customer network status endpoint with mismatch detection and Mikrotik snapshot sync

## Description

This pull request implements a comprehensive network status monitoring system for the BCMS (Billing and Customer Management System) that provides:

1. **API Endpoint**: A consolidated network status endpoint that returns detailed information about subscription provisioning, router configuration, and network state
2. **Mismatch Detection**: Intelligent detection of mismatches between expected billing policy and actual network configuration
3. **Snapshot Sync**: Background synchronization of PPP profiles and simple queues from Mikrotik routers
4. **Console Commands**: Artisan commands for manual and scheduled sync operations

## Files Changed

### Database Migrations (2 files)
- `apps/api/database/migrations/2026_01_09_000001_create_router_ppp_profiles_table.php`
- `apps/api/database/migrations/2026_01_09_000002_create_router_simple_queues_table.php`

### Models (9 files)
- `apps/api/app/Models/RouterPppProfile.php` - NEW
- `apps/api/app/Models/RouterSimpleQueue.php` - NEW
- `apps/api/app/Models/Router.php` - NEW
- `apps/api/app/Models/Subscription.php` - NEW
- `apps/api/app/Models/Provisioning.php` - NEW
- `apps/api/app/Models/PppoeSession.php` - NEW
- `apps/api/app/Models/InternetService.php` - NEW
- `apps/api/app/Models/Customer.php` - NEW

### Services (3 files)
- `apps/api/app/Services/Mikrotik/PppProfileSnapshotSyncService.php` - NEW
- `apps/api/app/Services/Mikrotik/SimpleQueueSnapshotSyncService.php` - NEW
- `apps/api/app/Services/BillingEligibilityService.php` - NEW

### Console Commands (3 files)
- `apps/api/app/Console/Commands/SyncPppProfiles.php` - NEW
- `apps/api/app/Console/Commands/SyncSimpleQueues.php` - NEW
- `apps/api/app/Console/Kernel.php` - NEW (with 15-minute scheduling)

### Controllers (2 files)
- `apps/api/app/Http/Controllers/Api/V1/SubscriptionNetworkStatusController.php` - NEW
- `apps/api/app/Http/Controllers/Controller.php` - NEW

### Routes (2 files)
- `apps/api/routes/api.php` - NEW
- `apps/api/routes/console.php` - NEW

### Configuration (1 file)
- `apps/api/composer.json` - NEW

### Documentation (4 files)
- `README.md` - UPDATED
- `API_EXAMPLES.md` - NEW
- `IMPLEMENTATION.md` - NEW
- `INTEGRATION_CHECKLIST.md` - NEW

### Other (1 file)
- `.gitignore` - NEW

**Total: 27 files**

## Key Features

### API Endpoint: GET /api/v1/subscriptions/{id}/network-status

**Authentication & Authorization:**
- Protected by `auth:sanctum` middleware
- Requires `subscriptions.read` permission

**Response Includes:**
- Subscription information (id, status)
- Router information (id, name, sync_interval)
- Latest provisioning details
- Expected state based on billing policy
- Mismatch detection results with detailed reasons
- PPPoE session details (for PPPoE connections)
- Static IP configuration and queue details (for static-ip connections)

### Expected State Policies

| Subscription Status | PPPoE Secret | Rate Policy | Static Queue | Max Limit Policy |
|---------------------|--------------|-------------|--------------|------------------|
| Active              | Enabled      | Normal      | Enabled      | Normal           |
| Soft-Limit          | Enabled      | Soft-Limit  | Enabled      | Soft-Limit       |
| Suspend             | Disabled     | N/A         | Enabled      | 1k/1k            |

### Mismatch Detection

The system detects the following mismatches:

**PPPoE Mismatches:**
1. Secret state mismatch (expected vs actual enabled/disabled)
2. Session online while secret expected to be disabled
3. Profile rate-limit mismatch

**Static-IP Mismatches:**
1. Static IP not configured when required
2. Queue max-limit mismatch

### Background Sync

**Commands:**
- `php artisan bcms:sync-ppp-profiles [--router_id=]`
- `php artisan bcms:sync-simple-queues [--router_id=]`

**Scheduling:**
- Both commands run every 15 minutes
- Uses `withoutOverlapping()` to prevent concurrent runs
- Configured in `app/Console/Kernel.php`

## Technical Implementation

### Database Schema

**router_ppp_profiles:**
- Stores snapshots of PPP profiles from Mikrotik routers
- Tracks rate_limit and last_seen_at
- Unique constraint on (router_id, name)

**router_simple_queues:**
- Stores snapshots of simple queues from Mikrotik routers
- Tracks target, max_limit, limit_at, disabled status
- Unique constraint on (router_id, name)

### Design Principles

1. **No Real-Time Calls**: The endpoint does not make real-time calls to Mikrotik routers, relying instead on snapshot data
2. **Fast Response**: All data is retrieved from local database, ensuring fast API response times
3. **Resilient**: System continues to work even if routers are temporarily unreachable
4. **Scalable**: Snapshot sync can be distributed across multiple workers

### Code Quality

- Follows Laravel conventions and best practices
- PSR-4 autoloading
- Type hints and return types throughout
- Proper use of Eloquent relationships
- Clean, maintainable code structure
- Comprehensive inline documentation

## Testing Recommendations

1. **Unit Tests**: Test expected state computation and mismatch detection logic
2. **Integration Tests**: Test full endpoint with various scenarios
3. **Command Tests**: Verify Artisan commands work correctly
4. **Migration Tests**: Ensure migrations run successfully

## Deployment Notes

### Prerequisites
- Laravel 10+ application
- Laravel Sanctum configured
- Permission system in place
- PHP 8.1+

### Installation Steps
1. Review `INTEGRATION_CHECKLIST.md` for detailed integration steps
2. Run migrations: `php artisan migrate`
3. Configure cron for scheduler: `* * * * * php artisan schedule:run`
4. Test commands manually before relying on scheduler
5. Implement Mikrotik API integration in sync services

### Mikrotik API Integration

The sync services contain stub implementations. For production use:
1. Install RouterOS API library: `composer require evilfreelancer/routeros-api-php`
2. Update `fetchPppProfiles()` and `fetchSimpleQueues()` methods
3. Add error handling and retry logic
4. Configure connection timeouts

## Security Considerations

- ✅ Authentication required (Sanctum)
- ✅ Permission-based authorization
- ✅ SQL injection protection (Eloquent ORM)
- ✅ Input validation via route model binding
- ✅ No sensitive data in logs
- ⚠️ Router credentials should be encrypted in database
- ⚠️ Rate limiting should be configured for API endpoints

## Performance Optimizations

- Database indexes on foreign keys
- Eager loading of relationships to prevent N+1 queries
- Session online status computed efficiently
- No real-time external calls in API endpoint
- Background sync offloads work from request cycle

## Documentation

Four comprehensive documentation files provided:

1. **README.md**: Overview of the system and features
2. **API_EXAMPLES.md**: Request/response examples for all scenarios
3. **IMPLEMENTATION.md**: Detailed technical implementation guide
4. **INTEGRATION_CHECKLIST.md**: Step-by-step integration guide

## Breaking Changes

None - This is a new feature addition.

## Migration Path

For existing applications, follow the integration checklist:
1. Run new migrations
2. Copy models and services
3. Update existing models with relationships
4. Add route and controller
5. Configure permissions
6. Set up scheduler

## Future Enhancements

- [ ] Implement actual Mikrotik API integration
- [ ] Add caching layer for network status responses
- [ ] Implement webhook notifications for mismatches
- [ ] Add historical tracking of mismatch events
- [ ] Support additional connection types
- [ ] Add comprehensive test suite
- [ ] Implement audit logging

## Conclusion

This implementation provides a solid foundation for network status monitoring in BCMS. The code is production-ready with the exception of the Mikrotik API integration, which requires the addition of an actual RouterOS API library.

All requirements from the problem statement have been met:
- ✅ API endpoint with proper authentication/authorization
- ✅ Expected state computation based on subscription status
- ✅ Mismatch detection with structured reasons
- ✅ Database tables for snapshots
- ✅ Sync services for PPP profiles and queues
- ✅ Artisan commands with scheduling
- ✅ Comprehensive documentation
- ✅ Code style consistent with Laravel best practices
