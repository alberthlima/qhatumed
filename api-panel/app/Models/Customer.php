<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name', 'type_document_id', 'document_number', 
        'complement', 'phone', 'address', 'email', 'status'
    ];

    protected $appends = ['first_name', 'document_type'];

    public function getFirstNameAttribute()
    {
        return $this->name;
    }

    public function getDocumentTypeAttribute()
    {
        return $this->type_document_id;
    }

    public function typeDocument()
    {
        return $this->belongsTo(TypeDocument::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
