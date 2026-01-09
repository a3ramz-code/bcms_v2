<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouterSimpleQueue extends Model
{
    protected $fillable = [
        'router_id',
        'name',
        'target',
        'max_limit',
        'limit_at',
        'disabled',
        'last_seen_at',
    ];

    protected $casts = [
        'disabled' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function router()
    {
        return $this->belongsTo(Router::class);
    }
}
