<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\Tariff;
use App\Models\User;
use App\Models\UserCategoryDefault;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['phone' => '+79990000000'],
            [
                'email' => 'admin@finwise.local',
                'role' => 'ADMIN',
                'password' => bcrypt('admin123'),
            ]
        );

        User::query()->firstOrCreate(
            ['phone' => '+79991112233'],
            [
                'email' => 'user1@example.com',
                'role' => 'USER',
                'password' => bcrypt('secret123'),
            ]
        );

        $users = User::query()->get();
        foreach ($users as $user) {
            $exists = UserCategoryDefault::query()->where('user_id', $user->id)->exists();
            if ($exists) {
                continue;
            }

            $income = IncomeCategory::query()->create([
                'user_id' => $user->id,
                'name' => 'Прочие доходы',
            ]);
            $expense = ExpenseCategory::query()->create([
                'user_id' => $user->id,
                'name' => 'Прочие расходы',
            ]);
            UserCategoryDefault::query()->create([
                'user_id' => $user->id,
                'income_category_id' => $income->id,
                'expense_category_id' => $expense->id,
            ]);
        }

        Tariff::query()->firstOrCreate(
            ['name' => 'Start'],
            [
                'description' => 'Base plan',
                'duration_days' => 30,
                'price_rub' => 499,
                'is_active' => true,
            ]
        );

        Tariff::query()->firstOrCreate(
            ['name' => 'Pro'],
            [
                'description' => 'Advanced plan',
                'duration_days' => 30,
                'price_rub' => 999,
                'is_active' => true,
            ]
        );
    }
}
