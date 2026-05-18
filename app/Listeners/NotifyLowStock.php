<?php

namespace App\Listeners;

use App\Events\StockAlertTriggered;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Log and notify magasiniers + directeurs when a product dips below stock_min.
 */
class NotifyLowStock implements ShouldQueue
{
    public function handle(StockAlertTriggered $event): void
    {
        $ps      = $event->productStock->loadMissing(['product', 'warehouse']);
        $product = $ps->product;
        $wh      = $ps->warehouse;

        Log::warning('Stock alert triggered', [
            'product'   => $product?->name,
            'reference' => $product?->reference,
            'warehouse' => $wh?->name,
            'available' => $event->availableQuantity,
            'stock_min' => $event->stockMin,
        ]);

        // In-app notification: store in audit log as reference
        // (or dispatch an email to responsible users in a real deployment)
        // For now we notify users with roles magasinier / directeur
        $recipients = User::whereHas('roles', fn($q) =>
            $q->whereIn('name', ['magasinier', 'directeur', 'super_admin'])
        )->whereNotNull('email')->get();

        foreach ($recipients as $user) {
            $user->notify(new LowStockAlert($ps, $event->availableQuantity, $event->stockMin));
        }
    }
}
