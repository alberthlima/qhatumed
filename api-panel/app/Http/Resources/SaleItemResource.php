<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_name' => $this->product ? $this->product->name : 'Unknown',
            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'discount' => (float) $this->discount,
            'subtotal' => (float) $this->subtotal,
            'margin' => (float) ($this->subtotal - ($this->average_cost_at_sale * $this->quantity))
        ];
    }
}
