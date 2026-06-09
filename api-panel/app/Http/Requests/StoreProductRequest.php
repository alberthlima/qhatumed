<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'barcode' => 'nullable|string|max:50|unique:products,barcode,' . ($this->product->id ?? ''),
            'name' => 'required|string|max:200',
            'laboratory_id' => 'nullable|exists:laboratories,id',
            'pharmaceutical_form_id' => 'nullable|exists:pharmaceutical_forms,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            
            'sale_price' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|integer|min:0',
            
            // Nuevos CAMPOS
            'dispenser_id' => 'nullable|exists:dispensers,id',
            'dispenser_quantity' => 'nullable|integer',
            'therapeutic_action' => 'nullable|string',
            'active_ingredient' => 'nullable|string',
            'indications' => 'nullable|string',
            
            // SIAT Campos
            'siat_product_code' => 'nullable|string|max:20',
            'siat_unit_measure_code' => 'nullable|integer',
            
            'image' => 'nullable|image|max:2048'
        ];
    }
}
