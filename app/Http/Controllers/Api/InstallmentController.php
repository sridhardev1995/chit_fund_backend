<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chit;
use Illuminate\Http\Request;

class InstallmentController extends Controller
{
    /**
     * All Customers -> Chits -> Installments (PENDING/PARTIAL only)
     *
     * No customer_id needed. Returns every customer's chits along with
     * their pending/partial installments, paginated to avoid loading the
     * entire table into memory as data grows.
     *
     * PAID installments are excluded at the DB level — the Collections
     * screen never showed them anyway (client used to filter them out
     * after download), so this is purely a performance change, not a
     * feature change.
     *
     * GET /api/installments?page=1&per_page=20
     */
    public function allInstallments(Request $request)
    {
        $perPage = (int) $request->get('per_page', 20);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 20; // safety cap

        $chits = Chit::with([
                'customer',
                'installments' => function ($query) {
                    $query->whereIn('status', ['PENDING', 'PARTIAL'])
                          ->orderBy('installment_number');
                }
            ])
            ->whereHas('installments', function ($query) {
                $query->whereIn('status', ['PENDING', 'PARTIAL']);
            })
            ->latest()
            ->paginate($perPage);

        $data = $chits->getCollection()->map(function ($chit) {
            return [
                'customer_name' => optional($chit->customer)->name,
                'phone_number'  => optional($chit->customer)->phone_number,
                'chit_code'     => $chit->chit_code,
                'chit_amount'   => $chit->chit_amount,
                'total_weeks'   => $chit->total_weeks,
                'chit_status'   => $chit->status,
                'installments'  => $chit->installments->map(function ($inst) {
                    return [
                        'id'                 => $inst->id,
                        'chit_id'            => $inst->chit_id,
                        'installment_number' => $inst->installment_number,
                        'due_date'           => $inst->due_date,
                        'amount'             => $inst->amount,
                        'paid_amount'        => $inst->paid_amount,
                        'status'             => $inst->status,
                        'paid_date'          => $inst->paid_date,
                    ];
                }),
            ];
        })->values();

        return response()->json([
            'status'  => true,
            'message' => 'All installments fetched successfully.',
            'data'    => $data,
            'meta'    => [
                'current_page' => $chits->currentPage(),
                'last_page'    => $chits->lastPage(),
                'per_page'     => $chits->perPage(),
                'total'        => $chits->total(),
            ],
        ]);
    }
}