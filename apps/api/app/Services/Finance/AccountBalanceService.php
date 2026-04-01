<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\Transaction;

class AccountBalanceService
{
    public function recalculate(Account $account): void
    {
        $transactions = Transaction::query()
            ->where('account_id', $account->id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['id', 'type', 'amount']);

        $balance = 0.0;
        $lastTransactionId = null;

        foreach ($transactions as $transaction) {
            $amount = (float) $transaction->amount;
            $balance += $transaction->type === 'expense' ? -$amount : $amount;
            $lastTransactionId = $transaction->id;
        }

        $account->balance = round($balance, 2);
        $account->balance_calculated_to_transaction_id = $lastTransactionId;
        $account->save();
    }
}
