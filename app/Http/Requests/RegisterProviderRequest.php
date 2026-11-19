<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * ⚠️ Los "max" deben coincidir con el tamaño real de cada columna en `providers`.
     * Si la validación permite más que la columna, PostgreSQL truena con
     * SQLSTATE 22001 y el proveedor solo ve un error genérico (caso Videojet, nov 2026).
     *
     * Columnas cortas actuales: rfc(13), exterior_number(50), interior_number(100),
     * postal_code(10), phone(20), clabe(18).
     */
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

            'tipo_persona'         => 'nullable|in:fisica,moral',
            'legal_representative' => 'nullable|string|max:255',
            'phone'                => 'required|string|max:20',

            // Dirección
            'street'          => 'required|string|max:255',
            'exterior_number' => 'required|string|max:50',   // columna varchar(50)
            'interior_number' => 'nullable|string|max:100',  // columna varchar(100) — antes 50 vs columna 20
            'neighborhood'    => 'required|string|max:255',
            'city'            => 'required|string|max:255',
            'state'           => 'required|string|max:255',
            'postal_code'     => 'required|digits:5',        // CP en México siempre son 5 dígitos

            // Datos bancarios (opcionales)
            'bank'           => 'nullable|string|max:255',
            'bank_branch'    => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'clabe'          => 'nullable|digits:18',
            'credit_amount'  => 'nullable|numeric|min:0',
            'credit_days'    => 'nullable|integer|min:0',
            'observations'   => 'nullable|string|max:2000',

            // Contactos
            'contacts'              => 'required|array|min:1',
            'contacts.*.type'       => 'required|in:sales,billing,quality',
            'contacts.*.name'       => 'required|string|max:255',
            'contacts.*.phone'      => 'required|string|max:20',
            'contacts.*.email'      => 'required|email|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'token.required'         => 'El token de invitación es requerido',
            'token.exists'           => 'El enlace de invitación no es válido. Solicita uno nuevo a compras@dasavena.com',
            'name.required'          => 'El nombre es requerido',
            'business_name.required' => 'La razón social es requerida',
            'business_name.max'      => 'La razón social no puede tener más de 255 caracteres',
            'rfc.required'           => 'El RFC es requerido',
            'rfc.min'                => 'El RFC debe tener 12 caracteres (persona moral) o 13 (persona física)',
            'rfc.max'                => 'El RFC debe tener 12 caracteres (persona moral) o 13 (persona física)',
            'rfc.unique'             => 'Este RFC ya está registrado',
            'rfc.regex'              => 'El formato del RFC no es válido. Ejemplo: ABC123456XY0 o ABCD123456XY0',
            'password.required'      => 'La contraseña es requerida',
            'password.min'           => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed'     => 'Las contraseñas no coinciden',

            'legal_representative.max' => 'El nombre del representante legal no puede tener más de 255 caracteres',
            'phone.required'           => 'El teléfono es requerido',
            'phone.max'                => 'El teléfono no puede tener más de 20 caracteres',

            // Dirección
            'street.required'          => 'La calle es requerida',
            'street.max'               => 'La calle no puede tener más de 255 caracteres',
            'exterior_number.required' => 'El número exterior es requerido',
            'exterior_number.max'      => 'El número exterior no puede tener más de 50 caracteres',
            'interior_number.max'      => 'El número interior no puede tener más de 100 caracteres',
            'neighborhood.required'    => 'La colonia es requerida',
            'neighborhood.max'         => 'La colonia no puede tener más de 255 caracteres',
            'city.required'            => 'La ciudad es requerida',
            'city.max'                 => 'La ciudad no puede tener más de 255 caracteres',
            'state.required'           => 'El estado es requerido',
            'postal_code.required'     => 'El código postal es requerido',
            'postal_code.digits'       => 'El código postal debe tener exactamente 5 dígitos (ej. 01040)',

            // Datos bancarios
            'bank.max'               => 'El nombre del banco no puede tener más de 255 caracteres',
            'bank_branch.max'        => 'La sucursal no puede tener más de 255 caracteres',
            'account_number.max'     => 'El número de cuenta no puede tener más de 50 caracteres',
            'clabe.digits'           => 'La CLABE debe tener exactamente 18 dígitos, sin espacios ni guiones',
            'credit_amount.numeric'  => 'El monto de crédito debe ser un número',
            'credit_days.integer'    => 'Los días de crédito deben ser un número entero',
            'observations.max'       => 'Las observaciones no pueden tener más de 2000 caracteres',

            // Contactos (antes estaban por error dentro de rules())
            'contacts.required'          => 'Debes agregar al menos un contacto',
            'contacts.min'               => 'Debes agregar al menos un contacto',
            'contacts.*.type.required'   => 'Selecciona el tipo de contacto',
            'contacts.*.type.in'         => 'El tipo de contacto no es válido',
            'contacts.*.name.required'   => 'El nombre del contacto es obligatorio',
            'contacts.*.name.max'        => 'El nombre del contacto no puede tener más de 255 caracteres',
            'contacts.*.phone.required'  => 'El teléfono del contacto es obligatorio',
            'contacts.*.phone.max'       => 'El teléfono del contacto no puede tener más de 20 caracteres',
            'contacts.*.email.required'  => 'El correo del contacto es obligatorio',
            'contacts.*.email.email'     => 'El correo del contacto no tiene un formato válido',
            'contacts.*.email.max'       => 'El correo del contacto no puede tener más de 255 caracteres',
        ];
    }
}