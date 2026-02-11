<?php

namespace App\Http\Requests\Feedback;

use App\Rules\NoUnsafeMarkup;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->normalizeSingleLine($this->input('name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'message' => $this->normalizeMultiLine($this->input('message')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', new NoUnsafeMarkup(false)],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:5', 'max:5000', new NoUnsafeMarkup()],
        ];
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
