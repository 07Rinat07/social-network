<?php

namespace App\Http\Controllers;

use App\Models\ConversationMessageAttachment;
use App\Models\PostImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function showPostImage(PostImage $postImage, Request $request): StreamedResponse
    {
        $user = $request->user();

        if (!$user->is_admin) {
            if ($postImage->post_id === null) {
                if ((int) $postImage->user_id !== (int) $user->id) {
                    abort(403, 'Access denied to this media file.');
                }
            } else {
                $post = $postImage->post()
                    ->select(['id', 'user_id', 'is_public'])
                    ->first();

                if (!$post) {
                    abort(404, 'Related post not found for this media file.');
                }

                if (!$post->is_public && (int) $post->user_id !== (int) $user->id) {
                    abort(403, 'Access denied to this media file.');
                }
            }
        }

        return $this->streamFromDisk(
            (string) ($postImage->storage_disk ?: 'public'),
            (string) $postImage->path,
            $postImage->mime_type,
            $postImage->original_name
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
}
