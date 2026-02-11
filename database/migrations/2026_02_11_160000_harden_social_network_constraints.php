<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $duplicates = DB::table('subscriber_followings')
            ->select(
                'subscriber_id',
                'following_id',
                DB::raw('MIN(id) as keep_id'),
                DB::raw('COUNT(*) as rows_count')
            )
            ->groupBy('subscriber_id', 'following_id')
            ->having('rows_count', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('subscriber_followings')
                ->where('subscriber_id', $duplicate->subscriber_id)
                ->where('following_id', $duplicate->following_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        DB::table('subscriber_followings')
            ->whereColumn('subscriber_id', 'following_id')
            ->delete();

        Schema::table('subscriber_followings', function (Blueprint $table) {
            $table->unique(
                ['subscriber_id', 'following_id'],
                'subscriber_followings_unique_pair'
            );
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'posts_user_created_at_idx');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->index(['post_id', 'created_at'], 'comments_post_created_at_idx');
        });
    }

    public function down()
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_post_created_at_idx');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_user_created_at_idx');
        });

        Schema::table('subscriber_followings', function (Blueprint $table) {
            $table->dropUnique('subscriber_followings_unique_pair');
        });
    }
};
