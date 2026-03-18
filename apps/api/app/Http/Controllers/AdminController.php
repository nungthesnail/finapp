<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CreditLedgerEntry;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function users(Request $request)
    {
        $this->requireAdmin($request);

        return response()->json([
            'items' => User::query()
                ->select('id', 'phone', 'email', 'role', 'created_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function dashboard(Request $request)
    {
        $this->requireAdmin($request);

        $stats = [
            'users_total' => User::query()->count(),
            'tariffs_total' => Tariff::query()->count(),
            'subscriptions_active' => Subscription::query()->where('status', 'active')->count(),
            'payments_succeeded' => Payment::query()->where('status', 'succeeded')->count(),
            'credits_total' => (float) CreditLedgerEntry::query()->sum('amount'),
        ];

        return response()->json([
            'stats' => $stats,
            'tariffs' => Tariff::query()->orderBy('price_rub')->get(),
            'subscriptions' => Subscription::query()->latest()->limit(50)->get(),
            'payments' => Payment::query()->latest()->limit(50)->get(),
            'usage' => CreditLedgerEntry::query()->latest()->limit(100)->get(),
        ]);
    }

    public function cancelSubscription(Request $request, int $subscriptionId)
    {
        $admin = $this->requireAdmin($request);
        $subscription = Subscription::query()->findOrFail($subscriptionId);

        DB::transaction(function () use ($admin, $subscription): void {
            $subscription->status = 'canceled';
            $subscription->canceled_at = now();
            $subscription->save();

            $this->writeAudit(
                $admin->id,
                'subscription.cancel',
                'subscription',
                (string) $subscription->id,
                ['status' => $subscription->status]
            );
        });

        return response()->json(['item' => $subscription]);
    }

    public function adjustCredit(Request $request, int $userId)
    {
        $admin = $this->requireAdmin($request);
        $target = User::query()->findOrFail($userId);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = DB::transaction(function () use ($admin, $target, $data) {
            $entry = CreditLedgerEntry::query()->create([
                'user_id' => $target->id,
                'payment_id' => null,
                'entry_type' => 'adjustment',
                'amount' => $data['amount'],
                'currency' => 'RUB',
                'description' => $data['description'] ?? 'Admin adjustment',
            ]);

            $this->writeAudit(
                $admin->id,
                'credit.adjust',
                'user',
                (string) $target->id,
                ['entry_id' => $entry->id, 'amount' => (float) $entry->amount]
            );

            return $entry;
        });

        return response()->json(['item' => $entry], 201);
    }

    public function auditLogs(Request $request)
    {
        $this->requireAdmin($request);
        $items = AuditLog::query()->latest()->limit(200)->get();
        return response()->json(['items' => $items]);
    }

    private function writeAudit(
        int $actorUserId,
        string $action,
        ?string $entityType,
        ?string $entityId,
        ?array $payload
    ): void {
        AuditLog::query()->create([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload,
        ]);
    }
}

