<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('media_storage_preference', 40)
                ->nullable()
                ->after('is_admin');
        });

        Schema::table('post_images', function (Blueprint $table) {
            $table->string('storage_disk', 50)->default('public')->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('post_images', function (Blueprint $table) {
            $table->dropColumn('storage_disk');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('media_storage_preference');
        });
    }
};
