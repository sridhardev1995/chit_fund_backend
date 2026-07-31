<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingInstallment extends Model
{
    protected $fillable = [
        'saving_chit_id',
        'installment_number',
        'due_date',
        'amount',
        'paid_amount',
        'paid_date',
        'status',
    ];

    public function savingChit()
    {
        return $this->belongsTo(SavingChit::class);
    }
}