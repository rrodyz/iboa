<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'number'           => $this->number,
            'status'           => $this->status,
            'client'           => $this->whenLoaded('client', fn() => [
                'id'   => $this->client->id,
                'name' => $this->client->name,
                'code' => $this->client->code,
            ]),
            'issued_at'        => $this->issued_at?->toDateString(),
            'due_at'           => $this->due_at?->toDateString(),
            'currency_code'    => $this->currency_code,
            'subtotal_ht'      => $this->subtotal_ht,
            'total_tax'        => $this->total_tax,
            'total_ttc'        => $this->total_ttc,
            'paid_amount'      => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'notes'            => $this->notes,
            'items'            => $this->whenLoaded('items', fn() =>
                $this->items->map(fn($item) => [
                    'id'             => $item->id,
                    'description'    => $item->description,
                    'quantity'       => $item->quantity,
                    'unit_price'     => $item->unit_price,
                    'tax_rate_value' => $item->tax_rate_value,
                    'line_total_ht'  => $item->line_total_ht,
                    'line_total_ttc' => $item->line_total_ttc,
                ])
            ),
            'created_at'       => $this->created_at?->toISOString(),
        ];
    }
}
