<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 32);
            $table->string('event_name', 80);
            $table->string('entity_type', 80)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('entity_key', 191)->nullable();
            $table->string('session_id', 120)->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->decimal('metric_value', 12, 2)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['feature', 'event_name', 'created_at'], 'analytics_events_feature_event_created_idx');
            $table->index(['user_id', 'created_at'], 'analytics_events_user_created_idx');
            $table->index(['entity_type', 'entity_id', 'created_at'], 'analytics_events_entity_idx');
            $table->index('entity_key', 'analytics_events_entity_key_idx');
            $table->index('session_id', 'analytics_events_session_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
