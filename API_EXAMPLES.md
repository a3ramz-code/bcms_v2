# API Examples

## Network Status Endpoint

### Request

```http
GET /api/v1/subscriptions/123/network-status HTTP/1.1
Host: api.bcms.example.com
Authorization: Bearer {sanctum_token}
Accept: application/json
```

### Response Examples

#### Example 1: Active PPPoE Subscription with No Mismatches

```json
{
  "subscription": {
    "id": 123,
    "status": "active"
  },
  "router": {
    "id": 5,
    "name": "MikroTik-Tower-01",
    "sync_interval": 60
  },
  "provisioning": {
    "id": 456,
    "router_id": 5,
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
      "bytes_in": 1048576000,
      "bytes_out": 524288000,
      "last_seen_at": "2026-01-09T08:15:00+00:00"
    },
    "profile_snapshot": {
      "name": "10M-Profile",
      "rate_limit": "10M/10M",
      "last_seen_at": "2026-01-09T08:00:00+00:00"
    }
  }
}
```

#### Example 2: Suspended PPPoE with Mismatch (Still Online)

```json
{
  "subscription": {
    "id": 124,
    "status": "suspend"
  },
  "router": {
    "id": 5,
    "name": "MikroTik-Tower-01",
    "sync_interval": 60
  },
  "provisioning": {
    "id": 457,
    "router_id": 5,
    "device_conn": "pppoe",
    "provisioning_status": "suspended",
    "pppoe_name": "user124",
    "static_ip": null,
    "static_gateway": null,
    "pppoe_secret_disabled": true,
    "pppoe_secret_last_sync_at": "2026-01-09T07:30:00+00:00",
    "provisioned_at": "2026-01-01T00:00:00+00:00"
  },
  "expected_state": {
    "status": "suspend",
    "pppoe_secret_enabled": false,
    "rate_policy": null,
    "static_queue_enabled": true,
    "max_limit_policy": "suspend"
  },
  "mismatch_flags": {
    "has_mismatch": true,
    "reasons": [
      {
        "type": "pppoe_online_while_expected_disabled",
        "message": "PPPoE session is online but secret is expected to be disabled"
      }
    ]
  },
  "pppoe": {
    "secret": {
      "enabled": false,
      "disabled": true,
      "last_sync_at": "2026-01-09T07:30:00+00:00"
    },
    "session": {
      "online": true,
      "address": "10.0.0.124",
      "caller_id": "00:11:22:33:44:66",
      "uptime": "15h45m",
      "bytes_in": 204800000,
      "bytes_out": 102400000,
      "last_seen_at": "2026-01-09T08:14:30+00:00"
    },
    "profile_snapshot": {
      "name": "10M-Profile",
      "rate_limit": "10M/10M",
      "last_seen_at": "2026-01-09T08:00:00+00:00"
    }
  }
}
```

#### Example 3: Soft-Limited PPPoE with Rate Mismatch

```json
{
  "subscription": {
    "id": 125,
    "status": "soft_limit"
  },
  "router": {
    "id": 5,
    "name": "MikroTik-Tower-01",
    "sync_interval": 60
  },
  "provisioning": {
    "id": 458,
    "router_id": 5,
    "device_conn": "pppoe",
    "provisioning_status": "active",
    "pppoe_name": "user125",
    "static_ip": null,
    "static_gateway": null,
    "pppoe_secret_disabled": false,
    "pppoe_secret_last_sync_at": "2026-01-09T08:00:00+00:00",
    "provisioned_at": "2026-01-01T00:00:00+00:00"
  },
  "expected_state": {
    "status": "soft_limit",
    "pppoe_secret_enabled": true,
    "rate_policy": "soft_limit",
    "static_queue_enabled": true,
    "max_limit_policy": "soft_limit"
  },
  "mismatch_flags": {
    "has_mismatch": true,
    "reasons": [
      {
        "type": "pppoe_profile_rate_limit_mismatch",
        "message": "PPPoE profile rate limit mismatch",
        "expected": "5M/5M",
        "actual": "10M/10M"
      }
    ]
  },
  "pppoe": {
    "secret": {
      "enabled": true,
      "disabled": false,
      "last_sync_at": "2026-01-09T08:00:00+00:00"
    },
    "session": {
      "online": true,
      "address": "10.0.0.125",
      "caller_id": "00:11:22:33:44:77",
      "uptime": "3h20m",
      "bytes_in": 314572800,
      "bytes_out": 157286400,
      "last_seen_at": "2026-01-09T08:15:00+00:00"
    },
    "profile_snapshot": {
      "name": "10M-Profile",
      "rate_limit": "10M/10M",
      "last_seen_at": "2026-01-09T08:00:00+00:00"
    }
  }
}
```

