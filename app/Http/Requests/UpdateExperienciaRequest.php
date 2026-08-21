<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExperienciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo'        => 'sometimes|string|max:30',
            'cargo'       => 'sometimes|string|max:100',
            'empresa'     => 'sometimes|string|max:100',
            'local'       => 'nullable|string|max:100',
            'data_inicio' => 'sometimes|date',
            'data_fim'    => 'nullable|date|after_or_equal:data_inicio',
            'descricao'   => 'nullable|string',
        ];
    }
}
