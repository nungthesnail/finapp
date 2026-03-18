<?php

namespace App\Http\Controllers;

use App\Models\RecurringTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurringTransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->requireUser($request);
        $items = RecurringTransaction::query()
            ->where('user_id', $user->id)
            ->orderByDesc('next_run_at')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function store(Request $request)
    {
        $user = $this->requireUser($request);
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
    }

    public function update(Request $request, RecurringTransaction $recurringTransaction)
    {
        $user = $this->requireUser($request);
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
    }

    public function destroy(Request $request, RecurringTransaction $recurringTransaction)
    {
        $user = $this->requireUser($request);
        abort_if($recurringTransaction->user_id !== $user->id, 404);
        $recurringTransaction->delete();

        return response()->json(['ok' => true]);
    }
}

