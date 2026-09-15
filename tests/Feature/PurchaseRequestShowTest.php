<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\PurchaseRequestService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

it('affiche le détail d\'une demande d\'achat convertie', function () {
    $this->withoutVite();

    $fiscalYear = FiscalYear::create([
        'label' => 'DA-SHOW-2026',
        'starts_at' => '2026-01-01',
        'ends_at' => '2026-12-31',
        'status' => 'ouvert',
        'is_current' => true,
    ]);

    $company = Company::create([
        'name' => 'DA Show Test',
        'email' => 'da-show@example.test',
        'current_fiscal_year_id' => $fiscalYear->id,
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    $supplier = Supplier::create([
        'code' => 'FOUR-DA-SHOW',
        'type' => 'entreprise',
        'name' => 'Fournisseur test DA',
        'is_active' => true,
        'balance' => 0,
    ]);

    $product = Product::factory()->create();
    $unit = Unit::firstOrCreate(
        ['name' => 'Unité DA Show'],
        ['abbreviation' => 'u-da']
    );

    $this->actingAs($user);

    $service = app(PurchaseRequestService::class);
    $purchaseRequest = $service->create([
        'department' => 'Production',
        'items' => [[
            'product_id' => $product->id,
            'description' => 'Article de test',
            'quantity' => 2,
            'estimated_price' => 10_000,
            'unit_id' => $unit->id,
        ]],
    ]);

    $service->submit($purchaseRequest);
    $service->approve($purchaseRequest);
    $service->convertToPurchaseOrder($purchaseRequest, $supplier->id);
    $purchaseRequest->refresh();

    // Certaines demandes historiques/importées n'ont pas de timestamp de
    // création. Leur consultation doit rester possible.
    $purchaseRequest->timestamps = false;
    $purchaseRequest->forceFill(['created_at' => null])->save();
    $purchaseRequest->refresh();

    $this->get(route('achats.demandes-achat.show', $purchaseRequest))
        ->assertOk()
        ->assertSee($purchaseRequest->number)
        ->assertSee('Commande générée');
});
