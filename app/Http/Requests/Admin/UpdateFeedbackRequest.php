<?php

namespace App\Http\Requests\Admin;

use App\Models\FeedbackMessage;
use App\Rules\NoUnsafeMarkup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('admin_note')) {
            $note = str_replace(["\r\n", "\r"], "\n", (string) $this->input('admin_note'));
            $this->merge([
                'admin_note' => trim($note),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                FeedbackMessage::STATUS_NEW,
                FeedbackMessage::STATUS_IN_PROGRESS,
                FeedbackMessage::STATUS_RESOLVED,
            ])],
            'admin_note' => ['nullable', 'string', 'max:5000', new NoUnsafeMarkup()],
        ];
    }
}
