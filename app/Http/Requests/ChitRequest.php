<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChitRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {

        return [

            'customer_id'=>
            [
                'required',
                'exists:customers,id'
            ],


            'chit_amount'=>
            [
                'required',
                'numeric',
                'min:1'
            ],


            'total_weeks'=>
            [
                'required',
                'integer',
                'min:1'
            ],


            'start_date'=>
            [
                'nullable',
                'date'
            ]

        ];

    }

}