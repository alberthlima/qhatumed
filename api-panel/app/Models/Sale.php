<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'customer_id', 'user_id', 'sale_date', 
        'business_name', 'nit_ci', 'invoice_number', 
        'cuf', 'cufd', 'siat_uid', 'invoice_url', 
        'total_amount', 'cash_tendered', 'change', 'status',
        'payment_method_code', 'document_type_code', 'is_invoice'
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
