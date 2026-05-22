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
    $this->merge([
        'credit_amount'      => $this->credit_amount === '' ? null : $this->credit_amount,
        'credit_days'        => $this->credit_days   === '' ? null : $this->credit_days,
        'account_number'     => $this->account_number === '' ? null : $this->account_number,
        'clabe'              => $this->clabe           === '' ? null : $this->clabe,
        'phone'              => $this->phone           === '' ? null : $this->phone,
        'bank'               => $this->bank            === '' ? null : $this->bank,
        'bank_branch'        => $this->bank_branch     === '' ? null : $this->bank_branch,
        'legal_representative' => $this->legal_representative === '' ? null : $this->legal_representative,
        'interior_number'    => $this->interior_number === '' ? null : $this->interior_number,
        'observations'       => $this->observations    === '' ? null : $this->observations,
        'street'             => $this->street          === '' ? null : $this->street,
        'exterior_number'    => $this->exterior_number === '' ? null : $this->exterior_number,
        'neighborhood'       => $this->neighborhood    === '' ? null : $this->neighborhood,
        'city'               => $this->city            === '' ? null : $this->city,
        'state'              => $this->state           === '' ? null : $this->state,
        'postal_code'        => $this->postal_code     === '' ? null : $this->postal_code,
    ]);
}

    public function rules(): array
    {
        $providerId = $this->route('provider')->id;

        return [
            'provider_type_id' => ['sometimes', 'exists:provider_types,id'],
            'business_name' => ['sometimes', 'string', 'max:255'],
           'rfc' => ['sometimes', 'string', 'min:12', 'max:13', Rule::unique('providers')->ignore($providerId), 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/'],
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