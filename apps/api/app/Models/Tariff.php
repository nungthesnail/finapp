<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    protected $fillable = [
        'name',
        'description',
        'duration_days',
        'price_rub',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price_rub' => 'decimal:2',
    ];
}

