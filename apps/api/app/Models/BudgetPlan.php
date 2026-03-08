<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetPlan extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'period_from',
        'period_to',
        'goal_type',
        'goal_amount',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'goal_amount' => 'decimal:2',
    ];

    public function categories()
    {
        return $this->hasMany(BudgetPlanCategory::class);
    }
}

