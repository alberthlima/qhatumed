<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Laboratory extends Model
{
    protected $fillable = ['name', 'status', 'image_path'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}