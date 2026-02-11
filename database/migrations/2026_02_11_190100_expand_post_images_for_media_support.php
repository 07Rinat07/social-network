<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_images', function (Blueprint $table) {
            $table->string('type', 20)->default('image')->after('path');
            $table->string('mime_type')->nullable()->after('type');
            $table->unsignedBigInteger('size')->nullable()->after('mime_type');
            $table->string('original_name')->nullable()->after('size');
            $table->index(['post_id', 'type'], 'post_images_post_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('post_images', function (Blueprint $table) {
            $table->dropIndex('post_images_post_type_idx');
            $table->dropColumn(['type', 'mime_type', 'size', 'original_name']);
        });
    }
};
