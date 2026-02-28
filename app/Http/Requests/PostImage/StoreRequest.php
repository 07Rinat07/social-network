<?php

namespace App\Http\Requests\PostImage;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreRequest extends FormRequest
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'file' => [
                'required',
                'file',
                'extensions:' . implode(',', self::ALLOWED_EXTENSIONS),
                'max:204800',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (!$value instanceof UploadedFile) {
                        return;
                    }

                    $clientExtension = strtolower(trim((string) $value->getClientOriginalExtension()));
                    $detectedMimeType = strtolower(trim((string) ($value->getMimeType() ?: '')));
                    $clientMimeType = strtolower(trim((string) ($value->getClientMimeType() ?: '')));

                    if ($this->matchesAllowedMediaSignature($clientExtension, $detectedMimeType, $clientMimeType)) {
                        return;
                    }

                    $fail($this->allowedFormatsMessage());
                },
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->hasFile('file') && $this->hasFile('image')) {
            $this->files->set('file', $this->file('image'));
        }
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please choose an image or video file.',
            'file.file' => 'The uploaded media must be a valid file.',
            'file.extensions' => $this->allowedFormatsMessage(),
            'file.max' => 'Media file must not exceed 200 MB.',
            'file.uploaded' => 'The media file could not be uploaded. Check the server upload limit and try again.',
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'media file',
        ];
    }

    private function matchesAllowedMediaSignature(string $extension, string $detectedMimeType, string $clientMimeType): bool
    {
        $normalizedExtension = strtolower(trim($extension));
        $normalizedDetectedMimeType = strtolower(trim($detectedMimeType));
        $normalizedClientMimeType = strtolower(trim($clientMimeType));

        if (!in_array($normalizedExtension, self::ALLOWED_EXTENSIONS, true)) {
            return false;
        }

        if ($normalizedDetectedMimeType !== '' && $normalizedDetectedMimeType !== 'application/octet-stream') {
            if (str_starts_with($normalizedDetectedMimeType, 'image/') || str_starts_with($normalizedDetectedMimeType, 'video/')) {
                return true;
            }

            return false;
        }

        if ($normalizedClientMimeType === '') {
            return true;
        }

        if (str_starts_with($normalizedClientMimeType, 'image/') || str_starts_with($normalizedClientMimeType, 'video/')) {
            return true;
        }

        return $normalizedExtension === 'mkv'
            && in_array($normalizedClientMimeType, ['application/octet-stream', 'audio/x-matroska'], true);
    }

    private function allowedFormatsMessage(): string
    {
        return 'Supported formats: jpg, jpeg, png, webp, gif, mp4, webm, mov, m4v, avi, mkv.';
    }
}
