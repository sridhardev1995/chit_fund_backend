<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavingChitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'      => 'required|exists:customers,id',
            'saving_scheme_id' => 'required|exists:saving_schemes,id',
            'start_date'       => 'required|date',

            // how many chits of this same scheme to create for this
            // customer in one shot, e.g. 50 chits of the ₹100 plan
            'quantity'         => 'nullable|integer|min:1|max:500',
        ];
    }
}