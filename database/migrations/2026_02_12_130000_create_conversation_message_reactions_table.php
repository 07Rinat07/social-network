<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_message_id')->constrained('conversation_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 32);
            $table->timestamps();

            $table->unique(
                ['conversation_message_id', 'user_id', 'emoji'],
                'chat_message_reactions_unique_user_emoji'
            );
            $table->index(
                ['conversation_message_id', 'emoji'],
                'chat_message_reactions_message_emoji_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_message_reactions');
    }
};

