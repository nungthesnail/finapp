<?php

namespace App\Services\Ai;

use App\Models\Account;
use App\Models\BudgetPlan;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserCategoryDefault;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiToolService
{
    /**
     * @return array{name:string,result:array<string,mixed>}|null
     */
    public function executeFromPrompt(User $user, string $prompt): ?array
    {
        $trimmed = trim($prompt);
        if (!str_starts_with($trimmed, 'tool:')) {
            return null;
        }

        $toolName = trim((string) strtok(substr($trimmed, 5), " \n\t"));
        $jsonPos = strpos($trimmed, '{');
        $args = [];
        if ($jsonPos !== false) {
            $raw = substr($trimmed, $jsonPos);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $args = $decoded;
            }
        }

        return match ($toolName) {
            'financial_summary' => [
                'name' => $toolName,
                'result' => $this->financialSummary($user),
            ],
            'forecast_30d' => [
                'name' => $toolName,
                'result' => $this->forecast30d($user),
            ],
            'create_transaction' => [
                'name' => $toolName,
                'result' => $this->createTransaction($user, $args),
            ],
            'create_budget_plan' => [
                'name' => $toolName,
                'result' => $this->createBudgetPlan($user, $args),
            ],
            default => [
                'name' => 'unknown',
                'result' => ['error' => 'Unknown tool'],
            ],
        };
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function executeByName(User $user, string $toolName, array $args): array
    {
        return match ($toolName) {
            'financial_summary' => $this->financialSummary($user),
            'forecast_30d' => $this->forecast30d($user),
            'create_transaction' => $this->createTransaction($user, $args),
            'create_budget_plan' => $this->createBudgetPlan($user, $args),
            default => ['error' => 'Unknown tool'],
        };
    }

    /**
     * @return array{name:string,args:array<string,mixed>}|null
     */
    public function extractDirective(string $prompt): ?array
    {
        $trimmed = trim($prompt);
        if (!str_starts_with($trimmed, 'tool:')) {
            return null;
        }

        $toolName = trim((string) strtok(substr($trimmed, 5), " \n\t"));
        $jsonPos = strpos($trimmed, '{');
        $args = [];
        if ($jsonPos !== false) {
            $raw = substr($trimmed, $jsonPos);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $args = $decoded;
            }
        }

        return ['name' => $toolName, 'args' => $args];
    }

    /**
     * @return array<string, mixed>
     */
    public function financialSummary(User $user): array
    {
        $income = (float) Transaction::query()->where('user_id', $user->id)->where('type', 'income')->sum('amount');
        $expense = (float) Transaction::query()->where('user_id', $user->id)->where('type', 'expense')->sum('amount');

        return [
            'income_total' => $income,
            'expense_total' => $expense,
            'net_total' => $income - $expense,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forecast30d(User $user): array
    {
        $from = now()->subDays(30);
        $income = (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'income')
            ->where('occurred_at', '>=', $from)
            ->sum('amount');
        $expense = (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('occurred_at', '>=', $from)
            ->sum('amount');

        $dailyNet = ($income - $expense) / 30.0;

        return [
            'period_days' => 30,
            'historical_income' => $income,
            'historical_expense' => $expense,
            'daily_net_avg' => round($dailyNet, 2),
            'forecast_net_30d' => round($dailyNet * 30, 2),
        ];
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function createTransaction(User $user, array $args): array
    {
        $type = (string) ($args['type'] ?? '');
        $amount = (float) ($args['amount'] ?? 0);
        $accountId = (int) ($args['account_id'] ?? 0);
        $description = isset($args['description']) ? (string) $args['description'] : null;

        if (!in_array($type, ['income', 'expense'], true) || $amount <= 0 || $accountId <= 0) {
            throw ValidationException::withMessages(['tool' => 'Invalid create_transaction arguments']);
        }

        $accountOk = Account::query()->where('user_id', $user->id)->whereKey($accountId)->exists();
        if (!$accountOk) {
            throw ValidationException::withMessages(['tool' => 'Account does not belong to user']);
        }

        $categoryId = isset($args['category_id']) ? (int) $args['category_id'] : 0;
        if ($categoryId <= 0) {
            $defaults = UserCategoryDefault::query()->where('user_id', $user->id)->firstOrFail();
            $categoryId = $type === 'income' ? (int) $defaults->income_category_id : (int) $defaults->expense_category_id;
        }

        $categoryOk = $type === 'income'
            ? IncomeCategory::query()->where('user_id', $user->id)->whereKey($categoryId)->exists()
            : ExpenseCategory::query()->where('user_id', $user->id)->whereKey($categoryId)->exists();
        if (!$categoryOk) {
            throw ValidationException::withMessages(['tool' => 'Category does not belong to user']);
        }

        $item = Transaction::query()->create([
            'user_id' => $user->id,
            'account_id' => $accountId,
            'type' => $type,
            'category_id' => $categoryId,
            'amount' => $amount,
            'description' => $description,
            'occurred_at' => now(),
        ]);

        return [
            'created_transaction_id' => $item->id,
            'type' => $item->type,
            'amount' => (float) $item->amount,
        ];
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function createBudgetPlan(User $user, array $args): array
    {
        $name = trim((string) ($args['name'] ?? 'AI plan'));
        $periodFrom = (string) ($args['period_from'] ?? now()->toDateString());
        $periodTo = (string) ($args['period_to'] ?? now()->addMonth()->toDateString());
        $goalType = (string) ($args['goal_type'] ?? 'savings');
        $goalAmount = isset($args['goal_amount']) ? (float) $args['goal_amount'] : null;

        if (!in_array($goalType, ['savings', 'target_balance'], true)) {
            throw ValidationException::withMessages(['tool' => 'Invalid goal_type']);
        }

        $plan = DB::transaction(function () use ($user, $name, $periodFrom, $periodTo, $goalType, $goalAmount) {
            return BudgetPlan::query()->create([
                'user_id' => $user->id,
                'name' => $name,
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'goal_type' => $goalType,
                'goal_amount' => $goalAmount,
            ]);
        });

        return [
            'created_plan_id' => $plan->id,
            'name' => $plan->name,
            'period_from' => $plan->period_from,
            'period_to' => $plan->period_to,
        ];
    }
}
