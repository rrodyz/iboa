<?php

namespace Database\Factories;

use App\Models\CashAccount;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashAccountFactory extends Factory
{
    protected $model = CashAccount::class;

    public function definition(): array
    {
        static $counter = 1;

        return [
            'company_id'      => Company::first()?->id ?? 1,
            'name'            => 'Caisse Test ' . $counter++,
            'code'            => 'CAISSE-' . str_pad($counter, 3, '0', STR_PAD_LEFT),
            'type'            => 'caisse',
            'currency_code'   => 'XOF',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_default'      => false,
            'is_active'       => true,
        ];
    }
}
