<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\User;
use App\Models\UserCategoryDefault;
use App\Models\CreditLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
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

        $this->ensureDefaults($user);
        $this->grantTrialCredit($user->id);

        $request->session()->put('uid', $user->id);
        $request->session()->regenerate();

        return response()->json(['user' => $user], 201);
    }

    public function login(Request $request)
    {
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
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    private function ensureDefaults(User $user): void
    {
        if (UserCategoryDefault::query()->where('user_id', $user->id)->exists()) {
            return;
        }

        $income = IncomeCategory::query()->create([
            'user_id' => $user->id,
            'name' => 'Other income',
        ]);
        $expense = ExpenseCategory::query()->create([
            'user_id' => $user->id,
            'name' => 'Other expense',
        ]);

        UserCategoryDefault::query()->create([
            'user_id' => $user->id,
            'income_category_id' => $income->id,
            'expense_category_id' => $expense->id,
        ]);
    }

    private function grantTrialCredit(int $userId): void
    {
        $amount = (float) config('ai.free_trial_credit', 0);
        if ($amount <= 0) {
            return;
        }

        $exists = CreditLedgerEntry::query()
            ->where('user_id', $userId)
            ->where('entry_type', 'trial_credit')
            ->exists();
        if ($exists) {
            return;
        }

        CreditLedgerEntry::query()->create([
            'user_id' => $userId,
            'payment_id' => null,
            'entry_type' => 'trial_credit',
            'amount' => $amount,
            'currency' => 'RUB',
            'description' => 'AI trial credit',
        ]);
    }
}
