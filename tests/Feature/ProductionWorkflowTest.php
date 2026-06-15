<?php
use App\Models\Company; use App\Models\FiscalYear; use App\Models\User; use App\Models\Product; use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder; use App\Modules\Production\Models\ProductionQualityControl;
use App\Modules\Production\Services\ProductionWorkflowService;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);
function wfAdmin(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'WF'],['email'=>'wf@wf.io','current_fiscal_year_id'=>$fy->id]);
    Warehouse::firstOrCreate(['code'=>'W'],['name'=>'W','company_id'=>$co->id,'is_active'=>true,'is_default'=>true]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}
function wfState(array $steps, string $key): string { return collect($steps)->firstWhere('key',$key)['state']; }

it('computes workflow states for an in-progress OF', function(){
    $this->actingAs(wfAdmin()); $co=Company::first();
    $o=ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-W1','status'=>'en_cours','quantity_requested'=>10]);
    $s=app(ProductionWorkflowService::class)->steps($o);
    expect(wfState($s,'of'))->toBe('done');
    expect(wfState($s,'production'))->toBe('current');
    expect(wfState($s,'commande'))->toBe('na'); // no order linked
});

it('blocks workflow on non-conforme quality', function(){
    $this->actingAs(wfAdmin()); $co=Company::first();
    $o=ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-W2','status'=>'termine','quantity_requested'=>10,'finished_at'=>now()]);
    ProductionQualityControl::create(['company_id'=>$co->id,'production_order_id'=>$o->id,'thickness_ok'=>false,'length_ok'=>true,'color_ok'=>true,'visual_ok'=>false,'status'=>'non_conforme','rejected_quantity'=>3,'controlled_at'=>now()]);
    $s=app(ProductionWorkflowService::class)->steps($o);
    expect(wfState($s,'production'))->toBe('done');
    expect(wfState($s,'quality'))->toBe('blocked');
});

it('renders workflow chain on OF show', function(){
    $this->actingAs(wfAdmin()); $co=Company::first();
    $o=ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-W3','status'=>'en_cours','quantity_requested'=>10]);
    $this->get(route('production.orders.show',$o))->assertOk()->assertSee('Chaîne de production');
});
