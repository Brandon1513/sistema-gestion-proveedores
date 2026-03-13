<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('documents.validate');
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:approved,rejected'],
            'comments' => ['required_if:action,rejected', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'La acción es requerida',
            'action.in' => 'La acción debe ser "approved" o "rejected"',
            'comments.required_if' => 'Los comentarios son requeridos al rechazar un documento',
        ];
    }
}