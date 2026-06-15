<?php
use App\Models\Company; use App\Models\FiscalYear; use App\Models\User;
use App\Modules\Production\Models\WorkCenter; use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\PlanningService;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);
function plAdmin(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'PL'],['email'=>'pl@pl.io','current_fiscal_year_id'=>$fy->id]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}
it('computes load vs capacity per work center', function(){
    $this->actingAs(plAdmin()); $co=Company::first();
    // centre 8h/j, rendement 100% -> horizon 1j = 480 min capacité
    $wc=WorkCenter::create(['company_id'=>$co->id,'code'=>'C1','name'=>'Découpe','capacity_hours_per_day'=>8,'cost_per_hour'=>5000,'efficiency_rate'=>100,'is_active'=>true]);
    $of=ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-PL','status'=>'en_cours','quantity_requested'=>10]);
    $of->operations()->create(['company_id'=>$co->id,'work_center_id'=>$wc->id,'sequence'=>10,'name'=>'A','planned_minutes'=>240,'status'=>'pending']);
    $of->operations()->create(['company_id'=>$co->id,'work_center_id'=>$wc->id,'sequence'=>20,'name'=>'B','planned_minutes'=>120,'status'=>'done']); // done -> exclu
    $p=app(PlanningService::class)->loadByWorkCenter(1);
    $row=collect($p['rows'])->firstWhere('id',$wc->id);
    expect($row['planned_h'])->toEqual(4.0);   // 240 min (B exclue car done)
    expect($row['capacity_h'])->toEqual(8.0);
    expect($row['occupation'])->toEqual(50.0);
    expect($row['status'])->toBe('ok');
});
it('flags overload', function(){
    $this->actingAs(plAdmin()); $co=Company::first();
    $wc=WorkCenter::create(['company_id'=>$co->id,'code'=>'C2','name'=>'X','capacity_hours_per_day'=>1,'cost_per_hour'=>0,'efficiency_rate'=>100,'is_active'=>true]); // 60 min/j
    $of=ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-PL2','status'=>'lance','quantity_requested'=>1]);
    $of->operations()->create(['company_id'=>$co->id,'work_center_id'=>$wc->id,'sequence'=>10,'name'=>'A','planned_minutes'=>200,'status'=>'pending']); // 200>60
    $p=app(PlanningService::class)->loadByWorkCenter(1);
    expect(collect($p['rows'])->firstWhere('id',$wc->id)['status'])->toBe('surcharge');
    expect($p['overloaded'])->toBe(1);
});
it('renders planning page', function(){
    $this->actingAs(plAdmin());
    $this->get(route('production.planning'))->assertOk()->assertSee('Plan de charge');
});
