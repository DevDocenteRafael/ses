<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCursoExternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome_curso'    => 'required|string|max:100',
            'instituicao'   => 'required|string|max:100',
            'carga_horaria' => 'nullable|integer|min:1',
            'concluido_em'  => 'required|date',
        ];
    }
}
