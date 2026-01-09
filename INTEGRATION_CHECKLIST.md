# Integration Checklist

This checklist helps integrate the network status endpoint into an existing BCMS Laravel application.

## Prerequisites

- [ ] Laravel 10+ installed
- [ ] Laravel Sanctum package installed and configured
- [ ] Permission system in place (e.g., Spatie Laravel Permission)
- [ ] Mikrotik RouterOS API library (optional for actual sync)

## Database Integration

### 1. Run Migrations

```bash
cd apps/api
php artisan migrate
```

This creates:
- `router_ppp_profiles` table
- `router_simple_queues` table

### 2. Verify Existing Tables

Ensure these tables exist in your database:
- [ ] `subscriptions` with columns: id, customer_id, internet_service_id, status
- [ ] `routers` with columns: id, name, host, port, username, password, sync_interval
- [ ] `provisionings` with columns: id, subscription_id, router_id, device_conn, provisioning_status, pppoe_name, static_ip, static_gateway, pppoe_secret_disabled, pppoe_secret_last_sync_at, provisioned_at
- [ ] `pppoe_sessions` with columns: id, router_id, name, address, caller_id, uptime, bytes_in, bytes_out, last_seen_at
- [ ] `internet_services` with columns: id, name, rate_limit, limit_at, price

### 3. Add Missing Columns (if needed)

If your existing tables are missing required columns, create migrations to add them:

```php
// Example: Add sync_interval to routers table
Schema::table('routers', function (Blueprint $table) {
    $table->integer('sync_interval')->default(60)->after('password');
});
```

## Model Integration

### 1. Copy Models

Copy these model files to your application:
- [ ] `app/Models/RouterPppProfile.php`
- [ ] `app/Models/RouterSimpleQueue.php`

### 2. Update Existing Models

Add relationships to existing models:

**Router.php**
```php
public function pppProfiles()
{
    return $this->hasMany(RouterPppProfile::class);
}

public function simpleQueues()
{
    return $this->hasMany(RouterSimpleQueue::class);
}
```

**Subscription.php**
```php
public function latestProvisioning()
{
    return $this->hasOne(Provisioning::class)->latestOfMany();
}
```

## Service Integration

### 1. Copy Services

- [ ] `app/Services/Mikrotik/PppProfileSnapshotSyncService.php`
- [ ] `app/Services/Mikrotik/SimpleQueueSnapshotSyncService.php`

### 2. Integrate with Existing BillingEligibilityService

If you already have a `BillingEligibilityService`, ensure it has the `internetServiceFor()` method:

```php
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
```

If not, copy the provided service.

### 3. Implement Mikrotik API Integration

Update the sync services to connect to actual Mikrotik routers:

```php
// Install RouterOS API library
composer require evilfreelancer/routeros-api-php

// Update fetchPppProfiles() and fetchSimpleQueues() methods
use RouterOS\Client;
use RouterOS\Query;

private function fetchPppProfiles(Router $router): array
{
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
}
```

## Command Integration

### 1. Register Commands

Commands are auto-registered via `Kernel.php`. Ensure your `app/Console/Kernel.php` loads commands:

```php
protected function commands(): void
{
    $this->load(__DIR__.'/Commands');
    require base_path('routes/console.php');
}
```

### 2. Update Schedule

Merge the schedule configuration into your existing `Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // ... existing schedules ...

    // Sync PPP profiles every 15 minutes
    $schedule->command('bcms:sync-ppp-profiles')
        ->everyFifteenMinutes()
        ->withoutOverlapping();

    // Sync simple queues every 15 minutes
    $schedule->command('bcms:sync-simple-queues')
        ->everyFifteenMinutes()
        ->withoutOverlapping();
}
```

### 3. Test Commands Manually

```bash
php artisan bcms:sync-ppp-profiles --router_id=1
php artisan bcms:sync-simple-queues --router_id=1
```

## Controller and Routes Integration

### 1. Copy Controller

- [ ] `app/Http/Controllers/Api/V1/SubscriptionNetworkStatusController.php`

