<?php
use App\Models\Company; use App\Models\FiscalYear; use App\Models\User; use App\Models\Client;
use App\Models\Invoice; use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use App\Services\DirectionService;
use Spatie\Permission\Models\Role; use Spatie\Permission\Models\Permission;
uses(\Tests\Concerns\RefreshDatabase::class);
function dirAdmin(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'DIR'],['email'=>'dir@dir.io','current_fiscal_year_id'=>$fy->id]);
    Warehouse::firstOrCreate(['code'=>'WD'],['name'=>'WD','company_id'=>$co->id,'is_active'=>true,'is_default'=>true]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}
it('aggregates cross-module direction KPIs', function(){
    $this->actingAs(dirAdmin()); $co=Company::first();
    // impayé
    Invoice::create(['company_id'=>$co->id,'client_id'=>Client::factory()->create()->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'FA-D','status'=>'en_retard','issued_at'=>now(),'total_ttc'=>118000,'remaining_amount'=>118000]);
    // OF en cours + terminé ce mois
    ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-D1','status'=>'en_cours','quantity_requested'=>10]);
    ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-D2','status'=>'termine','quantity_requested'=>5,'finished_at'=>now()]);
    $k=app(DirectionService::class)->kpis();
    expect($k['of_en_cours'])->toBe(1);
    expect($k['of_termine_month'])->toBe(1);
    expect($k['impayes_count'])->toBe(1);
    expect($k['impayes_montant'])->toBe(118000);
});
it('renders direction dashboard', function(){
    $this->actingAs(dirAdmin());
    $this->get(route('direction.dashboard'))->assertOk()->assertSee('Tableau de bord Direction');
});
it('blocks without reports.view', function(){
    $co=Company::firstOrCreate(['name'=>'DIR'],['email'=>'dir@dir.io']);
    Permission::firstOrCreate(['name'=>'reports.view','guard_name'=>'web']);
    $role=Role::firstOrCreate(['name'=>'noview','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($role);
    $this->actingAs($u)->get(route('direction.dashboard'))->assertForbidden();
});
