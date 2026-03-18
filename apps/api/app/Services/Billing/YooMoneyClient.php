<?php

namespace App\Services\Billing;

use App\Models\Payment;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class YooMoneyClient
{
    public function createPayment(Payment $payment, Tariff $tariff, User $user, string $returnUrl): array
    {
        $enabled = (bool) config('services.yoomoney.enabled', false);
        if (!$enabled) {
            return [
                'id' => 'sandbox-' . $payment->id,
                'status' => 'pending',
                'confirmation' => [
                    'confirmation_url' => 'https://yoomoney.ru/sandbox/checkout/' . $payment->id,
                ],
            ];
        }

        $shopId = (string) config('services.yoomoney.shop_id');
        $secretKey = (string) config('services.yoomoney.secret_key');
        $baseUrl = rtrim((string) config('services.yoomoney.base_url', 'https://api.yookassa.ru/v3'), '/');

        $payload = [
            'amount' => [
                'value' => number_format((float) $payment->amount_rub, 2, '.', ''),
                'currency' => 'RUB',
            ],
            'capture' => true,
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $returnUrl,
            ],
            'description' => sprintf('FinWise tariff %s (%d days)', $tariff->name, $tariff->duration_days),
            'metadata' => [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
                'tariff_id' => $tariff->id,
            ],
        ];

        $response = Http::withBasicAuth($shopId, $secretKey)
            ->withHeaders([
                'Idempotence-Key' => $payment->idempotence_key,
            ])
            ->post($baseUrl . '/payments', $payload);

        if (!$response->successful()) {
            throw new RuntimeException('YooMoney create payment failed: ' . $response->status());
        }

        /** @var array $json */
        $json = $response->json();
        return $json;
    }
}

