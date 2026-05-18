<?php

namespace App\Policies;

use App\Models\SupplierInvoice;
use App\Models\User;

class SupplierInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supplier_invoices.view');
    }

    public function view(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $user->can('supplier_invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->can('supplier_invoices.create');
    }

    public function update(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $user->can('supplier_invoices.edit');
    }

    public function validate(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $user->can('supplier_invoices.view');
    }
}
