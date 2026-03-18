<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint',
        'p256dh',
        'auth',
        'raw',
        'is_active',
        'last_seen_at',
    ];

    protected $casts = [
        'raw' => 'array',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];
}

