<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExperienciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo'        => 'required|string|max:30',
            'cargo'       => 'required|string|max:100',
            'empresa'     => 'required|string|max:100',
            'local'       => 'nullable|string|max:100',
            'data_inicio' => 'required|date',
            'data_fim'    => 'nullable|date|after_or_equal:data_inicio',
            'descricao'   => 'nullable|string',
        ];
    }
}
