<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{

    protected $fillable = [

        'chit_id',

        'installment_number',

        'due_date',

        'amount',

        'paid_amount',

        'status',

        'paid_date'

    ];


    public function chit()
    {
        return $this->belongsTo(Chit::class);
    }

    public function payments()
{
    return $this->hasMany(Payment::class);
}

}