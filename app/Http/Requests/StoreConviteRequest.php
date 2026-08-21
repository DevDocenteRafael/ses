<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'descricao'            => 'required|string|max:150',
            'empresa_cnpj'         => 'required|string|exists:empresa,cnpj',
            'candidatos_matricula' => 'required|integer|exists:candidato,matricula',
            'vagas_id_vaga'        => 'required|integer|exists:vagas,id_vaga',
        ];
    }
}
