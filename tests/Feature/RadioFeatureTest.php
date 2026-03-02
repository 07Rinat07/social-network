<?php

namespace Tests\Feature;

use App\Models\RadioFavorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RadioFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_radio_stations_endpoint_returns_normalized_stations_and_favorite_flag(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        RadioFavorite::query()->create([
            'user_id' => $user->id,
            'station_uuid' => 'station-1',
            'name' => 'Rock FM',
            'stream_url' => 'https://stream.example.com/rock',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/json/stations/search')) {
                return Http::response([
                    [
                        'stationuuid' => 'station-1',
                        'name' => 'Rock FM',
                        'url' => 'https://stream.example.com/rock-old',
                        'url_resolved' => 'https://stream.example.com/rock',
                        'homepage' => 'https://rock.example.com',
                        'favicon' => 'https://rock.example.com/icon.png',
                        'country' => 'Germany',
                        'language' => 'English',
                        'tags' => 'rock,pop',
                        'codec' => 'MP3',
                        'bitrate' => 128,
                        'votes' => 420,
                    ],
                    [
                        'stationuuid' => 'station-2',
                        'name' => 'Talk One',
                        'url' => 'https://stream.example.com/talk',
                        'url_resolved' => '',
                        'country' => 'USA',
                        'language' => 'English',
                    ],
                    [
                        'stationuuid' => 'station-3',
                        'name' => 'Broken stream',
                        'url' => '',
                        'url_resolved' => '',
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $response = $this->getJson('/api/radio/stations?q=rock&limit=5');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.station_uuid', 'station-1')
            ->assertJsonPath('data.0.stream_url', 'https://stream.example.com/rock')
            ->assertJsonPath('data.0.is_favorite', true)
            ->assertJsonPath('data.1.station_uuid', 'station-2')
            ->assertJsonPath('data.1.stream_url', 'https://stream.example.com/talk')
            ->assertJsonPath('data.1.is_favorite', false);
    }

    public function test_radio_stations_endpoint_returns_service_unavailable_when_catalog_fails(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $response = $this->getJson('/api/radio/stations?q=electro');

        $response
            ->assertStatus(503)
            ->assertJsonPath('data', []);
    }

    public function test_user_can_add_list_and_remove_radio_favorites(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $storeResponse = $this->postJson('/api/radio/favorites', [
            'station_uuid' => 'station-abc',
            'name' => 'Synthwave Station',
            'stream_url' => 'https://stream.example.com/synth',
            'homepage' => 'https://radio.example.com',
            'favicon' => 'https://radio.example.com/icon.png',
            'country' => 'France',
            'language' => 'French',
            'tags' => 'synthwave,electronic',
            'codec' => 'AAC',
            'bitrate' => 192,
            'votes' => 77,
        ]);

        $storeResponse
            ->assertStatus(201)
            ->assertJsonPath('data.station_uuid', 'station-abc');

        $this->assertDatabaseHas('radio_favorites', [
            'user_id' => $user->id,
            'station_uuid' => 'station-abc',
            'name' => 'Synthwave Station',
            'stream_url' => 'https://stream.example.com/synth',
        ]);

        $listResponse = $this->getJson('/api/radio/favorites');

        $listResponse
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.station_uuid', 'station-abc')
            ->assertJsonPath('data.0.codec', 'AAC');

        $deleteResponse = $this->deleteJson('/api/radio/favorites/station-abc');

        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('radio_favorites', [
            'user_id' => $user->id,
            'station_uuid' => 'station-abc',
        ]);
    }

    public function test_repeated_radio_favorite_requests_update_single_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/radio/favorites', [
            'station_uuid' => 'station-dup',
            'name' => 'Station One',
            'stream_url' => 'https://stream.example.com/one',
            'codec' => 'AAC',
        ])->assertStatus(201);

        $this->postJson('/api/radio/favorites', [
            'station_uuid' => 'station-dup',
            'name' => 'Station One Updated',
            'stream_url' => 'https://stream.example.com/two',
            'codec' => 'MP3',
            'votes' => 321,
        ])->assertStatus(201);

        $this->assertDatabaseCount('radio_favorites', 1);
        $this->assertDatabaseHas('radio_favorites', [
            'user_id' => $user->id,
            'station_uuid' => 'station-dup',
            'name' => 'Station One Updated',
            'stream_url' => 'https://stream.example.com/two',
            'codec' => 'MP3',
            'votes' => 321,
        ]);
    }

    public function test_radio_stream_proxy_returns_upstream_audio_stream(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Http::fake([
            'http://stream.example.com/live*' => Http::response('audio-frame', 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $response = $this->get('/api/radio/stream?url=' . urlencode('http://stream.example.com/live'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertStringContainsString('audio-frame', $response->streamedContent());
    }

    public function test_radio_stream_proxy_rejects_private_or_local_hosts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/radio/stream?url=' . urlencode('http://127.0.0.1/private-stream'));

        $response
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }
}
