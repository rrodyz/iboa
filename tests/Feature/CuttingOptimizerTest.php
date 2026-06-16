<?php
use App\Models\Company; use App\Models\FiscalYear; use App\Models\User;
use App\Modules\Production\Services\CuttingOptimizerService;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);
function cutAdmin(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'CUT'],['email'=>'cut@cut.io','current_fiscal_year_id'=>$fy->id]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}
it('optimizes a 1D cutting plan (FFD)', function(){
    // stock 6000 ; 3x2000 + 2x1500, kerf 0
    $plan=app(CuttingOptimizerService::class)->optimize(6000,0,[['length'=>2000,'quantity'=>3],['length'=>1500,'quantity'=>2]]);
    expect($plan['bars_count'])->toBe(2);      // [2000,2000,2000] + [1500,1500]
    expect($plan['used'])->toEqual(9000.0);
    expect($plan['waste'])->toEqual(3000.0);   // 2*6000 - 9000
    expect($plan['yield'])->toEqual(75.0);
});
it('accounts for kerf', function(){
    // stock 1000, kerf 50 ; 2x500 -> 500 + 50 + 500 = 1050 > 1000 -> 2 barres
    $plan=app(CuttingOptimizerService::class)->optimize(1000,50,[['length'=>500,'quantity'=>2]]);
    expect($plan['bars_count'])->toBe(2);
});
it('rejects pieces longer than stock', function(){
    expect(fn()=>app(CuttingOptimizerService::class)->optimize(1000,0,[['length'=>1500,'quantity'=>1]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
it('renders the cutting page and computes via route', function(){
    $this->actingAs(cutAdmin());
    $this->get(route('production.cutting'))->assertOk()->assertSee('Optimisation de découpe');
    $this->post(route('production.cutting.optimize'),['stock_length'=>6000,'kerf'=>0,'items'=>[['length'=>2000,'quantity'=>3]]])
        ->assertOk()->assertSee('Plan de coupe');
});
