<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\DeliveryNote;
use App\Models\Company;
use Illuminate\Http\Request;

class DeliveryNoteVerifyController extends Controller
{
    /**
     * Public verification page — no authentication required.
     * URL is signed with APP_KEY (HMAC) so it cannot be forged.
     */
    public function __invoke(Request $request, string $number)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Lien de vérification invalide ou expiré.');
        }

        $deliveryNote = DeliveryNote::with([
            'client:id,name,trade_name,ifu,rccm,address,city',
            'items',
            'warehouse:id,name',
            'order:id,number',
        ])->where('number', $number)->first();

        if (! $deliveryNote) {
            abort(404, 'Bon de livraison introuvable.');
        }

        $company = currentCompany();

        return view('ventes.bons-livraison.verify', compact('deliveryNote', 'company'));
    }
}
