<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class IptvTranscodeService
{
    private const SESSIONS_DIR = 'app/iptv-transcode';
    private const SESSION_TTL_SECONDS = 10800;
    private const MAX_ACTIVE_SESSIONS = 6;
    private const SESSION_ID_LENGTH = 24;
    private const PLAYLIST_FILE = 'playlist.m3u8';

    private ?bool $ffmpegAvailable = null;
    private ?string $ffmpegBinaryPath = null;
    private ?string $ffmpegVersionLine = null;

    public function __construct(private readonly IptvPlaylistService $iptvPlaylistService)
    {
    }

    /**
     * @return array{ffmpeg_available: bool, ffmpeg_version: string, max_sessions: int, session_ttl_seconds: int}
     */
    public function getCapabilities(): array
    {
        return [
            'ffmpeg_available' => $this->isFfmpegAvailable(),
            'ffmpeg_version' => $this->getFfmpegVersionLine(),
            'max_sessions' => self::MAX_ACTIVE_SESSIONS,
            'session_ttl_seconds' => self::SESSION_TTL_SECONDS,
        ];
    }

    public function isFfmpegAvailable(): bool
    {
        return $this->resolveFfmpegBinary() !== null;
    }

    public function getFfmpegVersionLine(): string
    {
        if ($this->ffmpegVersionLine !== null) {
            return $this->ffmpegVersionLine;
        }

        if (!$this->isFfmpegAvailable()) {
            $this->ffmpegVersionLine = '';
            return $this->ffmpegVersionLine;
        }

        if ($this->ffmpegVersionLine === null) {
            $this->ffmpegVersionLine = '';
        }

        return $this->ffmpegVersionLine;
    }

    /**
     * @return array{session_id: string, pid: int, profile: string, source_url: string}
     */
    public function startSession(string $url, string $profile = 'balanced'): array
    {
        $sourceUrl = $this->iptvPlaylistService->validateExternalUrl($url);

        if (!$this->isFfmpegAvailable()) {
            throw new RuntimeException('FFmpeg не установлен на сервере. Включите FFmpeg для режима совместимости.');
        }

        $resolvedProfile = $this->normalizeProfile($profile);

        $this->ensureSessionsRootExists();
        $this->cleanupExpiredSessions();
        $this->enforceSessionLimit();

        $sessionId = $this->generateSessionId();
        $sessionDir = $this->sessionDir($sessionId);
        if (!mkdir($sessionDir, 0775, true) && !is_dir($sessionDir)) {
            throw new RuntimeException('Не удалось создать директорию транскодера.');
        }

        $playlistPath = $this->playlistPath($sessionId);
        $segmentPath = $sessionDir . DIRECTORY_SEPARATOR . 'segment_%05d.ts';
        $logPath = $sessionDir . DIRECTORY_SEPARATOR . 'ffmpeg.log';

        $ffmpegBinary = $this->resolveFfmpegBinary();
        if ($ffmpegBinary === null) {
            throw new RuntimeException('FFmpeg не установлен на сервере. Включите FFmpeg для режима совместимости.');
        }

        try {
            $commandParts = $this->buildFfmpegCommandParts(
                $sourceUrl,
                $resolvedProfile,
                $playlistPath,
                $segmentPath,
                $ffmpegBinary
            );
            $pid = $this->spawnFfmpegProcess($commandParts, $logPath);
        } catch (RuntimeException $exception) {
            $this->deleteDirectory($sessionDir);
            throw new RuntimeException('Не удалось запустить FFmpeg-процесс для транскодирования: ' . $exception->getMessage());
        }

        if ($pid <= 1) {
            $this->deleteDirectory($sessionDir);
            throw new RuntimeException('Сервер не смог корректно запустить FFmpeg-процесс.');
        }

        $now = time();
        $metadata = [
            'session_id' => $sessionId,
            'pid' => $pid,
            'profile' => $resolvedProfile,
            'source_url' => $sourceUrl,
            'created_at' => $now,
            'last_access_at' => $now,
        ];

        $this->writeMetadata($sessionId, $metadata);
        return $metadata;
    }

    public function waitForPlaylist(string $sessionId, int $timeoutSeconds = 6): bool
    {
        $deadline = microtime(true) + max(1, $timeoutSeconds);
        $path = $this->playlistPath($sessionId);

        while (microtime(true) < $deadline) {
            if (is_file($path) && filesize($path) > 0) {
                return true;
            }

            usleep(200000);
        }

        return is_file($path) && filesize($path) > 0;
    }

    public function getPlaylistPath(string $sessionId): ?string
    {
        if (!$this->isValidSessionId($sessionId) || !$this->hasSession($sessionId)) {
            return null;
        }

        $path = $this->playlistPath($sessionId);
        if (!is_file($path)) {
            return null;
        }

        $this->touchSession($sessionId);
        return $path;
    }

    public function getSegmentPath(string $sessionId, string $segment): ?string
    {
        if (
            !$this->isValidSessionId($sessionId)
            || !$this->hasSession($sessionId)
            || !preg_match('/^segment_[0-9]{5}\.ts$/', $segment)
        ) {
            return null;
        }

        $path = $this->sessionDir($sessionId) . DIRECTORY_SEPARATOR . $segment;
        if (!is_file($path)) {
            return null;
        }

        $this->touchSession($sessionId);
        return $path;
    }

    public function stopSession(string $sessionId): void
    {
        if (!$this->isValidSessionId($sessionId)) {
            return;
        }

        $metadata = $this->readMetadata($sessionId);
        if (is_array($metadata)) {
            $pid = (int) ($metadata['pid'] ?? 0);
            if ($pid > 1) {
                $this->terminateProcess($pid);
            }
        }

        $this->deleteDirectory($this->sessionDir($sessionId));
    }

    public function cleanupExpiredSessions(): void
    {
        $root = $this->sessionsRoot();
        if (!is_dir($root)) {
            return;
        }

        $threshold = time() - self::SESSION_TTL_SECONDS;
        foreach ($this->listSessionIds() as $sessionId) {
            $metadata = $this->readMetadata($sessionId);
            if (!is_array($metadata)) {
                $this->deleteDirectory($this->sessionDir($sessionId));
                continue;
            }

            $lastAccess = (int) ($metadata['last_access_at'] ?? 0);
            if ($lastAccess < $threshold) {
                $this->stopSession($sessionId);
            }
        }
    }

    private function normalizeProfile(string $profile): string
    {
        $normalized = strtolower(trim($profile));
        return in_array($normalized, ['fast', 'balanced', 'stable'], true) ? $normalized : 'balanced';
    }

    /**
     * @return array{video_bitrate: string, maxrate: string, bufsize: string, audio_bitrate: string, hls_time: int, hls_list_size: int, preset: string}
     */
    private function profileSettings(string $profile): array
    {
        return match ($profile) {
            'fast' => [
                'video_bitrate' => '1800k',
                'maxrate' => '2300k',
                'bufsize' => '3600k',
                'audio_bitrate' => '128k',
                'hls_time' => 2,
                'hls_list_size' => 8,
                'preset' => 'veryfast',
            ],
            'stable' => [
                'video_bitrate' => '900k',
                'maxrate' => '1300k',
                'bufsize' => '2800k',
                'audio_bitrate' => '96k',
                'hls_time' => 4,
                'hls_list_size' => 12,
                'preset' => 'superfast',
            ],
            default => [
                'video_bitrate' => '1200k',
                'maxrate' => '1800k',
                'bufsize' => '3200k',
                'audio_bitrate' => '96k',
                'hls_time' => 3,
                'hls_list_size' => 10,
                'preset' => 'veryfast',
            ],
        };
    }

    private function buildFfmpegCommandParts(
        string $sourceUrl,
        string $profile,
        string $playlistPath,
        string $segmentPath,
        string $ffmpegBinary
    ): array {
        $settings = $this->profileSettings($profile);

        return [
            $ffmpegBinary,
            '-hide_banner',
            '-nostdin',
            '-loglevel', 'warning',
            '-analyzeduration', '20000000',
            '-probesize', '20000000',
            '-thread_queue_size', '1024',
            '-fflags', '+genpts+discardcorrupt',
            '-max_interleave_delta', '0',
            '-reconnect', '1',
            '-reconnect_streamed', '1',
            '-reconnect_delay_max', '2',
            '-rw_timeout', '15000000',
            '-http_persistent', '0',
            '-i', $sourceUrl,
            '-map', '0:v:0?',
            '-map', '0:a:0?',
            '-sn',
            '-dn',
            '-c:v', 'libx264',
            '-preset', $settings['preset'],
            '-tune', 'zerolatency',
            '-vf', 'scale=w=1280:h=720:force_original_aspect_ratio=decrease:force_divisible_by=2',
            '-profile:v', 'main',
            '-pix_fmt', 'yuv420p',
            '-g', '50',
            '-keyint_min', '25',
            '-sc_threshold', '0',
            '-b:v', $settings['video_bitrate'],
            '-maxrate', $settings['maxrate'],
            '-bufsize', $settings['bufsize'],
            '-c:a', 'aac',
            '-b:a', $settings['audio_bitrate'],
            '-ac', '2',
            '-ar', '48000',
            '-max_muxing_queue_size', '2048',
            '-f', 'hls',
            '-hls_time', (string) $settings['hls_time'],
            '-hls_list_size', (string) $settings['hls_list_size'],
            '-hls_flags', 'delete_segments+append_list+independent_segments',
            '-hls_segment_filename', $segmentPath,
            $playlistPath,
        ];
    }

    private function resolveFfmpegBinary(): ?string
    {
        if ($this->ffmpegAvailable === true && is_string($this->ffmpegBinaryPath) && $this->ffmpegBinaryPath !== '') {
            return $this->ffmpegBinaryPath;
        }

        foreach ($this->ffmpegBinaryCandidates() as $candidate) {
            $probe = new Process([$candidate, '-version']);
            $probe->setTimeout(20);
            $probe->setIdleTimeout(20);

            try {
                $probe->run();
            } catch (Throwable) {
                continue;
            }

            if (!$probe->isSuccessful()) {
                continue;
            }

            $this->ffmpegAvailable = true;
            $this->ffmpegBinaryPath = $candidate;
            $lines = preg_split('/\R/', trim($probe->getOutput())) ?: [];
            $this->ffmpegVersionLine = (string) ($lines[0] ?? '');
            return $this->ffmpegBinaryPath;
        }

        $this->ffmpegAvailable = false;
        $this->ffmpegBinaryPath = null;
        $this->ffmpegVersionLine = '';
        return null;
    }

    /**
     * @return string[]
     */
    private function ffmpegBinaryCandidates(): array
    {
        $configured = trim((string) env('IPTV_FFMPEG_BIN', 'ffmpeg'));
        $candidates = [$configured !== '' ? $configured : 'ffmpeg', 'ffmpeg'];

        if (DIRECTORY_SEPARATOR === '\\') {
            $candidates[] = 'ffmpeg.exe';
        }

        $unique = [];
        foreach ($candidates as $candidate) {
            $normalized = trim((string) $candidate);
            if ($normalized === '' || in_array($normalized, $unique, true)) {
                continue;
            }

            $unique[] = $normalized;
        }

        return $unique;
    }

    /**
     * @param string[] $commandParts
     */
    private function spawnFfmpegProcess(array $commandParts, string $logPath): int
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return $this->spawnFfmpegProcessOnWindows($commandParts, $logPath);
        }

        return $this->spawnFfmpegProcessOnUnix($commandParts, $logPath);
    }

    /**
     * @param string[] $commandParts
     */
    private function spawnFfmpegProcessOnUnix(array $commandParts, string $logPath): int
    {
        $escaped = implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $commandParts));
        $shellCommand = 'nohup ' . $escaped . ' > ' . escapeshellarg($logPath) . ' 2>&1 & echo $!';

        $process = Process::fromShellCommandline($shellCommand);
        $process->setTimeout(20);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('FFmpeg unix spawn failed.');
        }

        return (int) trim($process->getOutput());
    }

    /**
     * @param string[] $commandParts
     */
    private function spawnFfmpegProcessOnWindows(array $commandParts, string $logPath): int
    {
        $commandParts = array_map([$this, 'normalizeWindowsCommandPart'], $commandParts);
        $directDiagnostics = '';
        $directPid = $this->trySpawnWindowsProcessDirect($commandParts, $directDiagnostics);
        if ($directPid > 1) {
            return $directPid;
        }

        $wmicDiagnostics = '';
        $wmicPid = $this->trySpawnWindowsProcessViaWmic($commandParts, $wmicDiagnostics);
        if ($wmicPid > 1) {
            return $wmicPid;
        }

        $existingPids = $this->listWindowsFfmpegPids();
        $escapedCommand = implode(' ', array_map([$this, 'escapeWindowsCmdArgument'], $commandParts));
        // Windows cmd redirection (1>/2>) can break with strict quoting in some environments.
        // Run without shell redirection to keep spawn syntax robust.
        $startCommand = 'start "" /B ' . $escapedCommand;
        $cmdBinary = $this->resolveWindowsSystemBinary('cmd.exe') ?? 'cmd.exe';

        $process = new Process([
            $cmdBinary,
            '/V:OFF',
            '/S',
            '/C',
            '"' . $startCommand . '"',
        ]);

        $process->setTimeout(15);
        $process->run();

        if (!$process->isSuccessful()) {
            $details = trim($process->getErrorOutput() ?: $process->getOutput());
            $directPart = $directDiagnostics !== '' ? ' direct=' . $directDiagnostics : '';
            $wmicPart = $wmicDiagnostics !== '' ? ' wmic=' . $wmicDiagnostics : '';
            throw new RuntimeException('FFmpeg windows spawn failed.' . $directPart . $wmicPart . ' cmd=' . $startCommand . ($details !== '' ? ' ' . $details : ''));
        }

        $pid = $this->waitForNewWindowsFfmpegPid($existingPids, 8000);
        if ($pid <= 1) {
            $directPart = $directDiagnostics !== '' ? ' direct=' . $directDiagnostics : '';
            $wmicPart = $wmicDiagnostics !== '' ? ' wmic=' . $wmicDiagnostics : '';
            throw new RuntimeException('FFmpeg windows spawn returned invalid PID.' . $directPart . $wmicPart . ' Не удалось определить PID ffmpeg через tasklist.');
        }

        return $pid;
    }

    /**
     * @param string[] $commandParts
     */
    private function trySpawnWindowsProcessDirect(array $commandParts, string &$diagnostics): int
    {
        $diagnostics = '';
        $existingPids = $this->listWindowsFfmpegPids();

        $process = new Process($commandParts);
        $process->disableOutput();
        $process->setTimeout(15);
        $process->setIdleTimeout(15);
        $process->setOptions([
            'create_new_console' => true,
            'create_process_group' => true,
        ]);

        try {
            $process->start();
        } catch (Throwable $exception) {
            $diagnostics = 'direct-exception: ' . trim($exception->getMessage());
            return 0;
        }

        usleep(350000);

        if (!$process->isRunning()) {
            $exitCode = $process->getExitCode();
            $diagnostics = 'direct-exit=' . ($exitCode === null ? 'unknown' : (string) $exitCode);
            return 0;
        }

        $pid = (int) ($process->getPid() ?? 0);
        if ($pid > 1) {
            return $pid;
        }

        $pid = $this->waitForNewWindowsFfmpegPid($existingPids, 5000);
        if ($pid > 1) {
            return $pid;
        }

        $diagnostics = 'direct-no-pid';
        return 0;
    }

    private function escapeWindowsCmdArgument(string $value): string
    {
        $escaped = str_replace(['^', '%', '"'], ['^^', '^%', '""'], $value);
        return '"' . $escaped . '"';
    }

    private function normalizeWindowsCommandPart(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $value;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $trimmed) === 1 || str_starts_with($trimmed, '\\\\')) {
            return str_replace('/', '\\', $trimmed);
        }

        return $value;
    }

    /**
     * @param string[] $commandParts
     */
    private function trySpawnWindowsProcessViaWmic(array $commandParts, string &$diagnostics): int
    {
        $diagnostics = '';
        $existingPids = $this->listWindowsFfmpegPids();
        $wmicBinary = $this->resolveWindowsSystemBinary('wbem\\wmic.exe')
            ?? $this->resolveWindowsSystemBinary('wmic.exe');

        if ($wmicBinary === null) {
            $diagnostics = 'wmic-not-found';
            return 0;
        }

        $commandLine = implode(' ', array_map([$this, 'escapeWindowsWmicArgument'], $commandParts));
        $process = new Process([$wmicBinary, 'process', 'call', 'create', $commandLine]);
        $process->setTimeout(20);
        $process->setIdleTimeout(20);

        try {
            $process->run();
        } catch (Throwable $exception) {
            $diagnostics = 'wmic-exception: ' . trim($exception->getMessage());
            return 0;
        }

        $combinedOutput = $this->normalizeWindowsProcessOutput($process->getOutput() . "\n" . $process->getErrorOutput());
        $combinedOutput = trim($combinedOutput);
        if (!$process->isSuccessful()) {
            $diagnostics = 'wmic-failed: ' . $combinedOutput;
            return 0;
        }

        if (preg_match('/ReturnValue\s*=\s*(\d+)/i', $combinedOutput, $returnValueMatch) === 1) {
            $returnValue = (int) $returnValueMatch[1];
            if ($returnValue !== 0) {
                $diagnostics = 'wmic-return=' . $returnValue . ': ' . $combinedOutput;
                return 0;
            }
        }

        if (preg_match('/ProcessId\s*=\s*(\d+)/i', $combinedOutput, $processIdMatch) === 1) {
            $pid = (int) $processIdMatch[1];
            if ($pid > 1) {
                return $pid;
            }
        }

        $pid = $this->waitForNewWindowsFfmpegPid($existingPids, 5000);
        if ($pid > 1) {
            return $pid;
        }

        $diagnostics = 'wmic-no-pid: ' . $combinedOutput;
        return 0;
    }

    private function escapeWindowsWmicArgument(string $value): string
    {
        $escaped = str_replace('"', '\"', $value);
        return '"' . $escaped . '"';
    }

    private function normalizeWindowsProcessOutput(string $output): string
    {
        if ($output === '') {
            return '';
        }

        if (str_contains($output, "\0")) {
            $decoded = @iconv('UTF-16LE', 'UTF-8//IGNORE', $output);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }

            return str_replace("\0", '', $output);
        }

        return $output;
    }

    /**
     * @return int[]
     */
    private function listWindowsFfmpegPids(): array
    {
        $tasklistBinary = $this->resolveWindowsSystemBinary('tasklist.exe') ?? 'tasklist';
        $process = new Process([$tasklistBinary, '/FI', 'IMAGENAME eq ffmpeg.exe', '/NH', '/FO', 'CSV']);
        $process->setTimeout(4);
        $process->setIdleTimeout(4);

        try {
            $process->run();
        } catch (Throwable) {
            return [];
        }

        if (!$process->isSuccessful()) {
            return [];
        }

        $pids = [];
        $lines = preg_split('/\R/', trim($process->getOutput())) ?: [];
        foreach ($lines as $line) {
            $row = str_getcsv(trim($line));
            if (!is_array($row) || count($row) < 2) {
                continue;
            }

            $pid = (int) trim((string) $row[1], "\" \t\n\r\0\x0B");
            if ($pid > 1) {
                $pids[] = $pid;
            }
        }

        return array_values(array_unique($pids));
    }

    /**
     * @param int[] $existingPids
     */
    private function waitForNewWindowsFfmpegPid(array $existingPids, int $timeoutMs): int
    {
        $known = array_fill_keys(array_map('intval', $existingPids), true);
        $deadline = microtime(true) + (max(1000, $timeoutMs) / 1000);

        while (microtime(true) < $deadline) {
            $current = $this->listWindowsFfmpegPids();
            foreach ($current as $pid) {
                if (!isset($known[$pid])) {
                    return $pid;
                }
            }

            usleep(200000);
        }

        return 0;
    }

    private function resolveWindowsSystemBinary(string $binaryName): ?string
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return null;
        }

        $systemRoot = rtrim((string) (getenv('SystemRoot') ?: getenv('WINDIR') ?: 'C:\\Windows'), '\\/');
        $candidates = [];

        if (strtolower($binaryName) === 'cmd.exe') {
            $comSpec = trim((string) (getenv('ComSpec') ?: getenv('COMSPEC') ?: ''));
            if ($comSpec !== '') {
                $candidates[] = $comSpec;
            }
        }

        $candidates[] = $systemRoot . '\\System32\\' . $binaryName;
        $candidates[] = $systemRoot . '\\Sysnative\\' . $binaryName;
        $candidates[] = $systemRoot . '\\SysWOW64\\' . $binaryName;
        $candidates[] = $binaryName;

        $checked = [];
        foreach ($candidates as $candidate) {
            $binary = trim((string) $candidate);
            if ($binary === '' || in_array($binary, $checked, true)) {
                continue;
            }

            $checked[] = $binary;
            if (str_contains($binary, '\\') || str_contains($binary, '/')) {
                if (is_file($binary)) {
                    return $binary;
                }

                continue;
            }

            $probe = new Process([$binary, '/?']);
            $probe->setTimeout(2);
            $probe->setIdleTimeout(2);

            try {
                $probe->run();
            } catch (Throwable) {
                continue;
            }

            if ($probe->isSuccessful()) {
                return $binary;
            }
        }

        return null;
    }

    private function enforceSessionLimit(): void
    {
        $sessions = [];
        foreach ($this->listSessionIds() as $sessionId) {
            $metadata = $this->readMetadata($sessionId);
            if (!is_array($metadata)) {
                continue;
            }

            $sessions[] = [
                'id' => $sessionId,
                'last_access_at' => (int) ($metadata['last_access_at'] ?? 0),
            ];
        }

        if (count($sessions) < self::MAX_ACTIVE_SESSIONS) {
            return;
        }

        usort($sessions, static fn (array $a, array $b): int => $a['last_access_at'] <=> $b['last_access_at']);
        while (count($sessions) >= self::MAX_ACTIVE_SESSIONS) {
            $oldest = array_shift($sessions);
            if (!$oldest) {
                break;
            }

            $this->stopSession((string) $oldest['id']);
        }
    }

    private function touchSession(string $sessionId): void
    {
        $metadata = $this->readMetadata($sessionId);
        if (!is_array($metadata)) {
            return;
        }

        $metadata['last_access_at'] = time();
        try {
            $this->writeMetadata($sessionId, $metadata);
        } catch (Throwable) {
            // Non-blocking: playback endpoints must not fail because of metadata write permissions.
        }
    }

    private function hasSession(string $sessionId): bool
    {
        return is_array($this->readMetadata($sessionId));
    }

    /**
     * @return string[]
     */
    private function listSessionIds(): array
    {
        $root = $this->sessionsRoot();
        if (!is_dir($root)) {
            return [];
        }

        $directories = glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        $sessions = [];

        foreach ($directories as $path) {
            $sessionId = basename($path);
            if ($this->isValidSessionId($sessionId)) {
                $sessions[] = $sessionId;
            }
        }

        return $sessions;
    }

    private function generateSessionId(): string
    {
        do {
            $sessionId = Str::lower(Str::random(self::SESSION_ID_LENGTH));
        } while (is_dir($this->sessionDir($sessionId)));

        return $sessionId;
    }

    private function isValidSessionId(string $sessionId): bool
    {
        return preg_match('/^[a-z0-9]{' . self::SESSION_ID_LENGTH . '}$/', $sessionId) === 1;
    }

    private function ensureSessionsRootExists(): void
    {
        $root = $this->sessionsRoot();
        if (is_dir($root)) {
            return;
        }

        if (!mkdir($root, 0775, true) && !is_dir($root)) {
            throw new RuntimeException('Не удалось создать корневую директорию IPTV-транскодера.');
        }
    }

    private function sessionsRoot(): string
    {
        return storage_path(self::SESSIONS_DIR);
    }

    private function sessionDir(string $sessionId): string
    {
        return $this->sessionsRoot() . DIRECTORY_SEPARATOR . $sessionId;
    }

    private function playlistPath(string $sessionId): string
    {
        return $this->sessionDir($sessionId) . DIRECTORY_SEPARATOR . self::PLAYLIST_FILE;
    }

    private function metadataPath(string $sessionId): string
    {
        return $this->sessionDir($sessionId) . DIRECTORY_SEPARATOR . 'session.json';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readMetadata(string $sessionId): ?array
    {
        $path = $this->metadataPath($sessionId);
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function writeMetadata(string $sessionId, array $metadata): void
    {
        $path = $this->metadataPath($sessionId);
        file_put_contents($path, json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function terminateProcess(int $pid): void
    {
        if ($pid <= 1) {
            return;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            $taskkillBinary = $this->resolveWindowsSystemBinary('taskkill.exe') ?? 'taskkill';
            $process = new Process([$taskkillBinary, '/F', '/PID', (string) (int) $pid]);
            $process->setTimeout(4);
            $process->run();
            return;
        }

        if (function_exists('posix_kill')) {
            @posix_kill($pid, 15);
            usleep(250000);
            if ($this->isProcessRunning($pid)) {
                @posix_kill($pid, 9);
            }
            return;
        }

        $process = Process::fromShellCommandline('kill -TERM ' . (int) $pid);
        $process->setTimeout(4);
        $process->run();
    }

    private function isProcessRunning(int $pid): bool
    {
        if ($pid <= 1) {
            return false;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            $tasklistBinary = $this->resolveWindowsSystemBinary('tasklist.exe') ?? 'tasklist';
            $process = new Process([$tasklistBinary, '/FI', 'PID eq ' . (int) $pid, '/NH']);
            $process->setTimeout(4);
            $process->run();

            if (!$process->isSuccessful()) {
                return false;
            }

            $output = trim($process->getOutput());
            return $output !== '' && preg_match('/\b' . preg_quote((string) $pid, '/') . '\b/', $output) === 1;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        $process = Process::fromShellCommandline('ps -p ' . (int) $pid . ' -o pid=');
        $process->setTimeout(4);
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            @rmdir($directory);
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
