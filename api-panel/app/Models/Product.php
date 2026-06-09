<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'barcode', 'name', 'laboratory_id', 
        'pharmaceutical_form_id', 'supplier_id',
        'average_cost', 'sale_price', 'stock', 'min_stock', 'status',
        'image_path', 'dispenser_id', 'dispenser_quantity', 
        'therapeutic_action', 'active_ingredient', 'indications', 'dosage',
        'siat_product_code', 'siat_unit_measure_code'
    ];

    public function dispenser()
    {
        return $this->belongsTo(Dispenser::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
    
    public function laboratory()
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function pharmaceuticalForm()
    {
        return $this->belongsTo(PharmaceuticalForm::class);
    }



    // Un producto tiene muchos lotes (histórico de compras)
    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }
    
    // Solo lotes vigentes y con stock > 0
    public function activeBatches()
    {
        return $this->hasMany(PurchaseItem::class)
                    ->where('current_stock', '>', 0)
                    ->orderBy('expiration_date', 'asc'); // FEFO: First expired, first out
    }

    public function isExpired(): bool
    {
        $nextBatch = $this->activeBatches()->first();
        if ($nextBatch) {
            return $nextBatch->expiration_date && $nextBatch->expiration_date <= now()->toDateString();
        }
        return false;
    }
}
