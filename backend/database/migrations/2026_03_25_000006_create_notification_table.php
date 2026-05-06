<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id');

            $table->enum('type', [
                'trade_request',
                'trade_accepted',
                'trade_declined',
                'trade_completed',
                'new_message',
                'dispute_raised',
                'trade_cancelled'
            ]);

            $table->string('title');
            $table->text('body');

            $table->uuid('reference_id')->nullable();
            $table->string('reference_type')->nullable();

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // Foreign key
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};