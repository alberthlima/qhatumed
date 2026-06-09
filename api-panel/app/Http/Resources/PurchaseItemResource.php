<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch' => $this->batch,
            'expiration_date' => $this->expiration_date,
            'quantity' => (int) $this->quantity,
            'current_stock' => (int) $this->current_stock,
            'unit_cost' => (float) $this->unit_cost,
        ];
    }
}
