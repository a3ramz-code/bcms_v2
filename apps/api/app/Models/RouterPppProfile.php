<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouterPppProfile extends Model
{
    protected $fillable = [
        'router_id',
        'name',
        'rate_limit',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function router()
    {
        return $this->belongsTo(Router::class);
    }
}
