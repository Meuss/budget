<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_no')->unique();      // "No de transaction" — dedupe key
            $table->date('date');                             // booking / transaction date
            $table->date('value_date')->nullable();
            $table->string('currency', 8)->default('CHF');
            $table->decimal('amount', 12, 2);                 // signed: negative = expense, positive = income
            $table->string('direction', 8);                   // debit | credit
            $table->string('merchant')->nullable();           // Description1
            $table->string('type')->nullable();               // Description2
            $table->text('details')->nullable();              // Description3
            $table->decimal('balance', 14, 2)->nullable();    // running account balance (Solde)
            $table->string('category_id')->nullable();        // Statamic categories entry id
            $table->boolean('is_savings')->default(false);    // internal transfer to own savings
            $table->string('source', 16)->default('unclassified'); // manual | rule | unclassified
            $table->json('raw')->nullable();                  // original CSV row
            $table->timestamps();

            $table->index('date');
            $table->index('category_id');
            $table->index('is_savings');
            $table->index('direction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
