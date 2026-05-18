<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Politique d'accès aux pièces jointes.
 *
 * Règle métier : on ne peut consulter / téléverser / supprimer une pièce jointe
 * QUE si l'on a le droit de voir / modifier le document parent (Invoice, Quote, Order…).
 *
 * Mapping type morph → permission de visualisation / modification du parent.
 */
class AttachmentPolicy
{
    /**
     * Permissions requises par type de parent (clé = nom court morph).
     *  - 'view'   : pour consulter / télécharger
     *  - 'manage' : pour téléverser / supprimer une pièce jointe
     */
    private const PERMISSIONS = [
        'Invoice'           => ['view' => 'invoices.view',          'manage' => 'invoices.edit'],
        'SupplierInvoice'   => ['view' => 'supplier_invoices.view', 'manage' => 'supplier_invoices.edit'],
        'Quote'             => ['view' => 'quotes.view',            'manage' => 'quotes.edit'],
        'Order'             => ['view' => 'orders.view',            'manage' => 'orders.edit'],
        'PurchaseOrder'     => ['view' => 'purchase_orders.view',   'manage' => 'purchase_orders.edit'],
        'PurchaseRequest'   => ['view' => 'purchase_requests.view', 'manage' => 'purchase_requests.edit'],
        'DeliveryNote'      => ['view' => 'deliveries.view',        'manage' => 'deliveries.edit'],
        'CreditNote'        => ['view' => 'credit_notes.view',      'manage' => 'credit_notes.create'],
        'Client'            => ['view' => 'clients.view',           'manage' => 'clients.edit'],
        'Supplier'          => ['view' => 'suppliers.view',         'manage' => 'suppliers.edit'],
        'Product'           => ['view' => 'products.view',          'manage' => 'products.edit'],
        'StockMovement'     => ['view' => 'stocks.view',            'manage' => 'stocks.edit'],
        'InventorySession'  => ['view' => 'inventory.view',         'manage' => 'inventory.edit'],
        // [F] Pièces justificatives comptables
        'JournalEntry'      => ['view' => 'accounting.view',        'manage' => 'accounting.write'],
        'ClientPayment'     => ['view' => 'payments.view',          'manage' => 'payments.view'],
        'SupplierPayment'   => ['view' => 'payments.view',          'manage' => 'payments.view'],
    ];

    /**
     * Récupère la permission requise pour une action donnée (view|manage) sur un type morph.
     */
    private function permissionFor(string $morphType, string $action): ?string
    {
        // Normalise "App\Models\Invoice" → "Invoice"
        $shortType = class_basename($morphType);
        return self::PERMISSIONS[$shortType][$action] ?? null;
    }

    /**
     * L'utilisateur peut-il lister/consulter les pièces jointes d'un document parent ?
     *
     * Appelé avec un type + id (pas un Attachment) car on filtre côté requête.
     */
    public function viewAttachmentsOf(User $user, string $morphType, int $morphId): bool
    {
        if ($user->hasRole('super_admin')) return true;

        $permission = $this->permissionFor($morphType, 'view');
        if (!$permission) return false;

        return $user->can($permission);
    }

    /**
     * L'utilisateur peut-il télécharger cette pièce jointe ?
     */
    public function download(User $user, Attachment $attachment): bool
    {
        if ($user->hasRole('super_admin')) return true;

        $permission = $this->permissionFor($attachment->attachable_type, 'view');
        if (!$permission) return false;

        return $user->can($permission);
    }

    /**
     * L'utilisateur peut-il téléverser une pièce jointe sur ce parent ?
     */
    public function create(User $user, string $morphType, int $morphId): bool
    {
        if ($user->hasRole('super_admin')) return true;

        $permission = $this->permissionFor($morphType, 'manage');
        if (!$permission) return false;

        return $user->can($permission);
    }

    /**
     * L'utilisateur peut-il supprimer cette pièce jointe ?
     *
     * Règle supplémentaire : on autorise l'auteur de l'upload même sans permission
     * (utile pour "revenir sur un fichier que j'ai uploadé par erreur").
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        if ($user->hasRole('super_admin')) return true;

        // L'auteur peut toujours retirer sa propre pièce jointe
        if ($attachment->uploaded_by === $user->id) return true;

        $permission = $this->permissionFor($attachment->attachable_type, 'manage');
        if (!$permission) return false;

        return $user->can($permission);
    }
}
