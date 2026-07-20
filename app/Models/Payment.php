<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'chit_id',
        'installment_id',
        'customer_id',
        'amount',
        'payment_date',
        'payment_mode',
        'reference_number',
        'collected_by',
        'remarks',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function chit()
    {
        return $this->belongsTo(Chit::class);
    }

    public function installment()
    {
        return $this->belongsTo(Installment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}