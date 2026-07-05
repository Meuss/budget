<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Internal transfer (e.g. paying the credit-card bill). Excluded from
            // income, spending AND savings — mirrors is_savings but counts nowhere.
            $table->boolean('is_transfer')->default(false)->after('is_savings');
            $table->index('is_transfer');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['is_transfer']);
            $table->dropColumn('is_transfer');
        });
    }
};
