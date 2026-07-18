<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [

        'customer_code',
        'name',
        'phone_number',
        'address',
        'aadhaar_number',
        'pan_number',
        'bank_name',
        'account_number',
        'ifsc',
        'upi_id',
        'reference_name',
        'reference_number',
        'nominee_name',
        'nominee_number',
        'photo',
        'remarks',
        'status',

    ];
}