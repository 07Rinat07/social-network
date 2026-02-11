<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('radio_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('station_uuid', 64);
            $table->string('name', 255);
            $table->text('stream_url');
            $table->string('homepage', 1000)->nullable();
            $table->string('favicon', 1000)->nullable();
            $table->string('country', 120)->nullable();
            $table->string('language', 120)->nullable();
            $table->string('tags', 500)->nullable();
            $table->string('codec', 64)->nullable();
            $table->unsignedInteger('bitrate')->nullable();
            $table->unsignedInteger('votes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'station_uuid']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_favorites');
    }
};
