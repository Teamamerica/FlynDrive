<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Controller;

use App\Repositories\TBCommonRepo;
use App\Repositories\PaymRepo;

class BaseController extends Controller
{
	public $username, $initials;	
	
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {  
    }
	
	public function init($request) {
		$this->username = $request->header('php-auth-user');
		$this->initials = Auth::user()->Initials;
	}
}
