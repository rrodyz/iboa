<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DeliveryNote;
use App\Services\DeliveryNoteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class DeliveryNoteController extends Controller
{
    public function __construct(private DeliveryNoteService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', DeliveryNote::class);
        $filters       = $request->only(['client_id', 'status', 'order_id', 'search']);
        $deliveryNotes = $this->service->search($filters, 15);

        return view('ventes.bons-livraison.index', compact('deliveryNotes', 'filters'));
    }

    public function show(DeliveryNote $bonsLivraison)
    {
        $this->authorize('view', $bonsLivraison);
        $deliveryNote = $this->service->repository->findWithDetails($bonsLivraison->id);

        return view('ventes.bons-livraison.show', compact('deliveryNote'));
    }

    /**
     * GET ventes/bons-livraison/{deliveryNote}/edit
     * Show a form to adjust item quantities before validating (partial delivery).
     * Only allowed while the BL is still in 'brouillon' status.
     */
    public function edit(DeliveryNote $bonsLivraison)
    {
        $this->authorize('update', $bonsLivraison);
        if ($bonsLivraison->status !== 'brouillon') {
            return back()->with('error', 'Seuls les bons de livraison en brouillon peuvent être modifiés.');
        }

        $deliveryNote = $this->service->repository->findWithDetails($bonsLivraison->id);

        return view('ventes.bons-livraison.edit', compact('deliveryNote'));
    }

    /**
     * PUT ventes/bons-livraison/{deliveryNote}
     * Save adjusted quantities for partial delivery.
     */
    public function update(Request $request, DeliveryNote $bonsLivraison)
    {
        $this->authorize('update', $bonsLivraison);
        if ($bonsLivraison->status !== 'brouillon') {
            return back()->with('error', 'Seuls les bons de livraison en brouillon peuvent être modifiés.');
        }

        $request->validate([
            'items'            => ['required', 'array'],
            'items.*.id'       => ['required', 'integer', 'exists:delivery_note_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
        ], [
            'items.required'            => 'Aucune ligne à mettre à jour.',
            'items.*.id.required'       => 'Identifiant de ligne manquant.',
            'items.*.id.exists'         => 'Une ligne référencée est invalide.',
            'items.*.quantity.required' => 'La quantité est obligatoire pour chaque ligne.',
            'items.*.quantity.numeric'  => 'La quantité doit être un nombre valide.',
            'items.*.quantity.min'      => 'La quantité ne peut pas être négative.',
        ]);

        \DB::transaction(function () use ($request, $bonsLivraison) {
            $totalQty = 0;
            foreach ($request->input('items') as $row) {
                $item = $bonsLivraison->items()->find($row['id']);
                if (!$item) continue;

                $maxQty = $this->remainingQty($item);
                $qty    = min((float) $row['quantity'], $maxQty);
                $item->update(['quantity' => $qty]);
                $totalQty += $qty;
            }
            $bonsLivraison->update(['total_quantity' => $totalQty]);
        });

        return redirect()
            ->route('ventes.bons-livraison.show', $bonsLivraison)
            ->with('success', 'Bon de livraison mis à jour.');
    }

    /**
     * Return the max deliverable qty for a BL item (order item remaining − already delivered).
     */
    private function remainingQty(\App\Models\DeliveryNoteItem $item): float
    {
        if (!$item->order_item_id) {
            return (float) $item->quantity; // no order link → no cap
        }
        $orderItem = \App\Models\OrderItem::find($item->order_item_id);
        if (!$orderItem) {
            return (float) $item->quantity;
        }
        return max(0.0, (float) $orderItem->quantity - (float) $orderItem->delivered_quantity);
    }

    /**
     * POST ventes/bons-livraison/{deliveryNote}/invoice — create invoice from BL.
     */
    public function createInvoice(DeliveryNote $bonsLivraison)
    {
        $this->authorize('update', $bonsLivraison);
        try {
            $invoice = $this->service->createInvoice($bonsLivraison);
            return redirect()
                ->route('ventes.factures.show', $invoice)
                ->with('success', 'Facture ' . $invoice->number . ' créée depuis le bon de livraison ' . $bonsLivraison->number . '.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST ventes/bons-livraison/{deliveryNote}/cancel — cancel the delivery note.
     */
    public function cancel(DeliveryNote $bonsLivraison)
    {
        $this->authorize('update', $bonsLivraison);
        try {
            $this->service->cancel($bonsLivraison);
            return back()->with('success', 'Bon de livraison ' . $bonsLivraison->number . ' annulé.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST ventes/bons-livraison/{deliveryNote}/validate — validate the delivery note
     * and decrement stock.
     */
    public function validateNote(DeliveryNote $bonsLivraison)
    {
        $this->authorize('validate', $bonsLivraison);
        try {
            $dn = $this->service->validate($bonsLivraison);
            return redirect()
                ->route('ventes.bons-livraison.show', $dn)
                ->with('success', 'Bon de livraison ' . $dn->number . ' validé.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * GET ventes/bons-livraison/{deliveryNote}/pdf — download or stream PDF.
     * Add ?preview=1 to open in browser instead of downloading.
     */
    public function pdf(DeliveryNote $bonsLivraison, Request $request)
    {
        $this->authorize('view', $bonsLivraison);
        $deliveryNote = $this->service->repository->findWithDetails($bonsLivraison->id);
        $viewPath     = $this->service->generatePdfPath($deliveryNote);
        $settings     = Company::first()?->documentSetting;

        $pdf = Pdf::loadView($viewPath, compact('deliveryNote', 'settings'))
            ->setPaper(strtolower($settings?->page_size ?? 'a4'), $settings?->orientation ?? 'portrait');

        $filename = 'BL_' . str_replace(['/', '\\', ' '], '-', $deliveryNote->number) . '.pdf';

        return $request->boolean('preview')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }
}
