<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditLedgerEntry extends Model
{
    protected $table = 'credit_ledger';

    protected $fillable = [
        'user_id',
        'payment_id',
        'entry_type',
        'amount',
        'currency',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];
}

