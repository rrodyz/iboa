<?php

namespace App\Http\Controllers;

use App\Exports\ClientsExport;
use App\Exports\InvoicesExport;
use App\Exports\ProductsExport;
use App\Exports\StockMovementsExport;
use App\Exports\SuppliersExport;
use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function invoices(Request $request): mixed
    {
        abort_if(!auth()->user()->can('invoices.view'), 403);

        $filters = $request->only(['status', 'client_id', 'date_from', 'date_to']);

        return Excel::download(
            new InvoicesExport($filters),
            'factures-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function products(Request $request): mixed
    {
        abort_if(!auth()->user()->can('products.view'), 403);

        $filters = $request->only(['family_id', 'search']);

        return Excel::download(
            new ProductsExport($filters),
            'produits-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function stockMovements(Request $request): mixed
    {
        abort_if(!auth()->user()->can('stocks.view'), 403);

        $filters = $request->only(['product_id', 'warehouse_id', 'type', 'date_from', 'date_to']);

        return Excel::download(
            new StockMovementsExport($filters),
            'mouvements-stock-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function clients(Request $request): mixed
    {
        abort_if(!auth()->user()->can('clients.view'), 403);

        $filters = $request->only(['search', 'is_active', 'type']);

        return Excel::download(
            new ClientsExport($filters),
            'clients-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function suppliers(Request $request): mixed
    {
        abort_if(!auth()->user()->can('suppliers.view'), 403);

        $filters = $request->only(['search', 'is_active']);

        return Excel::download(
            new SuppliersExport($filters),
            'fournisseurs-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function productsPdf(Request $request): mixed
    {
        abort_if(!auth()->user()->can('products.view'), 403);

        $filters  = array_filter($request->only(['family_id', 'search']), fn($v) => $v !== '' && $v !== null);
        $products = Product::with(['family', 'brand', 'unit', 'taxRate'])
            ->where('is_active', true)
            ->orderBy('name')
            ->when(!empty($filters['family_id']), fn($q) => $q->where('family_id', $filters['family_id']))
            ->when(!empty($filters['search']),    fn($q) => $q->where(fn($q2) =>
                $q2->where('name', 'like', '%'.$filters['search'].'%')
                   ->orWhere('reference', 'like', '%'.$filters['search'].'%')
            ))
            ->get();
        $company = Company::first();

        $pdf = Pdf::loadView('products.pdf.index', compact('products', 'company', 'filters'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('produits_' . now()->format('Ymd_His') . '.pdf');
    }

    public function clientsPdf(Request $request): mixed
    {
        abort_if(!auth()->user()->can('clients.view'), 403);

        $filters = array_filter($request->only(['search', 'is_active', 'type']), fn($v) => $v !== '' && $v !== null);
        $clients = Client::orderBy('name')
            ->when(!empty($filters['search']), fn($q) => $q->where(fn($q2) =>
                $q2->where('name',  'like', '%'.$filters['search'].'%')
                   ->orWhere('code', 'like', '%'.$filters['search'].'%')
                   ->orWhere('email','like', '%'.$filters['search'].'%')
            ))
            ->when(isset($filters['is_active']), fn($q) => $q->where('is_active', (bool)$filters['is_active']))
            ->when(!empty($filters['type']),     fn($q) => $q->where('type', $filters['type']))
            ->get();
        $company = Company::first();

        $pdf = Pdf::loadView('clients.pdf.index', compact('clients', 'company', 'filters'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('clients_' . now()->format('Ymd_His') . '.pdf');
    }

    public function suppliersPdf(Request $request): mixed
    {
        abort_if(!auth()->user()->can('suppliers.view'), 403);

        $filters   = array_filter($request->only(['search', 'is_active']), fn($v) => $v !== '' && $v !== null);
        $suppliers = Supplier::orderBy('name')
            ->when(!empty($filters['search']),   fn($q) => $q->where(fn($q2) =>
                $q2->where('name',  'like', '%'.$filters['search'].'%')
                   ->orWhere('code', 'like', '%'.$filters['search'].'%')
                   ->orWhere('email','like', '%'.$filters['search'].'%')
            ))
            ->when(isset($filters['is_active']), fn($q) => $q->where('is_active', (bool)$filters['is_active']))
            ->get();
        $company = Company::first();

        $pdf = Pdf::loadView('suppliers.pdf.index', compact('suppliers', 'company', 'filters'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('fournisseurs_' . now()->format('Ymd_His') . '.pdf');
    }
}
