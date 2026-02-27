<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 32);
            $table->string('session_id', 120);
            $table->unsignedInteger('total_seconds')->default(0);
            $table->unsignedInteger('heartbeats_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('last_heartbeat_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'feature', 'session_id'], 'user_activity_sessions_unique');
            $table->index(['feature', 'last_heartbeat_at'], 'user_activity_sessions_feature_last_idx');
            $table->index(['user_id', 'started_at'], 'user_activity_sessions_user_started_idx');
        });

        Schema::create('user_activity_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 32);
            $table->date('activity_date');
            $table->unsignedInteger('seconds_total')->default(0);
            $table->unsignedInteger('heartbeats_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'feature', 'activity_date'], 'user_activity_daily_unique');
            $table->index(['feature', 'activity_date'], 'user_activity_daily_feature_date_idx');
            $table->index(['user_id', 'activity_date'], 'user_activity_daily_user_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_daily_stats');
        Schema::dropIfExists('user_activity_sessions');
    }
};
