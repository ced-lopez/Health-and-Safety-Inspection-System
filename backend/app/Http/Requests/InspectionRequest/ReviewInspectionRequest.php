<?php

namespace App\Http\Requests\InspectionRequest;

use Illuminate\Foundation\Http\FormRequest;

class ReviewInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:under_review,requirements_incomplete,approved_for_inspection,rejected'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
