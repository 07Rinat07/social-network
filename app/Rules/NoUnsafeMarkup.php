<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoUnsafeMarkup implements ValidationRule
{
    public function __construct(private readonly bool $allowNewLines = true)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || $value === '') {
            return;
        }

        $text = str_replace(["\r\n", "\r"], "\n", $value);

        if (!$this->allowNewLines && str_contains($text, "\n")) {
            $fail('Поле :attribute не должно содержать переносы строк.');

            return;
        }

        // Block hidden control chars and common script/code vectors.
        $patterns = [
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            '/<\s*\/?\s*[a-z][^>]*>/iu',
            '/&lt;\s*\/?\s*[a-z][^&]*&gt;/iu',
            '/(?:java|vb)script\s*:/iu',
            '/data\s*:\s*text\/html/iu',
            '/on[a-z]+\s*=/iu',
            '/<\?(?:php|=)?/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $fail('Поле :attribute содержит недопустимый код или разметку.');

                return;
            }
        }
    }
}
