<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInfoProfissionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sobre_mim'          => 'nullable|string|max:200',
            'cargo_de_interesse' => 'nullable|string|max:45',
            'area_de_atuacao'    => 'required|string|max:45',
            'habilidades'        => 'nullable|array',
            'habilidades.*'      => 'string|max:45',
        ];
    }
}
