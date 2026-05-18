<?php

use App\Events\StockAlertTriggered;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function stockAdmin(): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);
    return $user;
}

function stockCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2025'],
        ['starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(
        ['name' => 'Stock Test Co'],
        ['email' => 'stock@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

function stockWarehouse(): Warehouse
{
    $company = stockCompany();
    return Warehouse::firstOrCreate(
        ['code' => 'WH-TEST'],
        ['name' => 'Entrepôt Test', 'company_id' => $company->id, 'is_active' => true, 'is_default' => true]
    );
}

function stockProduct(array $attrs = []): Product
{
    return Product::factory()->create(array_merge([
        'name'             => 'Produit Test',
        'reference'        => 'PTEST-' . rand(100, 999),
        'is_stockable'     => true,
        'is_active'        => true,
        'valuation_method' => 'cmp',
        'stock_min'        => 5,
    ], $attrs));
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('StockService — CMP inbound', function () {

    it('creates a stock entry and calculates CMP correctly', function () {
        $user      = stockAdmin();
        $warehouse = stockWarehouse();
        $product   = stockProduct();
        $this->actingAs($user);

        /** @var StockService $svc */
        $svc = app(StockService::class);

        $movement = $svc->recordMovement([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'type'         => 'entree',
            'quantity'     => 100,
            'unit_cost'    => 1000,
            'occurred_at'  => now()->toDateString(),
        ]);

        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        expect($stock)->not->toBeNull()
            ->and((float) $stock->quantity)->toBe(100.0)
            ->and((float) $stock->avg_cost)->toBe(1000.0)
            ->and($movement->valuation_method)->toBe('cmp');
    });

    it('recalculates CMP correctly after second inbound', function () {
        $user      = stockAdmin();
        $warehouse = stockWarehouse();
        $product   = stockProduct(['valuation_method' => 'cmp']);
        $this->actingAs($user);

        $svc = app(StockService::class);

        // First entry: 100 units @ 1000
        $svc->recordMovement([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'type'         => 'entree',
            'quantity'     => 100,
            'unit_cost'    => 1000,
            'occurred_at'  => now()->toDateString(),
        ]);

        // Second entry: 50 units @ 1600
        // Expected CMP = (100*1000 + 50*1600) / 150 = 180000/150 = 1200
        $svc->recordMovement([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'type'         => 'entree',
            'quantity'     => 50,
            'unit_cost'    => 1600,
            'occurred_at'  => now()->toDateString(),
        ]);

        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        expect((float) $stock->quantity)->toBe(150.0)
            ->and((float) $stock->avg_cost)->toBe(1200.0);
    });

});

describe('StockService — outbound validation', function () {

    it('throws ValidationException when outbound quantity exceeds available stock', function () {
        $user      = stockAdmin();
        $warehouse = stockWarehouse();
        $product   = stockProduct();
        $this->actingAs($user);

        $svc = app(StockService::class);

        // Seed 10 units
        $svc->recordMovement([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'type'         => 'entree',
            'quantity'     => 10,
            'unit_cost'    => 500,
            'occurred_at'  => now()->toDateString(),
        ]);

        // Try to pull 15 — should fail
        expect(fn() => $svc->recordMovement([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'type'         => 'sortie',
            'quantity'     => 15,
            'unit_cost'    => 500,
            'occurred_at'  => now()->toDateString(),
        ]))->toThrow(ValidationException::class);
    });

    it('decrements stock correctly on valid outbound', function () {
        $user      = stockAdmin();
        $warehouse = stockWarehouse();
        $product   = stockProduct(['stock_min' => 0]); // disable alert for this test
        $this->actingAs($user);

        $svc = app(StockService::class);

        $svc->recordMovement([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'type'         => 'entree',
            'quantity'     => 20,
            'unit_cost'    => 500,
            'occurred_at'  => now()->toDateString(),
        ]);

        $svc->recordMovement([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'type'         => 'sortie',
            'quantity'     => 7,
            'unit_cost'    => 500,
            'occurred_at'  => now()->toDateString(),
        ]);

        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        expect((float) $stock->quantity)->toBe(13.0);
    });

    it('fires StockAlertTriggered when stock drops below minimum', function () {
        Event::fake([StockAlertTriggered::class]);

        $user      = stockAdmin();
        $warehouse = stockWarehouse();
        $product   = stockProduct(['stock_min' => 10]);
        $this->actingAs($user);

        $svc = app(StockService::class);

        // Seed 15 units
        $svc->recordMovement([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'type'         => 'entree',
            'quantity'     => 15,
            'unit_cost'    => 500,
            'occurred_at'  => now()->toDateString(),
        ]);

        // Pull 8 → remaining = 7 < stock_min 10 → alert should fire
        $svc->recordMovement([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'type'         => 'sortie',
            'quantity'     => 8,
            'unit_cost'    => 500,
            'occurred_at'  => now()->toDateString(),
        ]);

        Event::assertDispatched(StockAlertTriggered::class);
    });

});
