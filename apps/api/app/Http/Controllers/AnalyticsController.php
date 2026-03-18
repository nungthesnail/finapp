<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{
    public function summary(Request $request)
    {
        $user = $this->requireUser($request);
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
    }

    public function timeseries(Request $request)
    {
        $user = $this->requireUser($request);
        $rows = Transaction::query()
            ->where('user_id', $user->id)
            ->selectRaw('DATE(occurred_at) as d, type, SUM(amount) as total')
            ->groupByRaw('DATE(occurred_at), type')
            ->orderBy('d')
            ->get();

        return response()->json(['items' => $rows]);
    }

    public function categories(Request $request)
    {
        $user = $this->requireUser($request);
        $rows = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'expense')
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();

        return response()->json(['items' => $rows]);
    }
}

