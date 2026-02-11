<?php

namespace App\Http\Requests\PostImage;

use Illuminate\Foundation\Http\FormRequest;

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
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,gif,mp4,webm,mov,m4v,avi|max:204800',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->hasFile('file') && $this->hasFile('image')) {
            $this->files->set('file', $this->file('image'));
        }
    }
}
