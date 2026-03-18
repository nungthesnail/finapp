<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function me(Request $request)
    {
        return response()->json(['user' => $this->requireUser($request)]);
    }

    public function update(Request $request)
    {
        $user = $this->requireUser($request);
        $data = $request->validate([
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->email = $data['email'];
        $user->save();

        return response()->json(['user' => $user]);
    }
}

