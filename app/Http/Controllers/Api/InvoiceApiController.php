<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // [SEC-API] Isolation multi-tenant : on ne renvoie que les factures
        // de la société de l'utilisateur authentifié.
        $companyId = $request->user()->company_id;

        $query = Invoice::with(['client'])
            ->where('company_id', $companyId)
            ->latest('issued_at');

        if ($request->filled('status')) {
            $query->whereIn('status', explode(',', $request->status));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('issued_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('issued_at', '<=', $request->date_to);
        }

        if ($request->filled('overdue')) {
            $query->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
                  ->where('due_at', '<', now());
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return InvoiceResource::collection($query->paginate($perPage));
    }

    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        // [SEC-API] Vérifier que la facture appartient bien à la société du token.
        if ($invoice->company_id !== $request->user()->company_id) {
            abort(403, 'Accès refusé à cette ressource.');
        }

        $invoice->load(['client', 'items']);
        return new InvoiceResource($invoice);
    }
}
