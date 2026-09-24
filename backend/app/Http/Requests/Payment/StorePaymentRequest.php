<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:application_fee,clearance_fee'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'method' => ['nullable', 'in:manual,online'],
            'inspection_id' => ['nullable', 'exists:inspections,id'],
        ];
    }
}
