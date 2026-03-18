<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
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
            'balance' => ['required', 'numeric'],
        ]);

        $item = Account::query()->create($data + ['user_id' => $user->id]);

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
            'balance' => ['sometimes', 'required', 'numeric'],
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
}

