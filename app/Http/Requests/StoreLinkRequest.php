<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'linkedin'  => 'nullable|url|max:100',
            'portfolio' => 'nullable|url|max:100',
            'github'    => 'nullable|url|max:100',
        ];
    }
}
