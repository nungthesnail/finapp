<?php

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;

Artisan::command('app:process-recurring', function () {
    $now = now();
    $items = RecurringTransaction::query()
        ->where('is_active', true)
        ->where('next_run_at', '<=', $now)
        ->get();

    $count = 0;
    foreach ($items as $item) {
        Transaction::query()->create([
            'user_id' => $item->user_id,
            'account_id' => $item->account_id,
            'type' => $item->type,
            'category_id' => $item->category_id,
            'amount' => $item->amount,
            'description' => $item->description,
            'occurred_at' => $item->next_run_at,
        ]);

        $next = Carbon::parse($item->next_run_at);
        $next = match ($item->frequency) {
            'daily' => $next->addDay(),
            'weekly' => $next->addWeek(),
            default => $next->addMonth(),
        };

        if ($item->end_at && $next->greaterThan(Carbon::parse($item->end_at))) {
            $item->is_active = false;
        } else {
            $item->next_run_at = $next;
        }
        $item->save();
        $count++;
    }

    $this->info("Processed recurring transactions: {$count}");
})->purpose('Generate transactions from recurring schedules');

app()->booted(function () {
    app(Schedule::class)->command('app:process-recurring')->hourly();
});
