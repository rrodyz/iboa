<?php
use App\Models\Company; use App\Models\FiscalYear; use App\Models\User; use App\Models\Warehouse;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);
function rmAdmin(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'RM'],['email'=>'rm@rm.io','current_fiscal_year_id'=>$fy->id]);
    Warehouse::firstOrCreate(['code'=>'WR'],['name'=>'WR','company_id'=>$co->id,'is_active'=>true,'is_default'=>true]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}
it('renders all new MES/quality report types', function(){
    $this->actingAs(rmAdmin());
    foreach(['charge','maintenance','qualite','non_conformites'] as $type){
        $this->get(route('production.reports', ['type'=>$type]))->assertOk();
    }
});
it('exports MES report to excel and pdf', function(){
    $this->actingAs(rmAdmin());
    $xls=$this->get(route('production.reports', ['type'=>'maintenance','export'=>'excel']));
    $xls->assertOk(); expect($xls->headers->get('content-disposition'))->toContain('.xlsx');
    $pdf=$this->get(route('production.reports', ['type'=>'qualite','export'=>'pdf']));
    $pdf->assertOk(); expect($pdf->headers->get('content-type'))->toContain('application/pdf');
});
