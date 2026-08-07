<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Convertir strings vacíos a null
        $nullables = [
            'interior_number', 'bank', 'bank_branch', 'account_number',
            'clabe', 'credit_amount', 'credit_days', 'observations',
            'legal_representative',
        ];
        foreach ($nullables as $field) {
            if ($this->has($field) && $this->$field === '') {
                $this->merge([$field => null]);
            }
        }

        // ✅ Auto-detectar tipo_persona por longitud del RFC si no viene
        if (!$this->filled('tipo_persona') && $this->filled('rfc')) {
            $this->merge([
                'tipo_persona' => strlen(trim($this->rfc)) === 13 ? 'fisica' : 'moral',
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'provider_type_id'    => 'required|exists:provider_types,id',
            'business_name'       => 'required|string|max:255',
            'rfc'                 => 'required|string|min:12|max:13|unique:providers,rfc|regex:/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/i',
            'tipo_persona'        => 'required|in:moral,fisica',
            'legal_representative'=> 'nullable|string|max:255',
            'street'              => 'required|string|max:255',
            'exterior_number'     => 'required|string|max:20',
            'interior_number'     => 'nullable|string|max:20',
            'neighborhood'        => 'required|string|max:255',
            'city'                => 'required|string|max:255',
            'state'               => 'required|string|max:100',
            'postal_code'         => 'required|string|max:10',
            'phone'               => 'required|string|max:20',
            'email'               => 'required|email|max:255|unique:providers,email',
            'bank'                => 'nullable|string|max:100',
            'bank_branch'         => 'nullable|string|max:100',
            'account_number'      => 'nullable|string|max:30',
            'clabe'               => 'nullable|string|max:18',
            'credit_amount'       => 'nullable|numeric|min:0',
            'credit_days'         => 'nullable|integer|min:0',
            'observations'        => 'nullable|string|max:2000',
            'department_id' => 'nullable|exists:departments,id',
        ];
    }

    public function messages(): array
    {
        return [
            'provider_type_id.required' => 'El tipo de proveedor es obligatorio',
            'business_name.required'    => 'La razón social es obligatoria',
            'rfc.required'              => 'El RFC es obligatorio',
            'rfc.unique'                => 'Este RFC ya está registrado',
            'rfc.min'                   => 'El RFC debe tener al menos 12 caracteres',
            'rfc.max'                   => 'El RFC no puede tener más de 13 caracteres',
            'rfc.regex'                 => 'El formato del RFC no es válido',
            'tipo_persona.required'     => 'El tipo de persona es obligatorio',
            'tipo_persona.in'           => 'El tipo de persona debe ser moral o física',
            'email.unique'              => 'Este correo ya está registrado',
            'street.required'           => 'La calle es obligatoria',
            'exterior_number.required'  => 'El número exterior es obligatorio',
            'neighborhood.required'     => 'La colonia es obligatoria',
            'city.required'             => 'La ciudad es obligatoria',
            'state.required'            => 'El estado es obligatorio',
            'postal_code.required'      => 'El código postal es obligatorio',
            'phone.required'            => 'El teléfono es obligatorio',
            'email.required'            => 'El correo electrónico es obligatorio',
        ];
    }
}