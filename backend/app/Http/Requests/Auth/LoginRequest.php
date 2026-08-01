<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            // The portal the request came from: 'resident' (/login) or 'staff' (/admin/login).
            // The backend enforces that the authenticated role matches the portal.
            'portal' => ['required', 'string', 'in:resident,staff'],
        ];
    }
}
