<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trade_id')->constrained('trades')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items')->cascadeOnDelete();
            $table->enum('side', ['proposer', 'receiver']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_items');
    }
};