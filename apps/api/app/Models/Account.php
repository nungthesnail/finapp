<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'currency',
        'balance',
        'balance_calculated_to_transaction_id',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'balance_calculated_to_transaction_id' => 'integer',
    ];
}
