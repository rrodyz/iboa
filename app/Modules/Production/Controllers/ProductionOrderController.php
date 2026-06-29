<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\BillOfMaterial;
use App\Models\Client;
use App\Modules\Production\Models\Coil;
use App\Models\Order;
use App\Models\Product;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionOrder;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Production\Services\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionOrderController extends Controller
{
    public function __construct(private ProductionService $service)
    {
        $this->middleware('permission:production.view')->only(['index', 'show']);
        $this->middleware('permission:production.create')->only(['create', 'store', 'edit', 'update']);
        $this->middleware('permission:production.delete')->only(['destroy']);
        $this->middleware('permission:production.launch')->only(['launch', 'start']);
        $this->middleware('permission:production.validate')->only(['finish']);
        $this->middleware('permission:production.cancel')->only(['cancel']);
        $this->middleware('permission:production.approve_financial')->only(['authorizeFinance']);
        $this->middleware('permission:production.modify_launched')->only(['requestModification']);
    }

    public function index(Request $request): View
    {
        $orders = ProductionOrder::with(['client', 'product', 'productionLine'])
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('client_id'), fn ($q, $v) => $q->where('client_id', $v))
            ->when($request->input('q'), fn ($q, $v) => $q->where('number', 'like', "%$v%"))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        $stats = [
            'brouillon' => ProductionOrder::where('status', 'brouillon')->count(),
            'en_cours'  => ProductionOrder::whereIn('status', ['lance', 'en_cours'])->count(),
            'termine'   => ProductionOrder::where('status', 'termine')->count(),
            'metres'    => (float) \App\Modules\Production\Models\ProductionOrderLine::whereHas(
                            'productionOrder', fn ($q) => $q->where('status', 'termine')
                        )->sum('total_meters'),
        ];

        $clients = Client::orderBy('name')->get(['id', 'name', 'trade_name']);

        return view('production.orders.index', compact('orders', 'stats', 'clients'));
    }

    public function create(Request $request): View
    {
        $order = new ProductionOrder();

        // Pré-remplissage depuis une commande de vente
        if ($srcId = $request->input('order_id')) {
            $src = Order::with('items')->find($srcId);
            if ($src) {
                $first                     = $src->items->first();
                $order->client_id          = $src->client_id;
                $order->order_id           = $src->id;
                $order->product_id         = $first?->product_id;
                $order->quantity_requested = (float) $src->items->sum('quantity');
            }
        }

        return view('production.orders.form', $this->formData($order));
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $lines] = $this->validateData($request);
        $order = $this->service->create($data, $lines);

        return redirect()->route('production.orders.show', $order)->with('success', 'Ordre de fabrication créé : ' . $order->number);
    }

    public function show(ProductionOrder $order): View
    {
        $order->load([
            'client', 'order', 'product', 'billOfMaterial', 'productionLine.machine', 'responsible', 'lines.unit',
            'consumptions.coil', 'outputs.product', 'outputs.warehouse', 'wastes.machine', 'wastes.operator',
            'cost', 'qualityControls.controller', 'reservations.product', 'timeLogs.employee',
            'operations.workCenter', 'operations.operator', 'batches',
        ]);

        $consumedWeight = (float) $order->consumptions->sum('weight_consumed');
        $wasteWeight    = (float) $order->wastes->sum('weight');
        $metrics = [
            'consumed_weight' => $consumedWeight,
            'consumed_cost'   => (float) $order->consumptions->sum('cost'),
            'output_meters'   => (float) $order->outputs->sum('total_meters'),
            'output_qty'      => (float) $order->outputs->sum('quantity'),
            'waste_weight'    => $wasteWeight,
            'waste_value'     => (float) $order->wastes->sum('value'),
            // Rendement matière = (consommé - chutes) / consommé
            'yield'           => $consumedWeight > 0 ? round((($consumedWeight - $wasteWeight) / $consumedWeight) * 100, 1) : null,
        ];

        // Données pour les formulaires d'exécution (OF en cours)
        $coils      = $order->isInProgress() ? Coil::where('status', '!=', 'epuisee')->orderBy('reference')->get() : collect();
        $machines   = $order->isInProgress() ? \App\Modules\Production\Models\ProductionMachine::where('is_active', true)->orderBy('name')->get() : collect();
        $employees  = in_array($order->status, ['lance', 'en_cours', 'termine'], true) ? \App\Models\Employee::orderBy('last_name')->get() : collect();
        $warehouses = $order->isInProgress() ? \App\Models\Warehouse::orderByDesc('is_default')->orderBy('name')->get() : collect();

        $workflow = app(\App\Modules\Production\Services\ProductionWorkflowService::class)->steps($order);
        $opProgress = app(\App\Modules\Production\Services\RoutingService::class)->progress($order);

        return view('production.orders.show', compact('order', 'metrics', 'coils', 'machines', 'employees', 'warehouses', 'workflow', 'opProgress'));
    }

    public function edit(ProductionOrder $order): View
    {
        abort_unless($order->isEditable(), 403, 'OF non modifiable.');
        $order->load('lines');

        return view('production.orders.form', $this->formData($order));
    }

    public function update(Request $request, ProductionOrder $order): RedirectResponse
    {
        [$data, $lines] = $this->validateData($request);
        $this->service->update($order, $data, $lines);

        return redirect()->route('production.orders.show', $order)->with('success', 'OF mis à jour.');
    }

    public function destroy(ProductionOrder $order): RedirectResponse
    {
        if ($order->status !== 'brouillon') {
            return back()->with('error', 'Seul un OF en brouillon peut être supprimé.');
        }
        $order->delete();

        return redirect()->route('production.orders.index')->with('success', 'OF supprimé.');
    }

    public function launch(ProductionOrder $order): RedirectResponse
    {
        // Avertissement matière (non bloquant) AVANT le lancement.
        $shortages = $this->service->materialShortages($order);

        $this->service->launch($order);

        $resp = back()->with('success', 'OF lancé.');
        if ($shortages) {
            $msg = collect($shortages)->map(fn ($s) => sprintf(
                '%s (besoin %s / dispo %s)',
                $s['product'], number_format($s['need'], 0, ',', ' '), number_format($s['available'], 0, ',', ' ')
            ))->implode(' · ');
            $resp->with('warning', 'Matière insuffisante : ' . $msg);
        }

        return $resp;
    }

    public function allocateMaterial(ProductionOrder $order): RedirectResponse
    {
        $this->service->allocateMaterial($order);

        return back()->with('success', 'Matière allouée — OF prêt à lancer.');
    }

    public function start(ProductionOrder $order): RedirectResponse
    {
        $this->service->start($order);

        return back()->with('success', 'Production démarrée.');
    }

    public function partial(ProductionOrder $order): RedirectResponse
    {
        $this->service->markPartiallyDone($order);

        return back()->with('success', 'OF marqué terminé partiellement.');
    }

    public function finish(ProductionOrder $order): RedirectResponse
    {
        $this->service->finish($order);

        return back()->with('success', 'OF terminé.');
    }

    public function cancel(Request $request, ProductionOrder $order): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->service->cancel($order, $request->input('reason'));

        return back()->with('success', 'OF annulé.');
    }

    /**
     * [§13.2 CDC] Validation financière DAF/DG avant lancement OF.
     * Client comptant < 100% | acompte < 70% | crédit → autorisation obligatoire.
     */
    public function authorizeFinance(Request $request, ProductionOrder $order): RedirectResponse
    {
        $request->validate([
            'financial_notes' => ['nullable', 'string', 'max:500'],
            'bypass'          => ['nullable', 'boolean'],
        ]);

        abort_if(
            in_array($order->financial_authorization, ['approved', 'bypassed'], true),
            422,
            'OF déjà autorisé financièrement.'
        );

        $order->update([
            'financial_authorization' => $request->boolean('bypass') ? 'bypassed' : 'approved',
            'financial_authorized_at' => now(),
            'financial_authorized_by' => auth()->id(),
            'financial_notes'         => $request->input('financial_notes') ?? 'Autorisation manuelle DAF/DG.',
        ]);

        return back()->with('success', 'Autorisation financière accordée. L\'OF peut être lancé.');
    }

    /**
     * [§13.10 CDC] Demande de modification d'un OF lancé.
     * Multi-validation : Chef Production → Commercial → Finance → DG.
     */
    public function requestModification(Request $request, ProductionOrder $order): RedirectResponse
    {
        abort_unless(in_array($order->status, ['lance', 'en_cours', 'termine_partiellement'], true), 422, 'Modification impossible dans ce statut.');

        $request->validate([
            'modification_reason' => ['required', 'string', 'max:500'],
        ]);

        // Enregistrer dans les notes + passer en statut en attente de modification
        $note = "[DEMANDE MODIF " . now()->format('d/m/Y H:i') . " par " . auth()->user()->name . "] " . $request->input('modification_reason');
        $order->update([
            'notes' => ($order->notes ? $order->notes . "\n" : '') . $note,
        ]);

        // Notifier le DG/DAF via audit log
        \App\Models\AuditLog::create([
            'company_id'  => $order->company_id,
            'user_id'     => auth()->id(),
            'action'      => 'production_order_modification_requested',
            'entity_type' => ProductionOrder::class,
            'entity_id'   => $order->id,
            'description' => "Demande de modification OF {$order->number} : {$request->input('modification_reason')}",
            'ip_address'  => $request->ip(),
        ]);

        return back()->with('info', 'Demande de modification enregistrée. Validation DG requise.');
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function formData(ProductionOrder $order): array
    {
        return [
            'order'     => $order,
            'clients'   => Client::orderBy('name')->get(['id', 'name', 'trade_name']),
            'products'  => Product::orderBy('name')->get(['id', 'name', 'reference']),
            'boms'      => BillOfMaterial::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'lines'     => ProductionLine::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'units'     => Unit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'users'     => User::orderBy('name')->get(['id', 'name']),
            'salesOrders' => Order::orderByDesc('id')->limit(200)->get(['id', 'number']),
        ];
    }

    /** @return array{0: array, 1: array} */
    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'client_id'          => ['nullable', 'integer', 'exists:clients,id'],
            'order_id'           => ['nullable', 'integer', 'exists:orders,id'],
            'product_id'         => ['nullable', 'integer', 'exists:products,id'],
            'bill_of_material_id'=> ['nullable', 'integer', 'exists:bills_of_materials,id'],
            'production_line_id' => ['nullable', 'integer', 'exists:production_lines,id'],
            'responsible_id'     => ['nullable', 'integer', 'exists:users,id'],
            'sheet_type'         => ['nullable', 'string', 'max:60'],
            'thickness'          => ['nullable', 'numeric', 'min:0'],
            'color'              => ['nullable', 'string', 'max:60'],
            'length'             => ['nullable', 'numeric', 'min:0'],
            'usable_width'       => ['nullable', 'numeric', 'min:0'],
            'quantity_requested' => ['nullable', 'numeric', 'min:0'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'lines'              => ['nullable', 'array'],
            'lines.*.length'     => ['nullable', 'numeric', 'min:0'],
            'lines.*.quantity'   => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_id'    => ['nullable', 'integer', 'exists:units,id'],
            'lines.*.label'      => ['nullable', 'string', 'max:120'],
        ]);

        $lines = $validated['lines'] ?? [];
        unset($validated['lines']);

        return [$validated, $lines];
    }
}
