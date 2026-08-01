<?php

namespace App\Http\Requests\InspectionSchedule;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInspectionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'establishment_id' => ['nullable', 'exists:establishments,id'],
            'inspector_id' => ['required', 'exists:users,id'],
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'status' => ['required', 'in:scheduled,ongoing,completed,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
