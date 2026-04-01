<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            if (!Schema::hasColumn('accounts', 'balance_calculated_to_transaction_id')) {
                $table->unsignedBigInteger('balance_calculated_to_transaction_id')->nullable()->after('balance')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('accounts', 'balance_calculated_to_transaction_id')) {
                $table->dropColumn('balance_calculated_to_transaction_id');
            }
        });
    }
};
