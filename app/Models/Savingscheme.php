<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingScheme extends Model
{
    protected $fillable = [
        'scheme_code',
        'name',
        'weekly_amount',
        'total_weeks',
        'maturity_amount',
        'status',
    ];

    protected $casts = [
        'weekly_amount'   => 'decimal:2',
        'maturity_amount' => 'decimal:2',
        'status'          => 'boolean',
    ];

    public function savingChits()
    {
        return $this->hasMany(SavingChit::class);
    }

    // Total the customer actually pays in = weekly_amount x total_weeks
    public function getTotalCollectionAttribute()
    {
        return round($this->weekly_amount * $this->total_weeks, 2);
    }

    // Extra bonus given on top of what customer paid
    public function getBonusAmountAttribute()
    {
        return round($this->maturity_amount - $this->total_collection, 2);
    }
}