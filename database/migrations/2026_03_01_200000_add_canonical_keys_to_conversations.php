<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('canonical_key', 191)->nullable()->after('type');
        });

        $this->deduplicateConversations();

        Schema::table('conversations', function (Blueprint $table) {
            $table->unique('canonical_key', 'conversations_canonical_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique('conversations_canonical_key_unique');
            $table->dropColumn('canonical_key');
        });
    }

    private function deduplicateConversations(): void
    {
        $conversations = DB::table('conversations')
            ->select(['id', 'type', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->get();

        if ($conversations->isEmpty()) {
            return;
        }

        $messageCounts = DB::table('conversation_messages')
            ->selectRaw('conversation_id, COUNT(*) as aggregate')
            ->groupBy('conversation_id')
            ->pluck('aggregate', 'conversation_id');

        $moodCounts = Schema::hasTable('conversation_mood_statuses')
            ? DB::table('conversation_mood_statuses')
                ->selectRaw('conversation_id, COUNT(*) as aggregate')
                ->groupBy('conversation_id')
                ->pluck('aggregate', 'conversation_id')
            : collect();

        $groups = [];

        foreach ($conversations as $conversation) {
            $canonicalKey = $this->resolveCanonicalKey((int) $conversation->id, (string) $conversation->type);
            if ($canonicalKey === null) {
                continue;
            }

            $groups[$canonicalKey] ??= [];
            $groups[$canonicalKey][] = $conversation;
        }

        foreach ($groups as $canonicalKey => $items) {
            if (count($items) < 2) {
                continue;
            }

            $canonical = $this->pickCanonicalConversation($items, $messageCounts, $moodCounts);

            foreach ($items as $conversation) {
                if ((int) $conversation->id === (int) $canonical->id) {
                    continue;
                }

                $this->mergeConversationInto((int) $conversation->id, (int) $canonical->id);
            }

            DB::table('conversations')
                ->where('id', (int) $canonical->id)
                ->update([
                    'canonical_key' => $canonicalKey,
                ]);
        }

        $remaining = DB::table('conversations')
            ->select(['id', 'type'])
            ->orderBy('id')
            ->get();

        foreach ($remaining as $conversation) {
            DB::table('conversations')
                ->where('id', (int) $conversation->id)
                ->update([
                    'canonical_key' => $this->resolveCanonicalKey((int) $conversation->id, (string) $conversation->type),
                ]);
        }
    }

    private function resolveCanonicalKey(int $conversationId, string $type): ?string
    {
        if ($type === 'global') {
            return 'global';
        }

        if ($type !== 'direct') {
            return null;
        }

        $participantIds = DB::table('conversation_participants')
            ->where('conversation_id', $conversationId)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($participantIds->count() !== 2) {
            return null;
        }

        return sprintf('direct:%d:%d', $participantIds[0], $participantIds[1]);
    }

    private function pickCanonicalConversation(array $items, $messageCounts, $moodCounts): object
    {
        usort($items, function (object $left, object $right) use ($messageCounts, $moodCounts): int {
            $leftMessages = (int) ($messageCounts[$left->id] ?? 0);
            $rightMessages = (int) ($messageCounts[$right->id] ?? 0);
            if ($leftMessages !== $rightMessages) {
                return $rightMessages <=> $leftMessages;
            }

            $leftMoods = (int) ($moodCounts[$left->id] ?? 0);
            $rightMoods = (int) ($moodCounts[$right->id] ?? 0);
            if ($leftMoods !== $rightMoods) {
                return $rightMoods <=> $leftMoods;
            }

            $leftUpdated = (string) ($left->updated_at ?? '');
            $rightUpdated = (string) ($right->updated_at ?? '');
            if ($leftUpdated !== $rightUpdated) {
                return $rightUpdated <=> $leftUpdated;
            }

            return (int) $left->id <=> (int) $right->id;
        });

        return $items[0];
    }

    private function mergeConversationInto(int $sourceId, int $targetId): void
    {
        if ($sourceId === $targetId) {
            return;
        }

        $source = DB::table('conversations')->where('id', $sourceId)->first();
        $target = DB::table('conversations')->where('id', $targetId)->first();

        if ($source === null || $target === null) {
            return;
        }

        DB::table('conversation_messages')
            ->where('conversation_id', $sourceId)
            ->update(['conversation_id' => $targetId]);

        $this->mergeParticipants($sourceId, $targetId);
        $this->mergeMoodStatuses($sourceId, $targetId);

        if (Schema::hasTable('chat_archives')) {
            DB::table('chat_archives')
                ->where('conversation_id', $sourceId)
                ->update(['conversation_id' => $targetId]);

            DB::table('chat_archives')
                ->where('restored_conversation_id', $sourceId)
                ->update(['restored_conversation_id' => $targetId]);
        }

        DB::table('conversations')
            ->where('id', $targetId)
            ->update([
                'title' => $target->title ?: $source->title,
                'created_by' => $target->created_by ?: $source->created_by,
                'created_at' => $this->earlierTimestamp($target->created_at, $source->created_at),
                'updated_at' => $this->laterTimestamp($target->updated_at, $source->updated_at),
            ]);

        DB::table('conversations')
            ->where('id', $sourceId)
            ->delete();
    }

    private function mergeParticipants(int $sourceId, int $targetId): void
    {
        $rows = DB::table('conversation_participants')
            ->where('conversation_id', $sourceId)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $existing = DB::table('conversation_participants')
                ->where('conversation_id', $targetId)
                ->where('user_id', (int) $row->user_id)
                ->first();

            if ($existing !== null) {
                DB::table('conversation_participants')
                    ->where('id', (int) $existing->id)
                    ->update([
                        'last_read_at' => $this->laterTimestamp($existing->last_read_at, $row->last_read_at),
                        'created_at' => $this->earlierTimestamp($existing->created_at, $row->created_at),
                        'updated_at' => $this->laterTimestamp($existing->updated_at, $row->updated_at),
                    ]);

                DB::table('conversation_participants')
                    ->where('id', (int) $row->id)
                    ->delete();

                continue;
            }

            DB::table('conversation_participants')
                ->where('id', (int) $row->id)
                ->update([
                    'conversation_id' => $targetId,
                ]);
        }
    }

    private function mergeMoodStatuses(int $sourceId, int $targetId): void
    {
        if (!Schema::hasTable('conversation_mood_statuses')) {
            return;
        }

        $rows = DB::table('conversation_mood_statuses')
            ->where('conversation_id', $sourceId)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $existing = DB::table('conversation_mood_statuses')
                ->where('conversation_id', $targetId)
                ->where('user_id', (int) $row->user_id)
                ->first();

            if ($existing !== null) {
                $sourceIsNewer = $this->compareTimestamps($row->updated_at, $existing->updated_at) >= 0;

                if ($sourceIsNewer) {
                    DB::table('conversation_mood_statuses')
                        ->where('id', (int) $existing->id)
                        ->update([
                            'text' => $row->text,
                            'is_visible_to_all' => $row->is_visible_to_all,
                            'hidden_for_user_ids' => $row->hidden_for_user_ids,
                            'created_at' => $this->earlierTimestamp($existing->created_at, $row->created_at),
                            'updated_at' => $this->laterTimestamp($existing->updated_at, $row->updated_at),
                        ]);
                }

                DB::table('conversation_mood_statuses')
                    ->where('id', (int) $row->id)
                    ->delete();

                continue;
            }

            DB::table('conversation_mood_statuses')
                ->where('id', (int) $row->id)
                ->update([
                    'conversation_id' => $targetId,
                ]);
        }
    }

    private function earlierTimestamp(mixed $left, mixed $right): mixed
    {
        if ($left === null || $left === '') {
            return $right;
        }

        if ($right === null || $right === '') {
            return $left;
        }

        return strcmp((string) $left, (string) $right) <= 0 ? $left : $right;
    }

    private function laterTimestamp(mixed $left, mixed $right): mixed
    {
        if ($left === null || $left === '') {
            return $right;
        }

        if ($right === null || $right === '') {
            return $left;
        }

        return strcmp((string) $left, (string) $right) >= 0 ? $left : $right;
    }

    private function compareTimestamps(mixed $left, mixed $right): int
    {
        if ($left === null || $left === '') {
            return ($right === null || $right === '') ? 0 : -1;
        }

        if ($right === null || $right === '') {
            return 1;
        }

        return strcmp((string) $left, (string) $right);
    }
};
