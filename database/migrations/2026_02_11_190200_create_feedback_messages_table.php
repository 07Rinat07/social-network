<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->text('message');
            $table->string('status', 20)->default('new');
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'feedback_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_messages');
    }
};
