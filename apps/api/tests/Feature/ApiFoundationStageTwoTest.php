<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\BudgetPlan;
use App\Models\CreditLedgerEntry;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Subscription as SubscriptionModel;
use App\Models\Subscription;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiFoundationStageTwoTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_login_accounts_transactions_and_analytics_flow(): void
    {
        $this->postJson('/api/auth/register', [
            'phone' => '+79995556677',
            'email' => 'stage2-user@example.com',
            'password' => 'secret123',
        ])->assertCreated();

        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.phone', '+79995556677');

        $account = $this->postJson('/api/accounts', [
            'name' => 'Main',
            'type' => 'card',
            'currency' => 'RUB',
            'balance' => 10000,
        ])->assertCreated();
        $accountId = $account->json('item.id');

        $this->postJson('/api/transactions', [
            'type' => 'income',
            'account_id' => $accountId,
            'amount' => 5000,
            'description' => 'income',
        ])->assertCreated();

        $this->postJson('/api/transactions', [
            'type' => 'expense',
            'account_id' => $accountId,
            'amount' => 1500,
            'description' => 'expense',
        ])->assertCreated();

        $this->getJson('/api/transactions?type=income')
            ->assertOk()
            ->assertJsonCount(1, 'items');

        $summary = $this->getJson('/api/analytics/summary')->assertOk();
        $summary->assertJsonPath('income_total', 5000);
        $summary->assertJsonPath('expense_total', 1500);
        $summary->assertJsonPath('net_total', 3500);
    }

    public function test_security_headers_are_present_on_api_responses(): void
    {
        $response = $this->getJson('/api/health')->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
    }

    public function test_rbac_for_admin_endpoint(): void
    {
        $user = User::factory()->create([
            'phone' => '+79990001111',
            'email' => 'u@example.com',
            'role' => 'USER',
            'password' => bcrypt('pwd12345'),
        ]);

        $this->withSession(['uid' => $user->id])
            ->getJson('/api/admin/users')
            ->assertForbidden();

        $admin = User::factory()->create([
            'phone' => '+79990002222',
            'email' => 'admin2@example.com',
            'role' => 'ADMIN',
            'password' => bcrypt('pwd12345'),
        ]);

        $this->withSession(['uid' => $admin->id])
            ->getJson('/api/admin/users')
            ->assertOk();
    }

    public function test_ai_chat_streaming_endpoint(): void
    {
        $this->postJson('/api/auth/register', [
            'phone' => '+79996667788',
            'email' => 'chat-user@example.com',
            'password' => 'secret123',
        ])->assertCreated();

        $chat = $this->postJson('/api/ai/chats', [
            'title' => 'My chat',
            'selected_model' => 'gpt-4.1-mini',
        ])->assertCreated();

        $chatId = $chat->json('item.id');
        $this->assertNotNull($chatId);

        $response = $this->postJson("/api/ai/chats/{$chatId}/messages/stream", [
            'message' => 'How can I optimize spending?',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('content-type'));

        $list = $this->getJson("/api/ai/chats/{$chatId}/messages")->assertOk();
        $this->assertCount(2, $list->json('items'));
    }

    public function test_multiple_user_chats_and_last_active_behavior(): void
    {
        $this->postJson('/api/auth/register', [
            'phone' => '+79990001122',
            'email' => 'multichat@example.com',
            'password' => 'secret123',
        ])->assertCreated();

        $chatA = $this->postJson('/api/ai/chats', ['title' => 'Chat A'])->assertCreated()->json('item.id');
        $chatB = $this->postJson('/api/ai/chats', ['title' => 'Chat B'])->assertCreated()->json('item.id');

        $this->postJson("/api/ai/chats/{$chatA}/messages/stream", ['message' => 'first'])->assertOk();
        $this->postJson("/api/ai/chats/{$chatB}/messages/stream", ['message' => 'second'])->assertOk();

        $list = $this->getJson('/api/ai/chats')->assertOk()->json('items');
        $this->assertCount(2, $list);
        $this->assertSame($chatB, $list[0]['id']);

        $last = $this->getJson('/api/ai/chats/last-active')->assertOk()->json('item.id');
        $this->assertSame($chatB, $last);
    }

    public function test_technical_chat_is_hidden_from_user_chat_list(): void
    {
        $this->postJson('/api/auth/register', [
            'phone' => '+79990003344',
            'email' => 'techchat@example.com',
            'password' => 'secret123',
        ])->assertCreated();

        $me = $this->getJson('/api/me')->assertOk()->json('user');
        $userId = $me['id'];

        BudgetPlan::query()->create([
            'user_id' => $userId,
            'name' => 'Active plan',
            'period_from' => now()->subDay()->toDateString(),
            'period_to' => now()->addDay()->toDateString(),
            'goal_type' => 'savings',
            'goal_amount' => 1000,
        ]);

        $this->artisan('app:ai-control-plan')->assertExitCode(0);

        $techCount = AiConversation::query()
            ->where('user_id', $userId)
            ->where('is_technical', true)
            ->count();
        $this->assertGreaterThan(0, $techCount);
        $this->assertGreaterThan(0, Notification::query()->where('user_id', $userId)->count());

        $list = $this->getJson('/api/ai/chats')->assertOk()->json('items');
        foreach ($list as $chat) {
            $this->assertFalse((bool) $chat['is_technical']);
        }
    }

    public function test_yoomoney_checkout_and_webhook_processing_without_live_gateway(): void
    {
        config()->set('services.yoomoney.enabled', false);
        config()->set('services.yoomoney.webhook_secret', 'local-secret');
        config()->set('billing.credit_rate', 1.5);

        $this->postJson('/api/auth/register', [
            'phone' => '+79991110000',
            'email' => 'billing-user@example.com',
            'password' => 'secret123',
        ])->assertCreated();

        $tariff = Tariff::query()->create([
            'name' => 'Billing Test',
            'description' => 'test',
            'duration_days' => 30,
            'price_rub' => 1000,
            'is_active' => true,
        ]);

        $checkout = $this->postJson('/api/subscriptions/checkout', [
            'tariff_id' => $tariff->id,
            'return_url' => 'https://example.com/return',
        ])->assertCreated();

        $paymentId = (int) $checkout->json('payment.id');
        $providerPaymentId = (string) $checkout->json('payment.provider_payment_id');
        $this->assertNotEmpty($providerPaymentId);

        $payload = [
            'event' => 'payment.succeeded',
            'object' => [
                'id' => $providerPaymentId,
                'status' => 'succeeded',
                'metadata' => [
                    'payment_id' => $paymentId,
                ],
            ],
        ];

        $this->postJson(
            '/api/payments/yoomoney/webhook',
            $payload,
            ['X-Yoomoney-Webhook-Secret' => 'local-secret']
        )->assertOk();

        $payment = Payment::query()->findOrFail($paymentId);
        $this->assertSame('succeeded', $payment->status);
        $this->assertNotNull($payment->processed_at);

        $this->assertDatabaseCount('subscriptions', 1);
        $subscription = Subscription::query()->firstOrFail();
        $this->assertSame($tariff->id, $subscription->tariff_id);

        $this->assertDatabaseCount('credit_ledger', 1);
        $entry = CreditLedgerEntry::query()->firstOrFail();
        $this->assertEquals(1500.0, (float) $entry->amount);

        // idempotent webhook re-delivery must not duplicate effects
        $this->postJson(
            '/api/payments/yoomoney/webhook',
            $payload,
            ['X-Yoomoney-Webhook-Secret' => 'local-secret']
        )->assertOk();

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('credit_ledger', 1);
    }

    public function test_notifications_read_and_push_subscription_flow(): void
    {
        $this->postJson('/api/auth/register', [
            'phone' => '+79992223344',
            'email' => 'notify-user@example.com',
            'password' => 'secret123',
        ])->assertCreated();

        $userId = (int) $this->getJson('/api/me')->assertOk()->json('user.id');
        $notification = Notification::query()->create([
            'user_id' => $userId,
            'type' => 'system',
            'title' => 'Test',
            'content' => 'Notification content',
        ]);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'items');

        $this->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('item.id', $notification->id);

        $this->assertNotNull(Notification::query()->findOrFail($notification->id)->read_at);

        $this->postJson('/api/push/subscriptions', [
            'endpoint' => 'https://example.push.local/subscription/1',
            'keys' => [
                'p256dh' => 'p-key',
                'auth' => 'a-key',
            ],
        ])->assertCreated();

        $this->assertDatabaseCount('device_subscriptions', 1);
    }

    public function test_support_chat_user_admin_flow(): void
    {
        $this->postJson('/api/auth/register', [
            'phone' => '+79993334455',
            'email' => 'support-user@example.com',
            'password' => 'secret123',
        ])->assertCreated();
        $userId = (int) $this->getJson('/api/me')->assertOk()->json('user.id');

        $chatId = (int) $this->postJson('/api/support/chats', ['message' => 'Need help'])
            ->assertCreated()
            ->json('item.id');
        $this->postJson("/api/support/chats/{$chatId}/messages", ['content' => 'Any update?'])->assertCreated();

        $admin = User::factory()->create([
            'phone' => '+79994445566',
            'email' => 'support-admin@example.com',
            'role' => 'ADMIN',
            'password' => bcrypt('pwd12345'),
        ]);

        $this->withSession(['uid' => $admin->id])
            ->getJson('/api/admin/support/chats')
            ->assertOk()
            ->assertJsonCount(1, 'items');

        $this->withSession(['uid' => $admin->id])
            ->postJson("/api/admin/support/chats/{$chatId}/messages", ['content' => 'We are checking this now'])
            ->assertCreated();

        $items = $this->withSession(['uid' => $userId])
            ->getJson("/api/support/chats/{$chatId}/messages")
            ->assertOk()
            ->json('items');

        $this->assertCount(3, $items);
        $this->assertSame('ADMIN', $items[2]['sender_role']);
        $this->assertDatabaseCount('support_chats', 1);
        $this->assertDatabaseCount('support_messages', 3);
    }

    public function test_admin_actions_write_audit_log(): void
    {
        $admin = User::factory()->create([
            'phone' => '+79995550000',
            'email' => 'admin-actions@example.com',
            'role' => 'ADMIN',
            'password' => bcrypt('pwd12345'),
        ]);
        $target = User::factory()->create([
            'phone' => '+79995550001',
            'email' => 'target@example.com',
            'role' => 'USER',
            'password' => bcrypt('pwd12345'),
        ]);
        $tariff = Tariff::query()->create([
            'name' => 'Cancelable',
            'description' => 'x',
            'duration_days' => 30,
            'price_rub' => 500,
            'is_active' => true,
        ]);
        $subscription = SubscriptionModel::query()->create([
            'user_id' => $target->id,
            'tariff_id' => $tariff->id,
            'status' => 'active',
            'start_at' => now(),
            'end_at' => now()->addDays(30),
        ]);

        $this->withSession(['uid' => $admin->id])
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure(['stats', 'tariffs', 'subscriptions', 'payments', 'usage']);

        $this->withSession(['uid' => $admin->id])
            ->postJson("/api/admin/subscriptions/{$subscription->id}/cancel")
            ->assertOk();

        $this->withSession(['uid' => $admin->id])
            ->postJson("/api/admin/users/{$target->id}/credit-adjustment", [
                'amount' => 250.50,
                'description' => 'Manual bonus',
            ])->assertCreated();

        $this->assertSame('canceled', SubscriptionModel::query()->findOrFail($subscription->id)->status);
        $this->assertDatabaseCount('credit_ledger', 1);
        $this->assertDatabaseCount('audit_logs', 2);

        $this->withSession(['uid' => $admin->id])
            ->getJson('/api/admin/audit-logs')
            ->assertOk()
            ->assertJsonCount(2, 'items');
    }
}
