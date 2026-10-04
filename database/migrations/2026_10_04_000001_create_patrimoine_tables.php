<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Patrimoine: manually entered balances, independent of transactions (docs/adr/0002).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrimoine_classes', function (Blueprint $table) {
            $table->id();
            $table->string('title', 60);
            $table->string('description', 160)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('patrimoine_avoirs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('patrimoine_classes')->restrictOnDelete();
            $table->string('title', 60);
            $table->string('description', 160)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->decimal('versement_mensuel', 12, 2)->nullable(); // set = this Avoir tracks Versements
            $table->date('archived_on')->nullable();                  // left out of Relevés from this date
            $table->timestamps();
        });

        Schema::create('patrimoine_releves', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->timestamps();
        });

        Schema::create('patrimoine_releve_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('releve_id')->constrained('patrimoine_releves')->cascadeOnDelete();
            $table->foreignId('avoir_id')->constrained('patrimoine_avoirs')->restrictOnDelete();
            $table->decimal('balance', 14, 2);
            $table->decimal('versement', 12, 2)->nullable(); // null = the Avoir did not track Versements
            $table->timestamps();

            $table->unique(['releve_id', 'avoir_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrimoine_releve_lignes');
        Schema::dropIfExists('patrimoine_releves');
        Schema::dropIfExists('patrimoine_avoirs');
        Schema::dropIfExists('patrimoine_classes');
    }
};
