<?php

namespace App\Http\Requests\InspectionSchedule;

use Illuminate\Foundation\Http\FormRequest;

class StoreInspectionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'establishment_id' => ['required_without:inspection_request_id', 'nullable', 'exists:establishments,id'],
            'inspector_id' => ['required', 'exists:users,id'],
            'scheduled_date' => ['required_without:scheduled_at', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'scheduled_at' => ['nullable', 'date'],
            'schedule_type' => ['nullable', 'in:initial,follow_up'],
            'inspection_request_id' => ['nullable', 'exists:inspection_requests,id'],
            'inspection_assignment_id' => ['nullable', 'exists:inspection_assignments,id'],
            'status' => ['required', 'in:scheduled,ongoing,completed,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
