<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use App\Services\OrderService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function mtoAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'MTO'], ['email' => 'mto@mto.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'W'], ['name' => 'W', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function mtoOrder(Product $product, int $qty): Order
{
    $co = Company::first();
    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id, 'number' => 'CMD-MTO' . rand(100, 999),
        'status' => 'brouillon', 'issued_at' => now(),
    ]);
    $order->items()->create([
        'product_id' => $product->id, 'description' => $product->name, 'quantity' => $qty,
        'unit_price' => 1000, 'line_total_ht' => $qty * 1000, 'line_tax' => 0, 'line_total_ttc' => $qty * 1000,
    ]);

    return $order;
}

it('auto-creates a draft OF when confirming an order with an MTO product short on stock', function () {
    $this->actingAs(mtoAdmin());
    $co = Company::first();
    $product = Product::factory()->create(['production_mode' => 'mto', 'is_stockable' => true]);
    BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $product->id, 'name' => 'BOM MTO', 'is_active' => true]);

    $order = mtoOrder($product, 20); // aucun stock → manque 20

    app(OrderService::class)->confirm($order);

    $of = ProductionOrder::where('order_id', $order->id)->where('product_id', $product->id)->first();
    expect($of)->not->toBeNull();
    expect($of->status)->toBe('brouillon');
    expect((float) $of->quantity_requested)->toBe(20.0);
});

it('does not trigger an OF for an MTS product', function () {
    $this->actingAs(mtoAdmin());
    $co = Company::first();
    $product = Product::factory()->create(['production_mode' => 'mts', 'is_stockable' => true]);
    BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $product->id, 'name' => 'BOM MTS', 'is_active' => true]);

    $order = mtoOrder($product, 20);
    app(OrderService::class)->confirm($order);

    expect(ProductionOrder::where('order_id', $order->id)->exists())->toBeFalse();
});
