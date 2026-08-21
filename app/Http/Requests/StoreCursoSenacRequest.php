<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCursoSenacRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome_curso'    => 'required|string|max:100',
            'unidade'       => 'required|string|max:45',
            'carga_horaria' => 'nullable|integer|min:1',
            'concluido_em'  => 'required|date',
        ];
    }
}
