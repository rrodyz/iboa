<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\SalesProductionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function cockpitAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'CK'], ['email' => 'ck@ck.io', 'current_fiscal_year_id' => $fy->id]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function cockpitOrder(): Order
{
    $co = Company::first();
    $client = Client::factory()->create();

    return Order::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'client_id' => $client->id, 'number' => 'CMD-CK' . rand(100, 999), 'status' => 'confirme', 'issued_at' => now()]);
}

it('reports no OF when order has none', function () {
    $this->actingAs(cockpitAdmin());
    $s = app(SalesProductionService::class)->summary(cockpitOrder());
    expect($s['count'])->toBe(0);
    expect($s['aggregate']['label'])->toBe('Aucun OF');
});

it('aggregates en_production when an OF is in progress', function () {
    $this->actingAs(cockpitAdmin());
    $co = Company::first();
    $o = cockpitOrder();
    ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-CK1', 'status' => 'en_cours', 'order_id' => $o->id, 'quantity_requested' => 20]);
    $s = app(SalesProductionService::class)->summary($o);
    expect($s['count'])->toBe(1);
    expect($s['aggregate']['label'])->toBe('En production');
});

it('aggregates produit fini disponible when all OF finished', function () {
    $this->actingAs(cockpitAdmin());
    $co = Company::first();
    $o = cockpitOrder();
    ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-CK2', 'status' => 'termine', 'order_id' => $o->id, 'quantity_requested' => 20, 'quantity_produced' => 20, 'finished_at' => now()]);
    $s = app(SalesProductionService::class)->summary($o);
    expect($s['aggregate']['label'])->toBe('Produit fini disponible');
});

it('renders the production cockpit on the order page', function () {
    $this->actingAs(cockpitAdmin());
    $co = Company::first();
    $o = cockpitOrder();
    ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-CK3', 'status' => 'en_cours', 'order_id' => $o->id, 'quantity_requested' => 20]);
    $this->get(route('ventes.commandes.show', $o))
        ->assertOk()
        ->assertSee('Suivi production')
        ->assertSee('OF-CK3');
});
