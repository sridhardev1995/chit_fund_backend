<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chit;
use App\Models\Installment;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Payment List
     */
    public function index(Request $request)
    {
        $search = $request->search;

        $payments = Payment::with(['chit', 'installment', 'customer'])
            ->when($request->chit_id, fn ($q) => $q->where('chit_id', $request->chit_id))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->installment_id, fn ($q) => $q->where('installment_id', $request->installment_id))
            ->when($search, function ($query) use ($search) {

                $query->whereHas('chit', function ($q) use ($search) {
                        $q->where('chit_code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('phone_number', 'like', "%{$search}%");
                    });

            })
            ->latest()
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Payment list fetched successfully.',
            'data'    => $payments
        ]);
    }

    /**
     * Record Payment
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'installment_id' => 'required|exists:installments,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_mode' => 'required|in:CASH,UPI,BANK_TRANSFER,CHEQUE,OTHER',
            'reference_number' => 'nullable|string|max:100',
            'collected_by' => 'nullable|string|max:150',
            'remarks' => 'nullable|string',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {

            $payment = DB::transaction(function () use ($request) {

                $installment = Installment::where('id', $request->installment_id)
                    ->lockForUpdate()
                    ->first();

                if ($installment->status === 'PAID') {
                    throw new \RuntimeException('INSTALLMENT_ALREADY_PAID');
                }

                // ===== Sequential payment check (forward) =====
                // Munnadi irukura installments ellam PAID aaganum,
                // appo dhan indha installment-ku payment record pannalam.
                // lockForUpdate() ithula mukkiyam — concurrent requests
                // (eg. installment 1 & 2 same time) race pannama block aagum.
                $pendingBefore = Installment::where('chit_id', $installment->chit_id)
                    ->where('installment_number', '<', $installment->installment_number)
                    ->where('status', '!=', 'PAID')
                    ->orderBy('installment_number')
                    ->lockForUpdate()
                    ->first();

                if ($pendingBefore) {
                    throw new \RuntimeException('PREVIOUS_INSTALLMENT_PENDING:' . $pendingBefore->installment_number);
                }

                $remainingDue = round($installment->amount - $installment->paid_amount, 2);

                if ($request->amount > $remainingDue) {
                    throw new \RuntimeException('AMOUNT_EXCEEDS_DUE:' . $remainingDue);
                }

                $chit = Chit::where('id', $installment->chit_id)
                    ->lockForUpdate()
                    ->first();

                $payment = Payment::create([
                    'chit_id'          => $chit->id,
                    'installment_id'   => $installment->id,
                    'customer_id'      => $chit->customer_id,
                    'amount'           => $request->amount,
                    'payment_date'     => $request->payment_date,
                    'payment_mode'     => $request->payment_mode,
                    'reference_number' => $request->reference_number,
                    'collected_by'     => $request->collected_by,
                    'remarks'          => $request->remarks,
                ]);

                $newPaidAmount = round($installment->paid_amount + $request->amount, 2);

                $installment->update([
                    'paid_amount' => $newPaidAmount,
                    'status'      => $newPaidAmount >= $installment->amount ? 'PAID' : 'PARTIAL',
                    'paid_date'   => $newPaidAmount >= $installment->amount ? $request->payment_date : $installment->paid_date,
                ]);

                $allPaid = $chit->installments()->where('status', '!=', 'PAID')->doesntExist();

                if ($allPaid) {
                    $chit->update(['status' => 'COMPLETED']);
                }

                return $payment;
            });

        } catch (\RuntimeException $e) {

            if ($e->getMessage() === 'INSTALLMENT_ALREADY_PAID') {
                return response()->json([
                    'status'  => false,
                    'message' => 'This installment is already fully paid.'
                ], 422);
            }

            if (str_starts_with($e->getMessage(), 'PREVIOUS_INSTALLMENT_PENDING:')) {
                $pendingNum = substr($e->getMessage(), strlen('PREVIOUS_INSTALLMENT_PENDING:'));

                return response()->json([
                    'status'  => false,
                    'message' => "Installment #{$pendingNum} is still pending. Please clear it before paying this installment."
                ], 422);
            }

            if (str_starts_with($e->getMessage(), 'AMOUNT_EXCEEDS_DUE:')) {
                $due = substr($e->getMessage(), strlen('AMOUNT_EXCEEDS_DUE:'));

                return response()->json([
                    'status'  => false,
                    'message' => "Payment amount exceeds the remaining due of {$due}."
                ], 422);
            }

            Log::error('Payment creation failed: ' . $e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while recording the payment.'
            ], 500);

        } catch (\Throwable $e) {

            Log::error('Payment creation failed: ' . $e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while recording the payment.'
            ], 500);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Payment recorded successfully.',
            'data'    => $payment->load(['chit', 'installment', 'customer'])
        ], 201);
    }

    /**
     * Payment Details
     */
    public function show($id)
    {
        $payment = Payment::with(['chit', 'installment', 'customer'])->find($id);

        if (!$payment) {
            return response()->json([
                'status'  => false,
                'message' => 'Payment not found.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $payment
        ]);
    }

    /**
     * Reverse / Delete Payment
     */
    public function destroy($id)
    {
        try {

            $payment = DB::transaction(function () use ($id) {

                $payment = Payment::lockForUpdate()->find($id);

                if (!$payment) {
                    throw new \RuntimeException('PAYMENT_NOT_FOUND');
                }

                $installment = Installment::where('id', $payment->installment_id)
                    ->lockForUpdate()
                    ->first();

                // ===== Sequential integrity check (backward) =====
                // Idhu forward check-oda mirror. Apparam irukura
                // edhavadhu installment-ku already payment (PARTIAL/PAID)
                // irundha, indha installment-oda payment reverse panna
                // vidakoodathu — illana sequence break aagidum
                // (eg. installment 1 pending aagum, aana 2 paid-a irundhudum).
                $laterPaid = Installment::where('chit_id', $installment->chit_id)
                    ->where('installment_number', '>', $installment->installment_number)
                    ->where('status', '!=', 'PENDING')
                    ->orderBy('installment_number')
                    ->lockForUpdate()
                    ->first();

                if ($laterPaid) {
                    throw new \RuntimeException('LATER_INSTALLMENT_HAS_PAYMENT:' . $laterPaid->installment_number);
                }

                $newPaidAmount = round($installment->paid_amount - $payment->amount, 2);
                $newPaidAmount = max($newPaidAmount, 0);

                $installment->update([
                    'paid_amount' => $newPaidAmount,
                    'status'      => $newPaidAmount <= 0 ? 'PENDING' : ($newPaidAmount >= $installment->amount ? 'PAID' : 'PARTIAL'),
                    'paid_date'   => $newPaidAmount >= $installment->amount ? $installment->paid_date : null,
                ]);

                $chit = Chit::where('id', $installment->chit_id)
                    ->lockForUpdate()
                    ->first();

                if ($chit->status === 'COMPLETED') {
                    $chit->update(['status' => 'ACTIVE']);
                }

                $payment->delete();

                return $payment;
            });

        } catch (\RuntimeException $e) {

            if ($e->getMessage() === 'PAYMENT_NOT_FOUND') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Payment not found.'
                ], 404);
            }

            if (str_starts_with($e->getMessage(), 'LATER_INSTALLMENT_HAS_PAYMENT:')) {
                $num = substr($e->getMessage(), strlen('LATER_INSTALLMENT_HAS_PAYMENT:'));

                return response()->json([
                    'status'  => false,
                    'message' => "Cannot reverse this payment because installment #{$num} already has a payment recorded. Reverse the later installment's payment first."
                ], 422);
            }

            Log::error('Payment reversal failed: ' . $e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while reversing the payment.'
            ], 500);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Payment reversed and removed successfully.'
        ]);
    }
}