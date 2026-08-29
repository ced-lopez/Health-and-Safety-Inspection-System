<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', 'max:100'],
            'file' => [
                'required',
                'file',
                'max:'.config('ocr.max_file_size_kb', 10240),
                'mimes:'.implode(',', config('ocr.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png'])),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Documents must be one of the following: PDF, JPG, JPEG, PNG.',
            'file.max' => 'The document must not be larger than '.($this->maxKb() / 1024).' MB.',
        ];
    }

    private function maxKb(): int
    {
        return (int) config('ocr.max_file_size_kb', 10240);
    }
}
