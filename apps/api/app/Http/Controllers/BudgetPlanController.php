<?php

namespace App\Http\Controllers;

use App\Models\BudgetPlan;
use App\Models\BudgetPlanCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BudgetPlanController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->requireUser($request);
        $items = BudgetPlan::query()
            ->where('user_id', $user->id)
            ->with('categories')
            ->orderByDesc('id')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function store(Request $request)
    {
        $user = $this->requireUser($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'goal_type' => ['required', Rule::in(['savings', 'target_balance'])],
            'goal_amount' => ['nullable', 'numeric'],
            'categories' => ['array'],
            'categories.*.type' => ['required', Rule::in(['income', 'expense'])],
            'categories.*.category_id' => ['required', 'integer'],
            'categories.*.account_id' => ['nullable', 'integer'],
            'categories.*.budget_amount' => ['required', 'numeric', 'gte:0'],
        ]);

        $plan = BudgetPlan::query()->create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'period_from' => $data['period_from'],
            'period_to' => $data['period_to'],
            'goal_type' => $data['goal_type'],
            'goal_amount' => $data['goal_amount'] ?? null,
        ]);

        foreach (($data['categories'] ?? []) as $cat) {
            BudgetPlanCategory::query()->create([
                'budget_plan_id' => $plan->id,
                'type' => $cat['type'],
                'category_id' => (int) $cat['category_id'],
                'account_id' => $cat['account_id'] ?? null,
                'budget_amount' => $cat['budget_amount'],
            ]);
        }

        return response()->json(['item' => $plan->load('categories')], 201);
    }

    public function update(Request $request, BudgetPlan $budgetPlan)
    {
        $user = $this->requireUser($request);
        abort_if($budgetPlan->user_id !== $user->id, 404);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'period_from' => ['sometimes', 'required', 'date'],
            'period_to' => ['sometimes', 'required', 'date'],
            'goal_type' => ['sometimes', 'required', Rule::in(['savings', 'target_balance'])],
            'goal_amount' => ['sometimes', 'nullable', 'numeric'],
        ]);
        $budgetPlan->fill($data)->save();

        return response()->json(['item' => $budgetPlan->load('categories')]);
    }

    public function destroy(Request $request, BudgetPlan $budgetPlan)
    {
        $user = $this->requireUser($request);
        abort_if($budgetPlan->user_id !== $user->id, 404);
        $budgetPlan->delete();

        return response()->json(['ok' => true]);
    }
}

