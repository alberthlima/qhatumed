<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'customer_id', 'user_id', 'sale_date', 
        'business_name', 'nit_ci', 'invoice_number', 
        'cuf', 'cufd', 'siat_uid', 'invoice_url', 'leyenda',
        'total_amount', 'cash_tendered', 'change', 'status',
        'payment_method_code', 'card_number', 'document_type_code', 'is_invoice'
    ];

    protected $casts = [
        'sale_date'    => 'datetime',
        'is_invoice'   => 'boolean',
        'total_amount' => 'decimal:2',
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
}