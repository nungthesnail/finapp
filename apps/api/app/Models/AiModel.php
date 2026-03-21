<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    protected $fillable = [
        'code',
        'name',
        'provider',
        'is_active',
        'supports_tools',
        'input_cost_per_1k',
        'output_cost_per_1k',
        'cached_input_cost_per_1k',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'supports_tools' => 'boolean',
        'input_cost_per_1k' => 'decimal:6',
        'output_cost_per_1k' => 'decimal:6',
        'cached_input_cost_per_1k' => 'decimal:6',
    ];
}

