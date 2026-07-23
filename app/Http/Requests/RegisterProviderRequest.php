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
            'rfc' => [
                    'required', 'string', 'min:12', 'max:13',
                    'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/',
                    // Ignorar unique si ya existe un proveedor con el email de la invitación
                    \Illuminate\Validation\Rule::unique('providers', 'rfc')->where(function ($query) {
                        $token = $this->token;
                        $invitation = \App\Models\ProviderInvitation::where('token', $token)->first();
                        if ($invitation) {
                            $query->where('email', '!=', $invitation->email);
                        }
                    }),
                ],
            'password'      => 'required|string|min:8|confirmed',

            // ✅ NUEVO — antes se capturaban en el formulario pero no se validaban ni guardaban
            'tipo_persona'         => 'nullable|in:fisica,moral',
            'legal_representative' => 'nullable|string|max:255',
            'phone'                => 'required|string|max:20',

            // Dirección
            'street'          => 'required|string|max:255',
            'exterior_number' => 'required|string|max:50',
            'interior_number' => 'nullable|string|max:50',
            'neighborhood'    => 'required|string|max:255',
            'city'            => 'required|string|max:255',
            'state'           => 'required|string|max:255',
            'postal_code'     => 'required|string|max:10',

            // Datos bancarios (opcionales)
            'bank'           => 'nullable|string|max:255',
            'bank_branch'    => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'clabe'          => 'nullable|string|max:18',
            'credit_amount'  => 'nullable|numeric|min:0',
            'credit_days'    => 'nullable|integer|min:0',
            'observations'   => 'nullable|string|max:2000',
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

            // ✅ NUEVO
            'phone.required'           => 'El teléfono es requerido',
            'street.required'          => 'La calle es requerida',
            'exterior_number.required' => 'El número exterior es requerido',
            'neighborhood.required'   => 'La colonia es requerida',
            'city.required'           => 'La ciudad es requerida',
            'state.required'          => 'El estado es requerido',
            'postal_code.required'    => 'El código postal es requerido',
        ];
    }
}