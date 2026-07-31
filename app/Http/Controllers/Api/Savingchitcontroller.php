<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavingChitRequest;
use App\Models\SavingChit;
use App\Models\SavingScheme;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SavingChitController extends Controller
{
    /**
     * Saving Chit List
     */
    public function index(Request $request)
    {
        $search = $request->search;

        $savingChits = SavingChit::with(['customer', 'scheme'])
            ->when($search, function ($query) use ($search) {
                $query->where('saving_chit_code', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('phone_number', 'like', "%{$search}%");
                    });
            })
            ->latest()
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Saving chit list fetched successfully.',
            'data'    => $savingChits,
        ]);
    }

    /**
     * Create Saving Chit(s)
     *
     * ROW-COUNT OPTIMIZATION:
     * Earlier design created 1 row PER WEEK in a separate
     * saving_installments table -> quantity=50 chits x 52 weeks =
     * 2600 rows per request, 2,60,000+ rows across all customers.
     *
     * Now the entire 52-week schedule is generated once and stored as
     * a single JSON array inside the saving_chits row itself
     * (`installments` column). No matter how many weeks get paid,
     * row count for saving_chits NEVER grows -- 1 row per chit, period.
     * quantity=50 -> 50 rows total (not 2600).
     */
    public function store(SavingChitRequest $request)
    {
        $quantity = (int) ($request->quantity ?? 1);

        try {

            $savingChits = DB::transaction(function () use ($request, $quantity) {

                $scheme = SavingScheme::lockForUpdate()->find($request->saving_scheme_id);

                if (!$scheme) {
                    throw new \RuntimeException('SCHEME_NOT_FOUND');
                }

                if (!$scheme->status) {
                    throw new \RuntimeException('SCHEME_INACTIVE');
                }

                $weeklyAmount    = $scheme->weekly_amount;
                $totalWeeks      = (int) $scheme->total_weeks;
                $maturityAmount  = $scheme->maturity_amount;
                $totalCollection = round($weeklyAmount * $totalWeeks, 2);
                $startDate       = Carbon::parse($request->start_date ?? now());

                $lastSavingChit = SavingChit::latest('id')->lockForUpdate()->first();

                if ($lastSavingChit) {
                    preg_match('/(\d+)$/', $lastSavingChit->saving_chit_code, $matches);
                    $nextNumber = (isset($matches[1]) ? (int) $matches[1] : 0) + 1;
                } else {
                    $nextNumber = 1;
                }

                $createdChits = [];

                for ($c = 0; $c < $quantity; $c++) {

                    $savingChitCode = 'SC' . str_pad($nextNumber + $c, 5, '0', STR_PAD_LEFT);

                    // Build the full 52-week schedule as a plain array
                    $installments   = [];
                    $allocatedSoFar = 0;

                    for ($i = 1; $i <= $totalWeeks; $i++) {

                        if ($i < $totalWeeks) {
                            $amount = $weeklyAmount;
                            $allocatedSoFar += $amount;
                        } else {
                            $amount = round($totalCollection - $allocatedSoFar, 2);
                        }

                        $installments[] = [
                            'number'      => $i,
                            'due_date'    => $startDate->copy()->addWeeks($i)->format('Y-m-d'),
                            'amount'      => $amount,
                            'paid_amount' => 0,
                            'paid_date'   => null,
                            'status'      => 'PENDING',
                        ];
                    }

                    // ONE row per chit -- schedule lives inside it as JSON
                    $savingChit = SavingChit::create([
                        'saving_chit_code'  => $savingChitCode,
                        'customer_id'       => $request->customer_id,
                        'saving_scheme_id'  => $scheme->id,
                        'weekly_amount'     => $weeklyAmount,
                        'total_weeks'       => $totalWeeks,
                        'total_collection'  => $totalCollection,
                        'maturity_amount'   => $maturityAmount,
                        'start_date'        => $request->start_date,
                        'status'            => 'ACTIVE',
                        'installments'      => $installments,
                        'paid_weeks_count'  => 0,
                        'total_paid_amount' => 0,
                    ]);

                    $createdChits[] = $savingChit;
                }

                return $createdChits;
            });

        } catch (\RuntimeException $e) {

            if ($e->getMessage() === 'SCHEME_NOT_FOUND') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Saving scheme not found.',
                ], 404);
            }

            if ($e->getMessage() === 'SCHEME_INACTIVE') {
                return response()->json([
                    'status'  => false,
                    'message' => 'This saving scheme is not active.',
                ], 400);
            }

            Log::error('Saving chit creation failed: ' . $e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while creating the saving chit.',
            ], 500);

        } catch (\Throwable $e) {

            Log::error('Saving chit creation failed: ' . $e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while creating the saving chit.',
            ], 500);
        }

        $ids = collect($savingChits)->pluck('id');
        $fresh = SavingChit::with(['customer', 'scheme'])->whereIn('id', $ids)->get();

        $summary = [
            'quantity'                 => $quantity,
            'weekly_amount_per_chit'   => $fresh->first()->weekly_amount,
            'total_weekly_commitment'  => round($fresh->first()->weekly_amount * $quantity, 2),
            'total_weeks'              => $fresh->first()->total_weeks,
            'maturity_amount_per_chit' => $fresh->first()->maturity_amount,
            'total_maturity_amount'    => round($fresh->first()->maturity_amount * $quantity, 2),
        ];

        return response()->json([
            'status'  => true,
            'message' => $quantity > 1
                ? "{$quantity} saving chits created successfully."
                : 'Saving chit created successfully.',
            'summary' => $summary,
            'data'    => $fresh,
        ], 201);
    }

    /**
     * View Saving Chit (installments come for free -- they're in the row)
     */
    public function show($id)
    {
        $savingChit = SavingChit::with(['customer', 'scheme'])->find($id);

        if (!$savingChit) {
            return response()->json([
                'status'  => false,
                'message' => 'Saving chit not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $savingChit,
        ]);
    }

    /**
     * Update Saving Chit
     */
    public function update(Request $request, $id)
    {
        $savingChit = SavingChit::find($id);

        if (!$savingChit) {
            return response()->json([
                'status'  => false,
                'message' => 'Saving chit not found.',
            ], 404);
        }

        $savingChit->update([
            'start_date' => $request->start_date ?? $savingChit->start_date,
            'status'     => $request->status ?? $savingChit->status,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Saving chit updated successfully.',
            'data'    => $savingChit,
        ]);
    }

    /**
     * Delete Saving Chit
     */
    public function destroy($id)
    {
        $savingChit = SavingChit::find($id);

        if (!$savingChit) {
            return response()->json([
                'status'  => false,
                'message' => 'Saving chit not found.',
            ], 404);
        }

        $savingChit->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Saving chit deleted successfully.',
        ]);
    }
}