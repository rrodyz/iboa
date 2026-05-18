<?php

namespace App\Http\Controllers;

use App\Imports\ClientsImport;
use App\Imports\ProductsImport;
use App\Imports\SuppliersImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    private const TYPES = ['products', 'clients', 'suppliers'];

    public function index(): View
    {
        return view('import.index');
    }

    public function template(string $type): mixed
    {
        if (!in_array($type, self::TYPES)) {
            abort(404);
        }

        $headers = match($type) {
            'products'  => ['reference','nom','famille','unite','prix_vente','prix_achat','tva','stock_min','stock_max','description'],
            'clients'   => ['nom','code','email','telephone','adresse','ville','pays','ifu','rccm','notes'],
            'suppliers' => ['nom','code','email','telephone','adresse','ville','pays','ifu','rccm','notes'],
        };

        $filename = 'template_'.$type.'.csv';

        $callback = function () use ($headers) {
            $f = fopen('php://output', 'w');
            // UTF-8 BOM so Excel on Windows reads accents correctly
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, $headers, ';');
            fclose($f);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $type   = $request->type;
        $import = match($type) {
            'products'  => new ProductsImport(),
            'clients'   => new ClientsImport(),
            'suppliers' => new SuppliersImport(),
        };

        Excel::import($import, $request->file('file'));

        $label = match($type) {
            'products'  => 'Produits',
            'clients'   => 'Clients',
            'suppliers' => 'Fournisseurs',
        };

        $message = $label.' importés : '.$import->imported;
        if ($import->skipped > 0) {
            $message .= ' ('.$import->skipped.' ligne(s) ignorée(s))';
        }

        return back()->with('success', $message);
    }
}
