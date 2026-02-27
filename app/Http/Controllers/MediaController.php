<?php

namespace App\Http\Controllers;

use App\Models\ConversationMessageAttachment;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function showAvatar(User $user): StreamedResponse
    {
        $path = trim((string) ($user->avatar_path ?? ''));
        if ($path === '') {
            abort(404, 'Avatar not found.');
        }

        $mimeType = null;
        if (Storage::disk('public')->exists($path)) {
            $detectedMimeType = Storage::disk('public')->mimeType($path);
            $mimeType = is_string($detectedMimeType) && trim($detectedMimeType) !== '' ? $detectedMimeType : null;
        }

        return $this->streamFromDisk('public', $path, $mimeType, basename($path));
    }

    public function showPostImage(PostImage $postImage, Request $request): StreamedResponse
    {
        $user = $request->user();
        $isAdmin = (bool) ($user?->is_admin ?? false);

        if ($postImage->post_id === null) {
            if (!$user || (!$isAdmin && (int) $postImage->user_id !== (int) $user->id)) {
                abort(403, 'Access denied to this media file.');
            }

            $asset = $this->resolvePostImageAsset($postImage);

            return $this->streamFromDisk(
                $asset['disk'],
                $asset['path'],
                $asset['mime'],
                $asset['name']
            );
        }

        $post = $postImage->post()
            ->select(['id', 'user_id', 'is_public'])
            ->first();

        if (!$post) {
            abort(404, 'Related post not found for this media file.');
        }

        // Public post media can be rendered without session-bound API auth
        // so <img> requests do not fail when Sanctum stateful headers are absent.
        if (!$post->is_public) {
            if (!$user || (!$isAdmin && (int) $post->user_id !== (int) $user->id)) {
                abort(403, 'Access denied to this media file.');
            }
        }

        $asset = $this->resolvePostImageAsset($postImage);

        return $this->streamFromDisk(
            $asset['disk'],
            $asset['path'],
            $asset['mime'],
            $asset['name']
        );
    }

    public function showChatAttachment(ConversationMessageAttachment $attachment, Request $request): StreamedResponse
    {
        $this->ensureChatAttachmentAccess($attachment, $request);

        return $this->streamFromDisk(
            (string) ($attachment->storage_disk ?: 'public'),
            (string) $attachment->path,
            $attachment->mime_type,
            $attachment->original_name
        );
    }

    public function downloadChatAttachment(ConversationMessageAttachment $attachment, Request $request): StreamedResponse
    {
        $this->ensureChatAttachmentAccess($attachment, $request);

        return $this->streamFromDisk(
            (string) ($attachment->storage_disk ?: 'public'),
            (string) $attachment->path,
            $attachment->mime_type,
            $attachment->original_name,
            'attachment'
        );
    }

    protected function ensureChatAttachmentAccess(ConversationMessageAttachment $attachment, Request $request): void
    {
        $user = $request->user();

        if ($user->is_admin) {
            return;
        }

        $message = $attachment->message()
            ->with('conversation')
            ->first();

        if (!$message || !$message->conversation || !$message->conversation->isAccessibleBy($user->id)) {
            abort(403, 'Access denied to this chat attachment.');
        }
    }

    protected function streamFromDisk(
        string $disk,
        string $path,
        ?string $mimeType = null,
        ?string $name = null,
        string $disposition = 'inline'
    ): StreamedResponse
    {
        $path = trim($path);

        if ($path === '') {
            abort(404, 'Media file path is empty.');
        }

        $targetDisk = $disk;

        if (!Storage::disk($targetDisk)->exists($path)) {
            if ($targetDisk !== 'public' && Storage::disk('public')->exists($path)) {
                $targetDisk = 'public';
            } else {
                abort(404, 'Media file not found.');
            }
        }

        $headers = [];
        if (is_string($mimeType) && trim($mimeType) !== '') {
            $headers['Content-Type'] = $mimeType;
        }

        return Storage::disk($targetDisk)->response($path, $name ?: basename($path), $headers, $disposition);
    }

    /**
     * @return array{disk: string, path: string, mime: ?string, name: string}
     */
    protected function resolvePostImageAsset(PostImage $postImage): array
    {
        $disk = (string) ($postImage->storage_disk ?: 'public');
        $path = trim((string) $postImage->path);
        $mime = trim((string) ($postImage->mime_type ?? ''));
        $name = trim((string) ($postImage->original_name ?? ''));

        if ($name === '') {
            $name = basename((string) parse_url($path, PHP_URL_PATH) ?: $path);
            if ($name === '' || $name === '.' || $name === '/') {
                $name = 'post-image-' . $postImage->id;
            }
        }

        if ($this->isExternalPath($path)) {
            $downloaded = $this->downloadExternalPostImageToPublic($postImage, $path, $name);
            if ($downloaded !== null) {
                return $downloaded;
            }

            return $this->ensurePostImageFallbackPlaceholder($postImage, $name);
        }

        if ($path !== '' && Storage::disk($disk)->exists($path)) {
            return [
                'disk' => $disk,
                'path' => $path,
                'mime' => $mime !== '' ? $mime : null,
                'name' => $name,
            ];
        }

        if ($path !== '' && $disk !== 'public' && Storage::disk('public')->exists($path)) {
            return [
                'disk' => 'public',
                'path' => $path,
                'mime' => $mime !== '' ? $mime : null,
                'name' => $name,
            ];
        }

        return $this->ensurePostImageFallbackPlaceholder($postImage, $name);
    }

    protected function isExternalPath(string $path): bool
    {
        $normalized = strtolower(trim($path));
        return str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://');
    }

    /**
     * @return array{disk: string, path: string, mime: string, name: string}|null
     */
    protected function downloadExternalPostImageToPublic(PostImage $postImage, string $url, string $name): ?array
    {
        try {
            $response = Http::timeout(8)
                ->retry(1, 150)
                ->withHeaders(['User-Agent' => 'social-network-media-fix'])
                ->get($url);

            $mimeType = strtolower(trim((string) explode(';', (string) $response->header('Content-Type', ''), 2)[0]));
            $body = $response->body();

            if (
                !$response->successful()
                || $body === ''
                || !str_starts_with($mimeType, 'image/')
            ) {
                return null;
            }

            $extension = $this->extensionFromMime($mimeType);
            $path = sprintf('seed/posts/rehydrated-post-image-%d.%s', (int) $postImage->id, $extension);
            Storage::disk('public')->put($path, $body);

            $this->syncPostImageRecord($postImage, $path, $mimeType, strlen($body), $name);

            return [
                'disk' => 'public',
                'path' => $path,
                'mime' => $mimeType,
                'name' => $name,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{disk: string, path: string, mime: string, name: string}
     */
    protected function ensurePostImageFallbackPlaceholder(PostImage $postImage, string $name): array
    {
        $path = sprintf('seed/posts/missing-post-image-%d.svg', (int) $postImage->id);
        $disk = Storage::disk('public');

        if (!$disk->exists($path)) {
            $label = trim((string) ($postImage->original_name ?: ('Post #' . $postImage->id)));
            $svg = $this->buildMissingImageSvg($label);
            $disk->put($path, $svg);
        }

        $size = (int) ($disk->size($path) ?: 0);
        $mime = 'image/svg+xml';
        $this->syncPostImageRecord($postImage, $path, $mime, $size, $name);

        return [
            'disk' => 'public',
            'path' => $path,
            'mime' => $mime,
            'name' => $name,
        ];
    }

    protected function syncPostImageRecord(PostImage $postImage, string $path, string $mime, int $size, string $name): void
    {
        $postImage->forceFill([
            'path' => $path,
            'storage_disk' => 'public',
            'mime_type' => $mime,
            'size' => max(0, $size),
            'original_name' => $name !== '' ? $name : $postImage->original_name,
        ])->save();
    }

    protected function extensionFromMime(string $mime): string
    {
        return match (strtolower(trim($mime))) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => 'jpg',
        };
    }

    protected function buildMissingImageSvg(string $label): string
    {
        $safeLabel = htmlspecialchars($label !== '' ? $label : 'Image unavailable', ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#d6e7fb"/>
      <stop offset="100%" stop-color="#e5f3e4"/>
    </linearGradient>
  </defs>
  <rect width="1280" height="720" fill="url(#bg)"/>
  <g fill="none" stroke="#6b82a3" stroke-width="14" opacity="0.8">
    <rect x="420" y="210" width="440" height="250" rx="18"/>
    <circle cx="520" cy="285" r="26"/>
    <path d="M480 430 L600 320 L690 395 L760 340 L840 430 Z" fill="#6b82a3" stroke="none"/>
  </g>
  <text x="640" y="520" text-anchor="middle" font-size="40" font-family="Arial, sans-serif" fill="#405a7a">
    {$safeLabel}
  </text>
</svg>
SVG;
    }
}
