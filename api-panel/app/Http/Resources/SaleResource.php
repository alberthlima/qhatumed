<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_date' => $this->sale_date,
            'business_name' => $this->business_name,
            'nit_ci' => $this->nit_ci,
            
            // SIAT
            'invoice_number' => $this->invoice_number,
            'cuf' => $this->cuf,
            'invoice_url' => $this->invoice_url,
            'estado_siat' => $this->estado_siat,
            'codigo_emision' => (int) ($this->codigo_emision ?? 1),
            'cafc' => $this->cafc,
            'payment_method_code' => (int) ($this->payment_method_code ?? 1),
            'gift_card_amount' => (float) ($this->gift_card_amount ?? 0),
            'additional_discount' => (float) ($this->additional_discount ?? 0),
            
            'total_amount' => (float) $this->total_amount,
            
            'status' => $this->status,
            
            // Relations
            'customer' => $this->whenLoaded('customer'),
            'user' => $this->whenLoaded('user', function () {
                return ['id' => $this->user->id, 'name' => $this->user->name];
            }),
            'items' => SaleItemResource::collection($this->whenLoaded('items'))
        ];
    }
}
