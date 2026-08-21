<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVagaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled in controllers via middleware/roles.
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo'  => 'sometimes|string|max:100',
            'tipo'    => 'sometimes|integer',
            'area'    => 'sometimes|string|max:45',
            'status'  => 'sometimes|boolean',
        ];
    }
}
