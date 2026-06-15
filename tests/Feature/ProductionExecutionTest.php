<?php

use App\Modules\Production\Models\Coil;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Modules\Production\Models\ProductionOrder;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function p4Company(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );

    return Company::firstOrCreate(['name' => 'Exec Co'], ['email' => 'exec@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function p4Admin(): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => p4Company()->id, 'email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function p4Order(): ProductionOrder
{
    return ProductionOrder::create([
        'company_id'         => p4Company()->id,
        'fiscal_year_id'     => p4Company()->current_fiscal_year_id,
        'number'             => 'OF-2026-9000',
        'status'             => 'en_cours',
        'quantity_requested' => 100,
        'quantity_produced'  => 0,
        'launched_at'        => now(),
    ]);
}

function p4Coil(array $o = []): Coil
{
    return Coil::create(array_merge([
        'company_id'       => p4Company()->id,
        'reference'        => 'BOB-EXEC-' . rand(1000, 9999),
        'initial_weight'   => 1000,
        'remaining_weight' => 1000,
        'cost_per_kg'      => 500,
        'purchase_price'   => 500000,
        'status'           => 'disponible',
    ], $o));
}

function p4Warehouse(): Warehouse
{
    return Warehouse::firstOrCreate(
        ['code' => 'WH-EXEC'],
        ['name' => 'Exec WH', 'company_id' => p4Company()->id, 'is_active' => true, 'is_default' => true]
    );
}

it('consumes a coil: decrements weight, computes cost, sets status', function () {
    $this->actingAs(p4Admin());
    $order = p4Order();
    $coil  = p4Coil();

    $this->post(route('production.orders.consume', $order), [
        'coil_id' => $coil->id, 'weight_consumed' => 200, 'length_consumed' => 50,
    ])->assertRedirect();

    $coil->refresh();
    expect((float) $coil->remaining_weight)->toEqual(800.0);
    expect($coil->status)->toBe('en_production');

    $cons = $order->consumptions()->first();
    expect((float) $cons->cost)->toEqual(100000.0); // 200 * 500
});

it('rejects consuming more than remaining weight', function () {
    $this->actingAs(p4Admin());
    $order = p4Order();
    $coil  = p4Coil(['remaining_weight' => 100]);

    $this->post(route('production.orders.consume', $order), [
        'coil_id' => $coil->id, 'weight_consumed' => 500,
    ])->assertSessionHasErrors('weight');

    expect((float) $coil->fresh()->remaining_weight)->toEqual(100.0);
});

it('rejects consumption when OF not in progress', function () {
    $this->actingAs(p4Admin());
    $order = p4Order();
    $order->update(['status' => 'lance']);
    $coil = p4Coil();

    $this->post(route('production.orders.consume', $order), [
        'coil_id' => $coil->id, 'weight_consumed' => 10,
    ])->assertSessionHasErrors('status');
});

it('reverses a consumption and restores coil weight', function () {
    $this->actingAs(p4Admin());
    $order = p4Order();
    $coil  = p4Coil();

    $this->post(route('production.orders.consume', $order), ['coil_id' => $coil->id, 'weight_consumed' => 300]);
    $cons = $order->consumptions()->first();
    expect((float) $coil->fresh()->remaining_weight)->toEqual(700.0);

    $this->delete(route('production.consumptions.destroy', $cons))->assertRedirect();
    expect((float) $coil->fresh()->remaining_weight)->toEqual(1000.0);
    expect($coil->fresh()->status)->toBe('disponible');
    expect($order->consumptions()->count())->toBe(0);
});

it('records an output and enters finished goods into stock', function () {
    $this->actingAs(p4Admin());
    $order   = p4Order();
    $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $wh      = p4Warehouse();
    $order->update(['product_id' => $product->id]);

    $this->post(route('production.orders.output', $order), [
        'warehouse_id' => $wh->id, 'length' => 6, 'quantity' => 10, 'unit_cost' => 3000,
    ])->assertRedirect();

    $out = $order->outputs()->first();
    expect((float) $out->total_meters)->toEqual(60.0);
    expect($out->stock_movement_id)->not->toBeNull();
    expect((float) $order->fresh()->quantity_produced)->toEqual(10.0);

    $stock = \App\Models\ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->first();
    expect((float) $stock->quantity)->toEqual(10.0);
});

it('blocks reversing execution records once OF is no longer in progress', function () {
    $this->actingAs(p4Admin());
    $order = p4Order();
    $coil  = p4Coil();
    $wh    = p4Warehouse();
    $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $order->update(['product_id' => $product->id]);

    // record while en_cours
    $this->post(route('production.orders.consume', $order), ['coil_id' => $coil->id, 'weight_consumed' => 100]);
    $this->post(route('production.orders.output', $order), ['warehouse_id' => $wh->id, 'length' => 6, 'quantity' => 10, 'unit_cost' => 3000]);
    $this->post(route('production.orders.waste', $order), ['type' => 'rebut', 'weight' => 10]);

    $cons = $order->consumptions()->first();
    $out  = $order->outputs()->first();
    $waste = $order->wastes()->first();

    // close the OF
    $order->update(['status' => 'termine', 'finished_at' => now()]);

    $this->delete(route('production.consumptions.destroy', $cons))->assertSessionHasErrors('status');
    $this->delete(route('production.outputs.destroy', $out))->assertSessionHasErrors('status');
    $this->delete(route('production.wastes.destroy', $waste))->assertSessionHasErrors('status');

    // nothing removed, coil weight intact (still consumed)
    expect($order->consumptions()->count())->toBe(1);
    expect($order->outputs()->count())->toBe(1);
    expect($order->wastes()->count())->toBe(1);
    expect((float) $coil->fresh()->remaining_weight)->toEqual(900.0);
});

it('records a waste and values it from consumed cost', function () {
    $this->actingAs(p4Admin());
    $order = p4Order();
    $coil  = p4Coil();
    // consume first so average cost/kg = 500
    $this->post(route('production.orders.consume', $order), ['coil_id' => $coil->id, 'weight_consumed' => 100]);

    $this->post(route('production.orders.waste', $order), [
        'type' => 'rebut', 'weight' => 20, 'reason' => 'Bord abîmé',
    ])->assertRedirect();

    $waste = $order->wastes()->first();
    expect((float) $waste->value)->toEqual(10000.0); // 20 * 500
    expect($waste->type)->toBe('rebut');
});
