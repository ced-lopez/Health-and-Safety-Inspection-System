<?php

namespace App\Http\Requests\Certification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIssuanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'establishment_id' => ['required', 'exists:establishments,id'],
            'inspection_id' => ['nullable', 'exists:inspections,id'],
            'document_type' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'issue_date' => ['required', 'date'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'status' => ['required', 'in:pending,active,expired,revoked'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
