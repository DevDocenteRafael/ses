<?php

namespace App\Http\Requests;

use App\Models\Convite;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'    => 'required|integer|in:' . implode(',', [
                Convite::STATUS_PENDENTE,
                Convite::STATUS_ACEITO,
                Convite::STATUS_RECUSADO,
                Convite::STATUS_ARQUIVADO,
            ]),
            'descricao' => 'sometimes|string|max:150',
        ];
    }
}
