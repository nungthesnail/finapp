<?php

namespace App\Http\Controllers;

use App\Models\SupportChat;
use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportController extends Controller
{
    public function userChats(Request $request)
    {
        $user = $this->requireUser($request);
        $items = SupportChat::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function createUserChat(Request $request)
    {
        $user = $this->requireUser($request);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($user, $data) {
            $chat = SupportChat::query()->create([
                'user_id' => $user->id,
                'subject' => $data['subject'],
                'status' => 'open',
                'last_message_at' => now(),
            ]);

            return response()->json(['item' => $chat], 201);
        });
    }

    public function userMessages(Request $request, int $chatId)
    {
        $user = $this->requireUser($request);
        $chat = SupportChat::query()->where('user_id', $user->id)->findOrFail($chatId);

        $items = SupportMessage::query()
            ->where('chat_id', $chat->id)
            ->orderBy('id')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function sendUserMessage(Request $request, int $chatId)
    {
        $user = $this->requireUser($request);
        $chat = SupportChat::query()->where('user_id', $user->id)->findOrFail($chatId);
        $data = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
        ]);

        $message = DB::transaction(function () use ($user, $chat, $data) {
            $message = SupportMessage::query()->create([
                'chat_id' => $chat->id,
                'sender_user_id' => $user->id,
                'sender_role' => 'USER',
                'content' => $data['content'],
            ]);

            $chat->last_message_at = now();
            $chat->save();

            return $message;
        });

        return response()->json(['item' => $message], 201);
    }

    public function adminChats(Request $request)
    {
        $this->requireAdmin($request);
        $items = SupportChat::query()
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json(['items' => $items]);
    }

    public function adminMessages(Request $request, int $chatId)
    {
        $this->requireAdmin($request);
        $chat = SupportChat::query()->findOrFail($chatId);
        $items = SupportMessage::query()
            ->where('chat_id', $chat->id)
            ->orderBy('id')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function sendAdminMessage(Request $request, int $chatId)
    {
        $admin = $this->requireAdmin($request);
        $chat = SupportChat::query()->findOrFail($chatId);
        $data = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $message = DB::transaction(function () use ($admin, $chat, $data) {
            $message = SupportMessage::query()->create([
                'chat_id' => $chat->id,
                'sender_user_id' => $admin->id,
                'sender_role' => 'ADMIN',
                'content' => $data['content'],
            ]);

            if (!empty($data['status'])) {
                $chat->status = $data['status'];
            }
            $chat->last_message_at = now();
            $chat->save();

            return $message;
        });

        return response()->json(['item' => $message], 201);
    }
}
