<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDadosAcademicosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instituicao'      => 'required|string|max:100',
            'curso'            => 'required|string|max:45',
            'segmento'         => 'nullable|string|max:60',
            'tipo_curso'       => 'nullable|string|max:30',
            'unidade'          => 'required|string|max:45',
            'ano_de_conclusao' => 'required|date',
        ];
    }
}
