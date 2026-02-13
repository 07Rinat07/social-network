<?php

namespace App\Console\Commands;

use App\Models\RadioFavorite;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DistributeAdminRadioFavorites extends Command
{
    protected $signature = 'radio:distribute-admin-favorites {--dry-run : Показать, сколько записей будет добавлено, без изменений в БД}';

    protected $description = 'One-time copy of current admin radio favorites to all non-admin users';

    public function handle(): int
    {
        $adminFavorites = RadioFavorite::query()
            ->whereHas('user', static fn (Builder $query) => $query->where('is_admin', true))
            ->orderByDesc('updated_at')
            ->get([
                'station_uuid',
                'name',
                'stream_url',
                'homepage',
                'favicon',
                'country',
                'language',
                'tags',
                'codec',
                'bitrate',
                'votes',
            ])
            ->unique('station_uuid')
            ->values();

        if ($adminFavorites->isEmpty()) {
            $this->warn('У администраторов нет сохранённых радио-станций в избранном.');
            return self::SUCCESS;
        }

        $targetUsersQuery = User::query()->where('is_admin', false);
        $targetUsersCount = (int) $targetUsersQuery->count();

        if ($targetUsersCount === 0) {
            $this->warn('Не найдено не-админ пользователей для копирования.');
            return self::SUCCESS;
        }

        $stationsCount = $adminFavorites->count();
        $potentialRows = $targetUsersCount * $stationsCount;

        $this->line("Станций у админа: {$stationsCount}");
        $this->line("Пользователей (без админов): {$targetUsersCount}");
        $this->line("Потенциальных вставок: {$potentialRows}");

        if ($this->option('dry-run')) {
            $this->info('Режим dry-run: данные не изменены.');
            return self::SUCCESS;
        }

        $insertedRows = 0;
        $now = now();
        $stationTemplates = $this->buildStationTemplates($adminFavorites);

        $targetUsersQuery
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$insertedRows, $stationTemplates, $now): void {
                $rows = [];

                foreach ($users as $user) {
                    foreach ($stationTemplates as $template) {
                        $rows[] = [
                            'user_id' => $user->id,
                            'station_uuid' => $template['station_uuid'],
                            'name' => $template['name'],
                            'stream_url' => $template['stream_url'],
                            'homepage' => $template['homepage'],
                            'favicon' => $template['favicon'],
                            'country' => $template['country'],
                            'language' => $template['language'],
                            'tags' => $template['tags'],
                            'codec' => $template['codec'],
                            'bitrate' => $template['bitrate'],
                            'votes' => $template['votes'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                foreach (array_chunk($rows, 1000) as $chunk) {
                    $insertedRows += RadioFavorite::query()->insertOrIgnore($chunk);
                }
            });

        $skippedRows = max(0, $potentialRows - $insertedRows);

        $this->info("Готово. Добавлено: {$insertedRows}.");
        $this->line("Пропущено (уже существовали): {$skippedRows}.");

        return self::SUCCESS;
    }

    protected function buildStationTemplates(Collection $favorites): Collection
    {
        return $favorites->map(static function (RadioFavorite $favorite): array {
            return [
                'station_uuid' => $favorite->station_uuid,
                'name' => $favorite->name,
                'stream_url' => $favorite->stream_url,
                'homepage' => $favorite->homepage,
                'favicon' => $favorite->favicon,
                'country' => $favorite->country,
                'language' => $favorite->language,
                'tags' => $favorite->tags,
                'codec' => $favorite->codec,
                'bitrate' => $favorite->bitrate,
                'votes' => $favorite->votes,
            ];
        })->values();
    }
}
