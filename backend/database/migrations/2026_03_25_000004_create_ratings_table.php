<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trade_id')->constrained('trades')->cascadeOnDelete();
            $table->foreignUuid('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('rated_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->text('review')->nullable();
            $table->timestamps();

            $table->unique(['trade_id', 'rater_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};