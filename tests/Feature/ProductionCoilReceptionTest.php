<?php
use App\Models\Company; use App\Models\FiscalYear; use App\Models\User; use App\Models\Product;
use App\Models\Supplier; use App\Models\Reception;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Services\CoilReceptionService;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);

function crAdmin(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'CR'],['email'=>'cr@cr.io','current_fiscal_year_id'=>$fy->id]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}
function crReception(string $status='valide'): Reception {
    $co=Company::first(); $sup=Supplier::create(['company_id'=>$co->id,'name'=>'Fournisseur Test','code'=>'F'.rand(100,999)]);
    $p1=Product::factory()->create(); $p2=Product::factory()->create();
    $rec=Reception::create(['company_id'=>$co->id,'supplier_id'=>$sup->id,'number'=>'REC-'.rand(1000,9999),
        'status'=>$status,'received_at'=>now(),'type'=>'totale','validated_at'=>$status==='valide'?now():null]);
    $rec->items()->create(['product_id'=>$p1->id,'description'=>'Bobine galva','received_quantity'=>1500,'unit_cost'=>600,'lot_number'=>'L1']);
    $rec->items()->create(['product_id'=>$p2->id,'description'=>'Bobine prélaqué','received_quantity'=>2000,'unit_cost'=>750,'lot_number'=>'L2']);
    return $rec;
}

it('creates coils from a validated reception', function(){
    $this->actingAs(crAdmin());
    $rec=crReception('valide');
    $coils=app(CoilReceptionService::class)->createFromReception($rec);
    expect($coils)->toHaveCount(2);
    $c=Coil::where('reception_id',$rec->id)->where('lot_number','L1')->first();
    expect((float)$c->initial_weight)->toEqual(1500.0);
    expect((float)$c->remaining_weight)->toEqual(1500.0);
    expect((float)$c->cost_per_kg)->toEqual(600.0);
    expect($c->purchase_price)->toEqual(900000); // 1500*600
    expect($c->status)->toBe('disponible');
});

it('blocks generation when reception not validated', function(){
    $this->actingAs(crAdmin());
    $rec=crReception('brouillon');
    expect(fn()=>app(CoilReceptionService::class)->createFromReception($rec))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(Coil::count())->toBe(0);
});

it('is idempotent (no duplicate coils)', function(){
    $this->actingAs(crAdmin());
    $rec=crReception('valide');
    $this->post(route('production.receptions.coils',$rec))->assertRedirect();
    expect(Coil::where('reception_id',$rec->id)->count())->toBe(2);
    $this->post(route('production.receptions.coils',$rec))->assertSessionHas('error');
    expect(Coil::where('reception_id',$rec->id)->count())->toBe(2);
});
