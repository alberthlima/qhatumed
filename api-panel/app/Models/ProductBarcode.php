<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBarcode extends Model
{
    protected $fillable = [
        'product_id',
        'barcode',
        'note',
    ];

    /**
     * El producto al que pertenece este código de barras alternativo.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
