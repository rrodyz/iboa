<?php
use App\Models\Company; use App\Models\FiscalYear; use App\Models\User;
use App\Modules\Production\Models\BillOfMaterial; use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Services\ProductionCostService; use App\Modules\Production\Services\CoilConsumptionService;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);
function scAdmin(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'SC'],['email'=>'sc@sc.io','current_fiscal_year_id'=>$fy->id]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}
it('computes standard cost and variance', function(){
    $this->actingAs(scAdmin()); $co=Company::first();
    // std/unité = 5000+1000+500+200 = 6700 ; qty 10 -> standard 67000
    $bom=BillOfMaterial::create(['company_id'=>$co->id,'name'=>'B','is_active'=>true,'std_material_cost'=>5000,'std_labor_cost'=>1000,'std_machine_cost'=>500,'std_overhead_cost'=>200,'labor_per_unit'=>0,'machine_time_per_unit'=>0]);
    $of=ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-SC','status'=>'en_cours','bill_of_material_id'=>$bom->id,'quantity_requested'=>10,'quantity_produced'=>10]);
    // réel matière 50000 via conso
    $coil=Coil::create(['company_id'=>$co->id,'reference'=>'B1','initial_weight'=>1000,'remaining_weight'=>1000,'cost_per_kg'=>500,'purchase_price'=>500000,'status'=>'disponible']);
    app(CoilConsumptionService::class)->consume($of,$coil,100); // 100*500=50000
    $cost=app(ProductionCostService::class)->compute($of,['overhead_rate'=>0]);
    expect($cost->standard_total)->toBe(67000);       // 6700*10
    expect($cost->total_cost)->toBe(50000);           // matière réelle seule
    expect($cost->variance)->toBe(50000-67000);       // -17000 favorable
});
it('null variance when no standard defined', function(){
    $this->actingAs(scAdmin()); $co=Company::first();
    $bom=BillOfMaterial::create(['company_id'=>$co->id,'name'=>'B2','is_active'=>true]); // std=0
    $of=ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-SC2','status'=>'en_cours','bill_of_material_id'=>$bom->id,'quantity_requested'=>5,'quantity_produced'=>5]);
    $cost=app(ProductionCostService::class)->compute($of,['overhead_rate'=>0]);
    expect($cost->standard_total)->toBe(0);
    expect($cost->variance)->toBeNull();
});
it('saves std costs via BOM form', function(){
    $this->actingAs(scAdmin());
    $this->post(route('production.bom.store'),['name'=>'BomStd','std_material_cost'=>3000,'std_labor_cost'=>800,'std_machine_cost'=>400,'std_overhead_cost'=>100,'is_active'=>'1'])
        ->assertRedirect(route('production.bom.index'));
    $b=BillOfMaterial::where('name','BomStd')->first();
    expect((int)$b->std_material_cost)->toBe(3000);
});
