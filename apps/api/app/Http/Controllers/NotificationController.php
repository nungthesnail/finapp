<?php

namespace App\Http\Controllers;

use App\Models\DeviceSubscription;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->requireUser($request);
        $items = Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json(['items' => $items]);
    }

    public function markRead(Request $request, int $id)
    {
        $user = $this->requireUser($request);
        $item = Notification::query()
            ->where('user_id', $user->id)
            ->findOrFail($id);

        if ($item->read_at === null) {
            $item->read_at = now();
            $item->save();
        }

        return response()->json(['item' => $item]);
    }

    public function savePushSubscription(Request $request)
    {
        $user = $this->requireUser($request);
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2048'],
            'keys' => ['nullable', 'array'],
            'keys.p256dh' => ['nullable', 'string', 'max:1024'],
            'keys.auth' => ['nullable', 'string', 'max:1024'],
        ]);

        $item = DeviceSubscription::query()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $user->id,
                'p256dh' => $data['keys']['p256dh'] ?? null,
                'auth' => $data['keys']['auth'] ?? null,
                'raw' => $request->all(),
                'is_active' => true,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['item' => $item], 201);
    }
}

