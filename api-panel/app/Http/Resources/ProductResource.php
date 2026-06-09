<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'name' => $this->name,
            
            // Raw Foreign Keys for Frontend Select Forms
            'laboratory_id' => $this->laboratory_id,
            'pharmaceutical_form_id' => $this->pharmaceutical_form_id,
            'supplier_id' => $this->supplier_id,
            'dispenser_id' => $this->dispenser_id,
            
            // Farmacologia
            'therapeutic_action' => $this->therapeutic_action,
            'active_ingredient' => $this->active_ingredient,
            'indications' => $this->indications,
            'dosage' => $this->dosage,
            'dispenser_quantity' => $this->dispenser_quantity,
            
            'image_path' => $this->image_path,
            
            // Nested Relationships
            'laboratory' => $this->whenLoaded('laboratory'),
            'supplier' => $this->whenLoaded('supplier'),
            'pharmaceutical_form' => $this->whenLoaded('pharmaceuticalForm'),
            
            // Pricing & Stock
            'average_cost' => (float) $this->average_cost,
            'sale_price' => (float) $this->sale_price,
            'stock' => (int) $this->stock,
            'min_stock' => (int) $this->min_stock,
            'status' => (bool) $this->status,
            'is_expired' => $this->isExpired(),
            
            // Batches only loaded if explicitly requested (to avoid heavy queries)
            'active_batches' => PurchaseItemResource::collection($this->whenLoaded('activeBatches')),
        ];
    }
}
