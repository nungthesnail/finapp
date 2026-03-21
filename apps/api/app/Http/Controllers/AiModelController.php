<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiModelController extends Controller
{
    public function index()
    {
        $items = AiModel::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function adminIndex(Request $request)
    {
        $this->requireAdmin($request);

        return response()->json(['items' => AiModel::query()->orderBy('code')->get()]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:ai_models,code'],
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'max:64'],
            'is_active' => ['boolean'],
            'supports_tools' => ['boolean'],
            'input_cost_per_1k' => ['required', 'numeric', 'gte:0'],
            'output_cost_per_1k' => ['required', 'numeric', 'gte:0'],
            'cached_input_cost_per_1k' => ['nullable', 'numeric', 'gte:0'],
        ]);

        $item = AiModel::query()->create([
            ...$data,
            'is_active' => $data['is_active'] ?? true,
            'supports_tools' => $data['supports_tools'] ?? true,
            'cached_input_cost_per_1k' => $data['cached_input_cost_per_1k'] ?? 0,
        ]);

        return response()->json(['item' => $item], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->requireAdmin($request);
        $item = AiModel::query()->findOrFail($id);
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:64', Rule::unique('ai_models', 'code')->ignore($item->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'provider' => ['sometimes', 'required', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
            'supports_tools' => ['sometimes', 'boolean'],
            'input_cost_per_1k' => ['sometimes', 'required', 'numeric', 'gte:0'],
            'output_cost_per_1k' => ['sometimes', 'required', 'numeric', 'gte:0'],
            'cached_input_cost_per_1k' => ['sometimes', 'nullable', 'numeric', 'gte:0'],
        ]);

        $item->fill($data)->save();

        return response()->json(['item' => $item]);
    }
}

