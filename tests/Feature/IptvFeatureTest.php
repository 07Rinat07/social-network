<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IptvProxyService;
use App\Services\IptvTranscodeService;
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
}
