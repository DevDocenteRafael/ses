<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePreferenciasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_de_contratacao'        => 'nullable|integer|min:0',
            'disponibilidade_de_horario' => 'nullable|string|max:30',
            'regiao_administrativa'      => 'required|string|max:100',
            'pretensao_salarial'         => 'nullable|numeric|min:0',
        ];
    }
}
