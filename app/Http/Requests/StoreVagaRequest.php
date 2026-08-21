<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVagaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled in controllers via middleware/roles.
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo'          => 'required|string|max:100',
            'tipo'            => 'required|integer',
            'area'            => 'required|string|max:45',
            'status'          => 'required|boolean',
            'data_publicacao' => 'required|date',
            'empresa_cnpj'    => 'required|integer|exists:empresa,cnpj',
        ];
    }
}
