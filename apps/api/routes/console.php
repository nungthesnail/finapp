<?php

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\BudgetPlan;
use App\Models\Notification;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
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

Artisan::command('app:ai-control-plan', function () {
    $processed = 0;

    User::query()->chunk(100, function ($users) use (&$processed): void {
        foreach ($users as $user) {
            $activePlan = BudgetPlan::query()
                ->where('user_id', $user->id)
                ->whereDate('period_from', '<=', now()->toDateString())
                ->whereDate('period_to', '>=', now()->toDateString())
                ->latest('id')
                ->first();

            if (!$activePlan) {
                continue;
            }

            $income = (float) Transaction::query()->where('user_id', $user->id)->where('type', 'income')->sum('amount');
            $expense = (float) Transaction::query()->where('user_id', $user->id)->where('type', 'expense')->sum('amount');
            $net = $income - $expense;
            $target = (float) ($activePlan->goal_amount ?? 0);

            $text = sprintf(
                'Plan control: plan=%s period=%s..%s net=%.2f target=%.2f status=%s',
                $activePlan->name,
                $activePlan->period_from,
                $activePlan->period_to,
                $net,
                $target,
                ($target > 0 && $net >= $target) ? 'on_track' : 'attention'
            );

            $chat = AiConversation::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'is_technical' => true,
                    'title' => 'Technical: Plan Control',
                ],
                [
                    'selected_model' => 'gpt-4.1-mini',
                    'last_active_at' => now(),
                ]
            );

            AiMessage::query()->create([
                'conversation_id' => $chat->id,
                'user_id' => $user->id,
                'role' => 'assistant',
                'content' => $text,
                'model' => 'gpt-4.1-mini',
            ]);

            Notification::query()->create([
                'user_id' => $user->id,
                'type' => 'ai_plan_control',
                'title' => 'AI plan control update',
                'content' => $text,
                'meta' => [
                    'plan_id' => $activePlan->id,
                    'chat_id' => $chat->id,
                ],
            ]);

            $chat->last_active_at = now();
            $chat->save();
            $processed++;
        }
    });

    $this->info("AI plan control processed users: {$processed}");
})->purpose('Generate technical AI control messages for active budget plans');

app()->booted(function () {
    app(Schedule::class)->command('app:process-recurring')->hourly();
    app(Schedule::class)->command('app:ai-control-plan')->dailyAt('09:00');
});
