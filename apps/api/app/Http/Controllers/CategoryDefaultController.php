<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\UserCategoryDefault;
use Illuminate\Http\Request;

class CategoryDefaultController extends Controller
{
    public function show(Request $request)
    {
        $user = $this->requireUser($request);
        $item = UserCategoryDefault::query()->where('user_id', $user->id)->firstOrFail();

        return response()->json(['item' => $item]);
    }

    public function update(Request $request)
    {
        $user = $this->requireUser($request);
        $data = $request->validate([
            'income_category_id' => ['required', 'integer'],
            'expense_category_id' => ['required', 'integer'],
        ]);

        $incomeOk = IncomeCategory::query()
            ->where('user_id', $user->id)
            ->whereKey($data['income_category_id'])
            ->exists();
        $expenseOk = ExpenseCategory::query()
            ->where('user_id', $user->id)
            ->whereKey($data['expense_category_id'])
            ->exists();

        if (!$incomeOk || !$expenseOk) {
            return response()->json(['error' => 'Category does not belong to user'], 422);
        }

        $item = UserCategoryDefault::query()->where('user_id', $user->id)->firstOrFail();
        $item->fill($data)->save();

        return response()->json(['item' => $item]);
    }
}

