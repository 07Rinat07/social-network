<?php

namespace App\Http\Requests\Post;

use App\Rules\NoUnsafeMarkup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
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
            'title' => ['required', 'string', 'min:1', 'max:255', new NoUnsafeMarkup(false)],
            'content' => ['required', 'string', 'min:1', 'max:5000', new NoUnsafeMarkup()],
            'image_id' => [
                'nullable',
                'integer',
                Rule::exists('post_images', 'id')
                    ->where(fn ($query) => $query
                        ->where('user_id', $this->user()->id)
                        ->whereNull('post_id')),
            ],
            'media_ids' => ['nullable', 'array', 'max:10'],
            'media_ids.*' => [
                'required',
                'integer',
                Rule::exists('post_images', 'id')
                    ->where(fn ($query) => $query
                        ->where('user_id', $this->user()->id)
                        ->whereNull('post_id')),
            ],
            'is_public' => ['nullable', 'boolean'],
            'show_in_feed' => ['nullable', 'boolean'],
            'show_in_carousel' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->normalizeSingleLine($this->input('title')),
            'content' => $this->normalizeMultiLine($this->input('content')),
        ]);
    }

    private function normalizeSingleLine(mixed $value): string
    {
        $text = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $text === null ? '' : $text;
    }

    private function normalizeMultiLine(mixed $value): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $value);

        return trim($text);
    }
}
