<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chit;

class InstallmentController extends Controller
{
    /**
     * All Customers -> Chits -> Installments
     *
     * No customer_id needed. Returns every customer's chits along with
     * their installments in one shot.
     *
     * GET /api/installments
     */
    public function allInstallments()
    {
        $chits = Chit::with([
            'customer',
            'installments' => function ($query) {
                $query->orderBy('installment_number');
            }
        ])
            ->latest()
            ->get();

        $data = $chits->map(function ($chit) {
            return [
                'customer_name' => optional($chit->customer)->name,
                'phone_number' => optional($chit->customer)->phone_number,
                'chit_code' => $chit->chit_code,
                'chit_amount' => $chit->chit_amount,
                'total_weeks' => $chit->total_weeks,
                'chit_status' => $chit->status,
                'installments' => $chit->installments->map(function ($inst) {
                    return [
                        'id' => $inst->id,          // ✅ ADD THIS
                        'chit_id' => $inst->chit_id,      // ✅ ADD THIS (useful too)
                        'installment_number' => $inst->installment_number,
                        'due_date' => $inst->due_date,
                        'amount' => $inst->amount,
                        'paid_amount' => $inst->paid_amount,
                        'status' => $inst->status,
                        'paid_date' => $inst->paid_date,
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'All installments fetched successfully.',
            'data' => $data
        ]);
    }
}