<?php
use App\Models\Company; use App\Models\FiscalYear; use App\Models\User;
use App\Modules\Production\Models\WorkCenter; use App\Modules\Production\Models\ProductionMachine;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);
function wcAdmin(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'WC'],['email'=>'wc@wc.io','current_fiscal_year_id'=>$fy->id]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}
it('renders index + create', function(){
    $this->actingAs(wcAdmin());
    $this->get(route('production.work-centers.index'))->assertOk()->assertSee('Centres de travail');
    $this->get(route('production.work-centers.create'))->assertOk();
});
it('creates a work center', function(){
    $this->actingAs(wcAdmin()); $co=Company::first();
    $m=ProductionMachine::create(['company_id'=>$co->id,'code'=>'M','name'=>'M','type'=>'mixte','status'=>'active','is_active'=>true]);
    $this->post(route('production.work-centers.store'),['code'=>'CT1','name'=>'Découpe','machine_id'=>$m->id,'capacity_hours_per_day'=>16,'cost_per_hour'=>5000,'efficiency_rate'=>90,'is_active'=>'1'])
        ->assertRedirect(route('production.work-centers.index'));
    $c=WorkCenter::where('code','CT1')->first();
    expect($c)->not->toBeNull();
    expect((float)$c->cost_per_hour)->toEqual(5000.0);
    expect($c->machine_id)->toBe($m->id);
});
it('builds via factory', function(){
    Company::firstOrCreate(['name'=>'WC'],['email'=>'wc@wc.io']);
    expect(WorkCenter::factory()->create()->exists)->toBeTrue();
});
