<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provisioning extends Model
{
    protected $table = 'provisionings';

    protected $fillable = [
        'subscription_id',
        'router_id',
        'device_conn',
        'provisioning_status',
        'pppoe_name',
        'static_ip',
        'static_gateway',
        'pppoe_secret_disabled',
        'pppoe_secret_last_sync_at',
        'provisioned_at',
    ];

    protected $casts = [
        'pppoe_secret_disabled' => 'boolean',
        'pppoe_secret_last_sync_at' => 'datetime',
        'provisioned_at' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function router()
    {
        return $this->belongsTo(Router::class);
    }
}
