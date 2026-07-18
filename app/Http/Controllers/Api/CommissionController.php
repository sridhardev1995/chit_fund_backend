<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionSetting;
use Illuminate\Http\Request;


class CommissionController extends Controller
{

    // List
    public function index()
    {

        $data = CommissionSetting::latest()->get();


        return response()->json([

            'status'=>true,

            'message'=>'Commission settings fetched',

            'data'=>$data

        ]);

    }


    // Create
    public function store(Request $request)
    {

        $request->validate([

            'commission_rate'=>'required|numeric|min:0|max:100'

        ]);


        // Disable old commission

        CommissionSetting::where('status',true)
        ->update([
            'status'=>false
        ]);


        $commission = CommissionSetting::create([

            'commission_rate'=>$request->commission_rate,

            'status'=>true

        ]);


        return response()->json([

            'status'=>true,

            'message'=>'Commission rate updated',

            'data'=>$commission

        ]);

    }


    // View
    public function show($id)
    {

        return CommissionSetting::findOrFail($id);

    }


    // Update
    public function update(Request $request,$id)
    {

        $commission = CommissionSetting::findOrFail($id);


        $commission->update([

            'commission_rate'=>$request->commission_rate

        ]);


        return response()->json([

            'status'=>true,

            'message'=>'Commission updated',

            'data'=>$commission

        ]);

    }



    // Delete
    public function destroy($id)
    {

        CommissionSetting::findOrFail($id)->delete();


        return response()->json([

            'status'=>true,

            'message'=>'Commission deleted'

        ]);

    }

}