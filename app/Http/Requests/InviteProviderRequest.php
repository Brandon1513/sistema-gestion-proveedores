<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('providers.invite');
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'provider_type_id' => ['required', 'exists:provider_types,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'El correo electrónico es requerido',
            'email.email' => 'El correo electrónico debe ser válido',
            'provider_type_id.required' => 'El tipo de proveedor es requerido',
            'provider_type_id.exists' => 'El tipo de proveedor no es válido',
        ];
    }
}