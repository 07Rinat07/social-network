<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class SiteErrorLogService
{
    public const ENTRY_MARKER = '=== SITE ERROR ENTRY ===';
    public const DEFAULT_PREVIEW_BYTES = 131072;
    public const DEFAULT_ROTATE_MAX_BYTES = 10485760;
    public const DEFAULT_ROTATE_MAX_AGE_DAYS = 30;
    public const FILTER_TYPE_ALL = 'all';
    public const TYPE_SERVER_EXCEPTION = 'server_exception';
    public const TYPE_CLIENT_ERROR = 'client_error';
    public const TYPE_ANALYTICS_FAILURE = 'analytics_failure';

    /**
     * Failure analytics events that should be mirrored into the lifetime text log.
     *
     * @var array<int, string>
     */
    protected array $trackedFailureEvents = [
        AnalyticsEvent::EVENT_MEDIA_UPLOAD_FAILED,
        AnalyticsEvent::EVENT_RADIO_PLAY_FAILED,
        AnalyticsEvent::EVENT_IPTV_DIRECT_FAILED,
        AnalyticsEvent::EVENT_IPTV_PROXY_FAILED,
        AnalyticsEvent::EVENT_IPTV_RELAY_FAILED,
        AnalyticsEvent::EVENT_IPTV_FFMPEG_FAILED,
    ];

    public function logThrowable(Throwable $throwable, ?Request $request = null): void
    {
        $lines = [
            'Type: server_exception',
            'Environment: ' . app()->environment(),
            'Exception: ' . $throwable::class,
            'Message: ' . trim($throwable->getMessage()),
            'File: ' . $throwable->getFile() . ':' . $throwable->getLine(),
        ];

        $requestContext = $this->buildRequestContext($request);
        if ($requestContext !== []) {
            $lines[] = 'Request:';
            $lines[] = $this->encodePrettyJson($requestContext);
        }

        $trace = trim($throwable->getTraceAsString());
        if ($trace !== '') {
            $lines[] = 'Trace:';
            $lines[] = $trace;
        }

        $this->appendEntry($lines);
    }

    public function shouldLogAnalyticsFailure(string $eventName): bool
    {
        return in_array(trim(mb_strtolower($eventName)), $this->trackedFailureEvents, true);
    }

    public function logAnalyticsFailure(AnalyticsEvent $event): void
    {
        if (!$this->shouldLogAnalyticsFailure((string) $event->event_name)) {
            return;
        }

        $context = $this->normalizeContext($event->context);

        $lines = [
            'Type: analytics_failure',
            'Environment: ' . app()->environment(),
            'Feature: ' . (string) $event->feature,
            'Event: ' . (string) $event->event_name,
            'User ID: ' . ($event->user_id ?: 'guest'),
            'Entity Type: ' . ($event->entity_type ?: 'n/a'),
            'Entity ID: ' . ($event->entity_id ?: 'n/a'),
            'Entity Key: ' . ($event->entity_key ?: 'n/a'),
            'Session ID: ' . ($event->session_id ?: 'n/a'),
            'Metric Value: ' . ($event->metric_value !== null ? (string) $event->metric_value : 'n/a'),
            'Occurred At: ' . optional($event->created_at)->toIso8601String(),
        ];

        if ($context !== []) {
            $lines[] = 'Context:';
            $lines[] = $this->encodePrettyJson($context);
        }

        $this->appendEntry($lines);
    }

    public function logClientError(array $payload, ?Request $request = null, ?int $userId = null): void
    {
        $context = $this->normalizeContext($payload['context'] ?? null);

        $lines = [
            'Type: client_error',
            'Environment: ' . app()->environment(),
            'Kind: ' . trim((string) ($payload['kind'] ?? 'runtime')),
            'User ID: ' . ($userId ?: 'guest'),
            'Message: ' . trim((string) ($payload['message'] ?? 'Unknown client error')),
            'Page URL: ' . trim((string) ($payload['page_url'] ?? '')),
            'Route Name: ' . trim((string) ($payload['route_name'] ?? '')),
            'Request URL: ' . trim((string) ($payload['request_url'] ?? '')),
            'Request Method: ' . trim((string) ($payload['request_method'] ?? '')),
            'Status Code: ' . (($payload['status_code'] ?? null) !== null ? (string) $payload['status_code'] : ''),
            'Source: ' . $this->buildSourceLocation($payload),
        ];

        $requestContext = $this->buildRequestContext($request);
        if ($requestContext !== []) {
            $lines[] = 'Reporter Request:';
            $lines[] = $this->encodePrettyJson($requestContext);
        }

        if ($context !== []) {
            $lines[] = 'Context:';
            $lines[] = $this->encodePrettyJson($context);
        }

        $stack = trim((string) ($payload['stack'] ?? ''));
        if ($stack !== '') {
            $lines[] = 'Stack:';
            $lines[] = $stack;
        }

        $this->appendEntry($lines);
    }

    public function preview(int $maxBytes = self::DEFAULT_PREVIEW_BYTES): array
    {
        $path = $this->resolveLogPath();
        $archiveStats = $this->archiveStats();
        $exists = is_file($path);
        $size = $exists ? (int) (filesize($path) ?: 0) : 0;
        $updatedAt = $exists ? @filemtime($path) : false;
        $safeMaxBytes = max(1, $maxBytes);
        $preview = $exists ? $this->readTail($path, $safeMaxBytes) : '';

        return [
            'exists' => $exists,
            'file_name' => basename($path),
            'relative_path' => $this->relativeDisplayPath($path),
            'size_bytes' => $size,
            'updated_at' => $updatedAt ? Carbon::createFromTimestamp((int) $updatedAt)->toIso8601String() : null,
            'truncated' => $exists && $size > $safeMaxBytes,
            'preview' => $preview,
            'archive_count' => $archiveStats['count'],
            'archive_size_bytes' => $archiveStats['size_bytes'],
            'archive_relative_path' => $this->relativeDisplayPath($this->resolveArchiveDirectory()),
        ];
    }

    public function listEntries(?string $search = null, string $type = self::FILTER_TYPE_ALL, int $page = 1, int $perPage = 20): array
    {
        $normalizedType = $this->normalizeFilterType($type);
        $normalizedSearch = $this->normalizeSearchTerm($search);
        $safePerPage = max(1, min($perPage, 100));
        $safePage = max(1, $page);
        $entries = $this->filterEntries($normalizedSearch, $normalizedType);

        $total = count($entries);
        $lastPage = max(1, (int) ceil($total / $safePerPage));
        $currentPage = min($safePage, $lastPage);
        $offset = ($currentPage - 1) * $safePerPage;
        $items = array_slice($entries, $offset, $safePerPage);

        return [
            'items' => array_map(function (array $entry): array {
                unset($entry['searchable_text']);

                return $entry;
            }, $items),
            'meta' => [
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'per_page' => $safePerPage,
                'total' => $total,
                'from' => $total > 0 ? ($offset + 1) : 0,
                'to' => $total > 0 ? min($offset + count($items), $total) : 0,
            ],
            'filters' => [
                'search' => $normalizedSearch,
                'type' => $normalizedType,
            ],
        ];
    }

    public function buildFilteredExport(?string $search = null, string $type = self::FILTER_TYPE_ALL): array
    {
        $normalizedType = $this->normalizeFilterType($type);
        $normalizedSearch = $this->normalizeSearchTerm($search);
        $entries = $this->filterEntries($normalizedSearch, $normalizedType);

        $exportedAt = now()->toIso8601String();
        $filterParts = [
            'type=' . $normalizedType,
            'search=' . ($normalizedSearch !== '' ? $normalizedSearch : 'none'),
        ];

        $contentLines = [
            '# Site error log filtered export',
            'Exported At: ' . $exportedAt,
            'Filters: ' . implode(', ', $filterParts),
            'Matched Entries: ' . count($entries),
            '',
        ];

        if ($entries === []) {
            $contentLines[] = 'No matching log entries found.';
        } else {
            foreach ($entries as $entry) {
                $contentLines[] = (string) ($entry['raw'] ?? '');
                $contentLines[] = '';
            }
        }

        $content = implode("\n", $contentLines) . "\n";
        $timestamp = now()->format('Ymd_His');
        $typePart = $normalizedType !== self::FILTER_TYPE_ALL ? $normalizedType : 'all';
        $searchPart = $normalizedSearch !== '' ? Str::slug(Str::limit($normalizedSearch, 40, ''), '_') : 'all';
        if ($searchPart === '') {
            $searchPart = $normalizedSearch !== '' ? 'query' : 'all';
        }
        $fileName = "site-errors-filtered-{$typePart}-{$searchPart}-{$timestamp}.log";

        return [
            'file_name' => $fileName,
            'content' => $content,
            'matched_entries' => count($entries),
            'type' => $normalizedType,
            'search' => $normalizedSearch,
        ];
    }

    public function hasLogFile(): bool
    {
        return is_file($this->resolveLogPath());
    }

    public function logFileName(): string
    {
        return basename($this->resolveLogPath());
    }

    public function streamLogToOutput(int $chunkBytes = 65536): void
    {
        $path = $this->resolveLogPath();
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkBytes);
                if ($chunk === false) {
                    break;
                }

                echo $chunk;
            }
        } finally {
            fclose($handle);
        }
    }

    protected function appendEntry(array $lines): void
    {
        $path = $this->resolveLogPath();
        $directory = dirname($path);
        File::ensureDirectoryExists($directory);

        $timestamp = now()->toIso8601String();
        $normalizedLines = array_values(array_filter(array_map(function (mixed $line): string {
            $value = trim((string) $line);
            return $value;
        }, $lines), static fn (string $line): bool => $line !== ''));

        $payload = implode("\n", [
            self::ENTRY_MARKER,
            'Timestamp: ' . $timestamp,
            ...$normalizedLines,
            '',
        ]) . "\n";

        $this->withLogLock(function () use ($path, $payload): void {
            $this->rotateIfNeeded($path);
            file_put_contents($path, $payload, FILE_APPEND | LOCK_EX);
        });
    }

    protected function filterEntries(string $normalizedSearch, string $normalizedType): array
    {
        return array_values(array_filter(
            $this->readEntries(),
            function (array $entry) use ($normalizedSearch, $normalizedType): bool {
                if ($normalizedType !== self::FILTER_TYPE_ALL && ($entry['type'] ?? '') !== $normalizedType) {
                    return false;
                }

                if ($normalizedSearch === '') {
                    return true;
                }

                $searchableText = mb_strtolower((string) ($entry['searchable_text'] ?? ''));

                return str_contains($searchableText, $normalizedSearch);
            }
        ));
    }

    protected function readEntries(): array
    {
        $entries = [];

        foreach ($this->resolveReadableLogFiles() as $path) {
            foreach ($this->readEntriesFromFile($path) as $entry) {
                $entries[] = $entry;
            }
        }

        usort($entries, function (array $left, array $right): int {
            $leftTs = (string) ($left['timestamp'] ?? '');
            $rightTs = (string) ($right['timestamp'] ?? '');

            if ($leftTs === $rightTs) {
                return strcmp((string) ($right['id'] ?? ''), (string) ($left['id'] ?? ''));
            }

            return strcmp($rightTs, $leftTs);
        });

        return array_values(array_filter($entries));
    }

    protected function readEntriesFromFile(string $path): array
    {
        $content = $this->readFileContents($path);
        if ($content === '') {
            return [];
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        $chunks = preg_split('/^' . preg_quote(self::ENTRY_MARKER, '/') . '$/m', $normalized);
        if (!is_array($chunks)) {
            return [];
        }

        $entries = [];
        $sequence = 1;
        foreach ($chunks as $chunk) {
            $trimmed = trim($chunk);
            if ($trimmed === '') {
                continue;
            }

            $lines = array_merge(
                [self::ENTRY_MARKER],
                preg_split("/\n/", $trimmed) ?: []
            );

            $entry = $this->parseEntryLines($lines, $sequence);
            if ($entry !== null) {
                $entries[] = $entry;
                $sequence++;
            }
        }

        return $entries;
    }

    protected function parseEntryLines(array $lines, int $sequence): ?array
    {
        $normalizedLines = array_map(
            static fn (mixed $line): string => rtrim((string) $line),
            $lines
        );

        $raw = trim(implode("\n", $normalizedLines));
        if ($raw === '') {
            return null;
        }

        $fields = [];
        $fieldMap = [
            'Timestamp' => 'timestamp',
            'Type' => 'type',
            'Message' => 'message',
            'Exception' => 'exception',
            'File' => 'file',
            'Feature' => 'feature',
            'Event' => 'event',
            'Kind' => 'kind',
            'Status Code' => 'status_code',
            'Page URL' => 'page_url',
            'Request URL' => 'request_url',
            'Request Method' => 'request_method',
            'User ID' => 'user_id',
            'Source' => 'source',
            'Route Name' => 'route_name',
            'Environment' => 'environment',
            'Entity Type' => 'entity_type',
            'Entity ID' => 'entity_id',
            'Entity Key' => 'entity_key',
            'Session ID' => 'session_id',
            'Metric Value' => 'metric_value',
        ];

        foreach ($normalizedLines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed === self::ENTRY_MARKER) {
                continue;
            }

            foreach ($fieldMap as $prefix => $key) {
                $needle = $prefix . ':';
                if (str_starts_with($trimmed, $needle) && !array_key_exists($key, $fields)) {
                    $fields[$key] = trim(Str::after($trimmed, $needle));
                    break;
                }
            }
        }

        $type = $this->normalizeFilterType((string) ($fields['type'] ?? ''));
        $timestamp = $this->normalizeTimestamp($fields['timestamp'] ?? null);
        $message = trim((string) ($fields['message'] ?? ''));
        $exception = trim((string) ($fields['exception'] ?? ''));
        $event = trim((string) ($fields['event'] ?? ''));
        $kind = trim((string) ($fields['kind'] ?? ''));

        $headline = $message;
        if ($headline === '' && $type === self::TYPE_ANALYTICS_FAILURE) {
            $headline = $event !== '' ? $event : trim((string) ($fields['feature'] ?? ''));
        }
        if ($headline === '' && $type === self::TYPE_SERVER_EXCEPTION) {
            $headline = $exception;
        }
        if ($headline === '' && $type === self::TYPE_CLIENT_ERROR) {
            $headline = $kind !== '' ? $kind : 'client_error';
        }
        if ($headline === '') {
            $headline = 'Log entry #' . $sequence;
        }

        $summaryParts = array_values(array_filter([
            $exception !== '' ? $exception : null,
            trim((string) ($fields['feature'] ?? '')) !== '' ? trim((string) ($fields['feature'] ?? '')) : null,
            $event !== '' ? $event : null,
            trim((string) ($fields['status_code'] ?? '')) !== '' ? ('HTTP ' . trim((string) ($fields['status_code'] ?? ''))) : null,
            trim((string) ($fields['file'] ?? '')) !== '' ? trim((string) ($fields['file'] ?? '')) : null,
            trim((string) ($fields['request_url'] ?? '')) !== '' ? trim((string) ($fields['request_url'] ?? '')) : null,
        ]));

        return [
            'id' => sha1($sequence . '|' . $raw),
            'timestamp' => $timestamp,
            'type' => $type,
            'headline' => Str::limit($headline, 220, '...'),
            'message' => $message,
            'summary' => implode(' | ', array_slice($summaryParts, 0, 3)),
            'exception' => $exception,
            'file' => trim((string) ($fields['file'] ?? '')),
            'feature' => trim((string) ($fields['feature'] ?? '')),
            'event' => $event,
            'kind' => $kind,
            'status_code' => trim((string) ($fields['status_code'] ?? '')),
            'page_url' => trim((string) ($fields['page_url'] ?? '')),
            'request_url' => trim((string) ($fields['request_url'] ?? '')),
            'request_method' => trim((string) ($fields['request_method'] ?? '')),
            'user_id' => trim((string) ($fields['user_id'] ?? '')),
            'source' => trim((string) ($fields['source'] ?? '')),
            'route_name' => trim((string) ($fields['route_name'] ?? '')),
            'environment' => trim((string) ($fields['environment'] ?? '')),
            'entity_type' => trim((string) ($fields['entity_type'] ?? '')),
            'entity_id' => trim((string) ($fields['entity_id'] ?? '')),
            'entity_key' => trim((string) ($fields['entity_key'] ?? '')),
            'session_id' => trim((string) ($fields['session_id'] ?? '')),
            'metric_value' => trim((string) ($fields['metric_value'] ?? '')),
            'raw' => $raw,
            'searchable_text' => $raw,
        ];
    }

    protected function resolveLogPath(): string
    {
        $configuredPath = trim((string) config('logging.channels.site_errors.path', ''));

        return $configuredPath !== ''
            ? $configuredPath
            : storage_path('logs/site-errors.log');
    }

    protected function resolveArchiveDirectory(): string
    {
        $configuredPath = trim((string) config('logging.channels.site_errors.archive_path', ''));

        return $configuredPath !== ''
            ? $configuredPath
            : storage_path('logs/site-errors-archive');
    }

    protected function normalizeFilterType(string $type): string
    {
        $normalized = trim(mb_strtolower($type));

        return in_array($normalized, [
            self::FILTER_TYPE_ALL,
            self::TYPE_SERVER_EXCEPTION,
            self::TYPE_CLIENT_ERROR,
            self::TYPE_ANALYTICS_FAILURE,
        ], true)
            ? $normalized
            : self::FILTER_TYPE_ALL;
    }

    protected function normalizeSearchTerm(?string $search): string
    {
        return mb_strtolower(trim((string) $search));
    }

    protected function normalizeTimestamp(?string $timestamp): ?string
    {
        $value = trim((string) $timestamp);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (Throwable) {
            return $value;
        }
    }

    protected function relativeDisplayPath(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedBase = str_replace('\\', '/', base_path()) . '/';

        if (Str::startsWith($normalizedPath, $normalizedBase)) {
            return Str::after($normalizedPath, $normalizedBase);
        }

        return $normalizedPath;
    }

    protected function readTail(string $path, int $maxBytes): string
    {
        clearstatcache(true, $path);

        $size = (int) (filesize($path) ?: 0);
        if ($size <= 0) {
            return '';
        }

        $readLength = min(max(1, $maxBytes), $size);
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        try {
            if ($size > $readLength) {
                fseek($handle, -$readLength, SEEK_END);
            }

            $content = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }

        if (!is_string($content)) {
            return '';
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $content);

        if ($size > $readLength) {
            $markerOffset = strpos($normalized, self::ENTRY_MARKER);

            if ($markerOffset !== false) {
                $normalized = substr($normalized, $markerOffset);
            } else {
                $normalized = "[... older log entries omitted ...]\n" . ltrim($normalized);
            }
        }

        return trim($normalized);
    }

    protected function resolveReadableLogFiles(): array
    {
        $paths = [];
        $activePath = $this->resolveLogPath();
        if (is_file($activePath)) {
            $paths[] = $activePath;
        }

        foreach ($this->listArchiveFiles() as $archivePath) {
            $paths[] = $archivePath;
        }

        return array_values(array_unique($paths));
    }

    protected function listArchiveFiles(): array
    {
        $archiveDirectory = $this->resolveArchiveDirectory();
        if (!is_dir($archiveDirectory)) {
            return [];
        }

        $baseName = pathinfo($this->resolveLogPath(), PATHINFO_FILENAME);
        $files = [];

        foreach (File::files($archiveDirectory) as $file) {
            $fileName = $file->getFilename();
            if (!Str::startsWith($fileName, $baseName . '_')) {
                continue;
            }

            if (!Str::endsWith($fileName, ['.log', '.log.gz'])) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    protected function archiveStats(): array
    {
        $files = $this->listArchiveFiles();
        $sizeBytes = 0;

        foreach ($files as $path) {
            $sizeBytes += (int) (@filesize($path) ?: 0);
        }

        return [
            'count' => count($files),
            'size_bytes' => $sizeBytes,
        ];
    }

    protected function readFileContents(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        if (Str::endsWith(mb_strtolower($path), '.gz')) {
            $raw = @file_get_contents($path);
            if (!is_string($raw) || $raw === '') {
                return '';
            }

            $decoded = function_exists('gzdecode') ? @gzdecode($raw) : false;

            return is_string($decoded) ? $decoded : '';
        }

        $raw = @file_get_contents($path);

        return is_string($raw) ? $raw : '';
    }

    protected function rotateIfNeeded(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        clearstatcache(true, $path);

        $fileSize = (int) (@filesize($path) ?: 0);
        $modifiedAt = (int) (@filemtime($path) ?: 0);
        $maxBytes = max(0, (int) config('logging.channels.site_errors.rotate_max_bytes', self::DEFAULT_ROTATE_MAX_BYTES));
        $maxAgeDays = max(0, (int) config('logging.channels.site_errors.rotate_max_age_days', self::DEFAULT_ROTATE_MAX_AGE_DAYS));

        $shouldRotateBySize = $maxBytes > 0 && $fileSize >= $maxBytes;
        $shouldRotateByAge = $maxAgeDays > 0
            && $modifiedAt > 0
            && Carbon::createFromTimestamp($modifiedAt)->lte(now()->subDays($maxAgeDays));

        if (!$shouldRotateBySize && !$shouldRotateByAge) {
            return;
        }

        $reason = $shouldRotateBySize ? 'size' : 'age';
        $archivePath = $this->resolveNextArchivePath($reason, $modifiedAt > 0 ? $modifiedAt : time());
        File::ensureDirectoryExists(dirname($archivePath));

        if (!@rename($path, $archivePath)) {
            File::copy($path, $archivePath);
            @unlink($path);
        }

        if ($this->shouldCompressArchives()) {
            $this->compressArchiveFile($archivePath);
        }
    }

    protected function resolveNextArchivePath(string $reason, int $timestamp): string
    {
        $archiveDirectory = $this->resolveArchiveDirectory();
        $baseName = pathinfo($this->resolveLogPath(), PATHINFO_FILENAME);
        $datePart = Carbon::createFromTimestamp($timestamp)->format('Ymd_His');
        $candidate = $archiveDirectory . DIRECTORY_SEPARATOR . "{$baseName}_{$datePart}_{$reason}.log";
        $suffix = 1;

        while (is_file($candidate) || is_file($candidate . '.gz')) {
            $candidate = $archiveDirectory . DIRECTORY_SEPARATOR . "{$baseName}_{$datePart}_{$reason}_{$suffix}.log";
            $suffix++;
        }

        return $candidate;
    }

    protected function shouldCompressArchives(): bool
    {
        return (bool) config('logging.channels.site_errors.compress_archives', true)
            && function_exists('gzencode');
    }

    protected function compressArchiveFile(string $path): void
    {
        if (!is_file($path) || Str::endsWith(mb_strtolower($path), '.gz')) {
            return;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw)) {
            return;
        }

        $encoded = @gzencode($raw, 9);
        if (!is_string($encoded)) {
            return;
        }

        $compressedPath = $path . '.gz';
        if (@file_put_contents($compressedPath, $encoded, LOCK_EX) !== false) {
            @unlink($path);
        }
    }

    protected function withLogLock(callable $callback): mixed
    {
        $lockPath = $this->resolveLogPath() . '.lock';
        $lockHandle = @fopen($lockPath, 'c+');

        if ($lockHandle === false) {
            return $callback();
        }

        try {
            if (!@flock($lockHandle, LOCK_EX)) {
                return $callback();
            }

            return $callback();
        } finally {
            @flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    protected function buildRequestContext(?Request $request): array
    {
        if (!$request) {
            return [];
        }

        $userId = $request->user()?->id;

        return array_filter([
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'route_name' => optional($request->route())->getName(),
            'ip' => $request->ip(),
            'user_id' => $userId ? (int) $userId : null,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, '...'),
            'referer' => Str::limit((string) $request->headers->get('referer', ''), 500, '...'),
            'origin' => Str::limit((string) $request->headers->get('origin', ''), 300, '...'),
            'query' => $this->normalizeContext($request->query()),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    protected function buildSourceLocation(array $payload): string
    {
        $file = trim((string) ($payload['source_file'] ?? ''));
        $line = $payload['source_line'] ?? null;
        $column = $payload['source_column'] ?? null;

        if ($file === '') {
            return 'n/a';
        }

        $location = $file;
        if ($line !== null) {
            $location .= ':' . $line;
        }
        if ($column !== null) {
            $location .= ':' . $column;
        }

        return $location;
    }

    protected function normalizeContext(mixed $value, int $depth = 0): array
    {
        if (!is_array($value) || $depth > 3) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            $normalizedKey = trim(Str::limit((string) $key, 64, ''));
            if ($normalizedKey === '') {
                continue;
            }

            if (is_array($item)) {
                $nested = $this->normalizeContext($item, $depth + 1);
                if ($nested !== []) {
                    $result[$normalizedKey] = $nested;
                }

                continue;
            }

            if (is_bool($item) || is_int($item) || is_float($item)) {
                $result[$normalizedKey] = $item;
                continue;
            }

            if ($item === null) {
                continue;
            }

            $result[$normalizedKey] = Str::limit(trim((string) $item), 1000, '...');
        }

        return $result;
    }

    protected function encodePrettyJson(array $payload): string
    {
        $encoded = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return is_string($encoded) ? $encoded : '{}';
    }
}
