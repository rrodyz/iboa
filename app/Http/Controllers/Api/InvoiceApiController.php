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
        $query = Invoice::with(['client'])->latest('issued_at');

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

    public function show(Invoice $invoice): InvoiceResource
    {
        $invoice->load(['client', 'items']);
        return new InvoiceResource($invoice);
    }
}
