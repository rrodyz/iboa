<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

class InvoiceVerifyController extends Controller
{
    /**
     * Public verification page — no authentication required.
     * URL is signed with APP_KEY (HMAC) so it cannot be forged.
     */
    public function __invoke(Request $request, string $number)
    {
        // Validate the signed URL signature
        if (! $request->hasValidSignature()) {
            abort(403, 'Lien de vérification invalide ou expiré.');
        }

        $invoice = Invoice::with([
            'client:id,name,trade_name,ifu,rccm,address,city',
            'items',
        ])->where('number', $number)->first();

        if (! $invoice) {
            abort(404, 'Facture introuvable.');
        }

        $company = Company::first();

        return view('ventes.factures.verify', compact('invoice', 'company'));
    }
}
