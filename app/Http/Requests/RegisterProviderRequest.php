<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token'         => 'required|string|exists:provider_invitations,token',
            'name'          => 'required|string|max:255',
            'business_name' => 'required|string|max:255',
            //  min:12|max:13 — persona moral = 12, persona física = 13
            'rfc'           => 'required|string|min:12|max:13|unique:providers,rfc|regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/',
            'password'      => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'token.required'         => 'El token de invitación es requerido',
            'token.exists'           => 'El token de invitación no es válido',
            'name.required'          => 'El nombre es requerido',
            'business_name.required' => 'La razón social es requerida',
            'rfc.required'           => 'El RFC es requerido',
            'rfc.min'                => 'El RFC debe tener 12 caracteres (persona moral) o 13 (persona física)',
            'rfc.max'                => 'El RFC debe tener 12 caracteres (persona moral) o 13 (persona física)',
            'rfc.unique'             => 'Este RFC ya está registrado',
            'rfc.regex'              => 'El formato del RFC no es válido. Ejemplo: ABC123456XY0 o ABCD123456XY0',
            'password.required'      => 'La contraseña es requerida',
            'password.min'           => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed'     => 'Las contraseñas no coinciden',
        ];
    }
}