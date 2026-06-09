<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_id', 'purchase_item_id', 
        'quantity', 'unit_price', 'discount', 'subtotal',
        'average_cost_at_sale', 'batch_cost_at_sale'
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseItem() // El lote específico FEFO
    {
        return $this->belongsTo(PurchaseItem::class);
    }
}
