<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternetService extends Model
{
    protected $fillable = [
        'name',
        'rate_limit',
        'limit_at',
        'price',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
