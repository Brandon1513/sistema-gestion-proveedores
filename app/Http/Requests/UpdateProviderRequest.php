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

    public function rules(): array
    {
        $providerId = $this->route('provider')->id;

        return [
            'provider_type_id' => ['sometimes', 'exists:provider_types,id'],
            'business_name' => ['sometimes', 'string', 'max:255'],
            'rfc' => ['sometimes', 'string', 'size:13', Rule::unique('providers')->ignore($providerId)],
            'legal_representative' => ['nullable', 'string', 'max:255'],
            'street' => ['sometimes', 'string', 'max:255'],
            'exterior_number' => ['sometimes', 'string', 'max:20'],
            'interior_number' => ['nullable', 'string', 'max:20'],
            'neighborhood' => ['sometimes', 'string', 'max:255'],
            'city' => ['sometimes', 'string', 'max:255'],
            'state' => ['sometimes', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['sometimes', 'email', 'max:255'],
            'bank' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'clabe' => ['nullable', 'string', 'size:18'],
            'credit_amount' => ['nullable', 'numeric', 'min:0'],
            'credit_days' => ['nullable', 'integer', 'min:0'],
            'products' => ['nullable', 'string'],
            'services' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            
            // Contactos
            'contacts' => ['sometimes', 'array'],
            'contacts.*.type' => ['required', 'in:sales,billing,quality'],
            'contacts.*.name' => ['required', 'string', 'max:255'],
            'contacts.*.email' => ['required', 'email', 'max:255'],
            'contacts.*.phone' => ['required', 'string', 'max:20'],
            'contacts.*.extension' => ['nullable', 'string', 'max:10'],
            
            // Vehículos
            'vehicles' => ['sometimes', 'array'],
            'vehicles.*.brand_model' => ['required', 'string', 'max:255'],
            'vehicles.*.color' => ['required', 'string', 'max:50'],
            'vehicles.*.plates' => ['required', 'string', 'max:20'],
            
            // Personal
            'personnel' => ['sometimes', 'array'],
            'personnel.*.full_name' => ['required', 'string', 'max:255'],
            'personnel.*.position' => ['nullable', 'string', 'max:255'],
            
            // Certificaciones
            'certifications' => ['sometimes', 'array'],
            'certifications.*.certification_type' => ['required', 'string'],
            'certifications.*.certification_number' => ['nullable', 'string', 'max:255'],
            'certifications.*.expiry_date' => ['nullable', 'date'],
        ];
    }
}