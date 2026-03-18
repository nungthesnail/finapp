<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'tariff_id',
        'status',
        'start_at',
        'end_at',
        'canceled_at',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];
}

