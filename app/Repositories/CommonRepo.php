<?php

namespace App\Repositories;

class CommonRepo {	
	public function __construct() {
	}
	
	public static function objToArr($obj) {
		return collect($obj)->map(function($x){ return (array) $x; })->toArray();
	}	
}