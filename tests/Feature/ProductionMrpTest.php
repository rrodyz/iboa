<?php
use App\Models\Company; use App\Models\FiscalYear; use App\Models\User; use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Services\MrpService;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);

function mrpAdmin(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'M'],['email'=>'m@m.io','current_fiscal_year_id'=>$fy->id]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}

it('detects coil matiere shortfall vs stock_min', function(){
    $u=mrpAdmin(); $this->actingAs($u); $co=Company::first();
    $matiere=Product::factory()->create(['name'=>'Bobine galva 0.30','is_stockable'=>true,'stock_min'=>2000]);
    Coil::create(['company_id'=>$co->id,'product_id'=>$matiere->id,'reference'=>'B1','initial_weight'=>1000,'remaining_weight'=>500,'cost_per_kg'=>600,'purchase_price'=>600000,'status'=>'en_production']);
    Coil::create(['company_id'=>$co->id,'product_id'=>$matiere->id,'reference'=>'B2','initial_weight'=>1000,'remaining_weight'=>300,'cost_per_kg'=>600,'purchase_price'=>600000,'status'=>'disponible']);
    // available=800 < min 2000 -> deficit 1200
    $sf=app(MrpService::class)->analyze();
    expect($sf)->toHaveCount(1);
    expect($sf->first()['deficit'])->toEqual(1200.0);
    expect($sf->first()['estimated'])->toEqual(720000); // 1200*600
});

it('ignores products above threshold', function(){
    $u=mrpAdmin(); $this->actingAs($u); $co=Company::first();
    $p=Product::factory()->create(['stock_min'=>500]);
    Coil::create(['company_id'=>$co->id,'product_id'=>$p->id,'reference'=>'B3','initial_weight'=>1000,'remaining_weight'=>900,'cost_per_kg'=>500,'purchase_price'=>500000,'status'=>'disponible']);
    expect(app(MrpService::class)->analyze())->toHaveCount(0);
});

it('generates a purchase request from MRP via route', function(){
    $u=mrpAdmin(); $this->actingAs($u); $co=Company::first();
    $matiere=Product::factory()->create(['stock_min'=>2000]);
    Coil::create(['company_id'=>$co->id,'product_id'=>$matiere->id,'reference'=>'B4','initial_weight'=>1000,'remaining_weight'=>400,'cost_per_kg'=>600,'purchase_price'=>600000,'status'=>'disponible']);
    $this->get(route('production.mrp'))->assertOk()->assertSee('Réapprovisionnement');
    $this->post(route('production.mrp.generate'), ['product_ids'=>[$matiere->id]])->assertRedirect();
    expect(PurchaseRequest::count())->toBe(1);
    expect(PurchaseRequest::first()->items()->count())->toBe(1);
});
