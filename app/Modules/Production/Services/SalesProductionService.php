<?php

namespace App\Modules\Production\Services;

use App\Models\Order;
use App\Models\ProductStock;
use App\Models\StockReservation;
use Illuminate\Support\Collection;

/**
 * [VENTE → PRODUCTION] Cockpit production d'une commande client.
 * Agrège l'état des ordres de fabrication liés à la commande (lecture seule)
 * pour l'afficher sur la fiche commande.
 */
class SalesProductionService
{
    public function summary(Order $order): array
    {
        $ofs = $order->productionOrders()
            ->with(['qualityControls', 'outputs'])
            ->orderByDesc('id')->get();

        $rows = $ofs->map(function ($of) {
            $qc = $of->qualityControls->sortByDesc('id')->first();

            return [
                'id'             => $of->id,
                'number'         => $of->number,
                'status'         => $of->status,
                'status_label'   => $of->statusLabel(),
                'qty_requested'  => (float) $of->quantity_requested,
                'qty_produced'   => (float) $of->quantity_produced,
                'qc_status'      => $qc?->status,
                'qc_label'       => $qc?->statusLabel(),
                'has_output'     => $of->outputs->isNotEmpty(),
            ];
        });

        return [
            'orders'    => $rows,
            'count'     => $rows->count(),
            'aggregate' => $this->aggregate($rows),
        ];
    }

    /**
     * Analyse de disponibilité du produit fini par ligne de commande :
     * dispo stock vs commandé → réserver (dispo) ou produire (déficit).
     */
    public function stockAnalysis(Order $order): array
    {
        $order->loadMissing('items.product');

        $lines = $order->items->filter(fn ($i) => $i->product_id)->map(function ($item) use ($order) {
            $ordered   = (float) $item->quantity;
            $available = (float) ProductStock::where('product_id', $item->product_id)
                            ->selectRaw('COALESCE(SUM(quantity - reserved_quantity),0) a')->value('a');
            $reserved  = (float) StockReservation::where('order_id', $order->id)
                            ->where('product_id', $item->product_id)->where('status', 'reserved')->sum('quantity');

            $netNeeded  = max(0, $ordered - $reserved);
            $reservable = min($netNeeded, max(0, $available));
            $toProduce  = max(0, $netNeeded - $available);

            $decision = $toProduce <= 0 ? 'stock' : ($reservable <= 0 ? 'produce' : 'mixed');

            return [
                'product_id' => $item->product_id,
                'product'    => $item->product?->name ?? $item->description,
                'ordered'    => $ordered,
                'available'  => round($available, 2),
                'reserved'   => round($reserved, 2),
                'reservable' => round($reservable, 2),
                'to_produce' => round($toProduce, 2),
                'decision'   => $decision,
            ];
        })->values();

        return [
            'lines'      => $lines,
            'reservable' => (float) $lines->sum('reservable'),
            'to_produce' => (float) $lines->sum('to_produce'),
        ];
    }

    /** KPI Vente orientés production pour le dashboard. */
    public function dashboardKpis(): array
    {
        $enProduction = Order::whereHas('productionOrders', fn ($q) => $q->whereIn('status', ['lance', 'en_cours']))->count();

        $pretesALivrer = Order::whereNotIn('status', ['livre', 'facture', 'annule'])
            ->whereHas('productionOrders', fn ($q) => $q->where('status', 'termine')->whereHas('outputs'))
            ->count();

        $livreesNonFacturees = Order::where('status', 'livre')
            ->whereDoesntHave('invoices', fn ($q) => $q->where('status', '!=', 'annulee'))
            ->count();

        $totalQuotes = \App\Models\Quote::count();
        $converted   = \App\Models\Quote::where('status', 'converti')->count();
        $tauxTransfo = $totalQuotes > 0 ? round($converted / $totalQuotes * 100, 1) : 0;

        return [
            'en_production'           => $enProduction,
            'pretes_a_livrer'         => $pretesALivrer,
            'livrees_non_facturees'   => $livreesNonFacturees,
            'taux_transfo'            => $tauxTransfo,
        ];
    }

    private function aggregate(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return ['label' => 'Aucun OF', 'color' => 'gray', 'none' => true];
        }

        $statuses = $rows->pluck('status');

        if ($rows->contains('qc_status', 'non_conforme')) {
            return ['label' => 'Qualité non conforme', 'color' => 'red'];
        }
        if ($statuses->every(fn ($s) => $s === 'termine')) {
            return ['label' => 'Produit fini disponible', 'color' => 'green'];
        }
        if ($statuses->contains('en_cours')) {
            return ['label' => 'En production', 'color' => 'sky'];
        }
        if ($statuses->contains('lance')) {
            return ['label' => 'OF lancé', 'color' => 'amber'];
        }
        if ($statuses->contains('termine')) {
            return ['label' => 'Partiellement produit', 'color' => 'teal'];
        }

        return ['label' => 'OF créé (brouillon)', 'color' => 'gray'];
    }
}
