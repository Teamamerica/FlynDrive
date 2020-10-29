<?php

namespace App\Repositories;

use App\AES;
use App\CCtran;
use App\AcctRec;

class PaymRepo {
	
	public function __construct() {
	}	

	public static function cc_encrypt($ccnum) { 
		$ccnum = preg_replace('/\s+/', '', $ccnum);
		$aes = new AES("Tl4YvoqX6Qn116I8G2I35424717n6KrX", "CBC", "w5TsPl3Lg655r872");
		$encrypted_ccnum = $aes->encrypt($ccnum);
		return $encrypted_ccnum;
	}
	
	/**
	 * To decrypt Credit Card Number
	 * @param string $ccnum Credit Card Number
	 * @param boolean $display Displays full number if this parameter set to TRUE and displays last 4 digits if it is set to FALSE
	 * @return string
	 */
	public static function cc_decrypt($ccnum,$display=false) {
		if (env('CC_ENCRYPT')=="YES") {
			//function AES("secret_key","aes-mode","initialization vector"
			$aes = new AES("Tl4YvoqX6Qn116I8G2I35424717n6KrX", "CBC", "w5TsPl3Lg655r872");
			$decrypted_ccnum = $aes->decrypt($ccnum);

			//returns last 4 digits unless display is true, then it displays full number
			if($display) {
				return $decrypted_ccnum;
			}else {
				if (strlen($decrypted_ccnum)>1) {
					return substr($decrypted_ccnum,0,1)."xxx-xxxx-xxxx-".substr($decrypted_ccnum,-4);
				} else return "";
			}
		} else {
			if($display) {
				return $ccnum;
			}else {
				if (strlen($ccnum)>1) {
					return substr($ccnum,0,1)."xxx-xxxx-xxxx-".substr($ccnum,-4);
				} else return "";
			}
		}
	}

