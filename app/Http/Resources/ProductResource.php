<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'reference'      => $this->reference,
            'name'           => $this->name,
            'description'    => $this->description,
            'family'         => $this->whenLoaded('family', fn() => [
                'id'   => $this->family->id,
                'name' => $this->family->name,
            ]),
            'unit'           => $this->whenLoaded('unit', fn() => [
                'id'           => $this->unit->id,
                'abbreviation' => $this->unit->abbreviation,
            ]),
            'sale_price'     => $this->sale_price,
            'purchase_price' => $this->purchase_price,
            'tax_rate'       => $this->whenLoaded('taxRate', fn() => $this->taxRate?->rate),
            'is_stockable'   => $this->is_stockable,
            'is_active'      => $this->is_active,
            'stock_min'      => $this->stock_min,
            'stock_max'      => $this->stock_max,
            'barcode'        => $this->barcode,
            'stocks'         => $this->whenLoaded('productStocks', fn() =>
                $this->productStocks->map(fn($s) => [
                    'warehouse_id'   => $s->warehouse_id,
                    'warehouse_name' => $s->warehouse?->name,
                    'quantity'       => $s->quantity,
                    'reserved'       => $s->reserved_quantity,
                    'available'      => max(0, $s->quantity - $s->reserved_quantity),
                    'avg_cost'       => $s->avg_cost,
                ])
            ),
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
        ];
    }
}
