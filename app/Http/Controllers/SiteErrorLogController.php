<?php

namespace App\Http\Controllers;

use App\Services\SiteErrorLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiteErrorLogController extends Controller
{
    public function __construct(
        private readonly SiteErrorLogService $siteErrorLogService
    )
    {
    }

    public function preview(): JsonResponse
    {
        return response()->json([
            'data' => $this->siteErrorLogService->preview(),
        ]);
    }

    public function entries(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'type' => [
                'nullable',
                'string',
                Rule::in([
                    SiteErrorLogService::FILTER_TYPE_ALL,
                    SiteErrorLogService::TYPE_SERVER_EXCEPTION,
                    SiteErrorLogService::TYPE_CLIENT_ERROR,
                    SiteErrorLogService::TYPE_ANALYTICS_FAILURE,
                ]),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'data' => $this->siteErrorLogService->listEntries(
                $validated['search'] ?? null,
                (string) ($validated['type'] ?? SiteErrorLogService::FILTER_TYPE_ALL),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
            ),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'type' => [
                'nullable',
                'string',
                Rule::in([
                    SiteErrorLogService::FILTER_TYPE_ALL,
                    SiteErrorLogService::TYPE_SERVER_EXCEPTION,
                    SiteErrorLogService::TYPE_CLIENT_ERROR,
                    SiteErrorLogService::TYPE_ANALYTICS_FAILURE,
                ]),
            ],
        ]);

        $payload = $this->siteErrorLogService->buildFilteredExport(
            $validated['search'] ?? null,
            (string) ($validated['type'] ?? SiteErrorLogService::FILTER_TYPE_ALL),
        );

        return response()->streamDownload(
            static function () use ($payload): void {
                echo (string) ($payload['content'] ?? '');
            },
            (string) ($payload['file_name'] ?? 'site-errors-filtered.log'),
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function download(): StreamedResponse|JsonResponse
    {
        if (!$this->siteErrorLogService->hasLogFile()) {
            return response()->json([
                'message' => 'Site error log file does not exist yet.',
            ], 404);
        }

        return response()->streamDownload(
            function (): void {
                $this->siteErrorLogService->streamLogToOutput();
            },
            $this->siteErrorLogService->logFileName(),
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function storeClientError(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'string', Rule::in(['runtime', 'promise', 'vue', 'http'])],
            'message' => ['required', 'string', 'max:4000'],
            'stack' => ['nullable', 'string', 'max:30000'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'route_name' => ['nullable', 'string', 'max:120'],
            'request_url' => ['nullable', 'string', 'max:2048'],
            'request_method' => ['nullable', 'string', 'max:16'],
            'status_code' => ['nullable', 'integer', 'min:0', 'max:999'],
            'source_file' => ['nullable', 'string', 'max:2048'],
            'source_line' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'source_column' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'context' => ['nullable', 'array'],
        ]);

        $userId = (int) ($request->user('sanctum')?->id ?? 0);

        $this->siteErrorLogService->logClientError(
            $validated,
            $request,
            $userId > 0 ? $userId : null
        );

        return response()->json([
            'message' => 'Client error accepted.',
        ], 201);
    }
}
