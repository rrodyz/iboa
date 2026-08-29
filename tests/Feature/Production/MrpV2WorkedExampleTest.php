<?php

/**
 * [PROD-01 — Closure verification, section 2/3] Exemple chiffré EXACT de la
 * directive de clôture, exécuté sur le moteur réel (MrpPeggingService +
 * BomExplosionService) — jamais un résultat écrit à la main.
 *
 * CMD-X : PF-A × 10. BOM : 1 PF-A = 2 SF-B (semi-fini, propre BOM),
 * 1 SF-B = 3 MP-C. Besoin brut attendu : 20 SF-B, 60 MP-C.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\MrpPeggingService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

it('exemple chiffré complet PF-A/SF-B/MP-C, réel moteur, avec dispo/réservé/réception/OF attendu/date besoin', function () {
    $fy = FiscalYear::firstOrCreate(['label' => 'MRPV2-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'MRPV2 Co'], ['email' => 'mrpv2@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    $wh = Warehouse::firstOrCreate(['code' => 'W-MRPV2'], ['name' => 'W-MRPV2', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    $pfA = Product::factory()->create(['name' => 'PF-A', 'production_mode' => 'mto', 'is_manufacturable' => true]);
    $sfB = Product::factory()->create(['name' => 'SF-B', 'is_semi_finished' => true, 'is_manufacturable' => true, 'is_stockable' => true]);
    $mpC = Product::factory()->create(['name' => 'MP-C', 'is_stockable' => true]);

    BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pfA->id, 'name' => 'BOM PF-A', 'is_active' => true])
        ->lines()->create(['product_id' => $sfB->id, 'label' => 'SF-B', 'quantity_per_meter' => 2]);
    BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $sfB->id, 'name' => 'BOM SF-B', 'is_active' => true])
        ->lines()->create(['product_id' => $mpC->id, 'label' => 'MP-C', 'quantity_per_meter' => 3]);

    // Stock disponible + réservé.
    ProductStock::create(['product_id' => $sfB->id, 'warehouse_id' => $wh->id, 'quantity' => 8, 'reserved_quantity' => 3, 'avg_cost' => 500]);
    ProductStock::create(['product_id' => $mpC->id, 'warehouse_id' => $wh->id, 'quantity' => 20, 'reserved_quantity' => 5, 'avg_cost' => 100]);

    // OF attendu (planifié, non terminé) sur SF-B.
    ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-MRPV2-SFB',
        'status' => 'en_cours', 'quantity_requested' => 3, 'product_id' => $sfB->id,
    ]);

    // Réception attendue (PO ouvert, non soldé) sur MP-C.
    $po = PurchaseOrder::create([
        'company_id' => $co->id, 'supplier_id' => Supplier::factory()->create()->id,
        'number' => 'PO-MRPV2', 'status' => 'confirme', 'currency_code' => 'XOF',
        'issued_at' => now(), 'ordered_at' => now(), 'expected_at' => '2026-09-20',
    ]);
    $po->items()->create(['product_id' => $mpC->id, 'description' => 'MP-C', 'quantity' => 10, 'unit_price' => 100, 'line_total_ht' => 1000, 'line_tax' => 0, 'line_total_ttc' => 1000, 'received_quantity' => 0]);

    // CMD-X — date du besoin = 2026-10-01.
    $cmdX = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id, 'number' => 'CMD-X',
        'status' => 'confirme', 'issued_at' => now(), 'delivery_date' => '2026-10-01',
    ]);
    $cmdX->items()->create([
        'product_id' => $pfA->id, 'description' => 'PF-A', 'quantity' => 10,
        'delivered_quantity' => 0, 'unit_price' => 10000,
        'line_total_ht' => 100000, 'line_tax' => 0, 'line_total_ttc' => 100000,
    ]);

    $rows = app(MrpPeggingService::class)->explodedNetRequirements()->keyBy(fn ($r) => $r['product']->name);

    // ── Preuve moteur réel : dump brut pour le rapport ──────────────────────
    fwrite(STDERR, "\n=== MRP V2 WORKED EXAMPLE — REAL ENGINE OUTPUT ===\n");
    foreach ($rows as $name => $r) {
        fwrite(STDERR, sprintf(
            "SOURCE=%s SOURCE_ID=%s NEED_DATE=%s GROSS=%s AVAILABLE=%s PLAN(OF)=%s EXPECTED_RECEIPT=%s NET=%s\n",
            $name, $r['product']->id, (string) ($r['sources']->first()['need_date'] ?? '—'),
            $r['besoin_brut'], $r['dispo'], $r['plan'], $r['recu'], $r['besoin_net']
        ));
        foreach ($r['sources'] as $s) {
            fwrite(STDERR, sprintf("  PEGGING SOURCE: type=%s id=%s label=%s qty=%s need_date=%s\n", $s['source_type'], $s['source_id'], $s['source_label'], $s['quantity'], $s['need_date']));
        }
    }

    expect($rows)->toHaveKey('SF-B')->toHaveKey('MP-C');

    $sf = $rows['SF-B'];
    expect($sf['besoin_brut'])->toBe(20.0)   // 2 SF-B × 10 PF-A
        ->and($sf['dispo'])->toBe(5.0)        // 8 − 3
        ->and($sf['plan'])->toBe(3.0)         // OF-MRPV2-SFB
        ->and($sf['recu'])->toBe(0.0)
        ->and($sf['besoin_net'])->toBe(12.0); // 20 − 5 − 3 − 0

    $mp = $rows['MP-C'];
    expect($mp['besoin_brut'])->toBe(60.0)   // 3 MP-C × 20 SF-B (explosion cascadée, PAS le besoin net de SF-B)
        ->and($mp['dispo'])->toBe(15.0)       // 20 − 5
        ->and($mp['recu'])->toBe(10.0)        // PO-MRPV2
        ->and($mp['besoin_net'])->toBe(35.0); // 60 − 15 − 0 − 10

    // Pegging : chaque ligne de besoin remonte à sa source. LIMITATION
    // CONSTATÉE (documentée, pas masquée) : la source de MP-C est CMD-X
    // DIRECTEMENT — la chaîne affichée est MP-C→CMD-X (2 sauts), PAS
    // MP-C→SF-B→PF-A→CMD-X (4 sauts) : BomExplosionService cascade
    // correctement les QUANTITÉS à travers SF-B (60 = 3×20, pas 3×besoin
    // net de SF-B), mais MrpPeggingService::explodeOne() tague CHAQUE ligne
    // explosée avec la source de DEMANDE INITIALE, sans relayer le
    // sous-BOM intermédiaire dans le pegging affiché.
    expect($mp['sources']->first()['source_type'])->toBe('commande')
        ->and($mp['sources']->first()['source_label'])->toBe('CMD-X')
        ->and($mp['sources']->first()['depth'])->toBe(1); // depth=1 = MP-C est sous SF-B (depth=0), la profondeur est bien tracée
});
