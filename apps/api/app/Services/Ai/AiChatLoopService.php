<?php

namespace App\Services\Ai;

use App\Models\AiModel;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AiChatLoopService
{
    public function __construct(
        private readonly OpenAiClient $openAiClient,
        private readonly AiToolService $toolService
    ) {
    }

    /**
     * @param array<int,array<string,mixed>> $historyMessages
     * @return array{assistant_text:string,input_tokens:int,output_tokens:int,cached_input_tokens:int,meta:array<string,mixed>}
     */
    public function run(User $user, AiModel $model, array $historyMessages): array
    {
        $messages = $historyMessages;
        $inputTokens = 0;
        $outputTokens = 0;
        $toolCalls = [];
        $maxLoops = max(1, (int) config('ai.max_tool_loops', 6));

        for ($i = 0; $i < $maxLoops; $i++) {
            $response = $this->openAiClient->chatCompletions([
                'model' => $model->code,
                'messages' => $messages,
                'tools' => $this->toolDefinitions(),
                'tool_choice' => 'auto',
                'temperature' => 0.2,
            ]);

            $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];
            $inputTokens += (int) ($usage['prompt_tokens'] ?? 0);
            $outputTokens += (int) ($usage['completion_tokens'] ?? 0);

            $choice = $response['choices'][0] ?? null;
            if (!is_array($choice)) {
                return [
                    'assistant_text' => 'No response choices from model.',
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'cached_input_tokens' => 0,
                    'meta' => ['tool_calls' => $toolCalls, 'finish_reason' => 'error'],
                ];
            }

            $finishReason = (string) ($choice['finish_reason'] ?? 'stop');
            $assistantMessage = is_array($choice['message'] ?? null) ? $choice['message'] : [];
            $content = (string) ($assistantMessage['content'] ?? '');

            if ($finishReason === 'tool_calls') {
                $modelToolCalls = $assistantMessage['tool_calls'] ?? [];
                if (!is_array($modelToolCalls) || count($modelToolCalls) === 0) {
                    return [
                        'assistant_text' => 'Model requested tool call without payload.',
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                        'cached_input_tokens' => 0,
                        'meta' => ['tool_calls' => $toolCalls, 'finish_reason' => 'tool_error'],
                    ];
                }

                $messages[] = [
                    'role' => 'assistant',
                    'content' => $content,
                    'tool_calls' => $modelToolCalls,
                ];

                foreach ($modelToolCalls as $call) {
                    $callId = (string) ($call['id'] ?? '');
                    $fn = is_array($call['function'] ?? null) ? $call['function'] : [];
                    $toolName = (string) ($fn['name'] ?? '');
                    $rawArgs = (string) ($fn['arguments'] ?? '{}');
                    $args = json_decode($rawArgs, true);
                    if (!is_array($args)) {
                        $args = [];
                    }

                    try {
                        $result = $this->toolService->executeByName($user, $toolName, $args);
                    } catch (ValidationException $e) {
                        $result = ['error' => $e->getMessage()];
                    }

                    $toolCalls[] = [
                        'id' => $callId,
                        'name' => $toolName,
                        'arguments' => $args,
                        'result' => $result,
                    ];

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $callId,
                        'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                }

                continue;
            }

            return [
                'assistant_text' => $content !== '' ? $content : 'Model returned empty response.',
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cached_input_tokens' => 0,
                'meta' => ['tool_calls' => $toolCalls, 'finish_reason' => $finishReason],
            ];
        }

        return [
            'assistant_text' => 'Tool call loop limit reached.',
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cached_input_tokens' => 0,
            'meta' => ['tool_calls' => $toolCalls, 'finish_reason' => 'loop_limit'],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function toolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'financial_summary',
                    'description' => 'Get current income/expense/net summary for the user',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'forecast_30d',
                    'description' => 'Get simple 30-day forecast based on recent transactions',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_transaction',
                    'description' => 'Create a user transaction',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['income', 'expense']],
                            'amount' => ['type' => 'number'],
                            'account_id' => ['type' => 'integer'],
                            'category_id' => ['type' => 'integer'],
                            'description' => ['type' => 'string'],
                        ],
                        'required' => ['type', 'amount', 'account_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_budget_plan',
                    'description' => 'Create a new budget plan for the user',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'period_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                            'period_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                            'goal_type' => ['type' => 'string', 'enum' => ['savings', 'target_balance']],
                            'goal_amount' => ['type' => 'number'],
                        ],
                    ],
                ],
            ],
        ];
    }
}

