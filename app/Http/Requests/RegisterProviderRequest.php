<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterProviderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'token' => 'required|string|exists:provider_invitations,token',
            'name' => 'required|string|max:255',
            'business_name' => 'required|string|max:255',
            'rfc' => 'required|string|size:13|unique:providers,rfc|regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'token.required' => 'El token de invitación es requerido',
            'token.exists' => 'El token de invitación no es válido',
            'name.required' => 'El nombre es requerido',
            'business_name.required' => 'La razón social es requerida',
            'rfc.required' => 'El RFC es requerido',
            'rfc.size' => 'El RFC debe tener exactamente 13 caracteres',
            'rfc.unique' => 'Este RFC ya está registrado',
            'rfc.regex' => 'El formato del RFC no es válido',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
        ];
    }
}