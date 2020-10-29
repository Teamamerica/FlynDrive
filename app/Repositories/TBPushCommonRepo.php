<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TBPushCommonRepo {
	
	public function __construct() {
	}
	
	public function addNewPushEvent($agencyCode, $event, $prodCode,$startDate,$endDate) {
		if (!$prodCode) return false;
			
		$result = DB::table('tblTAProductMap')
					->where([['TAProductCode', $prodCode], ['Active', 1]])
					->get();
		if (count($result) == 0) return false;
		
		date_default_timezone_set('America/New_York');
		$t = date("Y-m-d H:i:s");
		
		if ($agencyCode == "ALL")
		{
			$agencies = $this->getPushAgencies();
			foreach($agencies as $agency)
			{
				if($event != 'stopsale'){
					//check first if event already exists with status new					
					$result = DB::table('tblPushEvent')
									->where([['agencyCode', $agency->AgCode], ['event', $event], ['status', 'new'], ['prodCode', $prodCode], ['startDate', $startDate], ['endDate', $endDate]])
									->get();
									
					if (count($result) > 0)
						continue;
				}
				
				//skip if product not mapped for the agency				
				$rss = DB::table('tblTAProductMap')
							->select('TAProductCode')
							->where([['PushCompany', $agency->AgCode], ['TAProductCode', $prodCode], ['Active', 1]])
							->get();
				if (count($rss) > 0) 
					continue;
				
				DB::table('tblPushEvent')->insert([
					'agencyCode' => $agency->AgCode,
					'event' => $event,
					'prodCode' => $prodCode,
					'startDate' => $startDate,
					'endDate' => $endDate,
					'status' => 'new',
					'addedDateTime' => $t
				]);
			}
		}
		else
		{
			if (!count(getPushAgencyHotels($agencyCode))) return false;

				if($event != 'stopsale'){
				//check first if event already exists with status new
					$sql = "select * from tblPushEvent where agencyCode='$agencyCode' and event = '$event' and status='new'
							and prodCode='$prodCode' and startDate='$startDate' and endDate='$endDate'";
					$result = sql_cover($sql, "select", 0, 0, 0, 0);
					if (mysql_num_rows($result) > 0) return true;
				}//skip stop sale

			//skip if product not mapped for the agency
			$select = "select TAProductCode from tblTAProductMap where PushCompany='{$agencyCode}' and TAProductCode='{$prodCode}' and Active = 1";
			$rss = sql_cover($select, "select", 0,0,0,0);
			$go = TRUE;
			if (mysql_num_rows($rss) == 0) $go = FALSE;			
			if ($go) {
				$sql = "insert into tblPushEvent (agencyCode, event, prodCode, startDate, endDate, status, addedDateTime) 
					values ('$agencyCode', '$event', '$prodCode', '$startDate', '$endDate', 'new', '$t')";
				
				sql_cover($sql,"select",0,0,0,0);
			}
			
		}
		
		//if ($event == 'updateprice') @file_put_contents('/var/log/teamlogs/push-common-'.date('Y-m-d'),"\n update price for $prodCode at $t from: ".print_r(debug_backtrace(),1), FILE_APPEND);
		
		if ($event == 'stopsale') {
			$sql = "select id from tblPushEvent where event='stopsale' and prodCode='$prodCode' and startDate='$startDate' and endDate='$endDate' and status='new'";
			$eventsObj = sql_cover($sql, "select",0,0,0,0);
			$outputfile = "/var/log/push/force_run.log"; 
			$pidfile = "/var/log/push/force_pid.log";
			while ($event = mysql_fetch_object($eventsObj)){
				$my_event_id = $event->id;	// (int)  [] <-- array IS needed
				$cmd = "/usr/bin/php -f ".PATH."/push/PushEngine.php allowedjobs $my_event_id";
				exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
				//include( 'PushEngine.php' );
			}
		}	//*/
		//var_dump($my_event_id);
		//echo ("\n<SCRIPT LANGUAGE='JavaScript'> Hey </SCRIPT>\n");
		//exec(sprintf("%s >> %s 2>&1", $cmd, $outputfile));
		//var_dump(sprintf("%s >> %s 2>&1", $cmd, $outputfile));
		//exit;
	}
	
	public function getPushAgencies() {
		$agencies = array();
		$row = DB::table('tblAgency')
				->where('IsPushEnabled', 1)
				->get()->first();
		if ($row)
		{
			$agencies[] = $row;
		}
		return $agencies;
	}
	
}