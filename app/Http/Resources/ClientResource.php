<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'code'      => $this->code,
            'name'      => $this->name,
            'type'      => $this->type,
            'email'     => $this->email,
            'phone'     => $this->phone,
            'address'   => $this->address,
            'city'      => $this->city,
            'country'   => $this->country,
            'ifu'       => $this->ifu,
            'balance'   => $this->balance,
            'is_active' => $this->is_active,
            'created_at'=> $this->created_at?->toISOString(),
        ];
    }
}
