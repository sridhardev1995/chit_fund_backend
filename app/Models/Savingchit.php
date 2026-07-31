<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingChit extends Model
{
    protected $fillable = [
        'saving_chit_code',
        'customer_id',
        'saving_scheme_id',
        'weekly_amount',
        'total_weeks',
        'total_collection',
        'maturity_amount',
        'start_date',
        'status',
        'installments',
        'paid_weeks_count',
        'total_paid_amount',
    ];

    protected $casts = [
        'installments' => 'array',   // JSON <-> PHP array automatically
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function scheme()
    {
        return $this->belongsTo(SavingScheme::class, 'saving_scheme_id');
    }

    public function getBonusAmountAttribute()
    {
        return round($this->maturity_amount - $this->total_collection, 2);
    }

    /**
     * Find one week's installment entry inside the JSON array.
     * Returns null if that week number doesn't exist.
     */
    public function getInstallment(int $number): ?array
    {
        foreach ($this->installments as $row) {
            if ((int) $row['number'] === $number) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Mark one week PAID inside the JSON array and persist it,
     * updating the running counters at the same time.
     * Returns false if that week is already paid or not found.
     */
    public function markInstallmentPaid(int $number, float $paidAmount, ?string $paidDate = null): bool
    {
        $rows  = $this->installments;
        $found = false;

        foreach ($rows as &$row) {
            if ((int) $row['number'] === $number) {

                if ($row['status'] === 'PAID') {
                    return false; // already paid, nothing to do
                }

                $row['paid_amount'] = $paidAmount;
                $row['paid_date']   = $paidDate ?? now()->format('Y-m-d');
                $row['status']      = $paidAmount >= $row['amount'] ? 'PAID' : 'PENDING';
                $found = true;
                break;
            }
        }
        unset($row);

        if (!$found) {
            return false;
        }

        $this->installments = $rows;
        $this->paid_weeks_count = collect($rows)->where('status', 'PAID')->count();
        $this->total_paid_amount = round(collect($rows)->sum('paid_amount'), 2);

        if ($this->paid_weeks_count === (int) $this->total_weeks) {
            $this->status = 'COMPLETED';
        }

        $this->save();

        return true;
    }
}