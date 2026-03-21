<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
        'message_id',
        'model_code',
        'input_tokens',
        'output_tokens',
        'cached_input_tokens',
        'total_cost_rub',
        'meta',
    ];

    protected $casts = [
        'total_cost_rub' => 'decimal:6',
        'meta' => 'array',
    ];
}

