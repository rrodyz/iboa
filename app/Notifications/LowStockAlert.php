<?php

namespace App\Notifications;

use App\Models\ProductStock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ProductStock $stock,
        public readonly float $available,
        public readonly float $minimum
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $product   = $this->stock->product;
        $warehouse = $this->stock->warehouse;

        return [
            'type'       => 'low_stock',
            'icon'       => 'archive',
            'color'      => 'orange',
            'title'      => 'Stock faible',
            'message'    => 'Le produit '.($product?->name ?? '—')
                           .' est en rupture imminente : '
                           .$this->available.' disponible(s) (minimum : '.$this->minimum.')'
                           .($warehouse ? ' — entrepôt '.$warehouse->name : '').'.',
            'url'        => $product ? route('stocks.index', ['product_id' => $product->id]) : route('stocks.index'),
            'model_type' => 'ProductStock',
            'model_id'   => $this->stock->id,
        ];
    }
}
