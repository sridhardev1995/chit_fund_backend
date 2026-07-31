<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SavingChit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SavingInstallmentController extends Controller
{
    /**
     * Week-wise CONSOLIDATED collection for one customer, across ALL of
     * their saving chits.
     *
     * ROW-COUNT OPTIMIZATION: previously this ran a query against a
     * 2,60,000+ row saving_installments table. Now it just loads the
     * customer's own chit rows (a handful, e.g. 50) -- each chit
     * carries its own 52-week schedule as JSON already in memory,
     * no extra table involved at all.
     */
    public function weeklyCollectionSummary($customerId)
    {
        $customer = Customer::find($customerId);

        if (!$customer) {
            return response()->json([
                'status'  => false,
                'message' => 'Customer not found.',
            ], 404);
        }

        $chits = SavingChit::where('customer_id', $customerId)
            ->select('id', 'installments')
            ->get();

        $byWeek = [];

        foreach ($chits as $chit) {
            foreach ($chit->installments as $row) {
                $number = $row['number'];

                if (!isset($byWeek[$number])) {
                    $byWeek[$number] = [
                        'installment_number' => $number,
                        'due_date'           => $row['due_date'],
                        'chit_count'         => 0,
                        'total_amount'       => 0,
                        'total_paid_amount'  => 0,
                        'paid_count'         => 0,
                    ];
                }

                $byWeek[$number]['chit_count']++;
                $byWeek[$number]['total_amount']      += $row['amount'];
                $byWeek[$number]['total_paid_amount'] += $row['paid_amount'];
                $byWeek[$number]['due_date'] = min($byWeek[$number]['due_date'], $row['due_date']);

                if ($row['status'] === 'PAID') {
                    $byWeek[$number]['paid_count']++;
                }
            }
        }

        $summary = collect($byWeek)
            ->map(function ($week) {
                $allPaid = $week['paid_count'] === $week['chit_count'];
                $anyPaid = $week['paid_count'] > 0;

                return [
                    'installment_number' => $week['installment_number'],
                    'due_date'           => $week['due_date'],
                    'chit_count'         => $week['chit_count'],
                    'total_amount'       => round($week['total_amount'], 2),
                    'total_paid_amount'  => round($week['total_paid_amount'], 2),
                    'status'             => $allPaid ? 'PAID' : ($anyPaid ? 'PARTIAL' : 'PENDING'),
                ];
            })
            ->sortBy('installment_number')
            ->values();

        return response()->json([
            'status'  => true,
            'message' => 'Weekly collection summary fetched successfully.',
            'data'    => $summary,
        ]);
    }

    /**
     * Collect ONE week's payment across ALL of a customer's saving chits
     * in a single action.
     *
     * ROW-COUNT OPTIMIZATION: no more per-installment rows to UPDATE
     * (previously up to 50 UPDATE queries). Now: load the customer's
     * chit rows once, mutate the JSON in memory, save each chit row
     * once (still one write per chit, but no separate installments
     * table exists to touch, and no per-chit COUNT query needed since
     * markInstallmentPaid() derives completion from the JSON itself).
     */
    public function payWeeklyCollection(Request $request, $customerId, $installmentNumber)
    {
        $customer = Customer::find($customerId);

        if (!$customer) {
            return response()->json([
                'status'  => false,
                'message' => 'Customer not found.',
            ], 404);
        }

        $chits = SavingChit::where('customer_id', $customerId)
            ->where('status', '!=', 'COMPLETED')
            ->get();

        if ($chits->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => 'No active chits found for this customer.',
            ], 400);
        }

        $collected = DB::transaction(function () use ($chits, $installmentNumber) {

            $totalCollected = 0;
            $touchedCount   = 0;
            $today          = now()->format('Y-m-d');

            foreach ($chits as $chit) {

                $week = $chit->getInstallment((int) $installmentNumber);

                if (!$week || $week['status'] === 'PAID') {
                    continue; // this chit has no such week, or already paid
                }

                $ok = $chit->markInstallmentPaid((int) $installmentNumber, $week['amount'], $today);

                if ($ok) {
                    $totalCollected += $week['amount'];
                    $touchedCount++;
                }
            }

            return ['total' => $totalCollected, 'count' => $touchedCount];
        });

        if ($collected['count'] === 0) {
            return response()->json([
                'status'  => false,
                'message' => 'Nothing pending to collect for this week (already paid or no chits found).',
            ], 400);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Week ' . $installmentNumber . ' collection recorded for ' . $collected['count'] . ' chits.',
            'data'    => [
                'installment_number' => (int) $installmentNumber,
                'chits_collected'    => $collected['count'],
                'total_collected'    => round($collected['total'], 2),
            ],
        ]);
    }

    /**
     * List installments ("weekly collection") for one saving chit --
     * comes straight from the JSON column, no join/query needed.
     */
    public function index($savingChitId)
    {
        $savingChit = SavingChit::find($savingChitId);

        if (!$savingChit) {
            return response()->json([
                'status'  => false,
                'message' => 'Saving chit not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $savingChit->installments,
        ]);
    }

    /**
     * Collect a weekly payment against ONE installment of ONE chit.
     * Body (optional): { "paid_amount": 100 } -- defaults to full installment amount
     */
    public function pay(Request $request, $savingChitId, $installmentNumber)
    {
        $savingChit = SavingChit::find($savingChitId);

        if (!$savingChit) {
            return response()->json([
                'status'  => false,
                'message' => 'Saving chit not found.',
            ], 404);
        }

        $week = $savingChit->getInstallment((int) $installmentNumber);

        if (!$week) {
            return response()->json([
                'status'  => false,
                'message' => 'Installment week not found.',
            ], 404);
        }

        if ($week['status'] === 'PAID') {
            return response()->json([
                'status'  => false,
                'message' => 'This installment is already paid.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'paid_amount' => 'nullable|numeric|min:0.01|max:' . $week['amount'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $paidAmount = $request->filled('paid_amount') ? $request->paid_amount : $week['amount'];

        $savingChit->markInstallmentPaid((int) $installmentNumber, $paidAmount);

        return response()->json([
            'status'  => true,
            'message' => 'Payment recorded successfully.',
            'data'    => $savingChit->fresh()->getInstallment((int) $installmentNumber),
        ]);
    }
}