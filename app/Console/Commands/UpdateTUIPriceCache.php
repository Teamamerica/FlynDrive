<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use App\Repositories\TBCommonRepo;
use App\Repositories\TBHistoryAPIRepo;
use App\Repositories\DMGrpFuncNewRepo;
use App\Repositories\TBFuncAvgCostRepo;

class UpdateTUIPriceCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UpdateTUIPriceCache {prodcode} {username}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update TUI Cached Pricing';
	
	public $commonRepo, $historyAPIRepo;
	
	public function __construct() {
        parent::__construct();		
		ini_set('memory_limit', '512M');
		
		$this->commonRepo = new TBCommonRepo();
		$this->historyAPIRepo = new TBHistoryAPIRepo();
    }
	
	public function handle() {
		$incoming_code = $this->argument('prodcode');
		$username = $this->argument('username');
		
		if(strlen($incoming_code)>0) {
			$table_name="tblPriceCacheForTUI2";
			$table_product="tblTUIProduct";
			$agcode = "TUIGEXML";
			
			$startDate = date("Y-m-d", strtotime("+1 day", strtotime(date("Y-m-d"))));
			//$startDate = date("Y-m-d", strtotime("+2 months", strtotime(date("Y-m-d")))); //for TEST purpose; comment this line out after testing
			$ProdCode="";
			$Description="";
			$AllotCode="";
			$IsProdComm="";
			$WebPriority="";
			$PackageFlag="";
			$MaxOcc=4;
			$VendorID="";
			$CityName="";
			$display_group_name="";
			$display_category="";
			$nights=1;
			$numpax=1;
			$numrooms=1;
			$DisplayClosedOut="Y";
			$DisplayOnRequest="Y";
			
			//query for only codes that needs to be updated with prices
			if(isset($incoming_code)) {				
				$rows = DB::table('tblProduct as p')
							->select(DB::raw('p.ProdCode, p.Vendor, p.AllotCode, p.IsProdCom, p.MarkTUISpain, p.MarkTUIGermany, p.MarkTUIUK, p.ResortFeeType'))
							->join("$table_product as t", 't.ProdCode', 'p.ProdCode')
							->where([['p.NonRefundable', 0], ['t.Active', 1], ['t.ProdCode', $incoming_code]])
							->limit(1)
							->get();
			} else {
				$sql = "select p.ProdCode, p.Vendor, p.AllotCode, p.IsProdCom, p.MarkTUISpain, p.MarkTUIGermany, p.MarkTUIUK, p.ResortFeeType from tblProduct as p, $table_product as t where p.NonRefundable=0 and p.ProdCode=t.ProdCode and t.Active=1 and t.NeedsPriceUpdate=1 and (t.LastPriceUpdate IS NULL or TIMESTAMPDIFF(MINUTE, LastPriceUpdate, NOW())>5) order by t.ProdCode";
				$rows = DB::table('tblProduct as p')
							->select(DB::raw('p.ProdCode, p.Vendor, p.AllotCode, p.IsProdCom, p.MarkTUISpain, p.MarkTUIGermany, p.MarkTUIUK, p.ResortFeeType'))
							->join("$table_product as t", 't.ProdCode', 'p.ProdCode')
							->where([['p.NonRefundable', 0], ['t.Active', 1], ['t.NeedsPriceUpdate', 1]])
							->where(function ($query) use ($occupancy) {
								$query->whereNull('t.LastPriceUpdate')
									  ->orWhere(DB::raw('TIMESTAMPDIFF(MINUTE, LastPriceUpdate, NOW())'), '>', 5);
							})
							->orderBy('t.ProdCode', 'asc')
							->get();
			}
			
			if(count($rows)>0) {
				$start = microtime(true);
				echo "\n\nstarted at : ".date('Y-m-d H:i:s');
			}
			
			foreach ($rows as $row) {
				//print_r($row); exit;
				$prod = $row->ProdCode;
				$vendor = $row->Vendor;
				
				$maxquery = DB::table('tblDefaultPrice')
								->select(DB::raw('MAX(End) as MaxDate'))
								->where('ProdCode', $row->ProdCode)
								->get()->first();
				
				$maxpricedate = $maxquery->MaxDate;
				$endDate = ($maxpricedate>=$startDate)?$maxpricedate:"";
				//$endDate = date("Y-m-d", strtotime("+1 months", strtotime($startDate))); //for TEST purpose; comment this line out after testing
				$stopdate = $endDate;
				if(empty($endDate)) {
					DB::table($table_product)
						->where('ProdCode', $row->ProdCode)
						->update([
							'NeedsPriceUpdate' => 0
						]);
				}
				
				$depdate = $startDate;
				
				//delete all rows for this product and recalculate prices
				DB::table($table_name)
					->where('ProductCode', $row->ProdCode)
					->delete();
				
				//delete all document from mongodb for this product				
				$service_url = env('MS_HISTORY_API_URL') . "cache/delete/product/$row->ProdCode";
				if(empty($username) && php_sapi_name()=='cli') $username = 'tania';
				$this->historyAPIRepo->msApiCall($service_url, '', $username, 'DELETE');
				
				while($depdate<=$endDate) {
					$ddd=$this->commonRepo->date_to_array($depdate);
					$year=$ddd[1];
					$month=$ddd[2];
					$day=$ddd[3];
					$this->get_21nights_avail_hotel($table_name,$row->ProdCode, $Description, $row->AllotCode,$row->IsProdCom,$WebPriority,$PackageFlag,$MaxOcc,$vendor,$CityName,$display_group_name,$display_category,$year,$month,$day,$nights,$agcode,$numpax,$numrooms,$DisplayClosedOut,$DisplayOnRequest,$webStartDate='',$webEndDate='',$details='');
					
					$depdate = date("Y-m-d", strtotime("+1 day", strtotime($depdate)));					
				}
				
				$this->generate_price_nightly($table_name, $row->ProdCode, $vendor, $startDate, $stopdate, $row);				
			}
			
			//for testing only
			/*if (isset($row)) {
				DB::table($table_product)
						->where('ProdCode', $row->ProdCode)
						->update([
							'NeedsPriceUpdate' => 8
						]);
			}*/
			
			echo "\ndone.\n";
		}
	}
	
	//insert nightly information based on number of nights in the cache table
	public function generate_price_nightly($table_name, $ProdCode, $VendorID, $startDate, $stopdate, $row) {
		
		$username = $this->argument('username');
		
		$resortfeetype = intval($row->ResortFeeType);
		$resortarr = array(0,7,8,9);
		$nightsArray = array('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21');
		$prows = DB::table($table_name)
				->where([['ProductCode', $ProdCode], ['Date', '<=', $stopdate]])
				->get();
		foreach ($prows as $prow) {
			$startDate = $prow->Date;
			$IsClosedOut = false;
			if($prow->Status=='CL') $IsClosedOut = true;
			if($prow->Status=='RQ') $prow->Status = "OnRequest";
			elseif($prow->Status=='OK') $prow->Status = "Available";
			elseif($prow->Status=='CL')	$prow->Status = "ClosedOut";
			elseif($prow->Status=='RS')	$prow->Status = "Restricted";
			
			//check 21 nights status in a row if they have at least one RQ or CL status 
			//(which status comes first it set to that one)
			$chk21days = $this->commonRepo->add_days($startDate, '20');
			$chkresult = DB::table($table_name)
							->select(DB::raw('Status, Date'))
							->where([['ProductCode', $ProdCode], ['Date', '>=', $startDate], ['Date', '<=', $chk21days], ['Status', '<>', 'OK']])
							->limit(1)
							->get()->first();
							
			//print_r($chkresult); exit;
			
			#TA(20141218) check on RS statuses and make adjustment in the cache table
			$Restricted = FALSE;
			if($prow->Status == "Restricted") {
				$Restricted = TRUE;
				$allotrow = DB::table('tblAllotment as A')
								->select(DB::raw('A.MaxStay, A.MinStay, A.NoDDBeds, P.MinNights, P.MaxOcc, A.IsBookable'))
								->join('tblProduct as P', 'P.AllotCode', 'A.ProdCode')
								->where([['P.ProdCode', $ProdCode], ['A.AllotDate', $startDate]])
								->get()->first();
				if ($allotrow->MinNights > $allotrow->MinStay) $allotrow->MinStay = $allotrow->MinNights;
			}
			
			for($i=0; $i<count($nightsArray); $i++) {
				
				$Single = "0.00";
				$Double = "0.00";
				$Triple = "0.00";
				$Quad = "0.00";
				
				$endDate = $this->commonRepo->add_days($startDate, $nightsArray[$i]-1);
				
				if(!$IsClosedOut) {
					//$chkdate = add_days($startDate, $nightsArray[$i]-1);
					$chkrow = DB::table($table_name)
								->select(DB::raw('count(*) as count'))
								->where([['ProductCode', $ProdCode], ['Date', $endDate], ['Status', 'CL']])
								->get()->first();
					if($chkrow->count > 0) {
						$IsClosedOut = true;
						$prow->Status = "ClosedOut";
					}
				}
				
				$prirow = DB::table($table_name)
							->select(DB::raw('sum(Price1) as sum_price1, sum(Price2) as sum_price2, sum(Price3) as sum_price3, sum(Price4) as sum_price4, count(*) as count'))
							->where([['ProductCode', $ProdCode], ['Date', '>=', $startDate], ['Date', '<=', $endDate]])
							->get()->first();
				
				if($prirow->count == $nightsArray[$i]) {
					
					#TA(20151217) NoDDBeds issue, Triple and Quad rate is calculating regardless of their restriction
					//following query will check if there's any rates with 0 prices in them, then set the Occupancy rate to zero
					$ndbedresult = DB::table($table_name)
										->select(DB::raw('sum(if(Price1=0,1,0)) as ZeroCount1, sum(if(Price2=0,1,0)) as ZeroCount2, sum(if(Price3=0,1,0)) as ZeroCount3, sum(if(Price4=0,1,0)) as ZeroCount4'))
										->where([['ProductCode', $ProdCode], ['Date', '>=', $startDate], ['Date', '<=', $endDate]])
										->get()->first();
					
					#TA(20150421) free nights stay promo implementation
					if (!$prow->NightsToStay) $prow->NightsToStay = 0;
					if (!$prow->NightsFree) $prow->NightsFree = 0;					
					if ($nightsArray[$i]>=($prow->NightsToStay+$prow->NightsFree)) {
						$free_night_clause="";
						//identify which nights are free, then deduct the price from cache pricing
						if($prow->NightsFree>0) {
							$free_night_date = $this->commonRepo->date_plus_minus($startDate,"ADD",$prow->NightsToStay+$prow->NightsFree-1);
							switch ($prow->NightsFree) {
								case "1":
									$free_night_clause = 1;
									break;
								case "2":
									$free_night_date2 = $this->commonRepo->date_plus_minus($free_night_date,"ADD",1);
									$free_night_clause = 2;
									break;
								case "3":
									$free_night_date2 = $this->commonRepo->date_plus_minus($free_night_date,"ADD",1);
									$free_night_date3 = $this->commonRepo->date_plus_minus($free_night_date2,"ADD",1);
									$free_night_clause = 3;
									break;
							} //end switch statement
							
						} //end nights free clause
						
						$promo_prirow = DB::table($table_name)
											->select(DB::raw('sum(Price1) as sum_price1, sum(Price2) as sum_price2, sum(Price3) as sum_price3, sum(Price4) as sum_price4'))
											->where([['ProductCode', $ProdCode], ['Date', '>=', $prow->Date], ['Date', '<=', $endDate]]);
											
						if ($free_night_clause == 1)
							$promo_prirow = $promo_prirow->whereNotIn('Date', [$free_night_date]);
						elseif ($free_night_clause == 2)
							$promo_prirow = $promo_prirow->whereNotIn('Date', [$free_night_date, $free_night_date2]);
						elseif ($free_night_clause == 3)
							$promo_prirow = $promo_prirow->whereNotIn('Date', [$free_night_date, $free_night_date2, $free_night_date3]);
											
						$promo_prirow = $promo_prirow->get()->first();
						
						if($ndbedresult->ZeroCount1==0) $Single = sprintf('%0.2f', $promo_prirow->sum_price1/$nightsArray[$i]);
						if($ndbedresult->ZeroCount2==0)	$Double = sprintf('%0.2f', $promo_prirow->sum_price2/$nightsArray[$i]);
						if($ndbedresult->ZeroCount3==0) $Triple = sprintf('%0.2f', $promo_prirow->sum_price3/$nightsArray[$i]);
						if($ndbedresult->ZeroCount4==0) $Quad = sprintf('%0.2f', $promo_prirow->sum_price4/$nightsArray[$i]);
						
					} else {
						if($ndbedresult->ZeroCount1==0) $Single = sprintf('%0.2f', $prirow->sum_price1/$nightsArray[$i]);
						if($ndbedresult->ZeroCount2==0) $Double = sprintf('%0.2f', $prirow->sum_price2/$nightsArray[$i]);
						if($ndbedresult->ZeroCount3==0) $Triple = sprintf('%0.2f', $prirow->sum_price3/$nightsArray[$i]);
						if($ndbedresult->ZeroCount4==0) $Quad = sprintf('%0.2f', $prirow->sum_price4/$nightsArray[$i]);
					}
					
				} else {
					$prow->Status = "N/A";
				}
				
				//update status if any date has RQ/CL in between 21nights
				if($chkresult && $endDate>=$chkresult->Date) {
					if($chkresult->Status=='CL') {
						$prow->Status = "ClosedOut";
					}
					elseif($chkresult->Status=='RQ') {
						$prow->Status = "OnRequest";
					}
					elseif($chkresult->Status=='RS') {
						$prow->Status = "Restricted";
					}
				}
				
				if($Restricted && ($Single>0 || $Double>0 || $Triple>0 || $Quad>0) && $allotrow->IsBookable!=0) {
					//check status in between nights (if any night has any CL or RQ, do not consider to update the status, keep it as it was before)
					$chknights = $this->commonRepo->add_days($startDate, $nightsArray[$i]-1);
					$chkstatusrow = DB::table($table_name)
										->select(DB::raw('count(*) as count'))
										->where([['ProductCode', $ProdCode], ['Date', '>=', $startDate], ['Date', '<=', $chknights]])
										->whereIn('Status', array('CL', 'RQ'))
										->get()->first();
					
					if ($allotrow->MinStay > 0) { // min stay
						if ($nightsArray[$i] >= $allotrow->MinStay && $chkstatusrow->count<1) {
							$prow->Status = "Available";
						}
					}
					if ($allotrow->MaxStay > 0) { // max stay
						if ($nightsArray[$i] >= $allotrow->MinStay && $nightsArray[$i] <= $allotrow->MaxStay && $chkstatusrow->count<1) {
							$prow->Status = "Available";
						} else if($nightsArray[$i] > $allotrow->MaxStay && $chkstatusrow->count<1) {
							$prow->Status = "Restricted";
						}
					}
					if ($allotrow->NoDDBeds == 1) { //if no double beds are allowed reset Triple and Quad price to 0
						if ($allotrow->MaxOcc > 2) {
							$Triple = '0.00';
							$Quad = '0.00';
						}
					}
				}
				
				//insert or replace data over mongo db if exist the row
				//$data["prodcode"] = $ProdCode;
				//$data["proddate"] = $prow->Date;
				//$data["nights"] = (int)$nightsArray[$i];
				$aglist = array();
				if($row->MarkTUIGermany && in_array($resortfeetype, $resortarr)) $aglist[] = "TUIGEXML";
				if($row->MarkTUISpain) {
					$aglist[] = "EXPO-BAR";
					$aglist[] = "TUISPPWK";
					$aglist[] = "TUIBR";
					$aglist[] = "KALIGOSG";
				}
				if($row->MarkTUIUK) $aglist[] = "TUIUKXML";
				$data["pricecache21nights_data"][$prow->Date][$i] = array(
						"ProductCode" => $ProdCode, 
						"ProductType" => 'Hotel', 
						"TeamvendorId" => (int)$VendorID, 
						"ProductDate" => $prow->Date, 
						"NumberOfNights" => (int)$nightsArray[$i], 
						"Status" => $prow->Status, 
						"Occupancy1" => 'Single', 
						"AvgNightlyRate1" => $Single, 
						"Occupancy2" => 'Double', 
						"AvgNightlyRate2" => $Double, 
						"Occupancy3" => 'Triple', 
						"AvgNightlyRate3" => $Triple, 
						"Occupancy4" => 'Quad', 
						"AvgNightlyRate4" => $Quad,
						"AgCodeList" => $aglist
					);
				/* $service_url = MS_HISTORY_API_URL . 'cache/price21nights';
				if(empty($username) && php_sapi_name()=='cli') $username = 'tania';
				msApiCall($service_url, $data, $username, 'POST'); */
				
			} //end 21 nights for loop
		} //end days loop
		//echo  mb_strlen(serialize((array)$data), '8bit');
		$service_url = env('MS_HISTORY_API_URL') . 'cache/price21nights';
		if(empty($username) && php_sapi_name()=='cli') $username = 'tania';
		$this->historyAPIRepo->msApiCall($service_url, $data, $username, 'POST');
	
	} //end generate_price_nightly
	
	public function get_21nights_avail_hotel($table_name,$ProdCode,$Description,$AllotCode,$IsProdComm,$WebPriority,$PackageFlag,$MaxOcc,$VendorID,$CityName,$display_group_name,$display_category,$year,$month,$day,$nights,$agcode,$numpax,$numrooms,$DisplayClosedOut,$DisplayOnRequest,$webStartDate='',$webEndDate='',$details='') {
		
		$MinStay1='';
		$MaxStay1='';
		$IsBookable1='';
		$MinStay2='';
		$MaxStay2='';
		$IsBookable2='';
		$MinStay3='';
		$MaxStay3='';
		$IsBookable3='';
		$MinStay4='';
		$MaxStay4='';
		$IsBookable4='';
		$MinStay5='';
		$MaxStay5='';
		$IsBookable5='';
		$MinStay6='';
		$MaxStay6='';
		$IsBookable6='';
		$MinStay7='';
		$MaxStay7='';
		$IsBookable7='';
		$status='';
		$price='';
		$day1='';
		$day2='';
		$day3='';
		$day4='';
		$day5='';
		$day6='';
		$day7='';
		$price1_1='';
		$price1_2='';
		$price1_3='';
		$price1_4='';
		$price2_1='';
		$price2_2='';
		$price2_3='';
		$price2_4='';
		$price3_1='';
		$price3_2='';
		$price3_3='';
		$price3_4='';
		$price4_1='';
		$price4_2='';
		$price4_3='';
		$price4_4='';
		$price5_1='';
		$price5_2='';
		$price5_3='';
		$price5_4='';
		$price6_1='';
		$price6_2='';
		$price6_3='';
		$price6_4='';
		$price7_1='';
		$price7_2='';
		$price7_3='';
		$price7_4='';
		$restricted='';
		$promo_message='';
		$NightsFree1='';
		$NightsFree2='';
		$NightsFree3='';
		$NightsFree4='';
		$NightsFree5='';
		$NightsFree6='';
		$NightsFree7='';
		$NoDDB1='';
		$NoDDB2='';
		$NoDDB3='';
		$NoDDB4='';
		$NoDDB5='';
		$NoDDB6='';
		$NoDDB7='';
		
		//Set RateType as default
		$RateType="selling_price";
		//Determine begin and end dates for query
		$arrival_night="$year-$month-$day";
		$last_night=$this->commonRepo->date_plus_minus($arrival_night,"ADD",$nights-1);
		$first_night_to_check=$this->commonRepo->date_plus_minus($arrival_night,"SUBTRACT",2);
		$last_night_to_check=$this->commonRepo->date_plus_minus($last_night,"ADD",2);

		$cutoff = "CutOff";		
		
		//Check if request is for a current/future date and not a past date
		$today=$this->commonRepo->todays_date();
		if ($this->commonRepo->date_compare("$year-$month-$day",$today,"<")) {
			//Generate Error that this is past date
			//js_alert("Request must be for a future date");
			return 0;
		}

		//Check if allotment code & product code are the same or not and then query appropriate table		
		$result = DB::table('tblPriceForAllotment as P')
						->select(DB::raw('P.ApproxPrice1, P.ApproxPrice2, P.ApproxPrice3, P.ApproxPrice4, P.PromoMessage, P.NightsToStay, P.NightsFree, P.ProdCode, A.AllotDate, A.Allotment, A.IsBookable, A.CloseOut, A.MaxStay, A.MinStay, A.MinFromArrival, A.ApproxQtyUsed, A.NoDDBeds, A.ChildAge, A.CutOff, R.MinNights'))
						->join('tblAllotment as A', 'A.RecID', 'P.AllotRecID')
						->join('tblProduct as R', 'R.ProdCode', 'P.ProdCode')
						->where([['P.ProdCode', $ProdCode], ['P.PriceDate', '>=', $first_night_to_check], ['P.PriceDate', '<=', $last_night_to_check]])
						->orderBy('PriceDate', 'asc')
						->get();
		
		if (count($result) < $nights+2) return 0; //Price records were not found for entire search period.
		$past_cutoff="NO";
		$ret_status="OK";

		$promo_messages = array();

		$TraditionalPriceMethod="FALSE";
		$result_i = 0;
		for ($i=1; $i<=$nights+2; $i++) {
			$past_cutoff="NO";
			$ret_status="OK";
			//Fill variables from NEW into day variables for loading into the temporary table
			$day_var="day".$i;
			$price_var_1="price".$i."_1";
			$price_var_2="price".$i."_2";
			$price_var_3="price".$i."_3";
			$price_var_4="price".$i."_4";
			$NightsFree_var="NightsFree".$i;
			$NightsToStay_var="NightsToStay".$i;

			$$price_var_1=0;
			$$price_var_2=0;
			$$price_var_3=0;
			$$price_var_4=0;
			$$NightsFree_var=0;
			$$NightsToStay_var=0;

			if ($i>$nights+2) continue; //we are passed the # of nights in search but have set values to 0

			//Loop through the # of nights + previous two and last two
			$row = isset($result[$result_i]) ? $result[$result_i] : '';
			if (!($row)) {
				//Error condition.  An allotment record does not exist
				//echo "query = $query";
				$result_i++;
				continue;
			}
			
			//Check Cutoff
			if ($past_cutoff=="NO" and $i >= 3) { //only check on/after arrival day
				if ($this->commonRepo->date_compare($today,$this->commonRepo->date_plus_minus($row->AllotDate,"SUBTRACT",$row->$cutoff+1),">=")==1) {
					$ret_status="RQ";
					$past_cutoff="YES";
				}
			}
		
			//if this is day 3 which is the actual arrival day, then check for min/max from arrival or arrival restrictions
			$remaining_nights=$nights-($i-3);
			$isbookvarname="IsBookable".$i;
			$$isbookvarname=$row->IsBookable;
			if ($i==3) {
				//Is arrival day restricted?
				if ($row->IsBookable==0) {
					$ret_status="RS";
				}
			}

			$minStay = $row->MinStay;
			if ($row->MinNights > $row->MinStay) $row->MinStay = $row->MinNights;
			
			$minvarname="MinStay".$i;
			$maxvarname="MaxStay".$i;
			$NoDDBvarname="NoDDB".$i;
			$$minvarname=$row->MinStay;
			$$maxvarname=$row->MaxStay;
			if ($i>2 and $i-2<=$nights) {
				$$NoDDBvarname=$row->NoDDBeds;
				if ($row->NoDDBeds == 1) {
					//Max Occ cannot be > 2
					if ($MaxOcc > 2) $MaxOcc=2;
				}
				//If this is one of the travel days, does it meet the min/max stay requirements
				if ($row->MinStay > 0) {
					if ($nights < $row->MinStay) {
						$ret_status="RS";
					}
				}
				if ($row->MaxStay > 0) {
					if ($nights > $row->MaxStay) {
						$ret_status="RS";
					}
				}
				//Is there a min-from-arrival restriction?		
				if ($row->MinFromArrival==1) {
					//Is the min_from_arrival requirement met?
					if ($remaining_nights < $minStay) {
						$ret_status="RS";
						//add remaining required min days
						$$minvarname = $nights + ($minStay - $remaining_nights);
					}
				}
			}
			//Is one of the travel dates closed out?
			$$day_var=0;
			
			if ($row->CloseOut==1) {
				if ($i>2 and $i-2<=$nights)		$ret_status="CL";
				$$day_var=-100;
				$ret_status="CL";
			}	

			if ($$day_var<>-100) { //not blacked or closed out
				$used_rooms=$row->ApproxQtyUsed;
						
				if ($row->Allotment - $used_rooms < $numrooms or $past_cutoff=="YES") {
					$$day_var=-99;
					$ret_status="RQ";
				} else $$day_var=$row->Allotment - $used_rooms;
			}
			//update pricing info from allotment
			//first check if there is agency specific pricing
			if ($i==3) {				
				$arow = DB::table('tblSpecialException')
							->select(DB::raw('count(*) as count'))
							->where([['prodcode', $ProdCode], ['agcode', $agcode], ['begin', '<=', $arrival_night], ['end', '>=', $arrival_night]])
							->get()->first();
				if ($arow->count >= 1) 
					$TraditionalPriceMethod="TRUE"; 
				else {
					$arow = DB::table('tblSpecialPrice')
							->select(DB::raw('count(*) as count'))
							->where([['prodcode', $ProdCode], ['agcode', $agcode], ['begin', '<=', $arrival_night], ['end', '>=', $arrival_night]])
							->get()->first();
					if ($arow->count >= 1) 
						$TraditionalPriceMethod="TRUE";
				}
				if ($RateType=="cost") 
					$TraditionalPriceMethod="TRUE";				
			}

			if ($TraditionalPriceMethod=="TRUE") {
				//Temp fix of putting H in type field and 1 in numpax field...
				$grpFuncRepo = new DMGrpFuncNewRepo();
				$grpFuncRepo->get_price($ProdCode,$row->AllotDate,1,1,"H",$agcode,$childprice,$$price_var_1,$$price_var_2,$$price_var_3,$$price_var_4,$pm,$$NightsFree_var,$$NightsToStay_var,$RateType);
				$pm = trim($pm);
				$pm = explode(",",$pm);
				foreach($pm as $p)
				{
					if (strlen($p) > 1 and !isset($promo_messages[$p]))
					{
						$promo_messages[$p] = TRUE;
					}
				}
				
				if ($RateType=="cost") {
					$avgCostRepo = TBFuncAvgCostRepo();
					$retval = $avgCostRepo->avg_cost($prod,"","0000-00-00","$date_to_check",$nights,$occupancy,$agcode,"A","YES",$pprice_1,$pprice_2,$pprice_3,$pprice_4,$PromoMsg,$NightsFree,$NightsToStay);
				}
			} else {
				//if ($row->ApproxPrice1<1 and $row->ApproxPrice2<1 and $row->ApproxPrice3<1 and $row->ApproxPrice4<1) return 0; //error - zero price
				if ($row->ApproxPrice1 > 0) $$price_var_1=$row->ApproxPrice1; else $$price_var_1="X";
				if ($row->ApproxPrice2 > 0) $$price_var_2=$row->ApproxPrice2; else $$price_var_2="X";
				if ($row->ApproxPrice3 > 0) $$price_var_3=$row->ApproxPrice3; else $$price_var_3="X";
				if ($row->ApproxPrice4 > 0) $$price_var_4=$row->ApproxPrice4; else $$price_var_4="X";
				$$NightsFree_var=$row->NightsFree;
				$$NightsToStay_var=$row->NightsToStay;
				if ($i>=3) {
					$pm = trim($row->PromoMessage);
					$pm = explode(",",$pm);
					foreach($pm as $p)
					{
						if (strlen($p) > 1 and !isset($promo_messages[$p]))
						{
							$promo_messages[$p] = TRUE;
						}
					}
				}
			}
			//If MaxOcc <= 2; reset tpl and quad prices to null
			if ($MaxOcc<4) $$price_var_4="X";
			if ($MaxOcc<3) $$price_var_3="X";
			if ($MaxOcc<2) $$price_var_2="X";
		} //End of the for loop that loops through each night
		$status=$ret_status;
		//if ($status=="RS") $restricted="RS"; else $restricted="NO";
		//if ($status=="RS") $restricted="1"; else $restricted="0";

		if ($status=="RS") {
			$restricted=1;
		} elseif ($status=="CL") {
			$restricted=2;
		} else {
			$restricted=0;
		}
		
		//$promo_message = implode(",", array_keys($promo_messages));
		//$promo_message = mysql_escape_string($promo_message);
		
		for ($i=3; $i<=$nights+2; $i++) {
			$price1="price".$i."_1";
			$price2="price".$i."_2";
			$price3="price".$i."_3";
			$price4="price".$i."_4";
			
			$single = $$price1;
			$double = $$price2;
			$triple = $$price3;
			$quad = $$price4;
						
			$single=round($single,2);
			$double=round($double,2);
			$triple=round($triple,2);
			$quad=round($quad,2);
					
			#TA(20150421) added free nights stay values
			$NightsToStay = $$NightsToStay_var;
			$NightsFree = $$NightsFree_var;
			if($single>0 || $double>0 || $triple>0 || $quad>0) {
				DB::table($table_name)
					->insert([
						'ProductCode' => $ProdCode,
						'Date' => $arrival_night,
						'Status' => $status,
						'Price1' => $single,
						'Price2' => $double,
						'Price3' => $triple,
						'Price4' => $quad,
						'NightsToStay' => $NightsToStay,
						'NightsFree' => $NightsFree
					]);
			}
		}

		return 1;
	} //end function
}