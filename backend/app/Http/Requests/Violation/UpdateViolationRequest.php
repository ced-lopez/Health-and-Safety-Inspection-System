<?php

namespace App\Http\Requests\Violation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inspection_id' => ['required', 'exists:inspections,id'],
            'inspection_result_id' => ['nullable', 'exists:inspection_results,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'severity' => ['required', 'in:minor,moderate,major'],
            'status' => ['required', 'in:open,under_review,resolved'],
            'correction_deadline' => ['nullable', 'date'],
        ];
    }
}
