<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReceptionController extends Controller
{
    public function __construct(private StockService $stockService) {}

    /**
     * List all receptions with filters.
     */
    public function index(Request $request): View
    {
        $query = Reception::with(['supplier', 'purchaseOrder', 'createdBy'])
            ->latest('received_at');

        if ($s = $request->search) {
            $query->where(function ($q) use ($s) {
                $q->where('number', 'like', "%{$s}%")
                  ->orWhereHas('supplier', fn($q2) => $q2->where('name', 'like', "%{$s}%"));
            });
        }

        if ($status = $request->status) {
            $query->where('status', $status);
        }

        if ($supplierId = $request->supplier_id) {
            $query->where('supplier_id', $supplierId);
        }

        $receptions = $query->paginate(15)->withQueryString();
        $filters    = $request->only(['search', 'status', 'supplier_id']);
        $suppliers  = Supplier::active()->orderBy('name')->get(['id', 'name']);

        $summary = [
            'total'     => Reception::count(),
            'pending'   => Reception::where('status', 'brouillon')->count(),
            'validated' => Reception::where('status', 'valide')->count(),
            'partial'   => Reception::where('status', 'partielle')->count(),
        ];

        return view('achats.receptions.index', compact('receptions', 'filters', 'suppliers', 'summary'));
    }

    /**
     * Show a single reception with its items and stock impact.
     */
    public function show(Reception $reception): View
    {
        $reception->load([
            'supplier',
            'purchaseOrder',
            'items.product',
            'items.unit',
            'createdBy',
            'validatedBy',
        ]);

        $warehouses = Warehouse::active()->orderBy('name')->get(['id', 'name']);

        return view('achats.receptions.show', compact('reception', 'warehouses'));
    }

    /**
     * Validate a reception: update quantities on PO items and create stock movements.
     */
    public function validateReception(Request $request, Reception $reception): RedirectResponse
    {
        $request->validate([
            'warehouse_id'                  => ['required', 'exists:warehouses,id'],
            'items'                         => ['required', 'array'],
            'items.*.received_quantity'     => ['required', 'numeric', 'min:0'],
            'items.*.lot_number'            => ['nullable', 'string', 'max:100'],
            'items.*.expiry_date'           => ['nullable', 'date'],
        ], [
            'warehouse_id.required' => 'Veuillez sélectionner un entrepôt de destination.',
            'warehouse_id.exists'   => 'L\'entrepôt sélectionné est invalide.',
            'items.required'        => 'Aucun article trouvé.',
            'items.*.received_quantity.required' => 'La quantité reçue est obligatoire pour chaque ligne.',
            'items.*.received_quantity.min'      => 'La quantité reçue ne peut pas être négative.',
        ]);

        // Compteurs pour message final transparent à l'utilisateur
        $movementsCreated = 0;
        $linesSkipped     = 0;

        try {
            DB::transaction(function () use ($request, $reception, &$movementsCreated, &$linesSkipped) {
                // Re-fetch under lock to prevent concurrent double-validation (TOCTOU).
                $reception = Reception::lockForUpdate()->findOrFail($reception->id);
                if ($reception->status !== 'brouillon') {
                    throw new \RuntimeException('Seules les réceptions en brouillon peuvent être validées.');
                }

                $warehouseId = $request->warehouse_id;

                // Update each reception item then create stock movement
                foreach ($request->items as $itemId => $itemData) {
                    $item = $reception->items()->find($itemId);
                    if (!$item) {
                        continue;
                    }

                    $receivedQty = (float) ($itemData['received_quantity'] ?? 0);

                    $item->update([
                        'received_quantity' => $receivedQty,
                        'lot_number'        => $itemData['lot_number']   ?? null,
                        'expiry_date'       => $itemData['expiry_date']  ?? null,
                    ]);

                    // Only create stock movement for positive quantities and stockable products
                    if ($receivedQty > 0 && !empty($item->product_id)) {
                        $this->stockService->recordMovement([
                            'product_id'     => $item->product_id,
                            'warehouse_id'   => $warehouseId,
                            'type'           => 'entree',
                            'quantity'       => $receivedQty,
                            'unit_cost'      => (float) $item->unit_cost,
                            'occurred_at'    => $reception->received_at->toDateString(),
                            'reference_type' => 'reception',
                            'reference_id'   => $reception->id,
                            'lot_number'     => $itemData['lot_number']  ?? null,
                            'expiry_date'    => $itemData['expiry_date'] ?? null,
                            'notes'          => 'Réception ' . $reception->number,
                        ]);
                        $movementsCreated++;
                    } elseif ($receivedQty > 0) {
                        // Ligne avec quantité reçue mais SANS product_id → pas de mouvement stock.
                        // Typiquement une ligne libre (description seulement). À tracer pour transparence.
                        $linesSkipped++;
                    }

                    // Update received_quantity on the linked PO item
                    if ($item->purchase_order_item_id) {
                        $poItem = $item->purchaseOrderItem;
                        if ($poItem) {
                            $totalReceived = $poItem->received_quantity + $receivedQty;
                            $poItem->update(['received_quantity' => min($totalReceived, $poItem->quantity)]);
                        }
                    }
                }

                // Mark reception as validated
                $reception->update([
                    'status'       => 'valide',
                    'warehouse_id' => $warehouseId,
                    'validated_by' => Auth::id(),
                    'validated_at' => now(),
                ]);

                // Update PO status
                $po = $reception->purchaseOrder;
                if ($po) {
                    $po->load('items');
                    $allReceived = $po->items->every(
                        fn($i) => (float) $i->received_quantity >= (float) $i->quantity
                    );
                    $po->update(['status' => $allReceived ? 'recu' : 'partiellement_recu']);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Message transparent : nombre de mouvements créés + warning si des lignes ont été ignorées
        if ($movementsCreated === 0 && $linesSkipped === 0) {
            $msg = 'Réception validée (aucune ligne avec quantité reçue).';
        } elseif ($movementsCreated === 0 && $linesSkipped > 0) {
            $msg = "Réception validée — ⚠ aucun mouvement de stock créé : les {$linesSkipped} ligne(s) sont sans produit catalogué (description libre / service).";
        } elseif ($linesSkipped > 0) {
            $msg = "Réception validée : {$movementsCreated} mouvement(s) de stock enregistré(s), {$linesSkipped} ligne(s) ignorée(s) (sans produit catalogué).";
        } else {
            $msg = "Réception validée : {$movementsCreated} mouvement(s) de stock enregistré(s).";
        }

        return redirect()
            ->route('achats.receptions.show', $reception)
            ->with($linesSkipped > 0 ? 'warning' : 'success', $msg);
    }
}
