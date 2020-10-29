<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TBHistoryAPIRepo {
	
	public function __construct() {
	}
	
	/**
	 * call micro service api via curl
	 * Author: Tania Akter
	 * Date: Nov 12th, 2018
	 */

	function msApiCall($service_url, $curl_post_data = '', $username = 'tania', $method = 'GET') {
		
		$result = DB::table('tblUser')
					->select('UserName', 'HashPW', 'Password')
					->where('UserName', $username)
					->get()->first();
		if(!$result) {
			return false;
		}
		
		$cPost = FALSE;
		if(!empty($curl_post_data)) {
			$cPost = TRUE;
			$curl_post_data = json_encode($curl_post_data);
		}
		
		$authres = $result;
		$headers = array(
						'Token: '.$authres->HashPW,
						'Content-Type: application/json',
						'Content-Length: ' . strlen($curl_post_data),
						'php-auth-user: '.$authres->UserName,
						'php-auth-pw: '.$authres->Password
					);
		
		$curlOptions = array(
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_POST => $cPost,
			CURLOPT_POSTFIELDS => $curl_post_data,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_SSL_VERIFYPEER => FALSE,
			//CURLOPT_SSL_VERIFYHOST => 2,
			//CURLOPT_CAINFO => MS_HISTORY_API_CERT
		);

		$handle = curl_init($service_url);
		curl_setopt_array($handle, $curlOptions);
		$output = curl_exec($handle);
		if($output <> "true") {
			$info = curl_getinfo($handle);
			$decoded = json_decode($output);
			if (isset($decoded->response->status) && $decoded->response->status == 'ERROR') {
				echo $decoded->response->errormessage;
			}
		}
		
		curl_close($handle);
		
		//print_r($output); exit;
		
		return $output;
	}
	
}