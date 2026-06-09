<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    //
    protected $fillable = [
        'trade_name',
        'legal_name',
        'nit',
        'siat_environment',
        'siat_modality',
        'siat_token',
        'siat_system_code',
        'siat_branch_code',
        'siat_pos_code',
    ];
}
