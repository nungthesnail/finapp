<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->requireUser($request);

        $items = AiConversation::query()
            ->where('user_id', $user->id)
            ->where('is_technical', false)
            ->orderByDesc('last_active_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function create(Request $request)
    {
        $user = $this->requireUser($request);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'selected_model' => ['nullable', 'string', 'max:64'],
        ]);

        $conversation = AiConversation::query()->create([
            'user_id' => $user->id,
            'title' => $data['title'] ?? 'New chat',
            'selected_model' => $data['selected_model'] ?? 'gpt-4.1-mini',
            'is_technical' => false,
            'last_active_at' => now(),
        ]);

        return response()->json(['item' => $conversation], 201);
    }

    public function lastActive(Request $request)
    {
        $user = $this->requireUser($request);

        $conversation = AiConversation::query()
            ->where('user_id', $user->id)
            ->where('is_technical', false)
            ->orderByDesc('last_active_at')
            ->orderByDesc('id')
            ->first();

        if (!$conversation) {
            $conversation = AiConversation::query()->create([
                'user_id' => $user->id,
                'title' => 'New chat',
                'selected_model' => 'gpt-4.1-mini',
                'is_technical' => false,
                'last_active_at' => now(),
            ]);
        }

        $messages = AiMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'item' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function stream(Request $request, int $conversationId)
    {
        $user = $this->requireUser($request);
        $conversation = AiConversation::query()
            ->where('id', $conversationId)
            ->where('user_id', $user->id)
            ->where('is_technical', false)
            ->firstOrFail();

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'model' => ['nullable', 'string', 'max:64'],
        ]);

        $model = $data['model'] ?? ($conversation->selected_model ?: 'gpt-4.1-mini');

        $userMessage = AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $data['message'],
            'model' => $model,
        ]);

        $assistantText = $this->buildAssistantReply($user, $data['message']);

        $assistantMessage = AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => $assistantText,
            'model' => $model,
        ]);

        $conversation->update([
            'selected_model' => $model,
            'last_active_at' => now(),
        ]);

        $chunks = $this->toChunks($assistantText, 36);

        return response()->stream(function () use ($chunks, $assistantMessage, $userMessage): void {
            echo "event:meta\n";
            echo 'data:' . json_encode(['user_message_id' => $userMessage->id], JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            flush();

            foreach ($chunks as $chunk) {
                echo "event:chunk\n";
                echo 'data:' . json_encode(['text' => $chunk], JSON_UNESCAPED_UNICODE) . "\n\n";
                @ob_flush();
                flush();
                usleep(28000);
            }

            echo "event:done\n";
            echo 'data:' . json_encode([
                'message' => [
                    'id' => $assistantMessage->id,
                    'role' => 'assistant',
                    'content' => $assistantMessage->content,
                    'model' => $assistantMessage->model,
                    'created_at' => $assistantMessage->created_at,
                ],
            ], JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function messages(Request $request, int $conversationId)
    {
        $user = $this->requireUser($request);
        $conversation = AiConversation::query()
            ->where('id', $conversationId)
            ->where('user_id', $user->id)
            ->where('is_technical', false)
            ->firstOrFail();

        $messages = AiMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->get();

        return response()->json(['items' => $messages]);
    }

    private function buildAssistantReply(User $user, string $prompt): string
    {
        $income = (float) Transaction::query()->where('user_id', $user->id)->where('type', 'income')->sum('amount');
        $expense = (float) Transaction::query()->where('user_id', $user->id)->where('type', 'expense')->sum('amount');
        $net = $income - $expense;

        $base = "Based on your data: income={$income}, expense={$expense}, net={$net}. ";

        $advice = $net >= 0
            ? 'You are in positive cash flow. Keep a 10-20% reserve and review largest expense categories weekly.'
            : 'You are in negative cash flow. Reduce non-essential expense categories first and set a strict weekly cap.';

        $followup = ' Next step: ask me for a category-level plan for the next 30 days.';

        return $base . 'About your request: "' . trim($prompt) . '". ' . $advice . $followup;
    }

    /**
     * @return array<int, string>
     */
    private function toChunks(string $text, int $size): array
    {
        $chunks = [];
        $length = mb_strlen($text);
        for ($i = 0; $i < $length; $i += $size) {
            $chunks[] = mb_substr($text, $i, $size);
        }
        return $chunks;
    }
}
