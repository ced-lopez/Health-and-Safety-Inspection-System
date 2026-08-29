<?php

namespace App\Http\Requests\Establishment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEstablishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $establishment = $this->route('establishment');
        $establishmentId = is_object($establishment) ? $establishment->id : $establishment;

        return [
            'name' => ['required', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'in:food_establishment,piggery,poultry,dog_raising_kennel'],
            'owner_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'registration_number' => ['required', 'string', 'unique:establishments,registration_number,' . $establishmentId],
            'status' => ['required', 'string', 'in:active,inactive,pending'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
