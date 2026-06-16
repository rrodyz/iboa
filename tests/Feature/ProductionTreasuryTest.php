<?php
use App\Models\Company; use App\Models\FiscalYear; use App\Models\User; use App\Models\Product;
use App\Modules\Production\Models\ProductionOrder; use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Services\ProductionTreasuryService;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);

function trAdmin(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'TR'],['email'=>'tr@tr.io','current_fiscal_year_id'=>$fy->id]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}
function ofWithCost(string $status, int $total, int $margin=0): ProductionOrder {
    $co=Company::first();
    $o=ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-T'.rand(1000,9999),'status'=>$status,'quantity_requested'=>10]);
    ProductionCost::create(['company_id'=>$co->id,'production_order_id'=>$o->id,'material_cost'=>$total,'labor_cost'=>0,'machine_cost'=>0,'overhead_cost'=>0,'total_cost'=>$total,'cost_per_meter'=>0,'cost_per_unit'=>0,'margin'=>$margin]);
    return $o;
}

it('aggregates production treasury forecast', function(){
    $this->actingAs(trAdmin()); $co=Company::first();
    ofWithCost('en_cours', 100000);
    ofWithCost('lance', 50000);
    ofWithCost('termine', 200000, 30000);   // engaged ignores terminé; margin counted
    // MRP material need
    $matiere=Product::factory()->create(['stock_min'=>1000]);
    Coil::create(['company_id'=>$co->id,'product_id'=>$matiere->id,'reference'=>'B','initial_weight'=>1000,'remaining_weight'=>200,'cost_per_kg'=>500,'purchase_price'=>500000,'status'=>'disponible']); // deficit 800*500=400000

    $f=app(ProductionTreasuryService::class)->forecast();
    expect($f['engaged_cost'])->toBe(150000);       // 100k+50k
    expect($f['realized_margin'])->toBe(30000);
    expect($f['material_need'])->toBe(400000);
    expect($f['financing_need'])->toBe(550000);     // 150k+400k
    expect($f['active_count'])->toBe(2);
});

it('renders treasury forecast page', function(){
    $this->actingAs(trAdmin());
    $this->get(route('production.treasury'))->assertOk()->assertSee('Besoin de financement');
});