#### Example 4: Active Static-IP Subscription

```json
{
  "subscription": {
    "id": 126,
    "status": "active"
  },
  "router": {
    "id": 6,
    "name": "MikroTik-Tower-02",
    "sync_interval": 60
  },
  "provisioning": {
    "id": 459,
    "router_id": 6,
    "device_conn": "static-ip",
    "provisioning_status": "active",
    "pppoe_name": null,
    "static_ip": "192.168.1.100",
    "static_gateway": "192.168.1.1",
    "pppoe_secret_disabled": null,
    "pppoe_secret_last_sync_at": null,
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
  "static_ip": {
    "ip": "192.168.1.100",
    "gateway": "192.168.1.1",
    "configured": true,
    "queue_snapshot": {
      "name": "BCMS-459-126",
      "target": "192.168.1.100/32",
      "max_limit": "20M/20M",
      "limit_at": "10M/10M",
      "disabled": false,
      "last_seen_at": "2026-01-09T08:00:00+00:00"
    }
  }
}
```

#### Example 5: Static-IP with Queue Max-Limit Mismatch

```json
{
  "subscription": {
    "id": 127,
    "status": "active"
  },
  "router": {
    "id": 6,
    "name": "MikroTik-Tower-02",
    "sync_interval": 60
  },
  "provisioning": {
    "id": 460,
    "router_id": 6,
    "device_conn": "static-ip",
    "provisioning_status": "active",
    "pppoe_name": null,
    "static_ip": "192.168.1.101",
    "static_gateway": "192.168.1.1",
    "pppoe_secret_disabled": null,
    "pppoe_secret_last_sync_at": null,
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
    "has_mismatch": true,
    "reasons": [
      {
        "type": "static_ip_queue_max_limit_mismatch",
        "message": "Static IP queue max-limit mismatch",
        "expected": "20M/20M",
        "actual": "10M/10M"
      }
    ]
  },
  "static_ip": {
    "ip": "192.168.1.101",
    "gateway": "192.168.1.1",
    "configured": true,
    "queue_snapshot": {
      "name": "BCMS-460-127",
      "target": "192.168.1.101/32",
      "max_limit": "10M/10M",
      "limit_at": "5M/5M",
      "disabled": false,
      "last_seen_at": "2026-01-09T08:00:00+00:00"
    }
  }
}
```

## Error Responses

### Subscription Not Found

```http
HTTP/1.1 404 Not Found
Content-Type: application/json
```

```json
{
  "message": "No query results for model [App\\Models\\Subscription] 999"
}
```

### Unauthorized

```http
HTTP/1.1 401 Unauthorized
Content-Type: application/json
```

```json
{
  "message": "Unauthenticated."
}
```

### Forbidden (Missing Permission)

```http
HTTP/1.1 403 Forbidden
Content-Type: application/json
```

```json
{
  "message": "This action is unauthorized."
}
```

## Command Usage Examples

### Sync PPP Profiles for All Routers

```bash
php artisan bcms:sync-ppp-profiles
```

Output:
```
Syncing PPP profiles for 5 routers
Syncing router MikroTik-Tower-01 (ID: 1)
✓ Router 1 synced successfully
Syncing router MikroTik-Tower-02 (ID: 2)
✓ Router 2 synced successfully
...
Sync completed for all routers
```

### Sync PPP Profiles for Specific Router

```bash
php artisan bcms:sync-ppp-profiles --router_id=1
```

Output:
```
Syncing PPP profiles for router MikroTik-Tower-01 (ID: 1)
Sync completed for router 1
```

### Sync Simple Queues for All Routers

```bash
php artisan bcms:sync-simple-queues
```

### Sync Simple Queues for Specific Router

```bash
php artisan bcms:sync-simple-queues --router_id=2
```
