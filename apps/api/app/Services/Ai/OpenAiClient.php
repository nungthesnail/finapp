<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiClient
{
    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function chatCompletions(array $payload): array
    {
        $gateway = (string) config('ai.gateway', 'mock');
        if ($gateway !== 'openai') {
            return $this->mockResponse($payload);
        }

        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured');
        }

        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $timeout = (int) config('services.openai.timeout', 45);

        $response = Http::timeout($timeout)
            ->withToken($apiKey)
            ->post("{$baseUrl}/chat/completions", $payload);

        if (!$response->successful()) {
            throw new RuntimeException('OpenAI request failed: ' . $response->status() . ' ' . $response->body());
        }

        /** @var array<string,mixed> $json */
        $json = $response->json();

        return $json;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function mockResponse(array $payload): array
    {
        $messages = $payload['messages'] ?? [];
        $lastUserText = '';
        $hasToolMessage = false;
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                $lastUserText = (string) ($msg['content'] ?? '');
            }
            if (($msg['role'] ?? '') === 'tool') {
                $hasToolMessage = true;
            }
        }

        $promptTokens = max(1, (int) ceil(strlen(json_encode($messages)) / 4));

        if (!$hasToolMessage && str_starts_with(trim($lastUserText), 'tool:')) {
            $directiveName = trim((string) strtok(substr(trim($lastUserText), 5), " \n\t"));
            $jsonPos = strpos($lastUserText, '{');
            $args = '{}';
            if ($jsonPos !== false) {
                $args = (string) substr($lastUserText, $jsonPos);
            }

            return [
                'choices' => [[
                    'finish_reason' => 'tool_calls',
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_mock_1',
                            'type' => 'function',
                            'function' => [
                                'name' => $directiveName,
                                'arguments' => $args,
                            ],
                        ]],
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => 10,
                    'total_tokens' => $promptTokens + 10,
                ],
            ];
        }

        $toolPayload = null;
        foreach (array_reverse($messages) as $msg) {
            if (($msg['role'] ?? '') === 'tool') {
                $toolPayload = (string) ($msg['content'] ?? '');
                break;
            }
        }

        $content = $toolPayload !== null
            ? 'Tool execution completed. Result: ' . $toolPayload
            : 'General advisory response from mock gateway.';

        return [
            'choices' => [[
                'finish_reason' => 'stop',
                'message' => [
                    'role' => 'assistant',
                    'content' => $content,
                ],
            ]],
            'usage' => [
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => max(1, (int) ceil(strlen($content) / 4)),
                'total_tokens' => $promptTokens + max(1, (int) ceil(strlen($content) / 4)),
            ],
        ];
    }
}

