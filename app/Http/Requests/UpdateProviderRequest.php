<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
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

        // ✅ Auto-detectar tipo_persona por RFC si no viene explícito
        if (!$this->filled('tipo_persona') && $this->filled('rfc')) {
            $this->merge([
                'tipo_persona' => strlen(trim($this->rfc)) === 13 ? 'fisica' : 'moral',
            ]);
        }
    }

    public function rules(): array
    {
        $providerId = $this->route('provider')?->id ?? $this->route('provider');

        return [
            'provider_type_id'    => 'sometimes|exists:provider_types,id',
            'business_name'       => 'sometimes|string|max:255',
            'rfc'                 => [
                'sometimes', 'string', 'min:12', 'max:13',
                Rule::unique('providers', 'rfc')->ignore($providerId),
                'regex:/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/i',
            ],
            'tipo_persona'        => 'sometimes|in:moral,fisica',
            'legal_representative'=> 'nullable|string|max:255',
            'street'              => 'sometimes|string|max:255',
            'exterior_number'     => 'sometimes|string|max:20',
            'interior_number'     => 'nullable|string|max:20',
            'neighborhood'        => 'sometimes|string|max:255',
            'city'                => 'sometimes|string|max:255',
            'state'               => 'sometimes|string|max:100',
            'postal_code'         => 'sometimes|string|max:10',
            'phone'               => 'sometimes|string|max:20',
            'email'               => [
                'sometimes', 'email', 'max:255',
                Rule::unique('providers', 'email')->ignore($providerId),
            ],
            'bank'                => 'nullable|string|max:100',
            'bank_branch'         => 'nullable|string|max:100',
            'account_number'      => 'nullable|string|max:30',
            'clabe'               => 'nullable|string|max:18',
            'credit_amount'       => 'nullable|numeric|min:0',
            'credit_days'         => 'nullable|integer|min:0',
            'observations'        => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'rfc.unique'        => 'Este RFC ya está registrado',
            'rfc.min'           => 'El RFC debe tener al menos 12 caracteres',
            'rfc.max'           => 'El RFC no puede tener más de 13 caracteres',
            'rfc.regex'         => 'El formato del RFC no es válido',
            'tipo_persona.in'   => 'El tipo de persona debe ser moral o física',
            'email.unique'      => 'Este correo ya está registrado en otro proveedor',
        ];
    }
}