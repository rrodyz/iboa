<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const MAX_PER_TYPE = 4;

    public function search(Request $request): JsonResponse
    {
        $q = trim($request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => [], 'total' => 0]);
        }

        $like    = '%'.$q.'%';
        $results = [];
        $user    = auth()->user();

        // Clients
        if ($user?->can('clients.view')) {
            $items = Client::where('is_active', true)
                ->where(fn($query) => $query->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('email', 'like', $like))
                ->limit(self::MAX_PER_TYPE)
                ->get(['id', 'name', 'code', 'email'])
                ->map(fn($c) => [
                    'type'     => 'Client',
                    'icon'     => 'user-group',
                    'color'    => 'blue',
                    'label'    => $c->name,
                    'sublabel' => $c->code ?? $c->email,
                    'url'      => route('clients.show', $c),
                ])->all();
            array_push($results, ...$items);
        }

        // Fournisseurs
        if ($user?->can('suppliers.view')) {
            $items = Supplier::where('is_active', true)
                ->where(fn($query) => $query->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('email', 'like', $like))
                ->limit(self::MAX_PER_TYPE)
                ->get(['id', 'name', 'code', 'email'])
                ->map(fn($s) => [
                    'type'     => 'Fournisseur',
                    'icon'     => 'truck',
                    'color'    => 'orange',
                    'label'    => $s->name,
                    'sublabel' => $s->code ?? $s->email,
                    'url'      => route('suppliers.show', $s),
                ])->all();
            array_push($results, ...$items);
        }

        // Produits
        if ($user?->can('products.view')) {
            $items = Product::where('is_active', true)
                ->where(fn($query) => $query->where('name', 'like', $like)->orWhere('reference', 'like', $like)->orWhere('barcode', 'like', $like))
                ->limit(self::MAX_PER_TYPE)
                ->get(['id', 'name', 'reference'])
                ->map(fn($p) => [
                    'type'     => 'Produit',
                    'icon'     => 'archive',
                    'color'    => 'emerald',
                    'label'    => $p->name,
                    'sublabel' => $p->reference,
                    'url'      => route('products.show', $p),
                ])->all();
            array_push($results, ...$items);
        }

        // Factures clients
        if ($user?->can('invoices.view')) {
            $items = Invoice::where('number', 'like', $like)
                ->orWhereHas('client', fn($cq) => $cq->where('name', 'like', $like))
                ->limit(self::MAX_PER_TYPE)
                ->with('client:id,name')
                ->get(['id', 'number', 'client_id', 'total_ttc', 'status'])
                ->map(fn($inv) => [
                    'type'     => 'Facture',
                    'icon'     => 'document-text',
                    'color'    => 'indigo',
                    'label'    => $inv->number,
                    'sublabel' => $inv->client?->name,
                    'url'      => route('ventes.factures.show', $inv),
                ])->all();
            array_push($results, ...$items);
        }

        // Commandes
        if ($user?->can('orders.view')) {
            $items = Order::where('number', 'like', $like)
                ->limit(self::MAX_PER_TYPE)
                ->with('client:id,name')
                ->get(['id', 'number', 'client_id', 'status'])
                ->map(fn($o) => [
                    'type'     => 'Commande',
                    'icon'     => 'shopping-cart',
                    'color'    => 'violet',
                    'label'    => $o->number,
                    'sublabel' => $o->client?->name,
                    'url'      => route('ventes.commandes.show', $o),
                ])->all();
            array_push($results, ...$items);
        }

        // Factures fournisseurs
        if ($user?->can('supplier_invoices.view')) {
            $items = SupplierInvoice::where('number', 'like', $like)
                ->orWhere('supplier_invoice_number', 'like', $like)
                ->limit(self::MAX_PER_TYPE)
                ->with('supplier:id,name')
                ->get(['id', 'number', 'supplier_id', 'total_ttc'])
                ->map(fn($inv) => [
                    'type'     => 'Fact. fourn.',
                    'icon'     => 'document',
                    'color'    => 'red',
                    'label'    => $inv->number,
                    'sublabel' => $inv->supplier?->name,
                    'url'      => route('achats.factures-fournisseurs.show', $inv),
                ])->all();
            array_push($results, ...$items);
        }

        return response()->json([
            'results' => $results,
            'total'   => count($results),
        ]);
    }
}
