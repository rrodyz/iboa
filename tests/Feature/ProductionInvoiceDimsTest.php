<?php
use App\Models\Company; use App\Models\FiscalYear; use App\Models\User; use App\Models\Client;
use App\Models\Product; use App\Models\Order; use App\Models\Invoice; use App\Modules\Production\Models\ProductionOrder;
use App\Models\DocumentSetting;
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);

function dimsSetup(): array {
    $fy = FiscalYear::firstOrCreate(['label'=>'2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co = Company::firstOrCreate(['name'=>'D'],['email'=>'d@d.io','current_fiscal_year_id'=>$fy->id]);
    $r = Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']);
    $u = User::factory()->create(['company_id'=>$co->id,'email_verified_at'=>now()]); $u->assignRole($r);
    $client = Client::factory()->create();
    $product = Product::factory()->create(['sale_price'=>8500]);
    $order = Order::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'client_id'=>$client->id,'number'=>'CMD-D1','status'=>'confirme','issued_at'=>now()]);
    $of = ProductionOrder::create(['company_id'=>$co->id,'fiscal_year_id'=>$co->current_fiscal_year_id,'number'=>'OF-D1','status'=>'termine','order_id'=>$order->id,'product_id'=>$product->id,'thickness'=>0.30,'quantity_requested'=>50,'finished_at'=>now()]);
    $of->outputs()->create(['company_id'=>$co->id,'product_id'=>$product->id,'length'=>4,'thickness'=>0.30,'quantity'=>50,'total_meters'=>200,'produced_at'=>now()]);
    return compact('co','u','client','product','order');
}

it('resolves production dimensions per product on the invoice', function(){
    ['co'=>$co,'order'=>$order,'product'=>$product] = dimsSetup();
    $inv = Invoice::create(['company_id'=>$co->id,'client_id'=>$order->client_id,'fiscal_year_id'=>$co->current_fiscal_year_id,'order_id'=>$order->id,'number'=>'FA-D1','status'=>'brouillon','issued_at'=>now(),'total_ttc'=>0,'remaining_amount'=>0]);
    $dims = $inv->productionDimensions();
    expect($dims[$product->id]['length'])->toEqual(4.0);
    expect($dims[$product->id]['thickness'])->toEqual(0.30);
});

it('renders facture PDF with longueur/epaisseur columns enabled', function(){
    ['co'=>$co,'u'=>$u,'order'=>$order,'product'=>$product] = dimsSetup();
    DocumentSetting::create(['company_id'=>$co->id,'product_columns'=>['description','longueur','epaisseur','quantity','total_ttc']]);
    $inv = Invoice::create(['company_id'=>$co->id,'client_id'=>$order->client_id,'fiscal_year_id'=>$co->current_fiscal_year_id,'order_id'=>$order->id,'number'=>'FA-D2','status'=>'brouillon','issued_at'=>now(),'subtotal_ht'=>425000,'total_tax'=>76500,'total_ttc'=>501500,'remaining_amount'=>501500]);
    $inv->items()->create(['product_id'=>$product->id,'description'=>'Tôle bac rouge','quantity'=>50,'unit_price'=>8500,'line_total_ht'=>425000,'line_tax'=>76500,'line_total_ttc'=>501500]);
    $res = $this->actingAs($u)->get(route('ventes.factures.pdf', $inv));
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
});
