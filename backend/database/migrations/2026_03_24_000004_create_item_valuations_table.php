<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_valuations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('value_min', 10, 2)->nullable();
            $table->decimal('value_max', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('confidence', 50)->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->text('failed_reason')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_valuations');
    }
};