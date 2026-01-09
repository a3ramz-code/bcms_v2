# Quick Start Guide

## For New Laravel Application

If you're starting from scratch, follow these steps:

### 1. Install Laravel and Dependencies

```bash
composer create-project laravel/laravel bcms
cd bcms
composer require laravel/sanctum
composer require spatie/laravel-permission
```

### 2. Copy Files from This Repository

Copy the entire `apps/api/` structure to your Laravel root directory, merging with existing files.

### 3. Run Migrations

```bash
php artisan migrate
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

### 4. Create Required Database Tables

You'll need to create migrations for the base tables (if they don't exist):

```bash
php artisan make:migration create_routers_table
php artisan make:migration create_subscriptions_table
php artisan make:migration create_provisionings_table
php artisan make:migration create_pppoe_sessions_table
php artisan make:migration create_internet_services_table
php artisan make:migration create_customers_table
```

### 5. Configure Environment

```bash
# .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bcms
DB_USERNAME=root
DB_PASSWORD=

# API Settings
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

### 6. Create Permission

```bash
php artisan tinker
```

```php
use Spatie\Permission\Models\Permission;
Permission::create(['name' => 'subscriptions.read']);
```

### 7. Set Up Scheduler

Add to crontab:
```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### 8. Test the Commands

```bash
# Create a test router
php artisan tinker
```

```php
$router = new App\Models\Router([
    'name' => 'Test Router',
    'host' => '192.168.1.1',
    'username' => 'admin',
    'password' => 'password',
    'sync_interval' => 60
]);
$router->save();
```

```bash
# Test sync commands
php artisan bcms:sync-ppp-profiles --router_id=1
php artisan bcms:sync-simple-queues --router_id=1
```

### 9. Test the API Endpoint

```bash
# Get authentication token (you'll need to set up auth routes)
# Then test the endpoint
curl -X GET http://localhost:8000/api/v1/subscriptions/1/network-status \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

## For Existing Laravel Application

### 1. Review Integration Checklist

Read `INTEGRATION_CHECKLIST.md` for detailed steps.

### 2. Run New Migrations

```bash
php artisan migrate
```

### 3. Copy Required Files

- Models: `RouterPppProfile.php`, `RouterSimpleQueue.php`
- Services: All files in `app/Services/Mikrotik/`
- Commands: Both sync command files
- Controller: `SubscriptionNetworkStatusController.php`

### 4. Update Existing Files

- Add route to `routes/api.php`
- Update `app/Console/Kernel.php` with schedule entries
- Add relationships to existing models

### 5. Configure Permissions

```bash
php artisan tinker
```

```php
use Spatie\Permission\Models\Permission;
Permission::create(['name' => 'subscriptions.read']);

// Assign to role
$role = Spatie\Permission\Models\Role::findByName('admin');
$role->givePermissionTo('subscriptions.read');
```

## Testing the Implementation

### 1. Create Test Data

```php
// Create a subscription with provisioning
$subscription = new App\Models\Subscription([
    'customer_id' => 1,
    'internet_service_id' => 1,
    'status' => 'active'
]);
$subscription->save();

$provisioning = new App\Models\Provisioning([
    'subscription_id' => $subscription->id,
    'router_id' => 1,
    'device_conn' => 'pppoe',
    'provisioning_status' => 'active',
    'pppoe_name' => 'testuser',
    'pppoe_secret_disabled' => false,
    'pppoe_secret_last_sync_at' => now(),
    'provisioned_at' => now()
]);
$provisioning->save();
```

### 2. Test Sync Commands

```bash
php artisan bcms:sync-ppp-profiles
php artisan bcms:sync-simple-queues
```

### 3. Test API Endpoint

```bash
curl -X GET http://localhost:8000/api/v1/subscriptions/1/network-status \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" | jq .
```

### 4. Verify Scheduled Tasks

```bash
php artisan schedule:list
```

You should see:
```
0 */15 * * * php artisan bcms:sync-ppp-profiles
0 */15 * * * php artisan bcms:sync-simple-queues
```

## Troubleshooting

### Error: "No query results for model [App\Models\Subscription]"

**Solution**: Create test subscription data or use a valid subscription ID.

### Error: "Unauthenticated"

**Solution**: Ensure you're passing a valid Sanctum token in the Authorization header.

### Error: "This action is unauthorized"

**Solution**: Ensure the authenticated user has the `subscriptions.read` permission.

### Error: "Class 'App\Models\Router' not found"

**Solution**: Run `composer dump-autoload` to regenerate autoload files.

### Error: Migration fails

**Solution**: Check if you have the required base tables. Create them if missing.

## Next Steps

1. **Implement Mikrotik Integration**: Update sync services to connect to actual routers
2. **Add Tests**: Create comprehensive test suite
3. **Configure Monitoring**: Set up alerts for sync failures
4. **Optimize Performance**: Add caching and indexes
5. **Deploy to Production**: Follow your deployment process

## Support

For detailed information, refer to:
- `README.md` - Overview and features
- `IMPLEMENTATION.md` - Technical details
- `API_EXAMPLES.md` - Request/response examples
- `INTEGRATION_CHECKLIST.md` - Complete integration guide
- `PR_SUMMARY.md` - Pull request summary

## Mikrotik API Integration Example

To implement actual Mikrotik integration:

```bash
composer require evilfreelancer/routeros-api-php
```

Update `PppProfileSnapshotSyncService.php`:

```php
use RouterOS\Client;
use RouterOS\Query;

private function fetchPppProfiles(Router $router): array
{
    try {
        $client = new Client([
            'host' => $router->host,
            'user' => $router->username,
            'pass' => $router->password,
            'port' => $router->port ?? 8728,
        ]);

        $query = new Query('/ppp/profile/print');
        $response = $client->query($query)->read();

        return array_map(function ($profile) {
            return [
                'name' => $profile['name'],
                'rate_limit' => $profile['rate-limit'] ?? null,
            ];
        }, $response);
    } catch (\Exception $e) {
        Log::error("Failed to fetch PPP profiles from router {$router->id}", [
            'error' => $e->getMessage(),
        ]);
        throw $e;
    }
}
```

Do the same for `SimpleQueueSnapshotSyncService.php` with `/queue/simple/print`.

## Production Checklist

Before deploying to production:

- [ ] All migrations tested
- [ ] Mikrotik API integration implemented
- [ ] Error handling and logging configured
- [ ] Permissions properly set up
- [ ] Scheduler running via cron
- [ ] API rate limiting configured
- [ ] Database indexes added
- [ ] Monitoring and alerts configured
- [ ] Backup strategy in place
- [ ] Documentation updated for your team

## Performance Tips

1. **Add Database Indexes**:
   ```php
   Schema::table('router_ppp_profiles', function (Blueprint $table) {
       $table->index(['router_id', 'last_seen_at']);
   });
   ```

2. **Cache API Responses**:
   ```php
   Cache::remember("network-status-{$id}", 900, function () use ($id) {
       // ... endpoint logic
   });
   ```

3. **Use Queue Workers for Sync**:
   ```php
   // Dispatch sync jobs to queue
   SyncPppProfilesJob::dispatch($router);
   ```

Good luck with your implementation! 🚀
