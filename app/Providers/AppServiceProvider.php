<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\ClientPayment;
use App\Models\DeliveryNote;
use App\Models\InventorySession;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\ClientPaymentPolicy;
use App\Policies\ClientPolicy;
use App\Policies\CreditNotePolicy;
use App\Policies\DeliveryNotePolicy;
use App\Policies\InventorySessionPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\RolePolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\SupplierInvoicePolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UserPolicy;
use App\Repositories\CompanyRepository;
use App\Repositories\ProductRepository;
use App\Events\CreditNoteValidated;
use App\Events\InvoiceValidated;
use App\Events\PaymentReceived;
use App\Events\StockAlertTriggered;
use App\Events\SupplierInvoiceValidated;
use App\Events\SupplierPaymentCreated;
use App\Listeners\NotifyLowStock;
use App\Listeners\NotifyCreditNoteValidated;
use App\Listeners\NotifySupplierInvoiceValidated;
use App\Listeners\SendInvoiceToClient;
use App\Listeners\SyncClientBalanceOnInvoice;
use App\Listeners\SyncSupplierBalanceOnInvoice;
use App\Listeners\UpdateClientBalance;
use App\Listeners\UpdateSupplierBalance;
use App\Services\UserHomeRoute;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CompanyRepository::class, fn() => new CompanyRepository(new \App\Models\Company));
        $this->app->bind(ProductRepository::class, fn() => new ProductRepository(new \App\Models\Product));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Event → Listener bindings ──────────────────────────────────────────
        // Ventes
        Event::listen(InvoiceValidated::class,          SendInvoiceToClient::class);
        Event::listen(InvoiceValidated::class,          SyncClientBalanceOnInvoice::class);
        Event::listen(PaymentReceived::class,            UpdateClientBalance::class);

        // Achats
        Event::listen(SupplierInvoiceValidated::class,  NotifySupplierInvoiceValidated::class);
        Event::listen(SupplierInvoiceValidated::class,  SyncSupplierBalanceOnInvoice::class);
        Event::listen(SupplierPaymentCreated::class,     UpdateSupplierBalance::class);

        // Avoirs
        Event::listen(CreditNoteValidated::class,        NotifyCreditNoteValidated::class);

        // Stock
        Event::listen(StockAlertTriggered::class,        NotifyLowStock::class);

        // Note: GL posting (AccountingService) is called DIRECTLY inside each
        // service transaction — not via listeners — to guarantee atomicity.

        // ── Rediriger les users déjà connectés vers leur module principal ────────
        RedirectIfAuthenticated::redirectUsing(fn () => UserHomeRoute::resolve());

        // ── Super admin bypasses all policy checks ─────────────────────────────
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
        });

        // Register model policies
        Gate::policy(Product::class,         ProductPolicy::class);
        Gate::policy(Client::class,          ClientPolicy::class);
        Gate::policy(Supplier::class,        SupplierPolicy::class);
        Gate::policy(\App\Models\Quote::class,          \App\Policies\QuotePolicy::class);
        Gate::policy(Order::class,           OrderPolicy::class);
        Gate::policy(Invoice::class,         InvoicePolicy::class);
        Gate::policy(DeliveryNote::class,    DeliveryNotePolicy::class);
        Gate::policy(CreditNote::class,      CreditNotePolicy::class);
        Gate::policy(PurchaseOrder::class,   PurchaseOrderPolicy::class);
        Gate::policy(SupplierInvoice::class, SupplierInvoicePolicy::class);
        Gate::policy(StockMovement::class,   StockMovementPolicy::class);
        Gate::policy(InventorySession::class,InventorySessionPolicy::class);
        Gate::policy(ClientPayment::class,   ClientPaymentPolicy::class);
        Gate::policy(User::class,            UserPolicy::class);
        Gate::policy(Role::class,            RolePolicy::class);
        Gate::policy(AuditLog::class,        AuditLogPolicy::class);
        // [SEC-PHASE2] AttachmentPolicy avec methodes personnalisees (viewAttachmentsOf, create, download, delete)
        Gate::policy(\App\Models\Attachment::class, \App\Policies\AttachmentPolicy::class);

        // [MED-4] Observers d'audit — trace dans audit_logs les opérations critiques.
        \App\Models\Invoice::observe(\App\Observers\InvoiceObserver::class);

        // [AUDIT-B] Observers pour Stock + Compta + Trésorerie
        \App\Models\StockMovement::observe(\App\Observers\StockMovementObserver::class);
        \App\Models\JournalEntry::observe(\App\Observers\JournalEntryObserver::class);
        \App\Models\ClientPayment::observe(\App\Observers\PaymentObserver::class);
        \App\Models\SupplierPayment::observe(\App\Observers\PaymentObserver::class);

        // [TRACE] Observers étendus — traçabilité complète des entités métier critiques.
        \App\Models\Quote::observe(\App\Observers\QuoteObserver::class);
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
        \App\Models\PurchaseOrder::observe(\App\Observers\PurchaseOrderObserver::class);
        \App\Models\SupplierInvoice::observe(\App\Observers\SupplierInvoiceObserver::class);
        \App\Models\StockTransfer::observe(\App\Observers\StockTransferObserver::class);
        \App\Models\Rfq::observe(\App\Observers\RfqObserver::class);
    }
}
