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
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }
}
