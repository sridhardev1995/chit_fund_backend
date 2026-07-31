<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavingScheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SavingSchemeController extends Controller
{
    /**
     * Saving Scheme List
     */
    public function index(Request $request)
    {
        $search = $request->search;

        $schemes = SavingScheme::when($search, function ($query) use ($search) {
                $query->where('scheme_code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
            })
            ->latest()
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Saving scheme list fetched successfully.',
            'data'    => $schemes,
        ]);
    }

    /**
     * Create Saving Scheme
     * e.g. { "name": "100 Weekly Plan", "weekly_amount": 100, "total_weeks": 52, "maturity_amount": 6000, "status": true }
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:150',
            'weekly_amount'   => 'required|numeric|min:1',
            'total_weeks'     => 'required|integer|min:1',
            'maturity_amount' => 'required|numeric|min:1',
            'status'          => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Maturity amount should never be less than what customer actually pays in
        $totalCollection = round($request->weekly_amount * $request->total_weeks, 2);

        if ($request->maturity_amount < $totalCollection) {
            return response()->json([
                'status'  => false,
                'message' => 'Maturity amount cannot be less than total collection (weekly_amount x total_weeks).',
            ], 422);
        }

        $scheme = DB::transaction(function () use ($request) {

            // Lock last row to avoid duplicate scheme_code under concurrent requests
            $lastScheme = SavingScheme::latest('id')->lockForUpdate()->first();

            if ($lastScheme) {
                $number     = (int) substr($lastScheme->scheme_code, 2);
                $schemeCode = 'SP' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
            } else {
                $schemeCode = 'SP00001';
            }

            return SavingScheme::create([
                'scheme_code'     => $schemeCode,
                'name'            => $request->name,
                'weekly_amount'   => $request->weekly_amount,
                'total_weeks'     => $request->total_weeks,
                'maturity_amount' => $request->maturity_amount,
                'status'          => $request->status,
            ]);
        });

        return response()->json([
            'status'  => true,
            'message' => 'Saving scheme created successfully.',
            'data'    => $scheme,
        ], 201);
    }

    /**
     * Saving Scheme Details
     */
    public function show($id)
    {
        $scheme = SavingScheme::find($id);

        if (!$scheme) {
            return response()->json([
                'status'  => false,
                'message' => 'Saving scheme not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $scheme,
        ]);
    }

    /**
     * Update Saving Scheme
     * Only name/status are editable — weekly_amount/total_weeks/maturity_amount
     * are frozen on any saving_chits already sold against this scheme, so
     * changing them here would not retroactively affect existing chits and
     * would be misleading. Create a new scheme instead if terms change.
     */
    public function update(Request $request, $id)
    {
        $scheme = SavingScheme::find($id);

        if (!$scheme) {
            return response()->json([
                'status'  => false,
                'message' => 'Saving scheme not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'   => 'required|string|max:150',
            'status' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $scheme->update([
            'name'   => $request->name,
            'status' => $request->status,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Saving scheme updated successfully.',
            'data'    => $scheme,
        ]);
    }

    /**
     * Delete Saving Scheme
     */
    public function destroy($id)
    {
        $scheme = SavingScheme::find($id);

        if (!$scheme) {
            return response()->json([
                'status'  => false,
                'message' => 'Saving scheme not found.',
            ], 404);
        }

        if ($scheme->savingChits()->exists()) {
            return response()->json([
                'status'  => false,
                'message' => 'Cannot delete a scheme that already has saving chits linked to it.',
            ], 400);
        }

        $scheme->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Saving scheme deleted successfully.',
        ]);
    }
}