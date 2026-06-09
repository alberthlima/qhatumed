<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|exists:customers,id',
            'business_name' => 'required|string|max:150',
            'nit_ci' => 'required|string|max:50',
            'cash_tendered' => 'nullable|numeric|min:0',
            'change' => 'nullable|numeric|min:0',
            
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',

            // SIAT Campos
            'payment_method_code' => 'sometimes|integer',
            'document_type_code'  => 'sometimes|integer',
            'is_invoice'          => 'sometimes|boolean',
            
            // Allow backdating sales
            'sale_date'           => 'nullable|date',
        ];
    }
}
