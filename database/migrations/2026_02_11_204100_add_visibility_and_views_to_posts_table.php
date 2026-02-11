<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_public')->default(true)->after('content');
            $table->boolean('show_in_feed')->default(true)->after('is_public');
            $table->boolean('show_in_carousel')->default(false)->after('show_in_feed');
            $table->unsignedBigInteger('views_count')->default(0)->after('show_in_carousel');

            $table->index(['is_public', 'show_in_feed', 'created_at'], 'posts_public_feed_idx');
            $table->index(['is_public', 'show_in_carousel', 'created_at'], 'posts_public_carousel_idx');
            $table->index(['views_count', 'created_at'], 'posts_views_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_public_feed_idx');
            $table->dropIndex('posts_public_carousel_idx');
            $table->dropIndex('posts_views_created_idx');
            $table->dropColumn(['is_public', 'show_in_feed', 'show_in_carousel', 'views_count']);
        });
    }
};
