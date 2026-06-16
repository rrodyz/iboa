<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StockReservation;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ReservationService;
use Illuminate\Http\RedirectResponse;

class ProductionReservationController extends Controller
{
    public function __construct(private ReservationService $service)
    {
        $this->middleware('permission:production.update');
    }

    /** Réserve le produit fini de l'OF pour son client. */
    public function reserve(ProductionOrder $order): RedirectResponse
    {
        try {
            $this->service->reserveForOrder($order);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Produit fini réservé pour le client.');
    }

    /** Réserve le produit fini disponible en stock pour toutes les lignes d'une commande. */
    public function reserveStock(\App\Models\Order $commande): RedirectResponse
    {
        $qty = $this->service->reserveStockForOrder($commande);

        if ($qty <= 0) {
            return back()->with('error', 'Aucun produit fini disponible à réserver (à produire).');
        }

        return back()->with('success', number_format($qty, 0, ',', ' ') . ' unité(s) de produit fini réservée(s) pour le client.');
    }

    /** Libère une réservation. */
    public function release(StockReservation $reservation): RedirectResponse
    {
        try {
            $this->service->release($reservation);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Réservation libérée.');
    }
}
