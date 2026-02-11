<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('conversation_message_attachments')) {
            return;
        }

        if (Schema::hasColumn('conversation_message_attachments', 'storage_disk')) {
            return;
        }

        Schema::table('conversation_message_attachments', function (Blueprint $table) {
            $table->string('storage_disk', 50)->default('public')->after('path');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('conversation_message_attachments')) {
            return;
        }

        if (!Schema::hasColumn('conversation_message_attachments', 'storage_disk')) {
            return;
        }

        Schema::table('conversation_message_attachments', function (Blueprint $table) {
            $table->dropColumn('storage_disk');
        });
    }
};
