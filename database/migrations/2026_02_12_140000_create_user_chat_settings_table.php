<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_chat_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('save_text_messages')->default(true);
            $table->boolean('save_media_attachments')->default(true);
            $table->boolean('save_file_attachments')->default(true);
            $table->unsignedInteger('retention_days')->nullable();
            $table->boolean('auto_archive_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_chat_settings');
    }
};

