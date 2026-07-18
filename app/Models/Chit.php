<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chit extends Model
{

    protected $fillable = [

        'chit_code',

        'customer_id',

        'chit_amount',

        'commission_rate',

        'commission_amount',

        'disbursed_amount',

        'total_weeks',

        'weekly_installment',

        'start_date',

        'status'

    ];


    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function installments()
{
    return $this->hasMany(Installment::class);
}

}