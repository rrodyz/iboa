<?php

namespace App\Http\Middleware;

use App\Models\Invoice;
use App\Models\SupplierInvoice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * [INVOICE-LOCK] Middleware de verrouillage strict des factures encaissées.
 *
 * Règle métier : une facture dont le statut est « payee » (Encaissée Totalement)
 * ou « annulee » est CONTRACTUELLE et ne peut plus être modifiée. Toute requête
 * PUT/PATCH/DELETE sur une facture verrouillée renvoie un 403 sécurisé.
 *
 * S'applique aussi aux opérations satellites (paiements liés, validation, etc.)
 * via les noms de paramètres de route : `facture`, `facturesFournisseur`, `invoice`.
 *
 * Usage dans routes/web.php :
 *   Route::put(...)->middleware('invoice.locked');
 *   Route::resource('factures', ...)->middleware('invoice.locked');
 *
 * Les requêtes GET (consultation, PDF) passent toujours — c'est la seule
 * action permise sur une facture verrouillée.
 */
class InvoiceLockGuard
{
    /** Statuts qui verrouillent la facture (lecture seule). */
    private const LOCKED_STATUSES = ['payee', 'annulee'];

    /** Noms de paramètres de route où chercher la facture. */
    private const ROUTE_PARAMS = ['facture', 'invoice', 'facturesFournisseur', 'supplierInvoice'];

    public function handle(Request $request, Closure $next): Response
    {
        // Les GET (consultation, PDF, aperçu) passent toujours.
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        $invoice = $this->resolveInvoice($request);

        if ($invoice && in_array($invoice->status, self::LOCKED_STATUSES, true)) {
            $label = $invoice->status === 'payee' ? 'entièrement payée' : 'annulée';
            $message = sprintf(
                "Action interdite : la facture %s est %s — verrouillée comptablement. "
                . "Seules la consultation et l'export PDF sont autorisés. "
                . "Pour corriger une erreur, créez un avoir.",
                $invoice->number ?? '#' . $invoice->id,
                $label
            );

            // API JSON ou HTTP classique
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'error'         => 'invoice_locked',
                    'message'       => $message,
                    'invoice_id'    => $invoice->id,
                    'invoice_number'=> $invoice->number,
                    'status'        => $invoice->status,
                ], 403);
            }

            // Redirect arrière avec flash error (UX classique)
            return back()
                ->withInput()
                ->with('error', $message)
                ->setStatusCode(403);
        }

        return $next($request);
    }

    /**
     * Récupère la facture concernée par la requête, soit via les paramètres de route,
     * soit via le payload (cas des paiements multi-factures).
     */
    private function resolveInvoice(Request $request): null|Invoice|SupplierInvoice
    {
        foreach (self::ROUTE_PARAMS as $param) {
            $value = $request->route($param);
            if ($value instanceof Invoice || $value instanceof SupplierInvoice) {
                return $value;
            }
            if (is_numeric($value)) {
                $inv = Invoice::find($value) ?? SupplierInvoice::find($value);
                if ($inv) return $inv;
            }
        }
        return null;
    }
}
