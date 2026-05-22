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
            'credit_amount'        => $this->credit_amount        === '' ? null : $this->credit_amount,
            'credit_days'          => $this->credit_days          === '' ? null : $this->credit_days,
            'account_number'       => $this->account_number       === '' ? null : $this->account_number,
            'clabe'                => $this->clabe                === '' ? null : $this->clabe,
            'phone'                => $this->phone                === '' ? null : $this->phone,
            'bank'                 => $this->bank                 === '' ? null : $this->bank,
            'bank_branch'          => $this->bank_branch          === '' ? null : $this->bank_branch,
            'legal_representative' => $this->legal_representative === '' ? null : $this->legal_representative,
            'interior_number'      => $this->interior_number      === '' ? null : $this->interior_number,
            'observations'         => $this->observations         === '' ? null : $this->observations,
            'street'               => $this->street               === '' ? null : $this->street,
            'exterior_number'      => $this->exterior_number      === '' ? null : $this->exterior_number,
            'neighborhood'         => $this->neighborhood         === '' ? null : $this->neighborhood,
            'city'                 => $this->city                 === '' ? null : $this->city,
            'state'                => $this->state                === '' ? null : $this->state,
            'postal_code'          => $this->postal_code          === '' ? null : $this->postal_code,
            'business_name'        => $this->business_name        === '' ? null : $this->business_name,
            'email'                => $this->email                === '' ? null : $this->email,
            'rfc'                  => $this->rfc                  === '' ? null : $this->rfc,
        ]);
    }

    public function rules(): array
    {
        $providerId = $this->route('provider')->id;

        return [
            'provider_type_id'     => ['nullable', 'exists:provider_types,id'],
            'business_name'        => ['nullable', 'string', 'max:255'],
            'rfc'                  => ['nullable', 'string', 'min:12', 'max:13', Rule::unique('providers')->ignore($providerId), 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/'],
            'legal_representative' => ['nullable', 'string', 'max:255'],
            'street'               => ['nullable', 'string', 'max:255'],
            'exterior_number'      => ['nullable', 'string', 'max:20'],
            'interior_number'      => ['nullable', 'string', 'max:20'],
            'neighborhood'         => ['nullable', 'string', 'max:255'],
            'city'                 => ['nullable', 'string', 'max:255'],
            'state'                => ['nullable', 'string', 'max:255'],
            'postal_code'          => ['nullable', 'string', 'max:10'],
            'phone'                => ['nullable', 'string', 'max:20'],
            'email'                => ['nullable', 'email', 'max:255'],
            'bank'                 => ['nullable', 'string', 'max:255'],
            'bank_branch'          => ['nullable', 'string', 'max:255'],
            'account_number'       => ['nullable', 'string', 'max:255'],
            'clabe'                => ['nullable', 'string', 'max:18'],
            'credit_amount'        => ['nullable', 'numeric', 'min:0'],
            'credit_days'          => ['nullable', 'integer', 'min:0'],
            'products'             => ['nullable', 'string'],
            'services'             => ['nullable', 'string'],
            'observations'         => ['nullable', 'string'],

            // Contactos
            'contacts'               => ['nullable', 'array'],
            'contacts.*.type'        => ['required', 'in:sales,billing,quality'],
            'contacts.*.name'        => ['required', 'string', 'max:255'],
            'contacts.*.email'       => ['required', 'email', 'max:255'],
            'contacts.*.phone'       => ['required', 'string', 'max:20'],
            'contacts.*.extension'   => ['nullable', 'string', 'max:10'],

            // Vehículos
            'vehicles'               => ['nullable', 'array'],
            'vehicles.*.brand_model' => ['required', 'string', 'max:255'],
            'vehicles.*.color'       => ['required', 'string', 'max:50'],
            'vehicles.*.plates'      => ['required', 'string', 'max:20'],

            // Personal
            'personnel'              => ['nullable', 'array'],
            'personnel.*.full_name'  => ['required', 'string', 'max:255'],
            'personnel.*.position'   => ['nullable', 'string', 'max:255'],

            // Certificaciones
            'certifications'                          => ['nullable', 'array'],
            'certifications.*.certification_type'     => ['required', 'string'],
            'certifications.*.certification_number'   => ['nullable', 'string', 'max:255'],
            'certifications.*.expiry_date'            => ['nullable', 'date'],
        ];
    }
}