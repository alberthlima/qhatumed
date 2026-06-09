<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegisterSession extends Model
{
    use HasFactory;
    
    protected $guarded = [];
    
    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function movements()
    {
        return $this->hasMany(CashMovement::class);
    }
}
