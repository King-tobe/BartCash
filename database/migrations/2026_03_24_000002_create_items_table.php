<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description');
            $table->enum('condition', ['new', 'good', 'fair', 'poor']);
            $table->string('desired_trade', 500)->nullable();
            $table->boolean('is_service')->default(false);
            $table->string('location', 100)->nullable();
            $table->enum('status', ['available', 'in_trade', 'traded', 'inactive'])->default('available');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};