<?php

namespace App\Http\Requests\InspectionRequest;

use Illuminate\Foundation\Http\FormRequest;

class StoreInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inspection_category_id' => ['required', 'exists:inspection_categories,id'],
            'application_type_id' => ['required', 'exists:application_types,id'],
            'sub_path' => ['nullable', 'string', 'in:household,commercial_kennel,backyard_micro_scale,commercial'],
            'declared_animal_count' => ['nullable', 'integer', 'min:1', 'max:5'],
            'establishment_id' => ['nullable', 'exists:establishments,id'],
            'applicant_name' => ['required', 'string', 'max:255'],
            'applicant_age' => ['nullable', 'integer', 'min:1', 'max:150'],
            'applicant_address' => ['required', 'string'],
            'contact_number' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
