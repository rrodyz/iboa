<?php

namespace App\Http\Controllers;

use App\Services\EditLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * [CONCURRENCE-MULTI-USER] Endpoints de gestion des verrous d'édition.
 *
 * Routes (dans routes/web.php, groupe auth) :
 *   POST /edit-lock/refresh   → renouvelle le TTL (ping JS toutes les 5 min)
 *   POST /edit-lock/release   → libère le verrou (quitter la page)
 *   POST /edit-lock/force     → force la libération (admin)
 */
class EditLockController extends Controller
{
    public function __construct(private EditLockService $lockService) {}

    /**
     * Renouvelle le TTL du verrou (ping AJAX toutes les 5 min depuis le formulaire).
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'model_type' => ['required', 'string'],
            'model_id'   => ['required', 'integer'],
        ]);

        $model = $this->resolveModel($request->input('model_type'), $request->integer('model_id'));

        if (!$model) {
            return response()->json(['ok' => false, 'message' => 'Document introuvable.'], 404);
        }

        $refreshed = $this->lockService->refresh($model, Auth::user());

        return response()->json([
            'ok'         => $refreshed,
            'expires_in' => EditLockService::TTL_MINUTES * 60,
            'message'    => $refreshed ? 'Verrou renouvelé.' : 'Verrou perdu — rechargez la page.',
        ]);
    }

    /**
     * Libère le verrou (appelé par beforeunload JS quand l'utilisateur quitte).
     */
    public function release(Request $request): JsonResponse
    {
        $request->validate([
            'model_type' => ['required', 'string'],
            'model_id'   => ['required', 'integer'],
        ]);

        $model = $this->resolveModel($request->input('model_type'), $request->integer('model_id'));

        if ($model) {
            $this->lockService->release($model, Auth::user());
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Force la libération d'un verrou (admin seulement).
     */
    public function forceRelease(Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('manage', \App\Models\User::class); // admin gate

        $request->validate([
            'model_type' => ['required', 'string'],
            'model_id'   => ['required', 'integer'],
        ]);

        $model = $this->resolveModel($request->input('model_type'), $request->integer('model_id'));

        if ($model) {
            $this->lockService->forceRelease($model);
        }

        return back()->with('success', 'Verrou libéré.');
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    /**
     * Résout un modèle Eloquent depuis son type (court ou FQN) et son ID.
     * Accepte aussi le nom court : "Invoice", "PurchaseOrder", etc.
     */
    private function resolveModel(string $type, int $id): ?\Illuminate\Database\Eloquent\Model
    {
        // Mapping court → FQN (sécurité : on n'accepte que des classes connues)
        $map = [
            'Invoice'         => \App\Models\Invoice::class,
            'Quote'           => \App\Models\Quote::class,
            'Order'           => \App\Models\Order::class,
            'PurchaseOrder'   => \App\Models\PurchaseOrder::class,
            'SupplierInvoice' => \App\Models\SupplierInvoice::class,
            'CreditNote'      => \App\Models\CreditNote::class,
            'Reception'       => \App\Models\Reception::class,
            'Rfq'             => \App\Models\Rfq::class,
        ];

        // Accepte le nom court ou le FQN complet si dans le mapping values
        $fqn = $map[$type] ?? (in_array($type, $map, true) ? $type : null);

        if (!$fqn || !class_exists($fqn)) {
            return null;
        }

        return $fqn::find($id);
    }
}
