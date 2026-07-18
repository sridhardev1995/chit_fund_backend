<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionSetting extends Model
{

    protected $fillable = [

        'commission_rate',
        'status'

    ];

}