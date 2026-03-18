<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\Transaction;
use App\Models\UserCategoryDefault;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->requireUser($request);
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
    }

    public function store(Request $request)
    {
        $user = $this->requireUser($request);
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
    }

    public function update(Request $request, Transaction $transaction)
    {
        $user = $this->requireUser($request);
        abort_if($transaction->user_id !== $user->id, 404);
        $data = $request->validate([
            'amount' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'description' => ['sometimes', 'nullable', 'string'],
            'occurred_at' => ['sometimes', 'required', 'date'],
        ]);
        $transaction->fill($data)->save();

        return response()->json(['item' => $transaction]);
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        $user = $this->requireUser($request);
        abort_if($transaction->user_id !== $user->id, 404);
        $transaction->delete();

        return response()->json(['ok' => true]);
    }
}

