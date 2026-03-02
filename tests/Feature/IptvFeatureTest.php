<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IptvProxyService;
use App\Services\IptvTranscodeService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class IptvFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_iptv_playlist_by_url(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Http::fake([
            'https://iptv.example.com/*' => Http::response("#EXTM3U\n#EXTINF:-1,News 24\nhttps://stream.example.com/live.m3u8\n", 200),
        ]);

        $response = $this->postJson('/api/iptv/playlist/fetch', [
            'url' => 'https://iptv.example.com/public.m3u',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Плейлист загружен.')
            ->assertJsonPath('data.source_url', 'https://iptv.example.com/public.m3u');

        $this->assertStringContainsString('#EXTM3U', (string) $response->json('data.playlist'));
    }

    public function test_iptv_playlist_endpoint_rejects_private_hosts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/iptv/playlist/fetch', [
            'url' => 'http://127.0.0.1/playlist.m3u',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ссылка ведет на недопустимый хост.');
    }

    public function test_iptv_playlist_endpoint_returns_service_unavailable_when_source_fails(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Http::fake([
            '*' => Http::response([], 502),
        ]);

        $response = $this->postJson('/api/iptv/playlist/fetch', [
            'url' => 'https://iptv.example.com/down.m3u',
        ]);

        $response
            ->assertStatus(503)
            ->assertJsonPath('data.playlist', '');
    }

    public function test_authenticated_user_can_check_transcode_capabilities(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/iptv/transcode/capabilities');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Проверка режима совместимости выполнена.');

        $this->assertIsBool($response->json('data.ffmpeg_available'));
        $this->assertIsInt($response->json('data.max_sessions'));
        $this->assertIsInt($response->json('data.session_ttl_seconds'));
    }

    public function test_proxy_start_returns_relative_playlist_url_for_proxy_player(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $sessionId = 'abcdefghijklmnopqrstuvwx';
        $sourceUrl = 'https://iptv.example.com/channel.m3u8';

        $this->mock(IptvProxyService::class, function ($mock) use ($sessionId, $sourceUrl): void {
            $mock
                ->shouldReceive('startSession')
                ->once()
                ->with($sourceUrl)
                ->andReturn([
                    'session_id' => $sessionId,
                    'source_url' => $sourceUrl,
                    'created_at' => time(),
                    'last_access_at' => time(),
                ]);
        });

        $response = $this->postJson('/api/iptv/proxy/start', [
            'url' => $sourceUrl,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.session_id', $sessionId)
            ->assertJsonPath('data.playlist_url', "/api/iptv/proxy/{$sessionId}/playlist.m3u8");
    }

    public function test_transcode_start_rejects_private_hosts_with_validation_error(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/iptv/transcode/start', [
            'url' => 'http://127.0.0.1/private.m3u8',
            'profile' => 'balanced',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ссылка ведет на недопустимый хост.');
    }

    public function test_transcode_start_returns_relative_playlist_url_for_compat_player(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $sessionId = 'abcdefghijklmnopqrstuvwx';
        $sourceUrl = 'https://iptv.example.com/channel.m3u8';

        $this->mock(IptvTranscodeService::class, function ($mock) use ($sessionId, $sourceUrl): void {
            $mock
                ->shouldReceive('startSession')
                ->once()
                ->with($sourceUrl, 'stable')
                ->andReturn([
                    'session_id' => $sessionId,
                    'pid' => 4321,
                    'profile' => 'stable',
                    'source_url' => $sourceUrl,
                ]);

            $mock
                ->shouldReceive('waitForPlaylist')
                ->once()
                ->with($sessionId, 12)
                ->andReturn(true);
        });

        $response = $this->postJson('/api/iptv/transcode/start', [
            'url' => $sourceUrl,
            'profile' => 'stable',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.session_id', $sessionId)
            ->assertJsonPath('data.profile', 'stable')
            ->assertJsonPath('data.playlist_url', "/api/iptv/transcode/{$sessionId}/playlist.m3u8");
    }

    public function test_transcode_start_returns_service_unavailable_when_playlist_is_not_ready(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $sessionId = 'abcdefghijklmnopqrstuvwx';
        $sourceUrl = 'https://iptv.example.com/channel.m3u8';

        $this->mock(IptvTranscodeService::class, function ($mock) use ($sessionId, $sourceUrl): void {
            $mock
                ->shouldReceive('startSession')
                ->once()
                ->with($sourceUrl, 'balanced')
                ->andReturn([
                    'session_id' => $sessionId,
                    'pid' => 4321,
                    'profile' => 'balanced',
                    'source_url' => $sourceUrl,
                ]);

            $mock
                ->shouldReceive('waitForPlaylist')
                ->once()
                ->with($sessionId, 12)
                ->andReturn(false);

            $mock
                ->shouldReceive('stopSession')
                ->once()
                ->with($sessionId);
        });

        $response = $this->postJson('/api/iptv/transcode/start', [
            'url' => $sourceUrl,
            'profile' => 'balanced',
        ]);

        $response
            ->assertStatus(503)
            ->assertJsonPath('message', 'Совместимый режим не успел подготовить поток. Попробуйте другой канал или профиль FFmpeg "устойчивый".');
    }

    public function test_transcode_start_returns_service_unavailable_when_ffmpeg_is_not_available(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->mock(IptvTranscodeService::class, function ($mock): void {
            $mock
                ->shouldReceive('startSession')
                ->once()
                ->with('https://iptv.example.com/channel.m3u8', 'balanced')
                ->andThrow(new RuntimeException('FFmpeg не установлен на сервере. Включите FFmpeg для режима совместимости.'));

            $mock->shouldNotReceive('waitForPlaylist');
        });

        $response = $this->postJson('/api/iptv/transcode/start', [
            'url' => 'https://iptv.example.com/channel.m3u8',
            'profile' => 'balanced',
        ]);

        $response
            ->assertStatus(503)
            ->assertJsonPath('message', 'FFmpeg не установлен на сервере. Включите FFmpeg для режима совместимости.');
    }

    public function test_relay_start_returns_relative_playlist_url_for_relay_player(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $sessionId = 'abcdefghijklmnopqrstuvwx';
        $sourceUrl = 'https://iptv.example.com/channel.m3u8';

        $this->mock(IptvTranscodeService::class, function ($mock) use ($sessionId, $sourceUrl): void {
            $mock
                ->shouldReceive('startRelaySession')
                ->once()
                ->with($sourceUrl)
                ->andReturn([
                    'session_id' => $sessionId,
                    'pid' => 4321,
                    'profile' => 'relay',
                    'source_url' => $sourceUrl,
                ]);

            $mock
                ->shouldReceive('waitForPlaylist')
                ->once()
                ->with($sessionId, 10)
                ->andReturn(true);
        });

        $response = $this->postJson('/api/iptv/relay/start', [
            'url' => $sourceUrl,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.session_id', $sessionId)
            ->assertJsonPath('data.playlist_url', "/api/iptv/relay/{$sessionId}/playlist.m3u8");
    }

    public function test_relay_start_returns_service_unavailable_when_playlist_is_not_ready(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $sessionId = 'abcdefghijklmnopqrstuvwx';
        $sourceUrl = 'https://iptv.example.com/channel.m3u8';

        $this->mock(IptvTranscodeService::class, function ($mock) use ($sessionId, $sourceUrl): void {
            $mock
                ->shouldReceive('startRelaySession')
                ->once()
                ->with($sourceUrl)
                ->andReturn([
                    'session_id' => $sessionId,
                    'pid' => 4321,
                    'profile' => 'relay',
                    'source_url' => $sourceUrl,
                ]);

            $mock
                ->shouldReceive('waitForPlaylist')
                ->once()
                ->with($sessionId, 10)
                ->andReturn(false);

            $mock
                ->shouldReceive('stopSession')
                ->once()
                ->with($sessionId);
        });

        $response = $this->postJson('/api/iptv/relay/start', [
            'url' => $sourceUrl,
        ]);

        $response
            ->assertStatus(503)
            ->assertJsonPath('message', 'Релейный режим не успел подготовить поток. Попробуйте другой канал или вернитесь в прямой режим.');
    }

    public function test_transcode_playlist_returns_not_found_for_unknown_session(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/iptv/transcode/abcdefghijklmnopqrstuvwx/playlist.m3u8');

        $response
            ->assertStatus(404)
            ->assertJsonPath('message', 'HLS-плейлист совместимого режима не найден или истек.');
    }

    public function test_proxy_playlist_returns_not_found_for_unknown_session(): void
    {
        $response = $this->getJson('/api/iptv/proxy/abcdefghijklmnopqrstuvwx/playlist.m3u8');

        $response
            ->assertStatus(404)
            ->assertJsonPath('message', 'Прокси-плейлист не найден или истек.');
    }

    public function test_guest_can_open_transcode_playlist_endpoint_without_redirect_to_login(): void
    {
        $response = $this->getJson('/api/iptv/transcode/abcdefghijklmnopqrstuvwx/playlist.m3u8');

        $response
            ->assertStatus(404)
            ->assertJsonPath('message', 'HLS-плейлист совместимого режима не найден или истек.');
    }

    public function test_relay_playlist_returns_not_found_for_unknown_session(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $sessionId = 'zzzzzzzzzzzzzzzzzzzzzzzz';
        $this->mock(IptvTranscodeService::class, function ($mock) use ($sessionId): void {
            $mock
                ->shouldReceive('getPlaylistPath')
                ->once()
                ->with($sessionId)
                ->andReturn(null);
        });

        $response = $this->getJson("/api/iptv/relay/{$sessionId}/playlist.m3u8");

        $response
            ->assertStatus(404)
            ->assertJsonPath('message', 'HLS-плейлист релейного режима не найден или истек.');
    }

    public function test_guest_can_open_relay_playlist_endpoint_without_redirect_to_login(): void
    {
        $sessionId = 'zzzzzzzzzzzzzzzzzzzzzzzz';
        $this->mock(IptvTranscodeService::class, function ($mock) use ($sessionId): void {
            $mock
                ->shouldReceive('getPlaylistPath')
                ->once()
                ->with($sessionId)
                ->andReturn(null);
        });

        $response = $this->getJson("/api/iptv/relay/{$sessionId}/playlist.m3u8");

        $response
            ->assertStatus(404)
            ->assertJsonPath('message', 'HLS-плейлист релейного режима не найден или истек.');
    }

    public function test_transcode_segment_returns_not_found_for_unknown_session(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/iptv/transcode/abcdefghijklmnopqrstuvwx/segment_00001.ts');

        $response
            ->assertStatus(404)
            ->assertJsonPath('message', 'HLS-сегмент не найден или истек.');
    }

    public function test_guest_can_open_transcode_segment_endpoint_without_redirect_to_login(): void
    {
        $response = $this->getJson('/api/iptv/transcode/abcdefghijklmnopqrstuvwx/segment_00001.ts');

        $response
            ->assertStatus(404)
            ->assertJsonPath('message', 'HLS-сегмент не найден или истек.');
    }

    public function test_relay_segment_returns_not_found_for_unknown_session(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $sessionId = 'zzzzzzzzzzzzzzzzzzzzzzzz';
        $segment = 'segment_00001.ts';
        $this->mock(IptvTranscodeService::class, function ($mock) use ($sessionId, $segment): void {
            $mock
                ->shouldReceive('getSegmentPath')
                ->once()
                ->with($sessionId, $segment)
                ->andReturn(null);
        });

        $response = $this->getJson("/api/iptv/relay/{$sessionId}/{$segment}");

        $response
            ->assertStatus(404)
            ->assertJsonPath('message', 'HLS-сегмент релейного режима не найден или истек.');
    }

    public function test_guest_can_open_relay_segment_endpoint_without_redirect_to_login(): void
    {
        $sessionId = 'zzzzzzzzzzzzzzzzzzzzzzzz';
        $segment = 'segment_00001.ts';
        $this->mock(IptvTranscodeService::class, function ($mock) use ($sessionId, $segment): void {
            $mock
                ->shouldReceive('getSegmentPath')
                ->once()
                ->with($sessionId, $segment)
                ->andReturn(null);
        });

        $response = $this->getJson("/api/iptv/relay/{$sessionId}/{$segment}");

        $response
            ->assertStatus(404)
            ->assertJsonPath('message', 'HLS-сегмент релейного режима не найден или истек.');
    }

    public function test_proxy_segment_requires_segment_url_parameter(): void
    {
        $response = $this->getJson('/api/iptv/proxy/abcdefghijklmnopqrstuvwx/segment');

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Не указан URL сегмента для прокси.');
    }

    public function test_guest_can_open_proxy_segment_endpoint_without_redirect_to_login(): void
    {
        $response = $this->getJson('/api/iptv/proxy/abcdefghijklmnopqrstuvwx/segment?url=https%3A%2F%2Fstream.example.com%2Fsegment.ts');

        $response
            ->assertStatus(404)
            ->assertJsonPath('message', 'Прокси-сегмент не найден или истек.');
    }

    public function test_proxy_service_throws_runtime_exception_for_upstream_http_errors(): void
    {
        Http::fake([
            'https://iptv.example.com/*' => Http::response('not found', 404),
        ]);

        /** @var IptvProxyService $service */
        $service = app(IptvProxyService::class);
        $session = $service->startSession('https://iptv.example.com/channel.m3u8');

        try {
            $service->getPlaylist($session['session_id']);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RequestException $exception) {
            $this->fail('RequestException must be converted into RuntimeException.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('IPTV-прокси не смог получить ресурс потока', $exception->getMessage());
        }
    }

    public function test_transcode_stop_returns_success_for_unknown_session(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/iptv/transcode/abcdefghijklmnopqrstuvwx');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Совместимый режим остановлен.');
    }

    public function test_proxy_stop_returns_success_for_unknown_session(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/iptv/proxy/abcdefghijklmnopqrstuvwx');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'IPTV-прокси остановлен.');
    }

    public function test_relay_stop_returns_success_for_unknown_session(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/iptv/relay/abcdefghijklmnopqrstuvwx');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Релейный режим остановлен.');
    }

    public function test_user_can_store_list_and_delete_saved_iptv_library_items(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $savePlaylistResponse = $this->postJson('/api/iptv/saved/playlists', [
            'name' => 'Мой список',
            'url' => 'https://iptv.example.com/my-list.m3u',
            'channels_count' => 320,
        ]);

        $savePlaylistResponse
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Мой список')
            ->assertJsonPath('data.url', 'https://iptv.example.com/my-list.m3u')
            ->assertJsonPath('data.channels_count', 320);

        $this->assertDatabaseHas('iptv_saved_playlists', [
            'user_id' => $user->id,
            'name' => 'Мой список',
            'source_url' => 'https://iptv.example.com/my-list.m3u',
            'channels_count' => 320,
        ]);

        $saveChannelResponse = $this->postJson('/api/iptv/saved/channels', [
            'name' => 'Новости 24',
            'url' => 'https://stream.example.com/news24.m3u8',
            'group' => 'Новости',
            'logo' => 'https://img.example.com/news24.png',
        ]);

        $saveChannelResponse
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Новости 24')
            ->assertJsonPath('data.url', 'https://stream.example.com/news24.m3u8')
            ->assertJsonPath('data.group', 'Новости');

        $this->assertDatabaseHas('iptv_saved_channels', [
            'user_id' => $user->id,
            'name' => 'Новости 24',
            'stream_url' => 'https://stream.example.com/news24.m3u8',
            'group_title' => 'Новости',
            'logo_url' => 'https://img.example.com/news24.png',
        ]);

        $listResponse = $this->getJson('/api/iptv/saved');

        $listResponse
            ->assertOk()
            ->assertJsonCount(1, 'data.playlists')
            ->assertJsonCount(1, 'data.channels')
            ->assertJsonPath('data.playlists.0.url', 'https://iptv.example.com/my-list.m3u')
            ->assertJsonPath('data.channels.0.url', 'https://stream.example.com/news24.m3u8');

        $playlistId = (int) $listResponse->json('data.playlists.0.id');
        $channelId = (int) $listResponse->json('data.channels.0.id');

        $deletePlaylistResponse = $this->deleteJson("/api/iptv/saved/playlists/{$playlistId}");
        $deleteChannelResponse = $this->deleteJson("/api/iptv/saved/channels/{$channelId}");

        $deletePlaylistResponse->assertOk();
        $deleteChannelResponse->assertOk();

        $this->assertDatabaseMissing('iptv_saved_playlists', [
            'id' => $playlistId,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('iptv_saved_channels', [
            'id' => $channelId,
            'user_id' => $user->id,
        ]);
    }

    public function test_repeated_saved_iptv_requests_update_single_playlist_and_channel_rows(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/iptv/saved/playlists', [
            'name' => 'Первый список',
            'url' => 'https://iptv.example.com/dup-list.m3u',
            'channels_count' => 10,
        ])->assertStatus(201);

        $this->postJson('/api/iptv/saved/playlists', [
            'name' => 'Обновленный список',
            'url' => 'https://iptv.example.com/dup-list.m3u',
            'channels_count' => 25,
        ])->assertStatus(201);

        $this->postJson('/api/iptv/saved/channels', [
            'name' => 'Первый канал',
            'url' => 'https://stream.example.com/dup-channel.m3u8',
            'group' => 'News',
        ])->assertStatus(201);

        $this->postJson('/api/iptv/saved/channels', [
            'name' => 'Обновленный канал',
            'url' => 'https://stream.example.com/dup-channel.m3u8',
            'group' => 'Updated',
            'logo' => 'https://img.example.com/dup-channel.png',
        ])->assertStatus(201);

        $this->assertDatabaseCount('iptv_saved_playlists', 1);
        $this->assertDatabaseHas('iptv_saved_playlists', [
            'user_id' => $user->id,
            'source_url' => 'https://iptv.example.com/dup-list.m3u',
            'name' => 'Обновленный список',
            'channels_count' => 25,
        ]);

        $this->assertDatabaseCount('iptv_saved_channels', 1);
        $this->assertDatabaseHas('iptv_saved_channels', [
            'user_id' => $user->id,
            'stream_url' => 'https://stream.example.com/dup-channel.m3u8',
            'name' => 'Обновленный канал',
            'group_title' => 'Updated',
            'logo_url' => 'https://img.example.com/dup-channel.png',
        ]);
    }

    public function test_user_can_rename_saved_iptv_library_items(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/iptv/saved/playlists', [
            'name' => 'Старое имя списка',
            'url' => 'https://iptv.example.com/rename-list.m3u',
            'channels_count' => 22,
        ])->assertStatus(201);

        $this->postJson('/api/iptv/saved/channels', [
            'name' => 'Старое имя канала',
            'url' => 'https://stream.example.com/rename-channel.m3u8',
            'group' => 'General',
        ])->assertStatus(201);

        $listResponse = $this->getJson('/api/iptv/saved')
            ->assertOk();

        $playlistId = (int) $listResponse->json('data.playlists.0.id');
        $channelId = (int) $listResponse->json('data.channels.0.id');

        $this->patchJson("/api/iptv/saved/playlists/{$playlistId}", [
            'name' => 'Новое имя списка',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Имя плейлиста обновлено.')
            ->assertJsonPath('data.name', 'Новое имя списка');

        $this->patchJson("/api/iptv/saved/channels/{$channelId}", [
            'name' => 'Новое имя канала',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Имя канала обновлено.')
            ->assertJsonPath('data.name', 'Новое имя канала');

        $this->assertDatabaseHas('iptv_saved_playlists', [
            'id' => $playlistId,
            'user_id' => $user->id,
            'name' => 'Новое имя списка',
        ]);
        $this->assertDatabaseHas('iptv_saved_channels', [
            'id' => $channelId,
            'user_id' => $user->id,
            'name' => 'Новое имя канала',
        ]);

        $this->patchJson("/api/iptv/saved/playlists/{$playlistId}", [
            'name' => '   ',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Имя не должно быть пустым.');

        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $this->patchJson("/api/iptv/saved/playlists/{$playlistId}", [
            'name' => 'Чужой список',
        ])->assertStatus(404);

        $this->patchJson("/api/iptv/saved/channels/{$channelId}", [
            'name' => 'Чужой канал',
        ])->assertStatus(404);
    }

    public function test_saved_iptv_library_isolated_by_user(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $this->postJson('/api/iptv/saved/playlists', [
            'name' => 'First user list',
            'url' => 'https://iptv.example.com/first.m3u',
            'channels_count' => 10,
        ])->assertStatus(201);

        Sanctum::actingAs($secondUser);
        $this->postJson('/api/iptv/saved/playlists', [
            'name' => 'Second user list',
            'url' => 'https://iptv.example.com/second.m3u',
            'channels_count' => 20,
        ])->assertStatus(201);

        $response = $this->getJson('/api/iptv/saved');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.playlists')
            ->assertJsonPath('data.playlists.0.name', 'Second user list');
    }

    public function test_saved_iptv_library_deleted_with_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/iptv/saved/playlists', [
            'name' => 'Delete with user',
            'url' => 'https://iptv.example.com/delete-me.m3u',
            'channels_count' => 77,
        ])->assertStatus(201);

        $this->postJson('/api/iptv/saved/channels', [
            'name' => 'Delete channel with user',
            'url' => 'https://stream.example.com/delete-me.m3u8',
            'group' => 'General',
        ])->assertStatus(201);

        $this->assertDatabaseHas('iptv_saved_playlists', [
            'user_id' => $user->id,
            'source_url' => 'https://iptv.example.com/delete-me.m3u',
        ]);
        $this->assertDatabaseHas('iptv_saved_channels', [
            'user_id' => $user->id,
            'stream_url' => 'https://stream.example.com/delete-me.m3u8',
        ]);

        $user->delete();

        $this->assertDatabaseMissing('iptv_saved_playlists', [
            'user_id' => $user->id,
            'source_url' => 'https://iptv.example.com/delete-me.m3u',
        ]);
        $this->assertDatabaseMissing('iptv_saved_channels', [
            'user_id' => $user->id,
            'stream_url' => 'https://stream.example.com/delete-me.m3u8',
        ]);
    }
}
