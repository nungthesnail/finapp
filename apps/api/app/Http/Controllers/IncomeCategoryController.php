<?php

namespace App\Http\Controllers;

use App\Models\IncomeCategory;
use Illuminate\Http\Request;

class IncomeCategoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->requireUser($request);

        return response()->json([
            'items' => IncomeCategory::query()->where('user_id', $user->id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->requireUser($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $item = IncomeCategory::query()->create($data + ['user_id' => $user->id]);

        return response()->json(['item' => $item], 201);
    }

    public function update(Request $request, int $id)
    {
        $user = $this->requireUser($request);
        $row = IncomeCategory::query()->where('user_id', $user->id)->findOrFail($id);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $row->fill($data)->save();

        return response()->json(['item' => $row]);
    }

    public function destroy(Request $request, int $id)
    {
        $user = $this->requireUser($request);
        $row = IncomeCategory::query()->where('user_id', $user->id)->findOrFail($id);
        $row->delete();

        return response()->json(['ok' => true]);
    }
}

