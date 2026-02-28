<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AnalyticsEventService;
use App\Services\SiteErrorLogService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class SiteErrorLogFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected string $siteErrorLogPath;
    protected string $siteErrorArchivePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siteErrorLogPath = storage_path('framework/testing/site-errors-test.log');
        $this->siteErrorArchivePath = storage_path('framework/testing/site-errors-archive');
        File::ensureDirectoryExists(dirname($this->siteErrorLogPath));
        File::ensureDirectoryExists($this->siteErrorArchivePath);
        File::delete($this->siteErrorLogPath);
        File::cleanDirectory($this->siteErrorArchivePath);
        config()->set('logging.channels.site_errors.path', $this->siteErrorLogPath);
        config()->set('logging.channels.site_errors.archive_path', $this->siteErrorArchivePath);
        config()->set('logging.channels.site_errors.rotate_max_bytes', 10485760);
        config()->set('logging.channels.site_errors.rotate_max_age_days', 30);
        config()->set('logging.channels.site_errors.compress_archives', false);
    }

    protected function tearDown(): void
    {
        File::delete($this->siteErrorLogPath);
        File::cleanDirectory($this->siteErrorArchivePath);

        parent::tearDown();
    }

    public function test_reported_exception_is_written_to_lifetime_site_error_log(): void
    {
        app(ExceptionHandler::class)->report(new RuntimeException('Site error log smoke test.'));

        $this->assertTrue(File::exists($this->siteErrorLogPath));

        $content = File::get($this->siteErrorLogPath);

        $this->assertStringContainsString('=== SITE ERROR ENTRY ===', $content);
        $this->assertStringContainsString('Type: server_exception', $content);
        $this->assertStringContainsString('Message: Site error log smoke test.', $content);
        $this->assertStringContainsString(RuntimeException::class, $content);
    }

    public function test_failure_analytics_event_is_appended_to_lifetime_site_error_log(): void
    {
        $user = User::factory()->create();

        $event = app(AnalyticsEventService::class)->recordForUser(
            (int) $user->id,
            'media',
            'media_upload_failed',
            [
                'entity_type' => 'post_image',
                'entity_id' => 17,
                'entity_key' => 'upload-17',
                'session_id' => 'media-session-001',
                'context' => [
                    'reason' => 'ffmpeg timeout',
                    'step' => 'poster_generation',
                ],
            ]
        );

        $this->assertNotNull($event);
        $this->assertTrue(File::exists($this->siteErrorLogPath));

        $content = File::get($this->siteErrorLogPath);

        $this->assertStringContainsString('Type: analytics_failure', $content);
        $this->assertStringContainsString('Event: media_upload_failed', $content);
        $this->assertStringContainsString('ffmpeg timeout', $content);
        $this->assertStringContainsString('media-session-001', $content);
    }

    public function test_public_client_error_endpoint_writes_text_entry_into_site_error_log(): void
    {
        $response = $this->postJson('/api/client-errors', [
            'kind' => 'http',
            'message' => 'Frontend received 500 from dashboard endpoint.',
            'request_url' => '/api/admin/dashboard',
            'request_method' => 'get',
            'status_code' => 500,
            'page_url' => 'http://localhost/ru/admin',
            'route_name' => 'admin.index',
            'source_file' => 'Admin.vue',
            'source_line' => 730,
            'source_column' => 18,
            'context' => [
                'response_message' => 'Internal Server Error',
                'network_error' => false,
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'message' => 'Client error accepted.',
            ]);

        $content = File::get($this->siteErrorLogPath);

        $this->assertStringContainsString('Type: client_error', $content);
        $this->assertStringContainsString('Kind: http', $content);
        $this->assertStringContainsString('Frontend received 500 from dashboard endpoint.', $content);
        $this->assertStringContainsString('/api/admin/dashboard', $content);
    }

    public function test_admin_can_preview_and_download_site_error_log(): void
    {
        File::put(
            $this->siteErrorLogPath,
            "=== SITE ERROR ENTRY ===\n"
            . "Timestamp: 2026-02-28T20:10:00+00:00\n"
            . "Type: server_exception\n"
            . "Message: Preview test failure\n\n"
        );

        $admin = User::factory()->create([
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $previewResponse = $this->getJson('/api/admin/error-log');

        $previewResponse
            ->assertOk()
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.file_name', 'site-errors-test.log')
            ->assertJsonPath('data.truncated', false);

        $this->assertStringContainsString(
            'Preview test failure',
            (string) $previewResponse->json('data.preview')
        );

        $downloadResponse = $this->get('/api/admin/error-log/download');

        $downloadResponse
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');

        $this->assertStringContainsString('Preview test failure', $downloadResponse->streamedContent());
    }

    public function test_admin_can_filter_search_and_paginate_site_error_log_entries(): void
    {
        File::put(
            $this->siteErrorLogPath,
            "=== SITE ERROR ENTRY ===\n"
            . "Timestamp: 2026-02-28T20:10:00+00:00\n"
            . "Type: server_exception\n"
            . "Exception: RuntimeException\n"
            . "Message: Database exploded in admin dashboard\n"
            . "File: app/Http/Controllers/AdminController.php:91\n\n"
            . "=== SITE ERROR ENTRY ===\n"
            . "Timestamp: 2026-02-28T20:11:00+00:00\n"
            . "Type: client_error\n"
            . "Kind: http\n"
            . "Message: Frontend received 500 from dashboard endpoint\n"
            . "Request URL: /api/admin/dashboard\n"
            . "Status Code: 500\n\n"
            . "=== SITE ERROR ENTRY ===\n"
            . "Timestamp: 2026-02-28T20:12:00+00:00\n"
            . "Type: analytics_failure\n"
            . "Feature: radio\n"
            . "Event: radio_play_failed\n"
            . "Message: Radio stream startup failed\n"
            . "Entity Key: retro-fm\n\n"
        );

        $admin = User::factory()->create([
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $filteredResponse = $this->getJson('/api/admin/error-log/entries?type=client_error&search=dashboard&per_page=1&page=1');

        $filteredResponse
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.meta.current_page', 1)
            ->assertJsonPath('data.meta.last_page', 1)
            ->assertJsonPath('data.items.0.type', 'client_error')
            ->assertJsonPath('data.items.0.status_code', '500');

        $paginatedResponse = $this->getJson('/api/admin/error-log/entries?per_page=2&page=1');

        $paginatedResponse
            ->assertOk()
            ->assertJsonPath('data.meta.total', 3)
            ->assertJsonPath('data.meta.current_page', 1)
            ->assertJsonPath('data.meta.last_page', 2)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.type', 'analytics_failure')
            ->assertJsonPath('data.items.1.type', 'client_error');
    }

    public function test_admin_can_export_only_filtered_log_entries(): void
    {
        File::put(
            $this->siteErrorLogPath,
            "=== SITE ERROR ENTRY ===\n"
            . "Timestamp: 2026-02-28T20:10:00+00:00\n"
            . "Type: server_exception\n"
            . "Message: Database exploded in admin dashboard\n\n"
            . "=== SITE ERROR ENTRY ===\n"
            . "Timestamp: 2026-02-28T20:12:00+00:00\n"
            . "Type: analytics_failure\n"
            . "Feature: radio\n"
            . "Event: radio_play_failed\n"
            . "Message: Radio stream startup failed\n"
            . "Entity Key: retro-fm\n\n"
        );

        $admin = User::factory()->create([
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->get('/api/admin/error-log/export?type=analytics_failure&search=retro');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Matched Entries: 1', $content);
        $this->assertStringContainsString('radio_play_failed', $content);
        $this->assertStringContainsString('retro-fm', $content);
        $this->assertStringNotContainsString('Database exploded in admin dashboard', $content);
    }

    public function test_log_is_rotated_to_archive_and_archived_entries_remain_searchable(): void
    {
        config()->set('logging.channels.site_errors.rotate_max_bytes', 200);
        config()->set('logging.channels.site_errors.compress_archives', false);

        app(SiteErrorLogService::class)->logClientError([
            'kind' => 'runtime',
            'message' => str_repeat('First archived error. ', 20),
            'page_url' => 'http://localhost/ru/admin',
        ]);

        app(SiteErrorLogService::class)->logClientError([
            'kind' => 'runtime',
            'message' => 'Second active error.',
            'page_url' => 'http://localhost/ru/admin',
        ]);

        $archiveFiles = File::files($this->siteErrorArchivePath);

        $this->assertNotEmpty($archiveFiles);
        $this->assertTrue(File::exists($this->siteErrorLogPath));

        $admin = User::factory()->create([
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/error-log/entries?search=archived&per_page=10&page=1');

        $response
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.type', 'client_error');

        $previewResponse = $this->getJson('/api/admin/error-log');

        $previewResponse
            ->assertOk()
            ->assertJsonPath('data.archive_count', count($archiveFiles));
    }
}
