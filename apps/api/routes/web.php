<?php

use App\Models\Account;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanCategory;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserCategoryDefault;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

if (!function_exists('api_user')) {
    function api_user(Request $request): User
    {
        $uid = (int) $request->session()->get('uid', 0);
        $user = User::find($uid);
        if (!$user) {
            abort(response()->json(['error' => 'Unauthorized'], 401));
        }

        return $user;
    }
}

if (!function_exists('ensure_defaults')) {
    function ensure_defaults(User $user): void
    {
        if (UserCategoryDefault::query()->where('user_id', $user->id)->exists()) {
            return;
        }

        $income = IncomeCategory::query()->create([
            'user_id' => $user->id,
            'name' => 'Прочие доходы',
        ]);
        $expense = ExpenseCategory::query()->create([
            'user_id' => $user->id,
            'name' => 'Прочие расходы',
        ]);

        UserCategoryDefault::query()->create([
            'user_id' => $user->id,
            'income_category_id' => $income->id,
            'expense_category_id' => $expense->id,
        ]);
    }
}

Route::prefix('api')->group(function (): void {
    Route::get('/health', fn () => response()->json(['service' => 'finwise-api', 'status' => 'healthy']));

    Route::post('/auth/register', function (Request $request) {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32', 'unique:users,phone'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::query()->create([
            'phone' => $data['phone'],
            'email' => $data['email'],
            'role' => 'USER',
            'password' => Hash::make($data['password']),
        ]);
        ensure_defaults($user);

        $request->session()->put('uid', $user->id);
        $request->session()->regenerate();

        return response()->json(['user' => $user], 201);
    });

    Route::post('/auth/login', function (Request $request) {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('phone', $data['phone'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $request->session()->put('uid', $user->id);
        $request->session()->regenerate();
        return response()->json(['user' => $user]);
    });

    Route::post('/auth/logout', function (Request $request) {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['ok' => true]);
    });

    Route::get('/me', function (Request $request) {
        return response()->json(['user' => api_user($request)]);
    });

    Route::put('/me', function (Request $request) {
        $user = api_user($request);
        $data = $request->validate([
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);
        $user->email = $data['email'];
        $user->save();
        return response()->json(['user' => $user]);
    });

    Route::get('/admin/users', function (Request $request) {
        $user = api_user($request);
        if ($user->role !== 'ADMIN') {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        return response()->json(['items' => User::query()->select('id', 'phone', 'email', 'role', 'created_at')->get()]);
    });

    Route::get('/accounts', function (Request $request) {
        $user = api_user($request);
        return response()->json(['items' => Account::query()->where('user_id', $user->id)->latest()->get()]);
    });

    Route::post('/accounts', function (Request $request) {
        $user = api_user($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:64'],
            'currency' => ['required', 'string', 'max:8'],
            'balance' => ['required', 'numeric'],
        ]);
        $item = Account::query()->create($data + ['user_id' => $user->id]);
        return response()->json(['item' => $item], 201);
    });

    Route::get('/accounts/{account}', function (Request $request, Account $account) {
        $user = api_user($request);
        abort_if($account->user_id !== $user->id, 404);
        return response()->json(['item' => $account]);
    });

    Route::put('/accounts/{account}', function (Request $request, Account $account) {
        $user = api_user($request);
        abort_if($account->user_id !== $user->id, 404);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'max:64'],
            'currency' => ['sometimes', 'required', 'string', 'max:8'],
            'balance' => ['sometimes', 'required', 'numeric'],
        ]);
        $account->fill($data)->save();
        return response()->json(['item' => $account]);
    });

    Route::delete('/accounts/{account}', function (Request $request, Account $account) {
        $user = api_user($request);
        abort_if($account->user_id !== $user->id, 404);
        $account->delete();
        return response()->json(['ok' => true]);
    });

    Route::get('/income-categories', function (Request $request) {
        $user = api_user($request);
        return response()->json(['items' => IncomeCategory::query()->where('user_id', $user->id)->orderBy('name')->get()]);
    });

    Route::post('/income-categories', function (Request $request) {
        $user = api_user($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $item = IncomeCategory::query()->create($data + ['user_id' => $user->id]);
        return response()->json(['item' => $item], 201);
    });

    Route::put('/income-categories/{id}', function (Request $request, int $id) {
        $user = api_user($request);
        $row = IncomeCategory::query()->where('user_id', $user->id)->findOrFail($id);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $row->fill($data)->save();
        return response()->json(['item' => $row]);
    });

    Route::delete('/income-categories/{id}', function (Request $request, int $id) {
        $user = api_user($request);
        $row = IncomeCategory::query()->where('user_id', $user->id)->findOrFail($id);
        $row->delete();
        return response()->json(['ok' => true]);
    });

    Route::get('/expense-categories', function (Request $request) {
        $user = api_user($request);
        return response()->json(['items' => ExpenseCategory::query()->where('user_id', $user->id)->orderBy('name')->get()]);
    });

    Route::post('/expense-categories', function (Request $request) {
        $user = api_user($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $item = ExpenseCategory::query()->create($data + ['user_id' => $user->id]);
        return response()->json(['item' => $item], 201);
    });

    Route::put('/expense-categories/{id}', function (Request $request, int $id) {
        $user = api_user($request);
        $row = ExpenseCategory::query()->where('user_id', $user->id)->findOrFail($id);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $row->fill($data)->save();
        return response()->json(['item' => $row]);
    });

    Route::delete('/expense-categories/{id}', function (Request $request, int $id) {
        $user = api_user($request);
        $row = ExpenseCategory::query()->where('user_id', $user->id)->findOrFail($id);
        $row->delete();
        return response()->json(['ok' => true]);
    });

    Route::get('/users/category-defaults', function (Request $request) {
        $user = api_user($request);
        $item = UserCategoryDefault::query()->where('user_id', $user->id)->firstOrFail();
        return response()->json(['item' => $item]);
    });

    Route::put('/users/category-defaults', function (Request $request) {
        $user = api_user($request);
        $data = $request->validate([
            'income_category_id' => ['required', 'integer'],
            'expense_category_id' => ['required', 'integer'],
        ]);
        $incomeOk = IncomeCategory::query()->where('user_id', $user->id)->whereKey($data['income_category_id'])->exists();
        $expenseOk = ExpenseCategory::query()->where('user_id', $user->id)->whereKey($data['expense_category_id'])->exists();
        if (!$incomeOk || !$expenseOk) {
            return response()->json(['error' => 'Category does not belong to user'], 422);
        }
        $item = UserCategoryDefault::query()->where('user_id', $user->id)->firstOrFail();
        $item->fill($data)->save();
        return response()->json(['item' => $item]);
    });

    Route::get('/transactions', function (Request $request) {
        $user = api_user($request);
        $q = Transaction::query()->where('user_id', $user->id);
        if ($request->filled('type')) {
            $q->where('type', $request->string('type'));
        }
        if ($request->filled('account_id')) {
            $q->where('account_id', (int) $request->query('account_id'));
        }
        if ($request->filled('category_id')) {
            $q->where('category_id', (int) $request->query('category_id'));
        }
        if ($request->filled('date_from')) {
            $q->where('occurred_at', '>=', Carbon::parse((string) $request->query('date_from'))->startOfDay());
        }
        if ($request->filled('date_to')) {
            $q->where('occurred_at', '<=', Carbon::parse((string) $request->query('date_to'))->endOfDay());
        }
        return response()->json(['items' => $q->orderByDesc('occurred_at')->get()]);
    });

    Route::post('/transactions', function (Request $request) {
        $user = api_user($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'account_id' => ['required', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $accountOk = Account::query()->where('user_id', $user->id)->whereKey((int) $data['account_id'])->exists();
        if (!$accountOk) {
            return response()->json(['error' => 'Valid account_id is required'], 422);
        }

        if (empty($data['category_id'])) {
            $defaults = UserCategoryDefault::query()->where('user_id', $user->id)->firstOrFail();
            $data['category_id'] = $data['type'] === 'income' ? $defaults->income_category_id : $defaults->expense_category_id;
        }

        $categoryOk = $data['type'] === 'income'
            ? IncomeCategory::query()->where('user_id', $user->id)->whereKey((int) $data['category_id'])->exists()
            : ExpenseCategory::query()->where('user_id', $user->id)->whereKey((int) $data['category_id'])->exists();

        if (!$categoryOk) {
            return response()->json(['error' => 'Category does not belong to user'], 422);
        }

        $item = Transaction::query()->create([
            'user_id' => $user->id,
            'account_id' => (int) $data['account_id'],
            'type' => $data['type'],
            'category_id' => (int) $data['category_id'],
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);
        return response()->json(['item' => $item], 201);
    });

    Route::put('/transactions/{transaction}', function (Request $request, Transaction $transaction) {
        $user = api_user($request);
        abort_if($transaction->user_id !== $user->id, 404);
        $data = $request->validate([
            'amount' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'description' => ['sometimes', 'nullable', 'string'],
            'occurred_at' => ['sometimes', 'required', 'date'],
        ]);
        $transaction->fill($data)->save();
        return response()->json(['item' => $transaction]);
    });

    Route::delete('/transactions/{transaction}', function (Request $request, Transaction $transaction) {
        $user = api_user($request);
        abort_if($transaction->user_id !== $user->id, 404);
        $transaction->delete();
        return response()->json(['ok' => true]);
    });

    Route::get('/recurring-transactions', function (Request $request) {
        $user = api_user($request);
        $items = RecurringTransaction::query()->where('user_id', $user->id)->orderByDesc('next_run_at')->get();
        return response()->json(['items' => $items]);
    });

    Route::post('/recurring-transactions', function (Request $request) {
        $user = api_user($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'account_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
        ]);

        $item = RecurringTransaction::query()->create([
            ...$data,
            'user_id' => $user->id,
            'next_run_at' => $data['start_at'],
            'is_active' => true,
        ]);
        return response()->json(['item' => $item], 201);
    });

    Route::put('/recurring-transactions/{recurringTransaction}', function (Request $request, RecurringTransaction $recurringTransaction) {
        $user = api_user($request);
        abort_if($recurringTransaction->user_id !== $user->id, 404);
        $data = $request->validate([
            'amount' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'end_at' => ['sometimes', 'nullable', 'date'],
            'next_run_at' => ['sometimes', 'required', 'date'],
        ]);
        $recurringTransaction->fill($data)->save();
        return response()->json(['item' => $recurringTransaction]);
    });

    Route::delete('/recurring-transactions/{recurringTransaction}', function (Request $request, RecurringTransaction $recurringTransaction) {
        $user = api_user($request);
        abort_if($recurringTransaction->user_id !== $user->id, 404);
        $recurringTransaction->delete();
        return response()->json(['ok' => true]);
    });

    Route::get('/budget-plans', function (Request $request) {
        $user = api_user($request);
        $items = BudgetPlan::query()->where('user_id', $user->id)->with('categories')->orderByDesc('id')->get();
        return response()->json(['items' => $items]);
    });

    Route::post('/budget-plans', function (Request $request) {
        $user = api_user($request);
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
    });

    Route::put('/budget-plans/{budgetPlan}', function (Request $request, BudgetPlan $budgetPlan) {
        $user = api_user($request);
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
    });

    Route::delete('/budget-plans/{budgetPlan}', function (Request $request, BudgetPlan $budgetPlan) {
        $user = api_user($request);
        abort_if($budgetPlan->user_id !== $user->id, 404);
        $budgetPlan->delete();
        return response()->json(['ok' => true]);
    });

    Route::get('/analytics/summary', function (Request $request) {
        $user = api_user($request);
        $base = Transaction::query()->where('user_id', $user->id);
        if ($request->filled('date_from')) {
            $base->where('occurred_at', '>=', Carbon::parse((string) $request->query('date_from'))->startOfDay());
        }
        if ($request->filled('date_to')) {
            $base->where('occurred_at', '<=', Carbon::parse((string) $request->query('date_to'))->endOfDay());
        }
        $income = (float) (clone $base)->where('type', 'income')->sum('amount');
        $expense = (float) (clone $base)->where('type', 'expense')->sum('amount');
        return response()->json([
            'income_total' => $income,
            'expense_total' => $expense,
            'net_total' => $income - $expense,
        ]);
    });

    Route::get('/analytics/timeseries', function (Request $request) {
        $user = api_user($request);
        $rows = Transaction::query()
            ->where('user_id', $user->id)
            ->selectRaw('DATE(occurred_at) as d, type, SUM(amount) as total')
            ->groupByRaw('DATE(occurred_at), type')
            ->orderBy('d')
            ->get();
        return response()->json(['items' => $rows]);
    });

    Route::get('/analytics/categories', function (Request $request) {
        $user = api_user($request);
        $rows = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'expense')
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();
        return response()->json(['items' => $rows]);
    });
});
