<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiatEventoContingencia extends Model
{
    use HasFactory;

    protected $table = 'siat_eventos_contingencia';
    protected $guarded = [];

    public function sales()
    {
        return $this->hasMany(Sale::class, 'evento_contingencia_id');
    }
}
