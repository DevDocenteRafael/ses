<?php

namespace App\Http\Requests;

use App\Models\Candidato;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCandidatoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pessoaId = Candidato::findOrFail($this->route('matricula'))->pessoa_id_pessoa;

        return [
            'status'   => 'sometimes|boolean',
            'nome'     => 'sometimes|string|max:100',
            'email'    => 'sometimes|email|unique:pessoa,email,' . $pessoaId . ',id_pessoa',
            'telefone' => 'sometimes|string|max:11|unique:pessoa,telefone,' . $pessoaId . ',id_pessoa',
        ];
    }
}
