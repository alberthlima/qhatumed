<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'customer_id', 'user_id', 'sale_date', 
        'business_name', 'nit_ci', 'invoice_number', 
        'cuf', 'cufd', 'siat_uid', 'invoice_url', 'leyenda',
        'total_amount', 'additional_discount', 'cash_tendered', 'change', 'status',
        'payment_method_code', 'card_number', 'card_amount', 'gift_card_amount', 'gift_card_code',
        'document_type_code', 'exception_code', 'is_invoice',
        'codigo_emision', 'cafc', 'estado_siat', 'evento_contingencia_id'
    ];

    protected $casts = [
        'sale_date'            => 'datetime',
        'is_invoice'           => 'boolean',
        'exception_code'       => 'integer',
        'codigo_emision'       => 'integer',
        'total_amount'         => 'decimal:2',
        'additional_discount'  => 'decimal:2',
        'card_amount'          => 'decimal:2',
        'gift_card_amount'     => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function eventoContingencia()
    {
        return $this->belongsTo(SiatEventoContingencia::class, 'evento_contingencia_id');
    }
}