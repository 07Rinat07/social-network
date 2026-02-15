<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('iptv_saved_playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('source_url');
            $table->string('source_url_hash', 64);
            $table->unsignedInteger('channels_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'source_url_hash']);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('iptv_saved_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('stream_url');
            $table->string('stream_url_hash', 64);
            $table->string('group_title', 160)->nullable();
            $table->string('logo_url', 2000)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'stream_url_hash']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iptv_saved_channels');
        Schema::dropIfExists('iptv_saved_playlists');
    }
};
