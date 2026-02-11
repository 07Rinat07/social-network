<?php

namespace App\Http\Requests\Post;

use App\Rules\NoUnsafeMarkup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommentRequest extends FormRequest
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
        $postId = $this->route('post')?->id;

        return [
            'body' => ['required', 'string', 'min:1', 'max:2000', new NoUnsafeMarkup()],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('comments', 'id')
                    ->where(fn ($query) => $query->where('post_id', $postId)),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'body' => $this->normalizeMultiLine($this->input('body')),
        ]);
    }

    private function normalizeMultiLine(mixed $value): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $value);

        return trim($text);
    }
}
