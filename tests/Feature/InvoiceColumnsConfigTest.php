<?php
use App\Models\Client; use App\Models\Company; use App\Models\FiscalYear; use App\Models\User;
use App\Models\Order; use App\Models\Product; use App\Models\Invoice; use App\Models\DocumentSetting;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);

function icUser(): User {
    $fy=FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co=Company::firstOrCreate(['name'=>'IC'],['email'=>'ic@ic.io','current_fiscal_year_id'=>$fy->id]);
    $r=Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u=User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]);$u->assignRole($r);
    return $u;
}
function icInvoice(): Invoice {
    $co=Company::first(); $client=Client::factory()->create(); $p=Product::factory()->create(['reference'=>'PRD-IC']);
    $inv=Invoice::create(['company_id'=>$co->id,'client_id'=>$client->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'FA-IC'.rand(100,999),'status'=>'brouillon','issued_at'=>now(),'subtotal_ht'=>10000,'total_tax'=>1800,'total_ttc'=>11800,'remaining_amount'=>11800]);
    $inv->items()->create(['product_id'=>$p->id,'description'=>'Article','quantity'=>2,'unit_price'=>5000,'discount_percent'=>0,'tax_rate_value'=>18,'line_total_ht'=>10000,'line_tax'=>1800,'line_total_ttc'=>11800]);
    return $inv;
}

it('renders invoice PDF with a minimal column set', function(){
    $this->actingAs(icUser()); $co=Company::first();
    DocumentSetting::create(['company_id'=>$co->id,'product_columns'=>['description','quantity','total_ttc']]);
    $res=$this->get(route('ventes.factures.pdf', icInvoice()));
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
});

it('renders invoice PDF with the full column set incl reference', function(){
    $this->actingAs(icUser()); $co=Company::first();
    DocumentSetting::create(['company_id'=>$co->id,'product_columns'=>['reference','description','longueur','epaisseur','quantity','unit_price','discount','tax','total_ht','total_ttc']]);
    $res=$this->get(route('ventes.factures.pdf', icInvoice()));
    $res->assertOk();
});

it('renders invoice PDF with no settings (default columns)', function(){
    $this->actingAs(icUser());
    $res=$this->get(route('ventes.factures.pdf', icInvoice()));
    $res->assertOk();
});
