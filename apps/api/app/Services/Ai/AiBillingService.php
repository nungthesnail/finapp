<?php

namespace App\Services\Ai;

use App\Models\AiMessage;
use App\Models\AiModel;
use App\Models\AiUsageLog;
use App\Models\CreditLedgerEntry;
use App\Models\User;
use RuntimeException;

class AiBillingService
{
    /**
     * @return array{input_tokens:int, output_tokens:int, cached_input_tokens:int}
     */
    public function estimateTokenUsage(string $input, string $output): array
    {
        // Rough heuristic suitable for deterministic local simulation.
        $inputTokens = max(1, (int) ceil(mb_strlen($input) / 4));
        $outputTokens = max(1, (int) ceil(mb_strlen($output) / 4));

        return [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cached_input_tokens' => 0,
        ];
    }

    public function calculateCost(AiModel $model, int $inputTokens, int $outputTokens, int $cachedInputTokens = 0): float
    {
        $inputCost = ((float) $model->input_cost_per_1k / 1000) * $inputTokens;
        $outputCost = ((float) $model->output_cost_per_1k / 1000) * $outputTokens;
        $cachedCost = ((float) $model->cached_input_cost_per_1k / 1000) * $cachedInputTokens;

        return round($inputCost + $outputCost + $cachedCost, 6);
    }

    public function getUserBalance(int $userId): float
    {
        return (float) CreditLedgerEntry::query()
            ->where('user_id', $userId)
            ->sum('amount');
    }

    /**
     * @return array{input_tokens:int,output_tokens:int,cached_input_tokens:int,total_cost_rub:float,balance_after_rub:float}
     */
    public function chargeAndLog(
        User $user,
        int $conversationId,
        AiMessage $assistantMessage,
        AiModel $model,
        int $inputTokens,
        int $outputTokens,
        int $cachedInputTokens,
        ?array $meta = null
    ): array {
        User::query()->lockForUpdate()->findOrFail($user->id);

        $cost = $this->calculateCost($model, $inputTokens, $outputTokens, $cachedInputTokens);
        $balance = $this->getUserBalance($user->id);
        if ($balance < $cost) {
            throw new RuntimeException('Insufficient credits');
        }

        if ($cost > 0) {
            CreditLedgerEntry::query()->create([
                'user_id' => $user->id,
                'payment_id' => null,
                'entry_type' => 'ai_debit',
                'amount' => -$cost,
                'currency' => 'RUB',
                'description' => "AI usage: {$model->code}",
            ]);
        }

        AiUsageLog::query()->create([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'message_id' => $assistantMessage->id,
            'model_code' => $model->code,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cached_input_tokens' => $cachedInputTokens,
            'total_cost_rub' => $cost,
            'meta' => $meta,
        ]);

        return [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cached_input_tokens' => $cachedInputTokens,
            'total_cost_rub' => $cost,
            'balance_after_rub' => round($balance - $cost, 6),
        ];
    }
}
