<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCategoryDefault extends Model
{
    protected $fillable = ['user_id', 'income_category_id', 'expense_category_id'];
}

