<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProviderDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'], // máx 10MB
            'document_type_id' => ['required', 'exists:document_types,id'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:issue_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'El archivo es requerido',
            'file.file' => 'Debe ser un archivo válido',
            'file.max' => 'El archivo no debe exceder 10MB',
            'document_type_id.required' => 'El tipo de documento es requerido',
            'document_type_id.exists' => 'El tipo de documento no es válido',
            'expiry_date.after' => 'La fecha de vencimiento debe ser posterior a la fecha de emisión',
        ];
    }
}