<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['blocker_id', 'blocked_user_id'], 'user_blocks_unique_pair');
            $table->index(['blocker_id', 'expires_at'], 'user_blocks_blocker_expires_idx');
            $table->index(['blocked_user_id', 'expires_at'], 'user_blocks_blocked_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
    }
};
