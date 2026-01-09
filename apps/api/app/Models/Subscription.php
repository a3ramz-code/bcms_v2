<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'customer_id',
        'internet_service_id',
        'status',
    ];

    public function provisioning()
    {
        return $this->hasMany(Provisioning::class);
    }

    public function latestProvisioning()
    {
        return $this->hasOne(Provisioning::class)->latestOfMany();
    }

    public function internetService()
    {
        return $this->belongsTo(InternetService::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
