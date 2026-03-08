<?php

namespace Tests\Feature;

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
}

