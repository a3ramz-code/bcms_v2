<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Router extends Model
{
    protected $fillable = [
        'name',
        'host',
        'port',
        'username',
        'password',
        'sync_interval',
    ];

    protected $casts = [
        'sync_interval' => 'integer',
    ];

    public function pppProfiles()
    {
        return $this->hasMany(RouterPppProfile::class);
    }

    public function simpleQueues()
    {
        return $this->hasMany(RouterSimpleQueue::class);
    }

    public function provisioning()
    {
        return $this->hasMany(Provisioning::class);
    }
}
