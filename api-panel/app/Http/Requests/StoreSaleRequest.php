<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Tipos 1 (CI) y 5 (NIT) son estrictamente numéricos. CEX/PAS/OD pueden ser alfanuméricos.
            'nit_ci' => [
                'required', 'string', 'max:50',
                Rule::when(
                    in_array((int) $this->input('document_type_code', 1), [1, 5], true),
                    ['regex:/^\d+$/']
                ),
            ],
            'cash_tendered' => 'nullable|numeric|min:0',
            'change' => 'nullable|numeric|min:0',
            
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',

            // Descuento adicional global (SIAT: descuentoAdicional)
            'discount_amount' => 'nullable|numeric|min:0',

            // SIAT Campos y Medios de Pago
            'payment_method_code' => 'sometimes|integer',
            'card_number'         => 'nullable|string',
            'card_amount'         => 'nullable|numeric|min:0',
            'gift_card_amount'    => 'nullable|numeric|min:0',
            'gift_card_code'      => 'nullable|string|max:50',
            'document_type_code'  => 'sometimes|integer',
            'exception_code'      => 'nullable|integer|in:0,1',
            'is_invoice'          => 'sometimes|boolean',
            'is_offline'          => 'nullable|boolean',
            'codigo_emision'      => 'nullable|integer|in:1,2,3',
            'cafc'                => 'nullable|string|max:50',
            'invoice_number'      => 'nullable|integer|min:1',
            
            // Allow backdating sales
            'sale_date'           => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'nit_ci.regex' => 'El campo NIT/CI es de formato estrictamente numérico para documentos tipo CI o NIT.',
        ];
    }
}
