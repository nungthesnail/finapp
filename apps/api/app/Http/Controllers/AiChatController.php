<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiModel;
use App\Services\Ai\AiBillingService;
use App\Services\Ai\AiChatLoopService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AiChatController extends Controller
{
    public function __construct(
        private readonly AiBillingService $billingService,
        private readonly AiChatLoopService $chatLoopService
    ) {
    }

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

        $selectedModel = $this->resolveModelCode($data['selected_model'] ?? null);

        $conversation = AiConversation::query()->create([
            'user_id' => $user->id,
            'title' => $data['title'] ?? 'New chat',
            'selected_model' => $selectedModel,
            'is_technical' => false,
            'last_active_at' => now(),
        ]);

        $this->ensureSystemMessage($conversation->id, $user->id, $selectedModel);

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
                'selected_model' => $this->resolveModelCode(null),
                'is_technical' => false,
                'last_active_at' => now(),
            ]);
        }

        $this->ensureSystemMessage($conversation->id, $user->id, (string) $conversation->selected_model);

        $messages = AiMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('is_hidden', false)
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

        $selectedModelCode = $this->resolveModelCode($data['model'] ?? $conversation->selected_model);
        $model = AiModel::query()
            ->where('code', $selectedModelCode)
            ->where('is_active', true)
            ->firstOrFail();

        $this->ensureSystemMessage($conversation->id, $user->id, $model->code);
        $history = $this->buildHistoryForModel($conversation->id);
        $history[] = ['role' => 'user', 'content' => $data['message']];

        try {
            $result = $this->chatLoopService->run($user, $model, $history);

            $userMessage = null;
            $assistantMessage = null;
            $usageSummary = null;

            DB::transaction(function () use (
                $user,
                $conversation,
                $data,
                $model,
                $result,
                &$userMessage,
                &$assistantMessage,
                &$usageSummary
            ): void {
                $userMessage = AiMessage::query()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'role' => 'user',
                    'content' => $data['message'],
                    'model' => $model->code,
                    'is_hidden' => false,
                ]);

                $assistantMessage = AiMessage::query()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'role' => 'assistant',
                    'content' => $result['assistant_text'],
                    'model' => $model->code,
                    'is_hidden' => false,
                ]);

                $usageSummary = $this->billingService->chargeAndLog(
                    $user,
                    $conversation->id,
                    $assistantMessage,
                    $model,
                    $result['input_tokens'],
                    $result['output_tokens'],
                    $result['cached_input_tokens'],
                    $result['meta']
                );

                $conversation->update([
                    'selected_model' => $model->code,
                    'last_active_at' => now(),
                ]);
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Insufficient credits') {
                return response()->json(['error' => 'Insufficient credits'], 402);
            }

            throw $e;
        }

        $chunks = $this->toChunks((string) $assistantMessage->content, 36);

        return response()->stream(function () use ($chunks, $assistantMessage, $userMessage, $usageSummary): void {
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
                'usage' => $usageSummary,
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
            ->where('is_hidden', false)
            ->orderBy('id')
            ->get();

        return response()->json(['items' => $messages]);
    }

    /**
     * @return array<int,array{role:string,content:string}>
     */
    private function buildHistoryForModel(int $conversationId): array
    {
        /** @var array<int,array{role:string,content:string}> $messages */
        $messages = AiMessage::query()
            ->where('conversation_id', $conversationId)
            ->orderBy('id')
            ->get(['role', 'content'])
            ->map(fn ($m) => ['role' => (string) $m->role, 'content' => (string) $m->content])
            ->all();

        return $messages;
    }

    private function ensureSystemMessage(int $conversationId, int $userId, string $modelCode): void
    {
        $exists = AiMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'system')
            ->exists();
        if ($exists) {
            return;
        }

        $prompt = $this->buildSystemPrompt();
        AiMessage::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'role' => 'system',
            'content' => $prompt,
            'model' => $modelCode,
            'is_hidden' => true,
        ]);
    }

    private function buildSystemPrompt(): string
    {
        $base = (string) config('ai.system_prompt');
        $toolList = [
            'financial_summary()',
            'forecast_30d()',
            'create_transaction(type, amount, account_id, category_id?, description?)',
            'create_budget_plan(name, period_from, period_to, goal_type, goal_amount?)',
        ];

        return $base . "\nAvailable tools:\n- " . implode("\n- ", $toolList);
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

    private function resolveModelCode(?string $requested): string
    {
        $requested = $requested ?: (string) config('ai.default_model', 'gpt-4.1-mini');

        $exists = AiModel::query()
            ->where('code', $requested)
            ->where('is_active', true)
            ->exists();
        if ($exists) {
            return $requested;
        }

        $fallback = AiModel::query()->where('is_active', true)->orderBy('id')->value('code');

        return is_string($fallback) && $fallback !== '' ? $fallback : 'gpt-4.1-mini';
    }
}
