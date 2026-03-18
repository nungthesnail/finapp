<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function requireUser(Request $request): User
    {
        $uid = (int) $request->session()->get('uid', 0);
        $user = User::find($uid);
        if (!$user) {
            abort(response()->json(['error' => 'Unauthorized'], 401));
        }

        return $user;
    }

    protected function requireAdmin(Request $request): User
    {
        $user = $this->requireUser($request);
        if ($user->role !== 'ADMIN') {
            abort(response()->json(['error' => 'Forbidden'], 403));
        }

        return $user;
    }
}
