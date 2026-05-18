<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain', 'text/csv',
    ];

    private const MAX_SIZE_MB = 10;

    // [SEC-C3] Whitelist of morph types accepted for attachment — prevents arbitrary class injection.
    private const ALLOWED_MORPH_TYPES = [
        'Invoice',
        'SupplierInvoice',
        'Quote',
        'Order',
        'PurchaseOrder',
        'PurchaseRequest',
        'DeliveryNote',
        'CreditNote',
        'Client',
        'Supplier',
        'Product',
        'Expense',
        'StockMovement',
        'InventorySession',
        'JournalEntry',          // [F] Pièces justificatives comptables
        'ClientPayment',
        'SupplierPayment',
    ];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'attachable_type' => ['required', 'string'],
            'attachable_id'   => ['required', 'integer'],
        ]);

        // [SEC-C3] Validate morph type against whitelist before querying.
        if (!in_array($request->attachable_type, self::ALLOWED_MORPH_TYPES, true)) {
            return response()->json(['error' => 'Type de document non autorisé.'], 422);
        }

        // [SEC-PHASE1] Vérifier que l'utilisateur a le droit de voir le parent.
        if (!Auth::user()->can('viewAttachmentsOf', [Attachment::class, $request->attachable_type, (int) $request->attachable_id])) {
            return response()->json(['error' => 'Accès refusé.'], 403);
        }

        $attachments = Attachment::where('attachable_type', 'App\\Models\\'.$request->attachable_type)
            ->where('attachable_id', (int) $request->attachable_id)
            ->latest()
            ->get()
            ->map(function (Attachment $attachment) {
                return [
                    'id'         => $attachment->id,
                    'filename'   => $attachment->filename,
                    'path'       => $attachment->path,
                    'size'       => $attachment->humanSize(),
                    'mime_type'  => $attachment->mime_type,
                    'label'      => $attachment->label,
                    'is_image'   => $attachment->isImage(),
                    'is_pdf'     => $attachment->isPdf(),
                    'created_at' => $attachment->created_at->format('d/m/Y H:i'),
                ];
            });

        return response()->json($attachments);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file'            => ['required', 'file', 'max:'.(self::MAX_SIZE_MB * 1024)],
            'attachable_type' => ['required', 'string'],
            'attachable_id'   => ['required', 'integer'],
            'label'           => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');

        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES)) {
            return response()->json(['error' => 'Type de fichier non autorisé.'], 422);
        }

        $morphType = $request->attachable_type;
        $morphId   = (int) $request->attachable_id;

        // [SEC-C3] Validate morph type against explicit whitelist to prevent class injection.
        if (!in_array($morphType, self::ALLOWED_MORPH_TYPES, true)) {
            return response()->json(['error' => 'Type de document non autorisé.'], 422);
        }

        // Validate that the parent model exists
        $modelClass = 'App\\Models\\'.$morphType;
        if (!class_exists($modelClass) || !$modelClass::find($morphId)) {
            return response()->json(['error' => 'Document parent introuvable.'], 404);
        }

        // [SEC-PHASE1] Permission de modifier le parent → permission de joindre une pièce.
        if (!Auth::user()->can('create', [Attachment::class, $morphType, $morphId])) {
            return response()->json(['error' => 'Accès refusé : permission insuffisante pour ce type de document.'], 403);
        }

        $path = $file->store('attachments/'.strtolower($morphType).'/'.$morphId, 'local');

        $attachment = Attachment::create([
            'attachable_type' => 'App\\Models\\'.$morphType,
            'attachable_id'   => $morphId,
            'disk'            => 'local',
            'path'            => $path,
            'filename'        => $file->getClientOriginalName(),
            'mime_type'       => $file->getMimeType(),
            'size'            => $file->getSize(),
            'label'           => $request->label,
            'uploaded_by'     => Auth::id(),
        ]);

        return response()->json([
            'id'         => $attachment->id,
            'filename'   => $attachment->filename,
            'size'       => $attachment->humanSize(),
            'mime_type'  => $attachment->mime_type,
            'label'      => $attachment->label,
            'is_image'   => $attachment->isImage(),
            'is_pdf'     => $attachment->isPdf(),
            'created_at' => $attachment->created_at->format('d/m/Y H:i'),
        ], 201);
    }

    public function download(Attachment $attachment): mixed
    {
        // [SEC-PHASE1] Vérification d'accès via Policy avant tout I/O disque.
        $this->authorize('download', $attachment);

        if (!Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404, 'Fichier introuvable.');
        }

        // Stream images inline (for preview); force download for other types
        if ($attachment->isImage()) {
            return response()->file(
                Storage::disk($attachment->disk)->path($attachment->path),
                ['Content-Type' => $attachment->mime_type]
            );
        }

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->filename);
    }

    public function destroy(Attachment $attachment): JsonResponse
    {
        // [SEC-PHASE1] Vérification d'accès via Policy.
        $this->authorize('delete', $attachment);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return response()->json(['ok' => true]);
    }
}
