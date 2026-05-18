<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        static $counter = 1;

        $subtotal  = $this->faker->numberBetween(100_000, 5_000_000);
        $tax       = (int) round($subtotal * 0.18);
        $total     = $subtotal + $tax;

        return [
            'company_id'       => Company::first()?->id ?? 1,
            'client_id'        => Client::factory(),
            'fiscal_year_id'   => FiscalYear::where('is_current', true)->first()?->id ?? FiscalYear::first()?->id ?? 1,
            'number'           => 'F2025-TEST-' . str_pad($counter++, 4, '0', STR_PAD_LEFT),
            'type'             => 'standard',
            'status'           => 'brouillon',
            'issued_at'        => now()->toDateString(),
            'due_at'           => now()->addDays(30)->toDateString(),
            'currency_code'    => 'XOF',
            'exchange_rate'    => 1,
            'subtotal_ht'      => $subtotal,
            'total_discount'   => 0,
            'total_tax'        => $tax,
            'total_ttc'        => $total,
            'paid_amount'      => 0,
            'remaining_amount' => $total,
            'created_by'       => User::first()?->id ?? 1,
        ];
    }
}
