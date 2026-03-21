<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'tariff_id',
        'provider',
        'provider_payment_id',
        'idempotence_key',
        'amount_rub',
        'currency',
        'status',
        'confirmation_url',
        'provider_payload',
        'paid_at',
        'processed_at',
    ];

    protected $casts = [
        'amount_rub' => 'decimal:2',
        'provider_payload' => 'array',
        'paid_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function tariff()
    {
        return $this->belongsTo(Tariff::class, 'tariff_id');
    }
}
