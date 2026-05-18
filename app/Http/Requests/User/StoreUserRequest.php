<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'role'      => ['required', 'string', 'exists:roles,name'],
            'password'  => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'      => 'nom',
            'email'     => 'adresse e-mail',
            'phone'     => 'téléphone',
            'job_title' => 'poste',
            'role'      => 'rôle',
            'password'  => 'mot de passe',
        ];
    }
}
