<?php

namespace App\Http\Controllers;

use App\Models\CreditLedgerEntry;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tariff;
use App\Services\Billing\YooMoneyClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function __construct(private readonly YooMoneyClient $yooMoneyClient)
    {
    }

    public function tariffs()
    {
        $items = Tariff::query()
            ->where('is_active', true)
            ->orderBy('price_rub')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function checkout(Request $request)
    {
        $user = $this->requireUser($request);
        $data = $request->validate([
            'tariff_id' => ['required', 'integer'],
            'return_url' => ['nullable', 'url'],
        ]);

        $tariff = Tariff::query()->where('is_active', true)->findOrFail((int) $data['tariff_id']);
        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'tariff_id' => $tariff->id,
            'provider' => 'yoomoney',
            'idempotence_key' => (string) Str::uuid(),
            'amount_rub' => $tariff->price_rub,
            'currency' => 'RUB',
            'status' => 'pending',
        ]);

        $result = $this->yooMoneyClient->createPayment(
            $payment,
            $tariff,
            $user,
            $data['return_url'] ?? (string) config('app.url')
        );

        $payment->provider_payment_id = $result['id'] ?? null;
        $payment->status = $result['status'] ?? 'pending';
        $payment->confirmation_url = $result['confirmation']['confirmation_url'] ?? null;
        $payment->provider_payload = $result;
        $payment->save();

        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'provider_payment_id' => $payment->provider_payment_id,
                'status' => $payment->status,
                'confirmation_url' => $payment->confirmation_url,
                'amount_rub' => $payment->amount_rub,
            ],
        ], 201);
    }

    public function webhook(Request $request)
    {
        $secret = (string) config('services.yoomoney.webhook_secret', '');
        if ($secret !== '') {
            $incoming = (string) $request->header('X-Yoomoney-Webhook-Secret', '');
            if (!hash_equals($secret, $incoming)) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        $payload = $request->all();
        $object = $payload['object'] ?? [];
        if (!is_array($object)) {
            return response()->json(['ok' => true]);
        }

        $providerPaymentId = (string) ($object['id'] ?? '');
        if ($providerPaymentId === '') {
            return response()->json(['ok' => true]);
        }

        $payment = Payment::query()->where('provider_payment_id', $providerPaymentId)->first();
        if (!$payment && isset($object['metadata']['payment_id'])) {
            $payment = Payment::query()->find((int) $object['metadata']['payment_id']);
        }
        if (!$payment) {
            return response()->json(['ok' => true]);
        }

        $payment->provider_payload = $payload;
        $payment->status = (string) ($object['status'] ?? $payment->status);
        $payment->save();

        if ($payment->status === 'succeeded') {
            $this->finalizeSucceededPayment($payment);
        }

        return response()->json(['ok' => true]);
    }

    private function finalizeSucceededPayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->processed_at !== null) {
                return;
            }

            $tariff = Tariff::query()->findOrFail($locked->tariff_id);
            $now = now();

            $active = Subscription::query()
                ->where('user_id', $locked->user_id)
                ->where('status', 'active')
                ->where('end_at', '>=', $now)
                ->orderByDesc('end_at')
                ->first();

            $startAt = $active ? $active->end_at->copy() : $now->copy();
            $endAt = $startAt->copy()->addDays((int) $tariff->duration_days);

            Subscription::query()->create([
                'user_id' => $locked->user_id,
                'tariff_id' => $tariff->id,
                'status' => 'active',
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]);

            $creditRate = (float) config('billing.credit_rate', 1.0);
            $creditAmount = round((float) $locked->amount_rub * $creditRate, 2);

            CreditLedgerEntry::query()->create([
                'user_id' => $locked->user_id,
                'payment_id' => $locked->id,
                'entry_type' => 'credit',
                'amount' => $creditAmount,
                'currency' => 'RUB',
                'description' => 'Subscription payment credits',
            ]);

            $locked->paid_at = $now;
            $locked->processed_at = $now;
            $locked->status = 'succeeded';
            $locked->save();
        });
    }
}
