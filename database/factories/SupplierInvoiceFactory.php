<?php

namespace Database\Factories;

use App\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierInvoice>
 */
class SupplierInvoiceFactory extends Factory
{
    protected $model = SupplierInvoice::class;

    public function definition(): array
    {
        $ttc = $this->faker->numberBetween(50_000, 2_000_000);

        return [
            'number'           => 'FF-' . $this->faker->unique()->numerify('######'),
            'status'           => 'validee',
            'received_at'      => now()->subDays(5)->toDateString(),
            'due_at'           => now()->addDays(25)->toDateString(),
            'currency_code'    => 'XOF',
            'exchange_rate'    => 1,
            'subtotal_ht'      => $ttc,
            'total_tax'        => 0,
            'total_ttc'        => $ttc,
            'paid_amount'      => 0,
            'remaining_amount' => $ttc,
        ];
    }
}
