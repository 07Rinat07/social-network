<?php

namespace Tests\Feature;

use App\Models\RadioFavorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DistributeAdminRadioFavoritesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_copies_current_admin_favorites_to_all_non_admin_users_once(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $userA = User::factory()->create(['is_admin' => false]);
        $userB = User::factory()->create(['is_admin' => false]);

        RadioFavorite::query()->create([
            'user_id' => $admin->id,
            'station_uuid' => 'station-1',
            'name' => 'Admin Rock',
            'stream_url' => 'https://stream.example.com/rock',
            'codec' => 'MP3',
            'bitrate' => 128,
        ]);

        RadioFavorite::query()->create([
            'user_id' => $admin->id,
            'station_uuid' => 'station-2',
            'name' => 'Admin Jazz',
            'stream_url' => 'https://stream.example.com/jazz',
            'codec' => 'AAC',
            'bitrate' => 192,
        ]);

        RadioFavorite::query()->create([
            'user_id' => $userA->id,
            'station_uuid' => 'station-1',
            'name' => 'User Custom Rock',
            'stream_url' => 'https://custom.example.com/rock',
        ]);

        $exitCode = Artisan::call('radio:distribute-admin-favorites');
        $this->assertSame(0, $exitCode);

        $this->assertDatabaseHas('radio_favorites', [
            'user_id' => $userA->id,
            'station_uuid' => 'station-1',
            'name' => 'User Custom Rock',
            'stream_url' => 'https://custom.example.com/rock',
        ]);

        $this->assertDatabaseHas('radio_favorites', [
            'user_id' => $userA->id,
            'station_uuid' => 'station-2',
            'name' => 'Admin Jazz',
            'stream_url' => 'https://stream.example.com/jazz',
        ]);

        $this->assertDatabaseHas('radio_favorites', [
            'user_id' => $userB->id,
            'station_uuid' => 'station-1',
            'name' => 'Admin Rock',
            'stream_url' => 'https://stream.example.com/rock',
        ]);

        $this->assertDatabaseHas('radio_favorites', [
            'user_id' => $userB->id,
            'station_uuid' => 'station-2',
            'name' => 'Admin Jazz',
            'stream_url' => 'https://stream.example.com/jazz',
        ]);

        $totalBeforeSecondRun = RadioFavorite::query()->count();
        $secondExitCode = Artisan::call('radio:distribute-admin-favorites');
        $this->assertSame(0, $secondExitCode);
        $this->assertSame($totalBeforeSecondRun, RadioFavorite::query()->count());
    }

    public function test_command_dry_run_does_not_write_rows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['is_admin' => false]);

        RadioFavorite::query()->create([
            'user_id' => $admin->id,
            'station_uuid' => 'station-11',
            'name' => 'Admin Talk',
            'stream_url' => 'https://stream.example.com/talk',
        ]);

        $countBefore = RadioFavorite::query()->count();
        $exitCode = Artisan::call('radio:distribute-admin-favorites', ['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame($countBefore, RadioFavorite::query()->count());
    }
}
