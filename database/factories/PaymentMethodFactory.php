<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        static $counter = 1;

        return [
            'name'               => 'Mode Paiement ' . $counter,
            'code'               => 'MP' . str_pad($counter++, 3, '0', STR_PAD_LEFT),
            'type'               => 'especes',
            'is_mobile_money'    => false,
            'requires_reference' => false,
            'is_active'          => true,
            'sort_order'         => $counter,
        ];
    }
}
