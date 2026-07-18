<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    /**
     * Customer List
     */
   public function index(Request $request)
{
    $search = $request->search;


    $customers = Customer::when($search, function ($query) use ($search) {

            $query->where('customer_code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('phone_number', 'like', "%{$search}%");

        })
        ->latest()
        ->get();


    return response()->json([

        'status' => true,

        'message' => 'Customer list fetched successfully.',

        'data' => $customers

    ]);
}

    /**
     * Create Customer
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'name' => 'required|string|max:150',

            'phone_number' => 'required|digits:10|unique:customers',

            'address' => 'required',

            'aadhaar_number' => 'required|digits:12|unique:customers',

            'pan_number' => 'nullable|max:10',

            'bank_name' => 'nullable',

            'account_number' => 'nullable',

            'ifsc' => 'nullable|max:20',

            'upi_id' => 'nullable',

            'reference_name' => 'nullable',

            'reference_number' => 'nullable|digits:10',

            'nominee_name' => 'nullable',

            'nominee_number' => 'nullable|digits:10',

            'remarks' => 'nullable',

            'status' => 'required|boolean',

            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'

        ]);

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ],422);
        }

        // Generate Customer Code
        $lastCustomer = Customer::latest('id')->first();

        if($lastCustomer){

            $number = (int) substr($lastCustomer->customer_code,2);

            $customerCode = 'MC'.str_pad($number+1,5,'0',STR_PAD_LEFT);

        }else{

            $customerCode='MC00001';
        }

        $photo = null;

        if($request->hasFile('photo')){

            $photo = $request->file('photo')->store('customers','public');
        }

        $customer = Customer::create([

            'customer_code'=>$customerCode,

            'name'=>$request->name,

            'phone_number'=>$request->phone_number,

            'address'=>$request->address,

            'aadhaar_number'=>$request->aadhaar_number,

            'pan_number'=>$request->pan_number,

            'bank_name'=>$request->bank_name,

            'account_number'=>$request->account_number,

            'ifsc'=>$request->ifsc,

            'upi_id'=>$request->upi_id,

            'reference_name'=>$request->reference_name,

            'reference_number'=>$request->reference_number,

            'nominee_name'=>$request->nominee_name,

            'nominee_number'=>$request->nominee_number,

            'remarks'=>$request->remarks,

            'status'=>$request->status,

            'photo'=>$photo

        ]);

        return response()->json([
            'status'=>true,
            'message'=>'Customer created successfully.',
            'data'=>$customer
        ],201);

    }

    /**
     * Customer Details
     */
    public function show($id)
    {
        $customer = Customer::find($id);

        if(!$customer){

            return response()->json([
                'status'=>false,
                'message'=>'Customer not found.'
            ],404);
        }

        return response()->json([
            'status'=>true,
            'data'=>$customer
        ]);
    }

    /**
     * Update Customer
     */
    public function update(Request $request, $id)
    {
        $customer = Customer::find($id);

        if(!$customer){

            return response()->json([
                'status'=>false,
                'message'=>'Customer not found.'
            ],404);
        }

        $validator = Validator::make($request->all(), [

            'name'=>'required|string|max:150',

            'phone_number'=>'required|digits:10|unique:customers,phone_number,'.$id,

            'address'=>'required',

            'aadhaar_number'=>'required|digits:12|unique:customers,aadhaar_number,'.$id,

            'pan_number'=>'nullable|max:10',

            'bank_name'=>'nullable',

            'account_number'=>'nullable',

            'ifsc'=>'nullable|max:20',

            'upi_id'=>'nullable',

            'reference_name'=>'nullable',

            'reference_number'=>'nullable|digits:10',

            'nominee_name'=>'nullable',

            'nominee_number'=>'nullable|digits:10',

            'remarks'=>'nullable',

            'status'=>'required|boolean',

            'photo'=>'nullable|image|mimes:jpg,jpeg,png|max:2048'

        ]);

        if($validator->fails()){

            return response()->json([
                'status'=>false,
                'errors'=>$validator->errors()
            ],422);
        }

        if($request->hasFile('photo')){

            if($customer->photo && Storage::disk('public')->exists($customer->photo)){

                Storage::disk('public')->delete($customer->photo);
            }

            $customer->photo = $request->file('photo')->store('customers','public');
        }

        $customer->update([
            'name'=>$request->name,
            'phone_number'=>$request->phone_number,
            'address'=>$request->address,
            'aadhaar_number'=>$request->aadhaar_number,
            'pan_number'=>$request->pan_number,
            'bank_name'=>$request->bank_name,
            'account_number'=>$request->account_number,
            'ifsc'=>$request->ifsc,
            'upi_id'=>$request->upi_id,
            'reference_name'=>$request->reference_name,
            'reference_number'=>$request->reference_number,
            'nominee_name'=>$request->nominee_name,
            'nominee_number'=>$request->nominee_number,
            'remarks'=>$request->remarks,
            'status'=>$request->status,
            'photo'=>$customer->photo
        ]);

        return response()->json([
            'status'=>true,
            'message'=>'Customer updated successfully.',
            'data'=>$customer
        ]);

    }

    /**
     * Delete Customer
     */
    public function destroy($id)
    {
        $customer = Customer::find($id);

        if(!$customer){

            return response()->json([
                'status'=>false,
                'message'=>'Customer not found.'
            ],404);
        }

        if($customer->photo && Storage::disk('public')->exists($customer->photo)){

            Storage::disk('public')->delete($customer->photo);
        }

        $customer->delete();

        return response()->json([
            'status'=>true,
            'message'=>'Customer deleted successfully.'
        ]);
    }
}