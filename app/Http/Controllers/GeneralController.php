<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\BaseController;

class GeneralController extends BaseController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */	
	public function __construct(Request $request) {  
		//$this->init($request);
    }
	
	public function login(Request $request) {
		//validate fields
		$rules = [
			'ResNum' => array('required', 'numeric'),
			'FirstName' => array('required'),
			'LastName' => array('required'),
			'DeviceID' => array('required')
		];
				
		$messages = [
		];
		
		$validator = Validator::make($request->all(), $rules, $messages);
		
		if ($validator->fails()) {
			//print_r($validator->errors()->all()); exit;
			
			$result['status'] = 'failed';
			$result['message'] = $validator->errors()->first();
			
			return response()->json($result);	
		}

		$user = DB::table('tblFnDUsers')
                        ->where([['FirstName', $request->FirstName], ['LastName', $request->LastName], ['ResNum', $request->ResNum]])
                        ->get()->first();

                if ($user) {
			DB::table('tblFnDUsers')
				->where([['FirstName', $request->FirstName], ['LastName', $request->LastName], ['ResNum', $request->ResNum]])
				->update(['DeviceID' => $request->DeviceID]);
                        $status['status'] = 'success';
		}
                else
                        $status['status'] = 'failed';
		
		return response()->json($status);	
	}
}

