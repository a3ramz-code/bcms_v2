<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PppoeSession extends Model
{
    protected $fillable = [
        'router_id',
        'name',
        'address',
        'caller_id',
        'uptime',
        'bytes_in',
        'bytes_out',
        'last_seen_at',
    ];

    protected $casts = [
        'bytes_in' => 'integer',
        'bytes_out' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    public function router()
    {
        return $this->belongsTo(Router::class);
    }
}
