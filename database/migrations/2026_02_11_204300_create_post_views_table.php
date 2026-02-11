<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('viewed_on');
            $table->timestamps();

            $table->unique(['post_id', 'user_id', 'viewed_on'], 'post_views_unique_per_day');
            $table->index(['user_id', 'viewed_on'], 'post_views_user_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_views');
    }
};
