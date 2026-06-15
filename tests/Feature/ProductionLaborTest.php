<?php
use App\Models\Company; use App\Models\FiscalYear; use App\Models\User; use App\Models\BillOfMaterial as BOMx;
use App\Modules\Production\Models\ProductionOrder; use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionTimeLog;
use App\Modules\Production\Services\LaborService; use App\Modules\Production\Services\ProductionCostService;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);

function laborAdmin(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'L'],['email'=>'l@l.io','current_fiscal_year_id'=>$fy->id]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}
function laborOrder(): ProductionOrder {
    $co=Company::first();
    return ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-L'.rand(100,999),'status'=>'en_cours','quantity_requested'=>10,'quantity_produced'=>10]);
}

it('logs operator time and computes labor cost', function(){
    $this->actingAs(laborAdmin());
    $o=laborOrder();
    app(LaborService::class)->log($o,['hours'=>8,'hourly_cost'=>1500]);
    app(LaborService::class)->log($o,['hours'=>4,'hourly_cost'=>2000,'is_overtime'=>true]);
    expect(app(LaborService::class)->totalLaborCost($o))->toBe(20000); // 8*1500 + 4*2000
    expect(app(LaborService::class)->totalHours($o))->toEqual(12.0);
});

it('cost service uses real labor from time logs over BOM estimate', function(){
    $this->actingAs(laborAdmin());
    $co=Company::first();
    $bom=BillOfMaterial::create(['company_id'=>$co->id,'name'=>'B','labor_per_unit'=>100,'is_active'=>true]); // estimate 100*10=1000
    $o=ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-L2'.rand(100,999),'status'=>'en_cours','quantity_requested'=>10,'quantity_produced'=>10,'bill_of_material_id'=>$bom->id]);
    app(LaborService::class)->log($o,['hours'=>10,'hourly_cost'=>1500]); // real 15000
    $cost=app(ProductionCostService::class)->compute($o,['overhead_rate'=>0]);
    expect($cost->labor_cost)->toBe(15000); // real, not BOM 1000
});

it('blocks pointing on draft OF', function(){
    $this->actingAs(laborAdmin());
    $o=laborOrder(); $o->update(['status'=>'brouillon']);
    expect(fn()=>app(LaborService::class)->log($o,['hours'=>5,'hourly_cost'=>1000]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('records time via route', function(){
    $this->actingAs(laborAdmin());
    $o=laborOrder();
    $this->post(route('production.orders.time',$o),['hours'=>6,'hourly_cost'=>1200])->assertRedirect();
    expect(ProductionTimeLog::where('production_order_id',$o->id)->count())->toBe(1);
});