### 2. Add Route

Merge the route into your existing `routes/api.php`:

```php
Route::prefix('api/v1')->middleware(['auth:sanctum'])->group(function () {
    // ... existing routes ...

    // Subscription Network Status
    Route::get('/subscriptions/{id}/network-status', 
        [SubscriptionNetworkStatusController::class, 'show'])
        ->middleware('permission:subscriptions.read');
});
```

## Permission Setup

### 1. Create Permission

If using Spatie Laravel Permission:

```php
use Spatie\Permission\Models\Permission;

Permission::create(['name' => 'subscriptions.read']);
```

### 2. Assign to Roles

```php
use Spatie\Permission\Models\Role;

$role = Role::findByName('admin');
$role->givePermissionTo('subscriptions.read');
```

## Testing

### 1. Database Testing

```bash
# Run migrations
php artisan migrate

# Verify tables created
php artisan tinker
>>> Schema::hasTable('router_ppp_profiles');
=> true
>>> Schema::hasTable('router_simple_queues');
=> true
```

### 2. Command Testing

```bash
# Create a test router
php artisan tinker
>>> $router = new App\Models\Router([
...   'name' => 'Test Router',
...   'host' => '192.168.1.1',
...   'username' => 'admin',
...   'password' => 'password',
...   'sync_interval' => 60
... ]);
>>> $router->save();

# Test sync commands
php artisan bcms:sync-ppp-profiles --router_id=1
php artisan bcms:sync-simple-queues --router_id=1
```

### 3. API Testing

```bash
# Get authentication token
curl -X POST https://your-api.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Test endpoint
curl -X GET https://your-api.com/api/v1/subscriptions/1/network-status \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### 4. Schedule Testing

```bash
# Test schedule run
php artisan schedule:run

# Check logs
tail -f storage/logs/laravel.log
```

## Troubleshooting

### Issue: Migration fails

**Solution**: Check if columns already exist in your tables. Create custom migrations to add only missing columns.

### Issue: Permission middleware error

**Solution**: Ensure you have a permission middleware registered. If using Spatie:

```php
// app/Http/Kernel.php
protected $middlewareAliases = [
    // ...
    'permission' => \Spatie\Permission\Middlewares\PermissionMiddleware::class,
];
```

### Issue: Relationship not found

**Solution**: Ensure all models have proper relationships defined. Check namespaces match your application structure.

### Issue: Mikrotik connection fails

**Solution**: 
- Verify router credentials
- Check firewall allows connections to RouterOS API port (default 8728)
- Test connection manually using RouterOS API library

### Issue: Queue naming mismatch

**Solution**: Verify your queue naming convention matches `BCMS-{provisioning_id}-{subscription_id}`. Update `buildStaticIpBlock()` if different.

## Deployment Checklist

- [ ] Migrations run successfully
- [ ] Commands registered and working
- [ ] Route accessible with authentication
- [ ] Permissions configured
- [ ] Scheduler configured in crontab
- [ ] Mikrotik API library installed (if using real sync)
- [ ] Environment variables configured
- [ ] Logs directory writable
- [ ] API documentation updated

## Security Considerations

- [ ] API endpoint requires authentication (Sanctum)
- [ ] Permission check enforced
- [ ] Router credentials stored securely (encrypted in database)
- [ ] Rate limiting configured for API endpoints
- [ ] Input validation on all parameters
- [ ] SQL injection protection (Eloquent ORM)
- [ ] XSS protection on API responses

## Performance Optimization

- [ ] Database indexes on foreign keys
- [ ] Query result caching (optional)
- [ ] Lazy loading vs eager loading optimized
- [ ] API response caching (15-minute cache aligned with sync)
- [ ] Database connection pooling configured
- [ ] Queue workers for sync commands (optional, for large deployments)

## Monitoring

- [ ] Log sync successes and failures
- [ ] Monitor API response times
- [ ] Alert on repeated sync failures
- [ ] Track mismatch detection frequency
- [ ] Monitor database table growth
- [ ] Schedule health checks
