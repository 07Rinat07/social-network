<?php

namespace App\Services;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class UploadedVideoTranscodeService
{
    private const DEFAULT_HEAVY_VIDEO_THRESHOLD_BYTES = 25 * 1024 * 1024;

    /**
     * Convert uploaded video to browser-friendly MP4 when needed.
     *
     * @return array{path: string, original_name: string, size: int, mime_type: string}|null
     */
    public function maybeConvertToBrowserFriendlyMp4(
        UploadedFile $file,
        int $heavyVideoThresholdBytes = self::DEFAULT_HEAVY_VIDEO_THRESHOLD_BYTES
    ): ?array {
        if (!$this->shouldConvertToMp4($file, $heavyVideoThresholdBytes)) {
            return null;
        }

        $binary = $this->resolveFfmpegBinary();
        if ($binary === null) {
            return null;
        }

        $sourcePath = trim((string) $file->getRealPath());
        if ($sourcePath === '' || !is_file($sourcePath)) {
            return null;
        }

        $outputPath = tempnam(sys_get_temp_dir(), 'upload-mp4-');
        if (!is_string($outputPath) || $outputPath === '') {
            return null;
        }

        @unlink($outputPath);
        $outputPath .= '.mp4';

        $process = new Process([
            $binary,
            '-hide_banner',
            '-nostdin',
            '-loglevel', 'error',
            '-y',
            '-i', $sourcePath,
            '-map', '0:v:0?',
            '-map', '0:a:0?',
            '-c:v', 'libx264',
            '-preset', 'veryfast',
            '-crf', '23',
            '-profile:v', 'main',
            '-pix_fmt', 'yuv420p',
            '-movflags', '+faststart',
            '-c:a', 'aac',
            '-b:a', '128k',
            $outputPath,
        ]);

        $process->setTimeout(420);
        $process->setIdleTimeout(420);

        try {
            $process->run();
        } catch (Throwable) {
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }

            return null;
        }

        if (!$process->isSuccessful() || !is_file($outputPath) || (int) filesize($outputPath) <= 0) {
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }

            return null;
        }

        return [
            'path' => $outputPath,
            'original_name' => $this->replaceFileExtensionWithMp4((string) $file->getClientOriginalName()),
            'size' => (int) (filesize($outputPath) ?: 0),
            'mime_type' => 'video/mp4',
        ];
    }

    /**
     * Persist a local temporary file to preferred disk with fallback to public disk.
     *
     * @return array{path: string, disk: string}
     */
    public function storeTemporaryFileWithFallback(string $localPath, string $folder, string $preferredDisk): array
    {
        $file = new File($localPath);

        try {
            $path = Storage::disk($preferredDisk)->putFile($folder, $file);
            if (!is_string($path) || trim($path) === '') {
                throw new \RuntimeException('Failed to store converted upload on preferred disk.');
            }

            return [
                'path' => $path,
                'disk' => $preferredDisk,
            ];
        } catch (Throwable) {
            $path = Storage::disk('public')->putFile($folder, $file);
            if (!is_string($path) || trim($path) === '') {
                throw new \RuntimeException('Failed to store converted upload on fallback disk.');
            }

            return [
                'path' => $path,
                'disk' => 'public',
            ];
        }
    }

    public function replaceFileExtensionWithMp4(string $originalName): string
    {
        $name = trim($originalName);
        if ($name === '') {
            return 'video.mp4';
        }

        $dotPosition = strrpos($name, '.');
        if ($dotPosition === false) {
            return $name . '.mp4';
        }

        $base = substr($name, 0, $dotPosition);

        return ($base !== '' ? $base : 'video') . '.mp4';
    }

    protected function shouldConvertToMp4(UploadedFile $file, int $heavyVideoThresholdBytes): bool
    {
        $serverMimeType = strtolower(trim((string) ($file->getMimeType() ?: '')));
        $clientMimeType = strtolower(trim((string) ($file->getClientMimeType() ?: '')));
        $clientExtension = strtolower(trim((string) $file->getClientOriginalExtension()));
        $fileSize = (int) ($file->getSize() ?: 0);

        // Trust server-side MIME first, but keep extension/client MIME as a fallback because browser
        // uploads for formats like MKV may still arrive as application/octet-stream.
        $isVideo = str_starts_with($serverMimeType, 'video/')
            || str_starts_with($clientMimeType, 'video/')
            || in_array($clientExtension, ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'], true);

        if (!$isVideo) {
            return false;
        }

        $isMp4Input = $clientExtension === 'mp4'
            || str_contains($serverMimeType, 'mp4')
            || str_contains($clientMimeType, 'mp4');

        if (!$isMp4Input) {
            return true;
        }

        return $fileSize >= max(0, $heavyVideoThresholdBytes);
    }

    protected function resolveFfmpegBinary(): ?string
    {
        static $resolved = false;
        static $binary = null;

        if ($resolved) {
            return $binary;
        }

        $configured = trim((string) env('UPLOAD_FFMPEG_BIN', env('CHAT_FFMPEG_BIN', env('IPTV_FFMPEG_BIN', 'ffmpeg'))));
        $candidates = [$configured !== '' ? $configured : 'ffmpeg', 'ffmpeg'];

        if (DIRECTORY_SEPARATOR === '\\') {
            $candidates[] = 'ffmpeg.exe';
        }

        $uniqueCandidates = [];
        foreach ($candidates as $candidate) {
            $normalized = trim((string) $candidate);
            if ($normalized === '' || in_array($normalized, $uniqueCandidates, true)) {
                continue;
            }

            $uniqueCandidates[] = $normalized;
        }

        foreach ($uniqueCandidates as $candidate) {
            // Probe each candidate once and memoize the first working binary so repeated uploads
            // do not pay the process startup cost on every request.
            $probe = new Process([$candidate, '-version']);
            $probe->setTimeout(15);
            $probe->setIdleTimeout(15);

            try {
                $probe->run();
            } catch (Throwable) {
                continue;
            }

            if (!$probe->isSuccessful()) {
                continue;
            }

            $resolved = true;
            $binary = $candidate;

            return $binary;
        }

        $resolved = true;

        return $binary;
    }
}
