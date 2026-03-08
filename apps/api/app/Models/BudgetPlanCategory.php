<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetPlanCategory extends Model
{
    protected $fillable = [
        'budget_plan_id',
        'type',
        'category_id',
        'account_id',
        'budget_amount',
    ];

    protected $casts = [
        'budget_amount' => 'decimal:2',
    ];
}

