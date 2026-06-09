<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'required_if:reason,COMPRA|nullable|exists:suppliers,id',
            'reason' => 'nullable|string|in:COMPRA,INVENTARIO_INICIAL',
            'purchase_date' => 'required|date',
            'invoice_number' => 'nullable|string|max:50',
            
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.batch' => 'nullable|string|max:50',
            'items.*.expiration_date' => 'nullable|date',
            'items.*.suggested_sale_price' => 'nullable|numeric|min:0',
            
            // Allow immediate price update catalog-wide from the purchase screen
            'items.*.sale_price' => 'nullable|numeric|min:0', 
        ];
    }
}
