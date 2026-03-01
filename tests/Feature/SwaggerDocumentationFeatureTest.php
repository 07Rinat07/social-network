<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SwaggerDocumentationFeatureTest extends TestCase
{
    public function test_swagger_docs_can_be_generated_and_include_key_routes(): void
    {
        $exitCode = Artisan::call('l5-swagger:generate');

        $this->assertSame(0, $exitCode);

        $docsPath = storage_path('api-docs/api-docs.json');
        $this->assertFileExists($docsPath);

        $spec = json_decode((string) file_get_contents($docsPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('3.0.0', $spec['openapi'] ?? null);
        $this->assertSame('1.4.0', $spec['info']['version'] ?? null);
        $this->assertArrayHasKey('/api/users', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/post_media', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/site/config', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/analytics/events', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/client-errors', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/posts/discover', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/radio/favorites', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/iptv/playlist/fetch', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/chats/settings', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/chats/archives', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/chats/{conversation}/mood-status', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/admin/summary', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/admin/dashboard', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/admin/dashboard/export', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/admin/error-log', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/admin/error-log/entries', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/admin/error-log/export', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/admin/error-log/download', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/api/chats/users', $spec['paths'] ?? []);
        $this->assertArrayHasKey('UserSummary', $spec['components']['schemas'] ?? []);
        $this->assertArrayHasKey('UploadedPostMedia', $spec['components']['schemas'] ?? []);
        $this->assertArrayHasKey('PlaybackSession', $spec['components']['schemas'] ?? []);
        $this->assertArrayHasKey('AnalyticsEventRequest', $spec['components']['schemas'] ?? []);
        $this->assertArrayHasKey('ClientErrorRequest', $spec['components']['schemas'] ?? []);
        $this->assertArrayHasKey('SiteErrorLogPreview', $spec['components']['schemas'] ?? []);
        $this->assertArrayHasKey('SiteErrorLogEntry', $spec['components']['schemas'] ?? []);

        $exportParameters = $spec['paths']['/api/admin/dashboard/export']['get']['parameters'] ?? [];
        $parameterNames = array_map(
            static fn (array $parameter): string => (string) ($parameter['name'] ?? ''),
            is_array($exportParameters) ? $exportParameters : []
        );
        $this->assertContains('locale', $parameterNames);

        $diagnosticsParameters = $spec['paths']['/api/admin/error-log/entries']['get']['parameters'] ?? [];
        $diagnosticsParameterNames = array_map(
            static fn (array $parameter): string => (string) ($parameter['name'] ?? ''),
            is_array($diagnosticsParameters) ? $diagnosticsParameters : []
        );
        $this->assertContains('search', $diagnosticsParameterNames);
        $this->assertContains('type', $diagnosticsParameterNames);
    }

    public function test_swagger_ui_route_is_available(): void
    {
        Artisan::call('l5-swagger:generate');

        $this->get('/api/documentation')
            ->assertOk()
            ->assertSee('id="swagger-ui"', false);
    }
}
