<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'        => 'required|in:appel,email,rdv,visite,autre',
            'subject'     => 'required|string|max:200',
            'notes'       => 'nullable|string',
            'occurred_at' => 'required|date',
        ];
    }

    public function attributes(): array
    {
        return [
            'type'        => 'type d\'interaction',
            'subject'     => 'sujet',
            'occurred_at' => 'date',
        ];
    }
}