	public static function refund($acctRecID, $CCNum, $ccExp, $resNum, $amount) {
		$host = env('MONETRA_HOST');
		$port = env('MONETRA_PORT');
		$username = env('MONETRA_USERNAME');
		$password = env('MONETRA_PASSWORD');
		
		$url="https://$host:$port"; 
		 
		$xml_in="<?xml version=\"1.0\" ?>\n" .
		 "<MonetraTrans>\n" .
		 "\t<Trans identifier=\"1\">\n" .
		 "\t\t<Username>$username</Username>\n" .
		 "\t\t<Password>$password</Password>\n" .
		 "\t\t<Action>RETURN</Action>\n" .
		 "\t\t<Account>$CCNum</Account>\n" .
		 "\t\t<ExpDate>$ccExp</ExpDate>\n" .
		 "\t\t<Amount>".abs($amount)."</Amount>\n" .
		 "\t\t<PTranNum>$resNum</PTranNum>\n" .
		 "\t</Trans>\n" .
		 "</MonetraTrans>\n";
		 
		$ch = curl_init($url);
		 
		curl_setopt($ch, CURLOPT_POST, 1);
		 
		// If using SSL, don't verify stuff as we're
		// not using a real cert
		if (strncasecmp($url, "https://", 8) == 0) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		}
		 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_in);
		 
		//for testing
		/*echo "endpoint: $url\n<br><br>";
		echo "XML_IN: \n";
		echo "$xml_in";
		echo "\n\n";*/
		
		$xml_out=curl_exec($ch);
		curl_close ($ch);
		
		if (!$xml_out) {
			//echo curl_error($ch);
			$presult['status'] = 'failed';
			$presult['message'] = 'System Error.';
			return $presult;
		}		
		
		//for testing
		/*echo "XML_OUT: \n";
		echo "$xml_out";
		echo "\n\n";
		exit;*/
		
		$result = simplexml_load_string($xml_out);

		$result_string="Response: {$result->Resp->verbiage}";
		$ttid = (string) $result->Resp->ttid;

		//Log Transaction
		CCtran::insert([
			'AcctRecID' => $acctRecID,
			'action' => 'Refund',
			'ttid' => $ttid,
			'result' => $result_string,
			'approval' => $result->Resp->auth
		]);

		if ($result->Resp->code=='AUTH' && $result->Resp->phard_code=='SUCCESS' && $result->Resp->verbiage=='APPROVED') {
			AcctRec::where('AcctRecID', $acctRecID)
					->update([
						'AVSverified' => 1,
						'CCttid' => $ttid,
						'status' => 'PNC',
						'CCrefunded' => 1
					]);

			$presult['status'] = 'success';
		}
		else {
			if ($result->Resp->verbiage == 'SERV NOT ALLOWED')
				$result->Resp->verbiage = 'NOT PERMITTED CARD';

			$presult['status'] = 'failed';
			$presult['message'] = (string) $result->Resp->verbiage;
		}

		return $presult;
	}

	//CC Verfiication
	public static function avAuth($acctRecID, $CCNum, $ccExp, $Add1, $ZIP, $CCV, $resNum) {
		//Strip any additional trailing digits and starting characters from the $street_num
		$begin=strcspn($Add1,"0123456789");
		$newstreet=substr($Add1,$begin,strlen($Add1));
		$street_pos=strspn($newstreet,"0123456789");
		$street_num=substr($newstreet,0,$street_pos);
		if (strlen($street_num<1)) $street_num="1";
		//echo $street_num; exit;

		$host = env('MONETRA_HOST');
		$port = env('MONETRA_PORT');
		$username = env('MONETRA_USERNAME');
		$password = env('MONETRA_PASSWORD');
		
		$url="https://$host:$port"; 
		 
		$xml_in="<?xml version=\"1.0\" ?>\n" .
		 "<MonetraTrans>\n" .
		 "\t<Trans identifier=\"1\">\n" .
		 "\t\t<Username>$username</Username>\n" .
		 "\t\t<Password>$password</Password>\n" .
		 "\t\t<Action>AVSONLY</Action>\n" .
		 "\t\t<Account>$CCNum</Account>\n" .
		 "\t\t<ExpDate>$ccExp</ExpDate>\n" .
		 "\t\t<CV>$CCV</CV>\n" .
		 "\t\t<Street>$street_num</Street>\n" .
		 "\t\t<Zip>$ZIP</Zip>\n" .
		 "\t\t<PTranNum>$resNum</PTranNum>\n" .
		 "\t</Trans>\n" .
		 "</MonetraTrans>\n";
		 
		$ch = curl_init($url);
		 
		curl_setopt($ch, CURLOPT_POST, 1);
		 
		// If using SSL, don't verify stuff as we're
		// not using a real cert
		if (strncasecmp($url, "https://", 8) == 0) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		}
		 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_in);
		 
		//for testing
		/*echo "endpoint: $url\n<br><br>";
		echo "XML_IN: \n";
		echo "$xml_in";
		echo "\n\n";*/
		
		$xml_out=curl_exec($ch);
		curl_close ($ch);
		
		if (!$xml_out) {
			//echo curl_error($ch);
			$presult['status'] = 'failed';
			$presult['message'] = 'System Error.';
			
			AcctRec::where('AcctRecID', $acctRecID)
					->update([
						'ProcessingStatus' => 0
					]);

			return $presult;
		}		
		
		//for testing
		/*echo "XML_OUT: \n";
		echo "$xml_out";
		echo "\n\n";*/
		//exit;
		
		$result = simplexml_load_string($xml_out);

		if ($result->Resp->code=='AUTH' && $result->Resp->phard_code=='SUCCESS' && $result->Resp->verbiage=='APPROVED') {
			$result_string="Response: {$result->Resp->verbiage}";
			$return_status = (string) $result->Resp->avs;

			if ($return_status=="GOOD" or $return_status=="STREET") {
				//GOOD means both verify.  Street means street failed but this often happens
				//Street numbers are not always stored perfectly
				AcctRec::where('AcctRecID', $acctRecID)
					->update([
						'AVSverified' => 1
					]);

				$presult['status'] = 'success';
			} else {
				AcctRec::where('AcctRecID', $acctRecID)
					->update([
						'ProcessingStatus' => 0
					]);

				$presult['status'] = 'failed';
				$presult['message'] = 'AVS address cannot be verified';
			}			
		}
		else {
			AcctRec::where('AcctRecID', $acctRecID)
					->update([
						'ProcessingStatus' => 0
					]);

			if ($result->Resp->verbiage == 'SERV NOT ALLOWED')
				$result->Resp->verbiage = 'NOT PERMITTED CARD';

			$presult['status'] = 'failed';
			$presult['message'] = (string) $result->Resp->verbiage;
		}

		//print_r($presult); exit;

		return $presult;
	}

	//Sale
	public static function charge($acctRecID, $CCNum, $ccExp, $Add1, $ZIP, $CCV, $resNum, $Amount) {
		if ($Amount <= 0) {
			$presult['status'] = 'failed';
			$presult['message'] = 'Amount must be larger than 0.';
		}

		$host = env('MONETRA_HOST');
		$port = env('MONETRA_PORT');
		$username = env('MONETRA_USERNAME');
		$password = env('MONETRA_PASSWORD');
		
		$url="https://$host:$port"; 
		 
		$xml_in="<?xml version=\"1.0\" ?>\n" .
		 "<MonetraTrans>\n" .
		 "\t<Trans identifier=\"1\">\n" .
		 "\t\t<Username>$username</Username>\n" .
		 "\t\t<Password>$password</Password>\n" .
		 "\t\t<Action>SALE</Action>\n" .
		 "\t\t<Account>$CCNum</Account>\n" .
		 "\t\t<ExpDate>$ccExp</ExpDate>\n" .		 
		 "\t\t<CV>$CCV</CV>\n" .
		 "\t\t<Amount>$Amount</Amount>\n" .
		 "\t\t<Street>$Add1</Street>\n" .
		 "\t\t<Zip>$ZIP</Zip>\n" .
		 "\t\t<PTranNum>$resNum</PTranNum>\n" .
		 "\t\t<CAVV>NONPARTICIPANT</CAVV>\n" .
		 "\t\t<CardPresent>no</CardPresent>\n" .
		 "\t</Trans>\n" .
		 "</MonetraTrans>\n";
		 
		$ch = curl_init($url);
		 
		curl_setopt($ch, CURLOPT_POST, 1);
		 
		// If using SSL, don't verify stuff as we're
		// not using a real cert
		if (strncasecmp($url, "https://", 8) == 0) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		}
		 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_in);
		 
		//for testing
		/*echo "endpoint: $url\n<br><br>";
		echo "XML_IN: \n";
		echo "$xml_in";
		echo "\n\n";*/
		
		$xml_out=curl_exec($ch);
		curl_close ($ch);
		
		if (!$xml_out) {
			//echo curl_error($ch);
			$presult['status'] = 'failed';
			$presult['message'] = 'System Error.';
			
			AcctRec::where('AcctRecID', $acctRecID)
					->update([
						'ProcessingStatus' => 0
					]);

			return $presult;
		}		
		
		//for testing
		/*echo "XML_OUT: \n";
		echo "$xml_out";
		echo "\n\n";
		exit;*/
		
		$result = simplexml_load_string($xml_out);
		$result_string="Response: {$result->Resp->verbiage} / AVS: {$result->Resp->avs} CV: {$result->Resp->cv}";
		$ttid = (string) $result->Resp->ttid;

		//Log Transaction
		CCtran::insert([
			'AcctRecID' => $acctRecID,
			'action' => 'Charge Card with AVS',
			'ttid' => $ttid,
			'result' => $result_string,
			'approval' => $result->Resp->auth
		]);

		if ($result->Resp->code=='AUTH' && $result->Resp->phard_code=='SUCCESS' && $result->Resp->verbiage=='APPROVED') {
			AcctRec::where('AcctRecID', $acctRecID)
					->update([
						'CCV' => '',
						'AVSverified' => 1,
						'CCttid' => $ttid,
						'status' => 'PNC',
						'ProcessingStatus' => '2'
					]);

			$presult['status'] = 'success';		
		}
		else {
			AcctRec::where('AcctRecID', $acctRecID)
					->update([
						'ProcessingStatus' => 0
					]);

			if ($result->Resp->verbiage == 'SERV NOT ALLOWED')
				$result->Resp->verbiage = 'NOT PERMITTED CARD';

			$presult['status'] = 'failed';
			$presult['message'] = (string) $result->Resp->verbiage;
		}

		//print_r($presult); exit;

		return $presult;
	}

	public static function reversal($acctRecID, $ttid) {
		$host = env('MONETRA_HOST');
		$port = env('MONETRA_PORT');
		$username = env('MONETRA_USERNAME');
		$password = env('MONETRA_PASSWORD');
		
		$url="https://$host:$port"; 
		 
		$xml_in="<?xml version=\"1.0\" ?>\n" .
		 "<MonetraTrans>\n" .
		 "\t<Trans identifier=\"1\">\n" .
		 "\t\t<Username>$username</Username>\n" .
		 "\t\t<Password>$password</Password>\n" .
		 "\t\t<Action>REVERSAL</Action>\n" .
		 "\t\t<Ttid>$ttid</Ttid>\n" .
		 "\t</Trans>\n" .
		 "</MonetraTrans>\n";
		 
		$ch = curl_init($url);
		 
		curl_setopt($ch, CURLOPT_POST, 1);
		 
		// If using SSL, don't verify stuff as we're
		// not using a real cert
		if (strncasecmp($url, "https://", 8) == 0) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		}
		 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_in);
		 
		//for testing
		/*echo "endpoint: $url\n<br><br>";
		echo "XML_IN: \n";
		echo "$xml_in";
		echo "\n\n";*/
		
		$xml_out=curl_exec($ch);
		curl_close ($ch);
		
		if (!$xml_out) {
			//echo curl_error($ch);
			$presult['status'] = 'failed';
			$presult['message'] = 'System Error.';

			return $presult;
		}		
		
		//for testing
		/*echo "XML_OUT: \n";
		echo "$xml_out";
		echo "\n\n";
		exit;*/
		
		$result = simplexml_load_string($xml_out);
		$result_string="Response: {$result->Resp->verbiage}";
		$new_ttid = (string) $result->Resp->ttid;		

		if ($result->Resp->code=='AUTH' && $result->Resp->phard_code=='SUCCESS' && $result->Resp->verbiage=='APPROVED') {
			AcctRec::where('AcctRecID', $acctRecID)
					->update([
						'CCV' => '',
						'AVSverified' => 0,
						'CCttid' => $ttid,
						'status' => 'PNO',
						'ProcessingStatus' => '0'
					]);

			$logMsg = 'Same Day Reversal of Charge';
			$presult['status'] = 'success';		
		}
		else {
			if ($result->Resp->verbiage == 'SERV NOT ALLOWED')
				$result->Resp->verbiage = 'NOT PERMITTED CARD';

			$logMsg = 'Same Day Reversal Failed';
			$presult['status'] = 'failed';
			$presult['message'] = (string) $result->Resp->verbiage;
		}

		//Log Transaction
		CCtran::insert([
			'AcctRecID' => $acctRecID,
			'action' => $logMsg,
			'ttid' => $new_ttid,
			'result' => $result_string,
			'approval' => $result->Resp->auth
		]);

		//print_r($presult); exit;

		return $presult;
	}

	public static function void($acctRecID, $ttid) {
		$host = env('MONETRA_HOST');
		$port = env('MONETRA_PORT');
		$username = env('MONETRA_USERNAME');
		$password = env('MONETRA_PASSWORD');
		
		$url="https://$host:$port"; 
		 
		$xml_in="<?xml version=\"1.0\" ?>\n" .
		 "<MonetraTrans>\n" .
		 "\t<Trans identifier=\"1\">\n" .
		 "\t\t<Username>$username</Username>\n" .
		 "\t\t<Password>$password</Password>\n" .
		 "\t\t<Action>VOID</Action>\n" .
		 "\t\t<Ttid>$ttid</Ttid>\n" .
		 "\t</Trans>\n" .
		 "</MonetraTrans>\n";
		 
		$ch = curl_init($url);
		 
		curl_setopt($ch, CURLOPT_POST, 1);
		 
		// If using SSL, don't verify stuff as we're
		// not using a real cert
		if (strncasecmp($url, "https://", 8) == 0) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		}
		 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_in);
		 
		//for testing
		/*echo "endpoint: $url\n<br><br>";
		echo "XML_IN: \n";
		echo "$xml_in";
		echo "\n\n";*/
		
		$xml_out=curl_exec($ch);
		curl_close ($ch);
		
		if (!$xml_out) {
			//echo curl_error($ch);
			$presult['status'] = 'failed';
			$presult['message'] = 'System Error.';

			return $presult;
		}		
		
		//for testing
		/*echo "XML_OUT: \n";
		echo "$xml_out";
		echo "\n\n";
		exit;*/
		
		$result = simplexml_load_string($xml_out);
		$result_string="Response: {$result->Resp->verbiage}";
		$new_ttid = (string) $result->Resp->ttid;		

		//Log Transaction
		CCtran::insert([
			'AcctRecID' => $acctRecID,
			'action' => "Void Transaction $ttid",
			'ttid' => $new_ttid,
			'result' => $result_string,
			'approval' => $result->Resp->auth
		]);

		if ($result->Resp->code=='AUTH' && ($result->Resp->phard_code=='SUCCESS' && $result->Resp->verbiage=='APPROVED' || $result->Resp->verbiage=='SUCCESS')) {
			AcctRec::where('AcctRecID', $acctRecID)
					->update([
						'CCV' => '',
						'AVSverified' => 0,
						'CCttid' => $new_ttid,
						'status' => 'PNO',
						'ProcessingStatus' => '0'
					]);

			$presult['status'] = 'success';		
		}
		else {
			if ($result->Resp->verbiage == 'SERV NOT ALLOWED')
				$result->Resp->verbiage = 'NOT PERMITTED CARD';

			$presult['status'] = 'failed';
			$presult['message'] = (string) $result->Resp->verbiage;
		}		

		//print_r($presult); exit;

		return $presult;
	}

	//Similar as Sale (function charge)
	public static function swipe($acctRecID, $TrackData, $resNum, $Amount) {
		if ($Amount <= 0) {
			$presult['status'] = 'failed';
			$presult['message'] = 'Amount must be larger than 0.';
		}

		$host = env('MONETRA_HOST');
		$port = env('MONETRA_PORT');
		$username = env('MONETRA_USERNAME');
		$password = env('MONETRA_PASSWORD');
		
		$url="https://$host:$port"; 
		 
		$xml_in="<?xml version=\"1.0\" ?>\n" .
		 "<MonetraTrans>\n" .
		 "\t<Trans identifier=\"1\">\n" .
		 "\t\t<Username>$username</Username>\n" .
		 "\t\t<Password>$password</Password>\n" .
		 "\t\t<Action>SALE</Action>\n" .
		 "\t\t<TrackData>$TrackData</TrackData>\n" .
		 "\t\t<Amount>$Amount</Amount>\n" .
		 "\t\t<PTranNum>$resNum</PTranNum>\n" .
		 "\t</Trans>\n" .
		 "</MonetraTrans>\n";
		 
		$ch = curl_init($url);
		 
		curl_setopt($ch, CURLOPT_POST, 1);
		 
		// If using SSL, don't verify stuff as we're
		// not using a real cert
		if (strncasecmp($url, "https://", 8) == 0) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		}
		 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_in);
		 
		//for testing
		/*echo "endpoint: $url\n<br><br>";
		echo "XML_IN: \n";
		echo "$xml_in";
		echo "\n\n";*/
		
		$xml_out=curl_exec($ch);
		curl_close ($ch);
		
		if (!$xml_out) {
			//echo curl_error($ch);
			$presult['status'] = 'failed';
			$presult['message'] = 'System Error.';
			return $presult;
		}		
		
		//for testing
		/*echo "XML_OUT: \n";
		echo "$xml_out";
		echo "\n\n";
		exit;*/
		
		$result = simplexml_load_string($xml_out);
		$result_string="Response: {$result->Resp->verbiage} / AVS: {$result->Resp->avs} CV: {$result->Resp->cv}";
		$ttid = (string) $result->Resp->ttid;

		//Log Transaction
		CCtran::insert([
			'AcctRecID' => $acctRecID,
			'action' => 'Charge Card with AVS',
			'ttid' => $ttid,
			'result' => $result_string,
			'approval' => $result->Resp->auth
		]);

		if ($result->Resp->code=='AUTH' && $result->Resp->phard_code=='SUCCESS' && $result->Resp->verbiage=='APPROVED') {
			AcctRec::where('AcctRecID', $acctRecID)
					->update([
						'CCttid' => $ttid,
						'status' => 'PNC'
					]);

			$presult['status'] = 'success';		
		}
		else {
			if ($result->Resp->verbiage == 'SERV NOT ALLOWED')
				$result->Resp->verbiage = 'NOT PERMITTED CARD';

			$presult['status'] = 'failed';
			$presult['message'] = (string) $result->Resp->verbiage;
		}

		//print_r($presult); exit;

		return $presult;
	}
	
	//Get Payments Reports
	public static function report($type='', $bdate='', $edate='', $resnum='', $ccnum='') {  //$bdate e.g. 2019-11-01
		if (!$type) {
			$presult['status'] = 'failed';
			$presult['message'] = 'Please select a report type.';
			return $presult;
		}
		
		if ($type == 'batch')
			$ptype = 'BT';
		elseif ($type == 'unsettled')
			$ptype = 'GUT';
		elseif ($type == 'settled')
			$ptype = 'GL';
		elseif ($type == 'failed')
			$ptype = 'GFT';
		else {
			$presult['status'] = 'failed';
			$presult['message'] = 'Invalid report type.';
			return $presult;
		}		
	
		$host = env('MONETRA_HOST');
		$port = env('MONETRA_PORT');
		$username = env('MONETRA_USERNAME');
		$password = env('MONETRA_PASSWORD');
		
		$url="https://$host:$port"; 
		 
		$xml_in="<?xml version=\"1.0\" ?>\n" .
		 "<MonetraTrans>\n" .
		 "\t<Trans identifier=\"1\">\n" .
		 "\t\t<Username>$username</Username>\n" .
		 "\t\t<Password>$password</Password>\n" .
		 "\t\t<Action>ADMIN</Action>\n" .
		 "\t\t<Admin>$ptype</Admin>\n" .
		 ($bdate ? "\t\t<BDate>$bdate</BDate>\n" : '') .
		 ($edate ? "\t\t<EDate>$edate</EDate>\n" : '') .
		 ($resnum ? "\t\t<PTranNum>$resnum</PTranNum>\n" : '') .
		 ($ccnum ? "\t\t<Acct>$ccnum</Acct>\n" : '') .
		 "\t</Trans>\n" .
		 "</MonetraTrans>\n";
		 
		$ch = curl_init($url);
		 
		curl_setopt($ch, CURLOPT_POST, 1);
		 
		// If using SSL, don't verify stuff as we're
		// not using a real cert
		if (strncasecmp($url, "https://", 8) == 0) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		}
		 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_in);
		 
		//for testing
		/*echo "endpoint: $url\n<br><br>";
		echo "XML_IN: \n";
		echo "$xml_in";
		echo "\n\n";*/
		
		$xml_out=curl_exec($ch);
		curl_close ($ch);
		
		if (!$xml_out) {
			//echo curl_error($ch);
			$presult['status'] = 'failed';
			$presult['message'] = 'System Error.';
			return $presult;
		}		
		
		//for testing
		/*echo "XML_OUT: \n";
		echo "$xml_out";
		echo "\n\n";
		exit;*/
		
		$result = simplexml_load_string($xml_out);

		if ($result->Resp->code=='SUCCESS') {
			$presult['status'] = 'success';		
			
			$data = array();
			$data_arr = explode("\n", $result->Resp->DataBlock);
			if (count($data_arr) > 1) {
				$titles = explode(',', $data_arr[0]);
				//print_r($titles); exit;
				unset($data_arr[0]);
				foreach ($data_arr as $i => $d) {
					if (!$d) 
						continue;
					$d_arr = explode(',', $d);
					foreach ($titles as $j => $t) {
						$data[$i-1][$t] = isset($d_arr[$j]) ? $d_arr[$j] : '';
					}
				}
			}
			//print_r($data); exit;
			$presult['result'] = $data;
		}
		else {
			$presult['status'] = 'failed';
			$presult['message'] = (string) $result->Resp->verbiage;
		}

		//print_r($presult); exit;

		return $presult;
	}
	
	//Force Batch Settle (Settlement occurs automatically each night.  Do you want to force a settlement now?)
	public static function forceSettle($batchno) {  	
		$host = env('MONETRA_HOST');
		$port = env('MONETRA_PORT');
		$username = env('MONETRA_USERNAME');
		$password = env('MONETRA_PASSWORD');
		
		$url="https://$host:$port"; 
		 
		$xml_in="<?xml version=\"1.0\" ?>\n" .
		 "<MonetraTrans>\n" .
		 "\t<Trans identifier=\"1\">\n" .
		 "\t\t<Username>$username</Username>\n" .
		 "\t\t<Password>$password</Password>\n" .
		 "\t\t<Action>ADMIN</Action>\n" .
		 "\t\t<Admin>forcesettle</Admin>\n" .
		 "\t\t<Batch>$batchno</Batch>\n" .
		 "\t</Trans>\n" .
		 "</MonetraTrans>\n";
		 
		$ch = curl_init($url);
		 
		curl_setopt($ch, CURLOPT_POST, 1);
		 
		// If using SSL, don't verify stuff as we're
		// not using a real cert
		if (strncasecmp($url, "https://", 8) == 0) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		}
		 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_in);
		 
		//for testing
		/*echo "endpoint: $url\n<br><br>";
		echo "XML_IN: \n";
		echo "$xml_in";
		echo "\n\n";*/
		
		$xml_out=curl_exec($ch);
		curl_close ($ch);
		
		if (!$xml_out) {
			//echo curl_error($ch);
			$presult['status'] = 'failed';
			$presult['message'] = 'System Error.';
			return $presult;
		}		
		
		//for testing
		/*echo "XML_OUT: \n";
		echo "$xml_out";
		echo "\n\n";
		exit;*/
		
		$result = simplexml_load_string($xml_out);

		if ($result->Resp->code=='AUTH' && $result->Resp->verbiage=='Batch Force Succeeded') {
			$presult['status'] = 'success';		
		}
		else {
			$presult['status'] = 'failed';
			$presult['message'] = (string) $result->Resp->verbiage;
		}

		//print_r($presult); exit;

		return $presult;
	}
}