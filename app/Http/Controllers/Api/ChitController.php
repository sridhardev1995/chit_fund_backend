<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChitRequest;
use App\Models\Chit;
use App\Models\CommissionSetting;
use Illuminate\Http\Request;
use App\Models\Installment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChitController extends Controller
{

    /**
     * Chit List
     */
    public function index(Request $request)
    {
        $search = $request->search;


        $chits = Chit::with('customer')
            ->when($search, function ($query) use ($search) {

                $query->where('chit_code', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {

                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('phone_number', 'like', "%{$search}%");

                    });

            })
            ->latest()
            ->get();


        return response()->json([

            'status'=>true,

            'message'=>'Chit list fetched successfully.',

            'data'=>$chits

        ]);
    }



    /**
     * Create Chit
     *
     * Wrapped in a DB transaction so that Chit + all Installments are
     * created atomically. Chit code generation uses row locking to avoid
     * duplicate codes under concurrent requests. Installment amounts are
     * adjusted on the last installment to absorb any rounding difference
     * so the sum of installments always equals chit_amount exactly.
     *
     * Commission rate:
     *  - If the admin passes `commission_rate` in the request, that value
     *    is used and frozen on the chit (overrides the global setting).
     *  - If not passed, falls back to the currently active
     *    CommissionSetting (locked for consistent read), same as before.
     */
    public function store(ChitRequest $request)
    {
        // Basic safety check (in addition to ChitRequest validation)
        if ((int) $request->total_weeks < 1) {
            return response()->json([
                'status'  => false,
                'message' => 'Total weeks must be at least 1.'
            ], 422);
        }

        // Validate the optional commission override here since it's not
        // guaranteed to be part of ChitRequest's base rules.
        if ($request->filled('commission_rate')) {
            $validator = \Illuminate\Support\Facades\Validator::make(
                $request->only('commission_rate'),
                ['commission_rate' => 'numeric|min:0|max:100']
            );

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid commission rate.',
                    'errors'  => $validator->errors()
                ], 422);
            }
        }

        try {

            $chit = DB::transaction(function () use ($request) {

                $chitAmount = $request->chit_amount;
                $totalWeeks = (int) $request->total_weeks;

                if ($request->filled('commission_rate')) {
                    // Admin has explicitly overridden the commission rate
                    // for this chit — use it as-is, no need to touch the
                    // global CommissionSetting table.
                    $commissionRate = (float) $request->commission_rate;
                } else {
                    // Lock the commission settings row to get a consistent read
                    $commission = CommissionSetting::where('status', true)
                        ->latest()
                        ->lockForUpdate()
                        ->first();

                    if (!$commission) {
                        // Throwing inside transaction() triggers automatic rollback
                        throw new \RuntimeException('COMMISSION_NOT_CONFIGURED');
                    }

                    $commissionRate = $commission->commission_rate;
                }

                // Calculation
                $commissionAmount = round(($chitAmount * $commissionRate) / 100, 2);
                $disbursedAmount  = $chitAmount - $commissionAmount;

                // Base weekly installment, rounded to 2 decimals
                $weeklyInstallment = round($chitAmount / $totalWeeks, 2);

                // Generate Chit Code — lock last row to prevent race condition
                // duplicate codes when two requests arrive at the same time
                $lastChit = Chit::latest('id')->lockForUpdate()->first();

                if ($lastChit) {
                    // Extract trailing digits regardless of prefix length
                    preg_match('/(\d+)$/', $lastChit->chit_code, $matches);
                    $number = isset($matches[1]) ? (int) $matches[1] : 0;

                    $chitCode = "CH" . str_pad(
                        $number + 1,
                        5,
                        '0',
                        STR_PAD_LEFT
                    );
                } else {
                    $chitCode = "CH00001";
                }

                $chit = Chit::create([

                    'chit_code'         => $chitCode,
                    'customer_id'       => $request->customer_id,
                    'chit_amount'       => $chitAmount,

                    // Freeze commission (custom or from settings, either way)
                    'commission_rate'   => $commissionRate,
                    'commission_amount' => $commissionAmount,

                    'disbursed_amount'  => $disbursedAmount,
                    'total_weeks'       => $totalWeeks,
                    'weekly_installment'=> $weeklyInstallment,
                    'start_date'        => $request->start_date,
                    'status'            => 'ACTIVE'

                ]);

                // Generate Installments
                $startDate = Carbon::parse($request->start_date ?? now());

                // Running total to calculate rounding-safe last installment
                $allocatedSoFar = 0;

                for ($i = 1; $i <= $totalWeeks; $i++) {

                    if ($i < $totalWeeks) {
                        $amount = $weeklyInstallment;
                        $allocatedSoFar += $amount;
                    } else {
                        // Last installment absorbs any rounding difference
                        // so total installments == chit_amount exactly
                        $amount = round($chitAmount - $allocatedSoFar, 2);
                    }

                    Installment::create([

                        'chit_id'            => $chit->id,
                        'installment_number' => $i,

                        // Weekly due date
                        'due_date' => $startDate->copy()
                                        ->addWeeks($i)
                                        ->format('Y-m-d'),

                        'amount'      => $amount,
                        'paid_amount' => 0,
                        'status'      => 'PENDING'

                    ]);
                }

                return $chit;
            });

        } catch (\RuntimeException $e) {

            if ($e->getMessage() === 'COMMISSION_NOT_CONFIGURED') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Commission rate not configured.'
                ], 400);
            }

            Log::error('Chit creation failed: ' . $e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while creating the chit.'
            ], 500);

        } catch (\Throwable $e) {

            Log::error('Chit creation failed: ' . $e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while creating the chit.'
            ], 500);
        }

        return response()->json([

            'status'  => true,
            'message' => 'Chit created successfully.',
            'data'    => $chit->load('customer')

        ], 201);

    }



    /**
     * View Chit
     */
    public function show($id)
    {

        $chit = Chit::with('customer')
                    ->find($id);



        if(!$chit){

            return response()->json([

                'status'=>false,

                'message'=>'Chit not found.'

            ],404);

        }



        return response()->json([

            'status'=>true,

            'data'=>$chit

        ]);

    }





    /**
     * Update Chit
     */
    public function update(ChitRequest $request,$id)
    {

        $chit = Chit::find($id);


        if(!$chit){

            return response()->json([

                'status'=>false,

                'message'=>'Chit not found.'

            ],404);

        }


        // Amount related fields should not change after creation

        $chit->update([

            'customer_id'=>$request->customer_id,

            'start_date'=>$request->start_date,

            'status'=>$request->status ?? $chit->status

        ]);



        return response()->json([

            'status'=>true,

            'message'=>'Chit updated successfully.',

            'data'=>$chit

        ]);

    }




    /**
     * Delete Chit
     */
    public function destroy($id)
    {

        $chit = Chit::find($id);



        if(!$chit){

            return response()->json([

                'status'=>false,

                'message'=>'Chit not found.'

            ],404);

        }


        $chit->delete();



        return response()->json([

            'status'=>true,

            'message'=>'Chit deleted successfully.'

        ]);

    }

}