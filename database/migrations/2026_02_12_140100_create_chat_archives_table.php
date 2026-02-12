<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('scope', 20)->default('all');
            $table->string('title')->nullable();
            $table->json('payload');
            $table->unsignedInteger('messages_count')->default(0);
            $table->timestamp('restored_at')->nullable();
            $table->foreignId('restored_conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'chat_archives_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_archives');
    }
};

