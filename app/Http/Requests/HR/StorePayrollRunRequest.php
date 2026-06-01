<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('rh.payroll.manage');
    }

    public function rules(): array
    {
        return [
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year'  => ['required', 'integer', 'min:2020', 'max:2100'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'period_month.between' => 'Le mois doit être compris entre 1 et 12.',
            'period_year.min'      => 'L\'année doit être 2020 ou ultérieure.',
            'period_year.max'      => 'L\'année ne peut pas dépasser 2100.',
        ];
    }
}
