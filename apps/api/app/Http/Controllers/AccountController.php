<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\Transaction;
use App\Models\UserCategoryDefault;
use App\Services\Finance\AccountBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountBalanceService $accountBalanceService
    ) {
    }

    public function index(Request $request)
    {
        $user = $this->requireUser($request);

        return response()->json([
            'items' => Account::query()->where('user_id', $user->id)->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->requireUser($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:64'],
            'currency' => ['required', 'string', 'max:8'],
            'balance' => ['nullable', 'numeric', 'gte:0'],
        ]);

        $item = DB::transaction(function () use ($user, $data) {
            $account = Account::query()->create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'type' => $data['type'],
                'currency' => $data['currency'],
                'balance' => 0,
                'balance_calculated_to_transaction_id' => null,
            ]);

            $initialBalance = (float) ($data['balance'] ?? 0);
            if ($initialBalance > 0) {
                $defaults = $this->ensureUserCategoryDefaults($user->id);

                Transaction::query()->create([
                    'user_id' => $user->id,
                    'account_id' => $account->id,
                    'type' => 'income',
                    'category_id' => $defaults->income_category_id,
                    'amount' => $initialBalance,
                    'description' => 'Начальное пополнение счета',
                    'occurred_at' => now(),
                ]);
            }

            $this->accountBalanceService->recalculate($account);

            return $account->fresh();
        });

        return response()->json(['item' => $item], 201);
    }

    public function show(Request $request, Account $account)
    {
        $user = $this->requireUser($request);
        abort_if($account->user_id !== $user->id, 404);

        return response()->json(['item' => $account]);
    }

    public function update(Request $request, Account $account)
    {
        $user = $this->requireUser($request);
        abort_if($account->user_id !== $user->id, 404);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'max:64'],
            'currency' => ['sometimes', 'required', 'string', 'max:8'],
        ]);
        $account->fill($data)->save();

        return response()->json(['item' => $account]);
    }

    public function destroy(Request $request, Account $account)
    {
        $user = $this->requireUser($request);
        abort_if($account->user_id !== $user->id, 404);
        $account->delete();

        return response()->json(['ok' => true]);
    }

    private function ensureUserCategoryDefaults(int $userId): UserCategoryDefault
    {
        $existing = UserCategoryDefault::query()->where('user_id', $userId)->first();
        if ($existing) {
            return $existing;
        }

        $incomeNames = [
            'Зарплата',
            'Премия',
            'Фриланс',
            'Инвестиции',
            'Подарки',
        ];
        $expenseNames = [
            'Продукты',
            'Транспорт',
            'Жилье',
            'Связь и интернет',
            'Здоровье',
            'Одежда',
            'Развлечения',
            'Образование',
            'Путешествия',
            'Прочие расходы',
        ];

        $incomeIds = [];
        foreach ($incomeNames as $name) {
            $incomeIds[] = IncomeCategory::query()->create([
                'user_id' => $userId,
                'name' => $name,
            ])->id;
        }

        $expenseIds = [];
        foreach ($expenseNames as $name) {
            $expenseIds[] = ExpenseCategory::query()->create([
                'user_id' => $userId,
                'name' => $name,
            ])->id;
        }

        return UserCategoryDefault::query()->create([
            'user_id' => $userId,
            'income_category_id' => $incomeIds[0],
            'expense_category_id' => $expenseIds[0],
        ]);
    }
}
