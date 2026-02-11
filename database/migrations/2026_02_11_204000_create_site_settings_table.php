<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type', 20)->default('string');
            $table->text('value')->nullable();
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->index(['key', 'type'], 'site_settings_key_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
