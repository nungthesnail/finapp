<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'type',
        'category_id',
        'amount',
        'description',
        'frequency',
        'start_at',
        'end_at',
        'next_run_at',
        'is_active',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'next_run_at' => 'datetime',
        'is_active' => 'boolean',
        'amount' => 'decimal:2',
    ];
}

