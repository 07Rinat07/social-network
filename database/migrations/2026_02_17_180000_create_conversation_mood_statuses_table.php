<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_mood_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('text', 500);
            $table->boolean('is_visible_to_all')->default(true);
            $table->json('hidden_for_user_ids')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id'], 'conversation_mood_statuses_unique_user');
            $table->index(['conversation_id', 'updated_at'], 'conversation_mood_statuses_conv_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_mood_statuses');
    }
};

