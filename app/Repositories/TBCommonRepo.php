<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

use App\Repositories\CommonRepo;
use App\Repositories\PaymRepo;

use App\PriceStatement;

class TBCommonRepo extends CommonRepo {
	
	public function __construct() {
	}
	
	public function todays_date() {
		$date_arr = getdate();
		$today=$date_arr["year"]."-".$date_arr["mon"]."-".$date_arr["mday"];
		return $today;
	}
	
	/**
	 * Takes "YYYYMMDD" and returns the string "MM/DD/YYYY"
	 * @param string $ISO Date
	 * @return string
	*/
	public function ISO_to_MMDDYYYY($ISO) {
		// Takes "YYYYMMDD" and returns the string "MM/DD/YYYY"
		$arr = $this->date_to_array($ISO);
		$sRet = "$arr[2]/$arr[3]/$arr[1]";
		if ($sRet == "//") $sRet = "00/00/0000";
		return $sRet;
	}
	
	/**
	 * Takes "MM/DD/YYYY" and returns the string "YYYY-MM-DD"
	 * @param string $date DATE [MM/DD/YYYY]
	 * @return string
	 */
	public function date_to_ISO_string ($date) {
		//Takes "MM/DD/YYYY" to "YYYY-MM-DD"
		list($month, $day, $year) = explode('[/.-]', $date);
		return "$year-$month-$day";
	}
	
	/**
	 * convert date to UNIX timestamp
	 * @param string $ISO ISO date
	 * @return string
	 */
	public function ISO_to_system_time ($ISO) {
		$arr = $this->date_to_array ($ISO);
		return (mktime (0, 0, 0, $arr[2], $arr[3], $arr[1])); // convert date to UNIX timestamp
	}
	
	/**
	 * Converts the date to word
	 * @param string $dte Date 'YYYY-MM-DD' format
	 * @return string
	 */
	public function date_to_word ($dte) {
		list ($year,$month,$day) = explode("-",$dte);
		return (strftime("%b %d, %Y", mktime(0,0,0,$month,$day,$year)));
	}
	
	/**
	 * Takes an ISO formatted date (YYYY-MM-DD) and return a one-based array  YYYY, MM, DD
	 * @param string $ISO ISO formatted date (YYYY-MM-DD)
	 * @return array
	 */
	public function date_to_array ($ISO) {
		//Takes an ISO formatted date (YYYY-MM-DD) and return a one-based array  YYYY, MM, DD
		$retval[1] = 0;
		$retval[2] = 0;
		$retval[3] = 0;
		if ($ISO != '0000-00-00') {
			if (preg_match ("/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/", $ISO, $dateparts)) {
				$retval =  $dateparts;
			} elseif ($ISO != "") {
				echo "306: Invalid date format:  $ISO";
			}
		}
		return $retval;
	}
	
	/**
	 * Converts the date to day
	 * @param string $dte Date 'YYYY-MM-DD' format
	 * @return string
	 */
	public function date_to_day ($dte) {
		list ($year,$month,$day) = explode("-",$dte);
		return (strftime("%a", mktime(0,0,0,$month,$day,$year)));
	}
	
	/**
	 * To convert the date to amerdt
	 * @param string $dte Date
	 * @return string
	 */
	public function date_to_amerdt ($dte) {
		list ($year,$month,$day) = explode ('-',$dte);
		return (date("n/j/y",mktime(0,0,0,$month,$day,$year)));
	}
	
	/**
	 * Takes a "YYYY-MM-DD" and "ADD" or "SUBRACT" $days to return in same format
	 * @param string $current_date Date [YYYY-MM-DD]
	 * @param string $operation [ADD/SUBSTRACT]
	 * @param integer $days No of Days
	 * @return string
	 */
	public function date_plus_minus($current_date,$operation,$days) {
		//Takes a "YYYY-MM-DD" and "ADD" or "SUBRACT" $days to return in same format
		$from_date = strtotime($current_date);
		if ($operation=="ADD") {
			//$query="select adddate('$current_date', interval $days day)";
			//$result=sql_cover($query,"select",0,0,0,0);
			//$itemInfoObject=mysql_fetch_array($result);
			$from_date = strtotime("+$days day", $from_date);
			return date('Y-m-d', $from_date);
			
		} else {
			//$query="select subdate('$current_date', interval $days day)";
			//$result=sql_cover($query,"select",0,0,0,0);
			//$itemInfoObject=mysql_fetch_array($result);

			$from_date = strtotime("-$days day", $from_date);
			return date('Y-m-d', $from_date);
		}
		
		return "0000-00-00";
	}
	
	/**
	 * Receives a date (YYYY-MM-DD) and the number of days to add; returns the new date
	 * @param string $sISO_start Date [YYYY-MM-DD]
	 * @param integer $iNum2Add Number of days to add.
	 * @return string
	 */
	public function add_days ($sISO_start, $iNum2Add) {
		// Receives a date (YYYY-MM-DD) and the number of days to add; returns the new date

		$aBegin = $this->date_to_array ($sISO_start);
		return date ("Y-m-d", mktime (0, 0, 0, $aBegin[2], $aBegin[3] + $iNum2Add, $aBegin[1]));
	} // end add_days()
	
	/**
	 * Compares the date parameters using tthe specified operator
	 * @param string $date1 Date1
	 * @param string $date2 Date2
	 * @param string $operator Operator['>','>=','<','<=','==' and '!=']
	 * @return boolean
	 */
	public function date_compare ($date1,$date2,$operator) {
		$d1=$this->date_to_array($date1);
		$d2=$this->date_to_array($date2);
		$d1t=mktime(0,0,0,$d1[2],$d1[3],$d1[1]);
		$d2t=mktime(0,0,0,$d2[2],$d2[3],$d2[1]);
		switch ("$operator") {
			case ">":
				if ($d1t > $d2t) return 1;
				break;
			case ">=":
				if ($d1t >= $d2t) return 1;
				break;
			case "<":
				if ($d1t < $d2t) return 1;
				break;
			case "<=":
				if ($d1t <= $d2t) return 1;
				break;
			case "==":
				if ($d1t==$d2t) return 1;
				break;
			case "!=":
				if ($d1t<>$d2t) return 1;
				break;
		}//switch
		return 0;
	} //date_compare
	
	/**
	 * Receives a numeral and returns the corresponding roomtype (SGL, DBL, etc)
	 * @param integer $sNumeral Occupancy
	 * @param boolean $bLongName Checks Whether to return long name or short name.
	 * @return string
	 */
	public function numeral_to_roomtype ($sNumeral, $bLongName) {
		// Receives a numeral and returns the corresponding roomtype (SGL, DBL, etc)

		switch ($sNumeral) {
			case "1":
				$sRetShort = "SGL";
				$sRetLong = "Single";
				break;
			case "2":
				$sRetShort = "DBL";
				$sRetLong = "Double";
				break;
			case "3":
				$sRetShort = "TPL";
				$sRetLong = "Triple";
				break;
			case "4":
				$sRetShort = "QUAD";
				$sRetLong = "Quad";
				break;
			case "5":
				$sRetShort = "QNT";
				$sRetLong = "Quint";
				break;
			case "-1":
				$sRetShort = "SGL+1CH";
				$sRetLong = "Single + 1 Child";
				break;
			case "-2":
				$sRetShort = "DBL+1CH";
				$sRetLong = "Double +  1 Child";
				break;
			case "-3":
				$sRetShort = "DBL+2CH";
				$sRetLong = "Double + 2 Children";
				break;
			case "-4":
				$sRetShort = "TPL+1CH";
				$sRetLong = "Triple + 1 Child";
				break;
			default:
				$sRetShort = $sNumeral;
				$sRetLong = "$sNumeral-person";
				break;
		}

		return ($bLongName ? $sRetLong : $sRetShort);

	} // end numeral_to_word()
	
	/**
	 * Determines whether or not to display a column of tblItem data, based on the Flag settings in tblProduct.
	 * @param string $field field in tblProduct
	 * @param integer $res Reservation Number
	 * @return boolean
	 */
	public function item_show_col ($field, $res) {
		// Determines whether or not to display a column of tblItem data, based on the Flag settings in tblProduct.
		$retval = 0;  // Initialize the return value
		
		$result = DB::table('tblItem as I')
					->select(DB::raw('I.ProdCode'))
					->join('tblProduct as P', 'P.ProdCode', '=', 'I.ProdCode')
					->where([['I.resnum', $res], ['I.Status', '!=', 'CX'], ["P.$field", '1']])
					->get()->first(); 
		if ($result) {
			$retval = 1;
		}

		// The following 5 lines guarantee that the column will be shown in the event that a
		// ProdCode exists in tblItem that does not exist in tblProduct
		$result1 = DB::table('tblItem as I')
					->select(DB::raw('I.ProdCode'))
					->join('tblProduct as P', 'P.ProdCode', '=', 'I.ProdCode')
					->where([['I.resnum', $res], ['I.Status', '!=', 'CX']])
					->get();
		$result1_num = count($result1);
		
		$result2 = DB::table('tblItem')
					->select('ProdCode')
					->where([['resnum', $res], ['Status', '!=', 'CX']])
					->get();
					
		$result2_num = count($result2);
		
		if ($result1_num < $result2_num) $retval = 1;
		
		return $retval;
	}
	
	/**
	 * To Calculate the Payable
	 * @param integer $itemid Item ID
	 * @return void
	 */
	public function calc_payable($itemid) {
		$item = DB::table('tblItem')
					->where('itemID',  $itemid)
					->get()->first();

		// check for existing payables
		//(VendorID=$item->Vendor and ItemID is null) or
		
		$payable = DB::table('tblAcctPay')
						->where('ItemID', $itemid)
						->get()->first();
						
		// if entry already exists and item has one of these status codes, delete
		if ($item->Status == 'UC' || $item->Status == 'XB' || $item->Status == 'PX' || $item->Status == 'XX' || $item->Status == 'CX') {
			if ($payable) {
				DB::table('tblAcctPay')
					->where(function ($query) use($item) {
						$query->where('VendorID', '=', $item->Vendor)
							  ->where('ItemID', '=', 1);
					})
					->orWhere(function ($query) use($itemid) {
						$query->where('ItemID', '=', $itemid)
							  ->where('Status', '=', 'Estimated');
					})
					->delete();
			}
			return;
		}

		// insert/update unless payable status is PAID or Pay Now
		if (!$payable || $payable->Status == 'Estimated' || $payable->Status == 'Pending') {
			$agcodeData = DB::table('tblBooking')
								->where('resnum', $item->ResNum)
								->get()->first();
			$agcode = $agcodeData->AgCode;

			// Check if the OurCost field is filled up, if yes then use the value in it
			$item_row = DB::table('tblItem')
							->select('OurCost')
							->where('ItemID', $itemid)
							->get()->first();
							
			if (floatval($item_row->OurCost)>0)
				$amount = $item_row->OurCost;
			else
				$amount=$this->avg_cost($item->ProdCode, "","", $item->DepDate, $item->Nights, $item->Occupancy, $item->Qty, $agcode, $item->PaxType,"YES",$pcost_1,$pcost_2,$pcost_3,$pcost_4,$dumm1,$dummy2,$dummy4,"TOTAL");
			//last 4 arguments from avg_cost not needed so they have been omitted. TOTAL is calc by default

			$totalAmount = $this->calc_item_total_cost($itemid);
			$vendorData = DB::table('tblItem')
							->where('ItemID', $itemid)
							->get()->first();
			$vendor = $vendorData->Vendor;

			if (intval($vendor) > 0)
				$vendor_exists = true;
			else
				$vendor_exists = false;

			if ($payable && $payable->type > 0)
				$fop = $payable->type;
			else {
				$fopData = DB::table('tblPayFOP')
								->orderBy('fopID')
								->get()->first();
				$fop = $fopData->fopID;
			}

			if ($payable && $payable->ExpenseCatID > 0)
				$expense = $payable->ExpenseCatID;
			else {
				$expenseData = DB::table('tblExpenseCategory')
									->orderBy('ExpenseCatID')
									->get()->first();
				
				$expense = $expenseData->ExpenseCatID;
			}

			if ($payable) {
				DB::table('tblAcctPay')
					->where('ItemID', $itemid)
					->update(['ResNum' => $item->ResNum, 'Amount' => $totalAmount, 'DatePaid' => date('Y-m-d'), 'VendorID' => $vendor, 'type' => $fop, 'ExpenseCatID' => $expense, 'Status' => 'Estimated']);
			}
			elseif ($vendor_exists) {
				DB::table('tblAcctPay')
					->insert([
						'ResNum' => $item->ResNum, 'Amount' => $totalAmount, 'DatePaid' => date('Y-m-d'), 'VendorID' => $vendor, 'type' => $fop, 'ExpenseCatID' => $expense, 'Status' => 'Estimated', 'ItemID' => $itemid
					]);
			}

			// Populate the OurCost field with the calculated amount
			DB:: table('tblItem')
				->where('ItemID', $itemid)
				->update(['OurCost' => $amount]);
		}
	}
	
	public function calc_item_total_cost($itemId) {
		$item = DB::table('tblItem')
					->where('ItemID', $itemId)
					->get()->first();
		if ($item)
		{
			if ($item->PricingType == 1)
			{
				if ($item->Occupancy < 0) {
					if ($item->Occupancy == -3) $item->Occupancy = 2;
					elseif ($item->Occupancy == -4) $item->Occupancy = 3;
					else $item->Occupancy = abs($item->Occupancy);
				}
				return $item->Nights * $item->OurCost * $item->Occupancy * $item->Qty;
			} else {
				return $item->Nights * $item->Qty * $item->OurCost;
			}
		}
		return false;
	}
		
	/**
	 * Cleans a value for inclusion in a query
	 * @param string $sStr
	 * @return string
	 */
	public function clean4query ($sStr) {
		// Cleans a value for inclusion in a query
		return addslashes (trim ($sStr));
	} // end clean4query()
		
	/**
	 * Cleans a value for inclusion in HTML
	 * @param string $sStr
	 * @return string
	 */
	public function clean4html ($sStr) {
		// Cleans a value for inclusion in HTML
		return htmlentities (stripslashes ($sStr));
	} // end clean4html()
	
	public function cf_header_string() {
		$jj="<center><img src=".env('LOGO_PATH')."><br>".env('CF_HEADER_INFO');
		return $jj;
	}
	
	/**
	 * To Determine the items average price over the given period
	 *
	 * <p>PricingType=0 - Per rooms (Nights * Qty * Price)</p>
	 * <p>PricingType=1 - Per Person / Hotel Only (Occ * Nights * Qty * Price)
	 * <p>PricingType=2 - Per Person - Inclusive Package (Occ * Qty * Price)</p>
	 * 
	 * @param string $prodcode Product Code
	 * @param string $pkgcode Package Code
	 * @param string $pkgdate Package Date
	 * @param string $date Date
	 * @param integer $nights Number of Nights
	 * @param integer $occupancy Occupancy
	 * @param integer $Qty Quantity
	 * @param string $agcode Agency Code
	 * @param string $PaxType Passenger Type
	 * @param string $check_all_prices
	 * @param float &$pprice_1
	 * @param float &$pprice_2
	 * @param float &$pprice_3
	 * @param float &$pprice_4
	 * @param string &$PromoMsg Promo Message
	 * @param integer &$NightsFree_rtn Number of Nights free
	 * @param integer &$NightsToStay_rtn Number of Nights to stay
	 * @param string $ReturnValue
	 * @return float|string
	 */
	 
	public function avg_cost($prodcode,$pkgcode,$pkgdate,$date,$nights,$occupancy,$Qty,$agcode,$PaxType,$check_all_prices,&$pprice_1,&$pprice_2,&$pprice_3,&$pprice_4,&$PromoMsg,&$NightsFree_rtn=0,&$NightsToStay_rtn=0,$ReturnValue="TOTAL",$Child1Age='', $Child2Age='') {
		//Determine the items average price over the given period
		$night_ctr=1;
		$PromoFound="NO";
		$FirstPromoNight=0;
		$today=$this->todays_date();
		$price=0;
		$pprice_1=0;
		$pprice_2=0;
		$pprice_3=0;
		$pprice_4=0;
		$DontDoThisAgain_BecauseFreeNightAlreadyGiven="NO";

		//calcuate # of occupants in room based on occupancy (including adults + children)
		switch ($occupancy) {
			case "-1":
				$NumOccupants=2;
				break;
			case "-2":
				$NumOccupants=3;
				break;
			case "-3":
				$NumOccupants=4;
				break;
			case "-4":
				$NumOccupants=4;
				break;
			case "1":
				$NumOccupants=1;
				break;
			case "2":
				$NumOccupants=2;
				break;
			case "3":
				$NumOccupants=3;
				break;
			case "4":
				$NumOccupants=4;
				break;
		}//switch

		//adjust occupancy for family plan pricing
		if ($occupancy<0) {
			if ($occupancy==-3) $occupancy=2;
			elseif ($occupancy==-4) $occupancy=3;
			else $occupancy=abs($occupancy);
		}		

		if ($check_all_prices<>"YES") {
			//Verify that the maximum # of occupants for this product has not been exceeded
			$p_row = DB::table('tblProduct')
						->where('prodcode', $prodcode)
						->get()->first();
			if ($p_row->MaxOcc > 0) {
				if ($NumOccupants > $p_row->MaxOcc) {
					//Not allowed... occupants exceeds max
	//				js_alert("$NumOccupants occupants exceeds $p_row->MaxOcc which is the maximum allowed ($prodcode)");
					return "fail";	
				}	
			} else {
				if ($NumOccupants > 1) {
					//Not allowed... occupants exceeds max
	//				js_alert("$NumOccupants occupants exceeds $p_row->MaxOcc which is the maximum allowed ($prodcode)");
					return "fail";	
				}	 
			}
		} //verify max # occ if checkall<>yes
		else {
			//Verify that the maximum # of occupants for this product has not been exceeded		
			$p_row = DB::table('tblProduct as p')
						->join('tblVendor as v', 'v.vendorid', '=', 'p.vendor')
						->select(DB::raw('p.*, v.overlappingPromos, v.VendorID, v.ChildAgeRange1Min, v.ChildAgeRange1Max, v.ChildAgeRange2Min, v.ChildAgeRange2Max, v.ChildAgeRange3Min, v.ChildAgeRange3Max, v.ChildAgeRange4Min, v.ChildAgeRange4Max'))
						->where('p.prodcode', $prodcode)
						->get()->first();
		}
		
		if (strlen(trim($pkgcode))>1) {
			//check if this is part of a package and if it must have a certain number of nights priced at 0
			$srow = DB::table('tblPkgItem')
						->where([['PkgCode', $pkgcode], ['ItemCOde', $prodcode]])
						->get()->first();
			if ($srow) {
				if ($srow->PullPriceFlag==0) { //don't pull price for package included nights
					if ($nights==1) { //this is probably not a hotel, don't pull price, set to 0
						$nt_price_flag[0]=0;
					} else {
					for ($i=0;$i<$nights;$i++) {
						$date_to_check=$this->date_plus_minus($date,"ADD",$i);
						$end_date=$this->date_plus_minus($pkgdate,"ADD",$srow->DefaultNights-1);
						if ($this->date_compare($date_to_check,$pkgdate,">=") and $this->date_compare($date_to_check,$end_date,"<=")) {
							$nt_price_flag[$i]=0;
						} else {
							$nt_price_flag[$i]=1;
						}
					} //end for
					} //end if nights=1
				} else {
					for ($i=0;$i<$nights;$i++) {
						$nt_price_flag[$i]=1;
					}
				}		
			} else { 
				for ($i=0;$i<$nights;$i++) {
					$nt_price_flag[$i]=1;
				}
			}
		} else {
			for ($i=0;$i<$nights;$i++) {
				$nt_price_flag[$i]=1;
			}
		}

		while ($night_ctr <= $nights) {
			$nt=$night_ctr-1;
			if ($nt_price_flag[$nt]) {

				//if (PRICE_CATEGORIES<>"NO") { Price Categories Not Needed for Costing
				if (1==0) { 
				} else { //pricing_catories=NO
					//echo $query; exit;
					$price_obj = DB::table('tblPromoPrice')
									->where([['prodcode', $prodcode], ['book_begin', '<=', $today], ['book_end', '>=', $today], ['overrideDef', '1'], ['overrideExp', '1']])
									->whereRaw("begin<=date_add('$date', interval $nt day)")
									->whereRaw("end >=date_add('$date', interval $nt day)")
									->where(function ($query) use ($occupancy) {
										$query->where('occ1', '=', $occupancy)
											  ->orWhere('occ2', '=', $occupancy)
											  ->orWhere('occ3', '=', $occupancy)
											  ->orWhere('occ4', '=', $occupancy);
									})
									->get()->first();
					$p_type="promo";
					if(!$price_obj or !$this->overlappingPromosAllowed(false, $price_obj, $p_row, $date)) { //No Promo with DEF&EXC FOUND
						//check if either a DEF or EXC is found
						$price_obj = DB::table('tblPromoPrice')
									->where([['prodcode', $prodcode], ['book_begin', '<=', $today], ['book_end', '>=', $today]])
									->whereRaw("begin<=date_add('$date', interval $nt day)")
									->whereRaw("end >=date_add('$date', interval $nt day)")
									->where(function ($query) use ($occupancy) {
										$query->where('occ1', '=', $occupancy)
											  ->orWhere('occ2', '=', $occupancy)
											  ->orWhere('occ3', '=', $occupancy)
											  ->orWhere('occ4', '=', $occupancy);
									})
									->where(function ($query) {
										$query->where('overrideDef', '=', '1')
											  ->orWhere('overrideExp', '=', '1');
									})
									->get()->first();
						
						if (!$price_obj or !$this->overlappingPromosAllowed(false, $price_obj, $p_row, $date)) {
							$price_obj = DB::table('tblDefaultException')
										->where([['prodcode', $prodcode]])
										->whereRaw("begin<=date_add('$date', interval $nt day)")
										->whereRaw("end >=date_add('$date', interval $nt day)")
										->where(function ($query) use ($occupancy) {
											$query->where('occ1', '=', $occupancy)
												  ->orWhere('occ2', '=', $occupancy)
												  ->orWhere('occ3', '=', $occupancy)
												  ->orWhere('occ4', '=', $occupancy);
										})
										->get()->first();
							
							$p_type="except";
							if(!$price_obj) {
								$price_obj = DB::table('tblDefaultPrice')
												->where([['prodcode', $prodcode]])
												->whereRaw("begin<=date_add('$date', interval $nt day)")
												->whereRaw("end >=date_add('$date', interval $nt day)")
												->where(function ($query) use ($occupancy) {
													$query->where('occ1', '=', $occupancy)
														  ->orWhere('occ2', '=', $occupancy)
														  ->orWhere('occ3', '=', $occupancy)
														  ->orWhere('occ4', '=', $occupancy);
												})
												->get()->first();
												
								$p_type="default";
								if(!$price_obj) {
									return "fail";
								} 
							}
						} else { //no promo record exists... check prices
							//result is found - check if this will override
							if ($price_obj->overrideDef==1) { //check if there is an exception price			
								$price_obj_bak=$price_obj;
								$price_obj = DB::table('tblDefaultException')
												->where([['prodcode', $prodcode]])
												->whereRaw("begin<=date_add('$date', interval $nt day)")
												->whereRaw("end >=date_add('$date', interval $nt day)")
												->where(function ($query) use ($occupancy) {
													$query->where('occ1', '=', $occupancy)
														  ->orWhere('occ2', '=', $occupancy)
														  ->orWhere('occ3', '=', $occupancy)
														  ->orWhere('occ4', '=', $occupancy);
												})
												->get()->first();
								
								$p_type="except";								
								if(!$price_obj) {
									$p_type="promo";
									$price_obj=$price_obj_bak;
								}
							} elseif ($price_obj->overrideExp==1) {			
								$price_obj_bak=$price_obj;
								$price_obj = DB::table('tblDefaultPrice')
												->where([['prodcode', $prodcode]])
												->whereRaw("begin<=date_add('$date', interval $nt day)")
												->whereRaw("end >=date_add('$date', interval $nt day)")
												->where(function ($query) use ($occupancy) {
													$query->where('occ1', '=', $occupancy)
														  ->orWhere('occ2', '=', $occupancy)
														  ->orWhere('occ3', '=', $occupancy)
														  ->orWhere('occ4', '=', $occupancy);
												})
												->get()->first();
												
								$p_type="default";
								if(!$price_obj) {
									$p_type="promo";
									$price_obj=$price_obj_bak;
								}
							}	
						}
					} 
				} //end if prcicing categories

		
				//Determine if a nightly free promo exists
				//--MH--1/14/2010--Nightly Free Logic 
				/*
					1) In order to receive a FREE night, The NightsToStay must be consecutive and immediatly preceeding the free night.
					2) A nightly free promo will be active on the first night during the stay that it is encountered.  
					2.5) A check will be done to make sure that the free night exists within the current pricing date range.  If it does not, the promo will not apply.
					3) Subsequent "nights" counting towards the free one will be counted as long as they are also within the promo range	
					4) If one of the subsequent nights is not within the promo range, the promo will be set to NO and the process begins again
					5) This will be repeated until either a free night is achieved or the number of nights in the stay is exhausted
					6) At this time, we do not support cumulative nightly free promos
				*/ // - Old Notes Below //
				//This promo will be checked for each night of the stay.  If a promo is found, we will not check for addl promos 
				//And the nightly free promo flag will be set to true.  
				//After the nightly free flag is set to true, the # of remaining addl nights will be counted so that a promotion can
				//be applied if necessary.
				// Promotion Message - Based on first night

				if  ($p_type<>"promo") {
					//check if there is a promo that has no pricing but supercedes this pricing entry
					$promo_row = DB::table('tblPromoPrice')
									->where([['prodcode', $prodcode], ['book_begin', '<=', $today], ['book_end', '>=', $today]])
									->whereRaw("begin<=date_add('$date', interval $nt day)")
									->whereRaw("end >=date_add('$date', interval $nt day)")
									->get()->first();
									
					if ($promo_row) {						
						if (!isset($promoFoundAlready))
							$promoFoundAlready = false;
						if ($this->overlappingPromosAllowed($promoFoundAlready,$promo_row,$p_row,$date))
						{
							$promoFoundAlready = true;
							//populate promo info from promo record
							$PromoMsg = $promo_row->PromoMsg;
							if ($promo_row->NightsToStay>0 and $promo_row->NightsFree>0) {
								
								//check if current date + nights needed to stay for promo is within the date range of the promo
								//Added 1/10/10-MH
								$current_night_date=$this->date_plus_minus($date,"ADD",$nt);
								$end_night_date=$this->date_plus_minus($current_night_date,"ADD",($promo_row->NightsToStay+$promo_row->NightsFree-1));
								if ($this->date_compare($end_night_date,$promo_row->End,"<=")) {
									$NightsToStay=$promo_row->NightsToStay;
									$NightsFree=$promo_row->NightsFree;
									$NightsToStay_rtn=$promo_row->NightsToStay;
									$NightsFree_rtn=$promo_row->NightsFree;
									if ($PromoFound=="NO") { //This is now the first night of the promo
										$FirstPromoNight=$night_ctr;
										$PromoFound="YES";
									}
								} //EndIF Free night exceeds PromoPeriod 
							}
						}
					} else {
						
						///check for promo info on current record and populate
						$PromoMsg = $price_obj->PromoMsg;
						if ($price_obj->NightsToStay>0 and $price_obj->NightsFree>0) {
							//check if current date + nights needed to stay for promo is within the date range of the promo
							//Added 1/10/10-MH
							$current_night_date=$this->date_plus_minus($date,"ADD",$nt);
							$end_night_date=$this->date_plus_minus($current_night_date,"ADD",($price_obj->NightsToStay+$price_obj->NightsFree-1));
							if ($this->date_compare($end_night_date,$price_obj->End,"<=")) {
								$NightsToStay=$price_obj->NightsToStay;
								$NightsFree=$price_obj->NightsFree;
								$NightsToStay_rtn=$price_obj->NightsToStay;
								$NightsFree_rtn=$price_obj->NightsFree;
								if ($PromoFound=="NO") {
									$FirstPromoNight=$night_ctr;
									$PromoFound="YES";
								}	
							} //EndIF Free night exceeds PromoPeriod 
						} else { //endif nightstostay & nightsfree <> 0
							//No promo was found
							$PromoFound="NO";
							$NightsToStay=0;
							$NightsFree=0;
							$NightsToStay_rtn=0;
							$NightsFree_rtn=0;
						} //endif nightstostay=0 and nightsfree=0
					}
				} else { //This is type promo... we have to check this record
					///check for promo info on current record and populate
					$PromoMsg = $price_obj->PromoMsg;
					if ($price_obj->NightsToStay>0 and $price_obj->NightsFree>0) {
						//check if current date + nights needed to stay for promo is within the date range of the promo
						//Added 1/10/10-MH
						$current_night_date=$this->date_plus_minus($date,"ADD",$nt);
						$end_night_date=$this->date_plus_minus($current_night_date,"ADD",($price_obj->NightsToStay+$price_obj->NightsFree-1));
						if ($this->date_compare($end_night_date,$price_obj->End,"<=")) {
							$NightsToStay=$price_obj->NightsToStay;
							$NightsFree=$price_obj->NightsFree;
							$NightsToStay_rtn=$price_obj->NightsToStay;
							$NightsFree_rtn=$price_obj->NightsFree;
							if ($PromoFound=="NO") {
								$FirstPromoNight=$night_ctr;
								$PromoFound="YES";
							}
						} //EndIF Free night exceeds PromoPeriod 
					} else { //endif nightstostay & nightsfree <> 0
						//No promo was found
						$PromoFound="NO";
						$NightsToStay=0;
						$NightsFree=0;
						$NightsToStay_rtn=0;
						$NightsFree_rtn=0;
					} //endif nightstostay=0 and nightsfree=0
				} //type=promo

				//Check if promo/free night price should be applied to this day
				if ($PromoFound=="YES" and ($FirstPromoNight+$NightsToStay)==$night_ctr) {
					//Apply promo by skipping the addition of this night's cost
					//Check if more than one night will be free
					if (!isset($NightFreeApplied))
						$NightFreeApplied=0;
					$NightFreeApplied++;
					if (--$NightsFree>0) $FirstPromoNight+=1;
					$AddPriceInThisIteration="NO";
				} else {
					$AddPriceInThisIteration="YES";
				}
				
				if ($DontDoThisAgain_BecauseFreeNightAlreadyGiven=="YES") {
					//There can be no more FREE nights
					$AddPriceInThisIteration="YES";
				}
		
				if ($AddPriceInThisIteration=="YES") { 
					//If Child then don't check occ prices
					if ($PaxType=="C") {
						$price+=$price_obj->ChildTax;
					} else {
						//Get the correct price for the occupancy required and check for day schg
						$MaxOccPrice=1;
						$qqrow = DB::table('tblPriceDays')
									->where([['id', $price_obj->PriceID], ['type', $p_type]])
									->get()->first();
						if ($qqrow) $day_surcharges_present="YES";
						else $day_surcharges_present="NO";
						for ($i=1;$i<=4;$i++) {
							$occ="Occ".$i;
							//$pri="Price".$i;
							$pri="OurCost".$i;
							$ppri="pprice_".$i;
							if ($price_obj->$occ > $MaxOccPrice and $price_obj->$pri > 0) $MaxOccPrice=$price_obj->$occ;
							if ($price_obj->$occ==$occupancy or $check_all_prices=="YES") {
							 if ($price_obj->$pri=="") {
									if ($check_all_prices<>"YES") return "fail";
									else continue;
								} 
								if ($price_obj->$pri >= 0) {
									$price+=$price_obj->$pri;
									$$ppri+=$price_obj->$pri;
									//check for surcharge
									if ($day_surcharges_present=="YES") {
										//determine day of week
										$curr_day=$this->date_plus_minus($date,"ADD",$nt);
										//ereg( "([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})", $curr_day, $datebits);	
										preg_match('/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/', $curr_day, $datebits);	
										$day=date("D",mktime(0,0,0,$datebits[2],$datebits[3],$datebits[1]));
										$day=strtolower($day).$i;
										$nday="tax_".$day;
										if ($qqrow->$day > 0) {
											$price+=$qqrow->$day;
											$$ppri+=$qqrow->$nday;
										}
									} //surcharge record present	
								} //price_obj->$pri > 0
							} //price exists for occupancy or check_all=yes
						} //end for
						
						if($Child1Age>0 || $Child2Age>0) {
							if($Child1Age>=$p_row->ChildAgeRange1Min and $Child1Age<=$p_row->ChildAgeRange1Max) {
								$pprice_1+=$price_obj->ChildTax;
								$pprice_2+=$price_obj->ChildTax;
								$pprice_3+=$price_obj->ChildTax;
								$pprice_4+=$price_obj->ChildTax;
							} elseif($Child1Age>=$p_row->ChildAgeRange2Min and $Child1Age<=$p_row->ChildAgeRange2Max) {
								$pprice_1+=$price_obj->ChildCost1;
								$pprice_2+=$price_obj->ChildCost1;
								$pprice_3+=$price_obj->ChildCost1;
								$pprice_4+=$price_obj->ChildCost1;
							} elseif($Child1Age>=$p_row->ChildAgeRange3Min and $Child1Age<=$p_row->ChildAgeRange3Max) {
								$pprice_1+=$price_obj->ChildCost2;
								$pprice_2+=$price_obj->ChildCost2;
								$pprice_3+=$price_obj->ChildCost2;
								$pprice_4+=$price_obj->ChildCost2;
							} elseif($Child1Age>=$p_row->ChildAgeRange4Min and $Child1Age<=$p_row->ChildAgeRange4Max) {
								$pprice_1+=$price_obj->ChildCost3;
								$pprice_2+=$price_obj->ChildCost3;
								$pprice_3+=$price_obj->ChildCost3;
								$pprice_4+=$price_obj->ChildCost3;
							}
							
							if($Child2Age>=$p_row->ChildAgeRange1Min and $Child2Age<=$p_row->ChildAgeRange1Max) {
								$pprice_1+=$price_obj->ChildTax;
								$pprice_2+=$price_obj->ChildTax;
								$pprice_3+=$price_obj->ChildTax;
								$pprice_4+=$price_obj->ChildTax;
							} elseif($Child2Age>=$p_row->ChildAgeRange2Min and $Child2Age<=$p_row->ChildAgeRange2Max) {
								$pprice_1+=$price_obj->ChildCost1;
								$pprice_2+=$price_obj->ChildCost1;
								$pprice_3+=$price_obj->ChildCost1;
								$pprice_4+=$price_obj->ChildCost1;
							} elseif($Child2Age>=$p_row->ChildAgeRange3Min and $Child2Age<=$p_row->ChildAgeRange3Max) {
								$pprice_1+=$price_obj->ChildCost2;
								$pprice_2+=$price_obj->ChildCost2;
								$pprice_3+=$price_obj->ChildCost2;
								$pprice_4+=$price_obj->ChildCost2;
							} elseif($Child2Age>=$p_row->ChildAgeRange4Min and $Child2Age<=$p_row->ChildAgeRange4Max) {
								$pprice_1+=$price_obj->ChildCost3;
								$pprice_2+=$price_obj->ChildCost3;
								$pprice_3+=$price_obj->ChildCost3;
								$pprice_4+=$price_obj->ChildCost3;
							}
						}
						
					} //endif child price
				} //AddPriceInThisIteration=YES
				else if ($NightFreeApplied == $NightsFree_rtn) { //AddPriceInThisIteration=YES
					$DontDoThisAgain_BecauseFreeNightAlreadyGiven="YES";
				}

			} else { //nt_price_flag=1 
				$price+=0;
			}
			$night_ctr+=1;	
		} //while looping through the nights

		$ttlprice=$price*$Qty;
		$price=round(($price/$nights),2);
		$pricing_type=$price_obj->PricingType;
		$parentCostObj = null;
		if(substr($prodcode, 3,1) == 'M'){
			//Just assume it's not gonna happen
			//$parentCostObj = $this->get_parent_cost_obj($date,$nights,$occupancy,$Qty,$agcode,$PaxType,$check_all_prices,$pprice_1,$pprice_2,$pprice_3,$pprice_4,$PromoMsg,$NightsFree_rtn,$NightsToStay_rtn,$ReturnValue,$Child1Age, $Child2Age);
		}
		
		if ($ReturnValue=="TOTAL") {
			$pprice_1=$pprice_1*$Qty;
			$pprice_2=$pprice_2*$Qty;
			$pprice_3=$pprice_3*$Qty;
			$pprice_4=$pprice_4*$Qty;
			
			if(substr($prodcode, 3, 1) == "M" && $parentCostObj!=null){
				if($nights == ($parentCostObj->NightsToStay + $parentCostObj->NightsFree)){
					$ttlprice = round(($ttlprice*$parentCostObj->NightsToStay)/$nights, 2);
				}
			}
			
			return $ttlprice;
		} else {
			$pprice_1=round(($pprice_1/$nights),2);
			$pprice_2=round(($pprice_2/$nights),2);
			$pprice_3=round(($pprice_3/$nights),2);
			$pprice_4=round(($pprice_4/$nights),2);
			
			if(substr($prodcode, 3, 1) == "M" && $parentCostObj!=null){
				//echo __LINE__." Nights: ".$nights . " NightsToStay: ".$parentCostObj->NightsToStay." NightsFree: ".$parentCostObj->NightsFree." price: ".$price."\n";
				if($nights >= ($parentCostObj->NightsToStay + $parentCostObj->NightsFree)){
					$price = round(($price*$parentCostObj->NightsToStay)/$nights, 2);
					$pprice_1 = round(($pprice_1*($nights-$parentCostObj->NightsFree))/$nights, 2);
					$pprice_2 = round(($pprice_2*($nights-$parentCostObj->NightsFree))/$nights, 2);
					$pprice_3 = round(($pprice_3*($nights-$parentCostObj->NightsFree))/$nights, 2);
					$pprice_4 = round(($pprice_4*($nights-$parentCostObj->NightsFree))/$nights, 2);
				}
			}
			
			return $price;
		}
	} //end function avg_cost
	
	public function overlappingPromosAllowed($promoFoundAlready, $promo_row, $p_row,  $date) {
		if (!$promo_row) return false;
		
		//don't apply new promos if the vendor does not allow overlapping promos
		//don't apply promos that require arriving on or after the start date
		return  ((!$promoFoundAlready or $p_row->overlappingPromos) 
					and (!$promo_row->arriveOnStartDate or strtotime($promo_row->Begin) <= strtotime("$date")));
		
	}
	
	public function get_country_discount($agcode, $date) {
		#TA(20150311) apply monthly country discount
		$month = date("M", strtotime($date));
		switch($month) {
			case "Jan":
				$DiscountAmount = "DiscountAmount1";
				break;
			case "Feb":
				$DiscountAmount = "DiscountAmount2";
				break;
			case "Mar":
				$DiscountAmount = "DiscountAmount3";
				break;
			case "Apr":
				$DiscountAmount = "DiscountAmount4";
				break;
			case "May":
				$DiscountAmount = "DiscountAmount5";
				break;
			case "Jun":
				$DiscountAmount = "DiscountAmount6";
				break;
			case "Jul":
				$DiscountAmount = "DiscountAmount7";
				break;
			case "Aug":
				$DiscountAmount = "DiscountAmount8";
				break;
			case "Sep":
				$DiscountAmount = "DiscountAmount9";
				break;
			case "Oct":
				$DiscountAmount = "DiscountAmount10";
				break;
			case "Nov":
				$DiscountAmount = "DiscountAmount11";
				break;
			case "Dec":
				$DiscountAmount = "DiscountAmount12";
				break;
		}
		
		$row = DB::table('tblAgency')
					->select(DB::raw("$DiscountAmount as DiscountAmount, ExcludeCountryDiscount"))
					->where('AgCode', $agcode)
					->get()->first();
		if($row->ExcludeCountryDiscount==1) $row->DiscountAmount = 0;
		
		return $row->DiscountAmount;
	}
	
	/**
	 * To display the aging details
	 * @param string $current_ag Agency
	 * @param string $format Format {INVOICE or else]
	 * @return string
	 */
	public function aging($current_ag,$format) {
		$invoices = DB::table('tblInvoice')
					->where('Agency', $current_ag)
					->get();
		if ($invoices && count($invoices)) {
			for ($i = 0; $i < 5; $i++)
				$aged[$i] = 0;
			foreach ($invoices as $invoice) {
				if ($invoice->AmtDue > 0.001 || $invoice->Status == 'ADJ') {
					//add invoice to aging totals
					$dateDiffData = DB::select("select DATEDIFF('".$this->todays_date()."','$invoice->Date') as datediff");
					$datediff=$dateDiffData[0]->datediff;
					if ($datediff <= 30) $aged[0] += $invoice->AmtDue;
					elseif ($datediff > 30 and $datediff <= 60) $aged[1] += $invoice->AmtDue;
					elseif ($datediff > 60 and $datediff <= 90) $aged[2] += $invoice->AmtDue;
					elseif ($datediff > 90 and $datediff <= 120) $aged[3] += $invoice->AmtDue;
					else $aged[4] += $invoice->AmtDue;
				}
			}
			if ($format=="INVOICE") {
				$output = "<b><u>BALANCES DUE:</b></u><br><table width=600 class='aging'><tr><td><u>Days</u></td><td><u>Current (within 30 days)</u></td><td><u>31-60 Days</u></td><td><u>61-90 Days</u></td><td><u>91-120 Days</u></td><td><u>More than 121 Days</u></td></tr><tr><td><u>Amt Due</u></td>";
				for ($i = 0; $i < 5; $i++)
					$output .= "<td>\$".number_format($aged[$i], 2, '.', ',')."</td>";
				$output .= "</tr></table>";
			} else {
				$output="
				<BR>
				<H1>Aging</H1>
				<TABLE cellpadding=5>
				<TR><TH><U>Days</U></TH><TH><U>30-</U></TH><TH><U>31-60</U></TH><TH><U>61-90</U></TH><TH><U>91-120</U></TH><TH><U>121+</U></TH></TR>";
				$output.=("\t\t\t\t<TR><TH><U>Amt Due</U></TH>");
				for ($i = 0; $i < 5; $i++)
					$output .= "<td>\$".number_format($aged[$i], 2, '.', ',')."</td>";
				$output .= "</tr></table>";
			}
		}
		return $output;
	} //function aging
	
	/**
	 * To cancel the rule
	 * Added by Michel Mimar
	 * @param string $prodcode Product Code
	 * @param string $date Date
	 * @return string
	 */
	public function cancelRule($prodcode,$date) {
		$allotmentTable = "tblAllotment";
		
		$today=$this->todays_date();
		
		$nonRef = DB::table('tblProduct')
						->select('NonRefundable')
						->where('ProdCode', $prodcode)
						->get()->first();
		if ($nonRef->NonRefundable)
		{
			return "NonRefundable";
		}
		
		if ($allotmentTable == "tblAllotmentNEW")
		{
			$priceQuery = "SELECT d.UseCancel,d.CxlDaysPrior1,d.CxlType1,d.CxlAmount1,d.CxlDaysPrior2,d.CxlType2,d.CxlAmount2,d.CxlDaysPrior3,d.CxlType3,d.CxlAmount3 FROM ";
			$priceQuery .= "tblPriceNEW d, tblAllotmentNEW a WHERE d.UseCancel='1' and d.ProdCode='$prodcode' and d.AllotmentRecID=a.RecID";
			$priceQuery .= " AND a.AllotDate = '$date' AND (d.BookBeginDate = '0000-00-00' OR '$today' BETWEEN d.BookBeginDate AND d.BookEndDate)";
			$daysprior1 = "CxlDaysPrior1";
			$priceTable = "tblPriceNEW";
		}
		else
		{
			$itemInfoObject = DB::table('tblDefaultException')
								->select('days_prior1')
								->where([['ProdCode', $prodcode], ['Begin', '<=', $date], ['End', '>=', $date]])
								->get()->first();
			if (isset($itemInfoObject->days_prior1) && $itemInfoObject->days_prior1<>0)
			//if ($itemInfoObject->days_prior1!="")   //#FIX BY RCID 5 MAR 2011
				return "tblDefaultException";
			
			$daysprior1 = "days_prior1";
			$priceTable = "tblDefaultPrice";
			
			$itemInfoObject = DB::table('tblDefaultPrice')
								->select('days_prior1')
								->where([['ProdCode', $prodcode], ['Begin', '<=', $date], ['End', '>=', $date]])
								->get()->first();
		}
	   
		if (isset($itemInfoObject->$daysprior1) && $itemInfoObject->$daysprior1<>0) { #TA(20140107) FIX WHEN DAYS_PRIOR IS NOT 0
			//if ($itemInfoObject->days_prior1!="") //#FIX BY RCID 5 Mar 2011
			return $priceTable;
		}
		else {
			$itemInfoObject = DB::table('tblProduct')
								->select('days_prior1')
								->where('ProdCode', $prodcode)
								->get()->first();
			if ($itemInfoObject->days_prior1!="")
				return "tblProduct";
		}
	  
		 
		 return "No cancellation policy";
	}  
	
	/**
	 * To fix the amount
	 * @param float $amount Amount
	 * @param string $type Type [Nights or else]
	 * @return string
	 */
	public function fixamt($amount,$type) {
		if($type=="Nights") {
			$temp = array();
			$temp = explode(".",$amount);
			return $temp[0] . " " . $type . " penalty <i style='color:#b91515;font-size:14px;font-weight:bold;'>(Based on First Night Price)</i>";
		}
		else {
			return $amount . " " . $type . " penalty";
		}
	}
	
	/**
	 * To display the cancellation penalties
	 * @param integer $days1 Days1
	 * @param string $type1 Type1
	 * @param float $amount1 Amount1
	 * @param integer $days2 Days1
	 * @param string $type2 Type2
	 * @param float $amount2 Amount2
	 * @param integer $days3 Days3
	 * @param string $type3 Type3
	 * @param float $amount3 Amount3
	 * @return string
	 */
	public function Display_Cancel($days1,$type1,$amount1,$days2,$type2,$amount2,$days3,$type3,$amount3) {
		$displayCancel=0;
		$displayMessage = '';

		if((float)$amount1>0) {
			$displayMessage .= "\t<STRONG>CANCELLATION PENALTIES:</STRONG><UL>";
			$displayMessage .= "\t<LI>".$days1." days or less prior to travel - " . $this->fixamt($amount1,$type1)."\n";
			$displayCancel=1;
		}
		if((float)$amount2>0) {
			$displayMessage .= "\t<LI>".$days2." days or less prior to travel - " . $this->fixamt($amount2,$type2)."\n";
			$displayCancel=1;
		}

		if((float)$amount3>0) {
			$displayMessage .= "\t<LI>".$days3." days or less prior to travel - " . $this->fixamt($amount3,$type3)."\n";
			$displayCancel=1;
		}
		if($displayCancel!=0) {
			$displayMessage .= "</UL>";
			return $displayMessage;
		}
		//else {    return true;    }

	}
	
	/**
	 * To write the agency information to the file
	 * @param integer $res Reservation Number
	 * @param string $filename File Name
	 * @return void
	 */
	public function agencyConfirmation($res,$filename) {
		$bAgencyConf=true;
		$bIncludePrice=true;

		if (!isset($action) || $action <> "Email Confirmation") {
			// Retrieve info from tblBooking:
			$book = DB::table('tblBooking')
						->where('ResNum', $res)
						->get()->first();

			// Retrieve Pax info:
			$paxs = DB::table('tblPax')
						->where([['ResNum', $res], ['Status', '!=', 'CX']])
						->orderBy('PaxID')
						->get();
						
			if (count($paxs)) {
				$pax_ind = 0;
				$pax = $paxs[$pax_ind];
			}

			// Retrieve Item info:
			$items = DB::table('tblItem')
						->where('ResNum', $res)
						->whereNOTIn('Status', ['CX', 'PX', 'XR'])
						->orderBy('DepDate')
						->orderBy('putime')
						->orderBy('ItemID')
						->get();
			
			if (count($items)) {
				$item_ind = 0;
				$item = $items[$item_ind];
			}
						
			$departure_date = $item->DepDate;

			// Use the Product code from the item to determine which type of confirmation to use.
			$prod = DB::table('tblProduct')
						->where('ProdCode', $item->ProdCode)
						->get()->first();

			// Now retrieve that Confirmation Type
			if ($prod) {
				$ctype=$prod->ConfType;
			} else {
				$ctype=1;
			}
			
			$conf = DB::table('tblConfDetails')
						->where('ConfType', $ctype)
						->get()->first();

			// Pricing info:
			if ($bIncludePrice) {
				$price = new PriceStatement ($res);
			}

			if ($bAgencyConf) { // Agency-oriented conf
				$ag = DB::table('tblAgency')
						->where('AgCode', $book->AgCode)
						->get()->first();
			}

			$output = "<BR>";
			if (env('CO_NAME')=="Vagabond Tours")
				if ($company=="eta") $output .= $this->cf_header_string_eta();
				else $output .= $this->cf_header_string();
			else $output .= $this->cf_header_string();
			$output .= "<BR><BR><H3 align=center>Confirmation - Invoice</H3>\n";
			if ($book->Status == 'CX') $output .= "<H3 align=center>Canceled</H3>\n";
			if ($book->Status == 'QU') $output .= "<H3 align=center>Quotation</H3>\n";

			$output .= "\n<TABLE cellspacing=0 cellpadding=0 border=0><TR><TD>Date Booked:  " . $this->ISO_to_MMDDYYYY ($book->DateBooked) . "</TD><TD width=280></TD><TD width=100>Res #$res</TD>" . ($book->ClientRef ? "<TD>Reference #$book->ClientRef</TD>" : "" ) . "</TR>";
			if (strlen($book->InvNum)>0 or $book->InvNum<>0) $output .= "<tr><td colspan=2></td><td>Invoice Number: $book->InvNum</td></tr>";
			$output .= "</TABLE>\n";

			if ($bAgencyConf) { // Agency-oriented conf
				$output .= "To: $book->AgentName<BR>$ag->AgName<BR>";
			} else { // Pax-oriented conf
				$output .= "To: $pax->Salutation $pax->FirstName $pax->LastName<BR>";
			}
			$output .= "$book->SAdd1 <BR>\n";
			if ($book->SAdd2 != "") $output .= "    $book->SAdd2 <BR>";
			$output .= "    $book->SCity";
			if ($book->SState) $output.= ", $book->SState";
			if ($book->SZIP) $output.= ", $book->SZIP";
			if ($book->SCountry) $output .= ", $book->SCountry";
			$output .= "<HR>\n";

			// Output Pax info
			if (count($paxs)) {
				$output .= "Passengers Booked:<OL>\n";
				do {
					$output .= "<LI>$pax->Salutation $pax->FirstName $pax->LastName</LI>\n";
					$pax_ind += 1;
				} while (isset($paxs[$pax_ind]) && $pax = $paxs[$pax_ind]);  // Get the next pax
				$output .= "</OL>\n";
			}

			// Output Header
			$output .= nl2br ($conf->Header) . "<BR>\n";

			// Output Item info
			$output .= "\n<TABLE border=1 cellspacing=0>\n" .
					"\t<TR>\n" .
					"\t\t<TH>Item</TH><TH>Date</TH>\n\t\t<TH>Service</TH>\n"
			;
			
			$show_time = 0;
			if ($this->item_show_col ("PUTimeFlag", $res)) {
				$output .= "\t\t<TH>Time</TH>  <!-- PUTime -->\n";
				$show_time = 1;
			}

			$bDisplay_Misc1 = $this->item_show_col ("Misc1Flag", $res);
			if ($bDisplay_Misc1) {
				$output .= "\t\t<TH>Misc1</TH>\n";
			}

			$bDisplay_Misc2 = $this->item_show_col ("Misc2Flag", $res);
			if ($bDisplay_Misc2) {
				$output .= "\t\t<TH>Misc2</TH>\n";
			}

			$bDisplay_Occ = $this->item_show_col ("DisplayOccFlag", $res);
			if ($bDisplay_Occ) {
				$output .= "\t\t<TH>Occ</TH>\n";
			}

			$bDisplay_Nights = $this->item_show_col ("DisplayNightsFlag", $res);
			if ($bDisplay_Nights) {
				$output .= "\t\t<TH>Nights</TH>\n";
			}

			if ($bIncludePrice) {
				$output .= "\t\t<TH>Price</TH>\n";
			}

			$output .= "\t\t<TH>Qty</TH>\n";

			if ($bIncludePrice) {
				$output .= "\t\t<TH>Sub-total</TH>\n";
			}

			$output .= "\t\t<TH>Conf#</TH><TH>Status</TH>\n";
			$output .= "\t</TR>\n";

			$item_no = 1;
			$flight_output = "";
			$puloc_output = "";
			
			if (count($items)) {
				do {
					//determine if there are associated pax
					$px_output="";
					
					$pax_result = DB::table('tblAssocItems as A')
									->join('tblPax as P', 'A.PaxID', '=', 'P.PaxID')
									->where([['P.Status', '<>', 'CX'], ['A.ItemID', $item->ItemID]])
									->get();
					
					if (count($pax_result)>0) {
						//determine ttl # of pax
						$p_row = DB::table('tblPax')
								->select(DB::raw('count(*) as numpax'))
								->where([['resnum', $item->ResNum], ['status', '<>', 'CX']])
								->get()->first();
								
						$numpax=$p_row->numpax;
						if ($numpax<>count($pax_result)) {
							//output associated pax info
							foreach($pax_result as $pax_row) {
								$px_output.="&nbsp; &nbsp; &nbsp; &nbsp; $pax_row->LastName/$pax_row->FirstName<br>";
							}
						}
					} //there are assoc pax

					$prod = DB::table('tblProduct')
								->select(DB::raw('PUTimeFlag, DisplayOccFlag, DisplayNightsFlag, PULocFlag, FlightItinFlag, Flight1, Flight2, Flight3, Flight4, Misc1Flag, Misc2Flag'))
								->where('ProdCode', $item->ProdCode)
								->get()->first();

					$output .= "\t<TR>\n" .
							"\t\t<TD valign=top align=right>$item_no</TD>\n" .
							"\t\t<TD valign=top>" . $this->ISO_to_MMDDYYYY($item->DepDate) . "</TD>\n" .
							"\t\t<TD valin=top>$item->Description";
					if (strlen($px_output) > 0) {
						$output .= "<br>" . $px_output;
					}

					if ($show_time) {  // Display PUTime column?
						$output .= "\t\t<TD valign=top align=center>";
						if ($prod->PUTimeFlag) {
							$item->PUTime = substr ($item->PUTime, 0, 5);
							$output .= "$item->PUTime";
						} else {
							$output .= "&nbsp;";
						}
						$output .= "</TD>\n";
					}

					// Misc1:
					if ($bDisplay_Misc1) {
						$output .= "\t\t<TD valign=top align=center>";
						$item->Misc1 = $this->clean4html ($item->Misc1);
						if ($item->Misc1 == "") $item->Misc1 = "&nbsp;";
						if ($prod->Misc1Flag) {
							$output .= $item->Misc1;
						} else {
							$output .= "&nbsp;";
						}
						$output .= "</TD>\n";
					}

					// Misc2:
					if ($bDisplay_Misc2) {
						$output .= "\t\t<TD valign=top align=center>";
						$item->Misc2 = $this->clean4html ($item->Misc2);
						if ($item->Misc2 == "") $item->Misc2 = "&nbsp;";
						if ($prod->Misc2Flag) {
							$output .= $item->Misc2;
						} else {
							$output .= "&nbsp;";
						}
						$output .= "</TD>\n";
					}

					// Occupancy:
					if ($bDisplay_Occ) {
						$output .= "\t\t<TD valign=top align=center>";
						if ($prod->DisplayOccFlag) {
							$output .= $this->numeral_to_roomtype ($item->Occupancy, 0);
						} else {
							$output .= "&nbsp;";
						}
						$output .= "</TD>\n";
					}

					// Nights:
					if ($bDisplay_Nights) {
						$output .= "\t\t\t<TD valign=top align=center>";
						if ($prod->DisplayNightsFlag) {
							$output .= $this->clean4html ($item->Nights);
						} else {
							$output .= "&nbsp;";
						}
						$output .= "</TD>\n";
					}

					#TA(20140508) Added for agency who has ShowCommission unchecked
					if($ag->ShowCommission==0 && $item->AgCommFlag == 1) {
						$price_without_comm = $item->Price - (($item->Price * $book->AgCommLevel)/100);
						$item->Price = $price_without_comm ;
					}

					if ($bIncludePrice) {
						if ($item->Price != 0) {
							$output .= "<TD valign=top align=right>$item->Price</TD>\n";
						} else {
							$output .= "<TD valign=top align=center>&nbsp;</TD>\n";
						}
					}
					$output .= "\t\t<TD valign=top align=right>$item->Qty</TD>\n";
					if ($bIncludePrice) {
						if ($item->Price != 0) {
							if ($item->PricingType == 1) { // Per Person - Hotel Only
								$output .= "\t\t<TD valign=top align=right>\$" . number_format ($item->Nights * $item->Occupancy * $item->Qty * $item->Price, 2) . "</TD>\n";
							} else {                       // Per Room **OR** Per Person - Inclusive Package
								$output .= "\t\t<TD valign=top align=right>\$" . number_format ($item->Nights * $item->Qty * $item->Price, 2) . "</TD>\n";
							}
						} else {
							$output .= "<TD valign=top align=center>&nbsp;</TD>\n";
						}
					}

					$output .= "\t\t<TD valign=top>$item->SupplierRef &nbsp;</TD>";
					//Status
					if ($item->Status=="RQ") {
						$output .= "\t\t<TD valign=top><font color=red>On Request</font></TD>";
					} elseif ($item->Status=="CF" or $item->Status=="OK" or $item->Status=="BK") {
						$output .= "\t\t<TD valign=top>Confirmed</TD>";
					} elseif ($item->Status=="UC") {
						$output .= "\t\t<TD valign=top><font color=red>UnConfirmed</font></TD>";
					} elseif ($item->Status=="XX" and $book->Status=="CX") {
						$output .= "\t\t<TD valign=top><font color=red>Canceled<font></TD>";
					} elseif ($item->Status=="XX" and $book->Status=="QU") {
						$output .= "\t\t<TD valign=top><font color=red>Quote<font></TD>";
					} elseif ($item->Status=="PR" or $item->Status=="PI") {
						$output .= "\t\t<TD valign=top><font color=red>Pending Request<font></TD>";
					} elseif ($item->Status=="XR" or $item->Status=="XB") {
						$output .= "\t\t<TD valign=top><font color=red>Request Cxl<font></TD>";
					} elseif ($item->Status=="PX") {
						$output .= "\t\t<TD valign=top><font color=red>Pending Cxl<font></TD>";
					} elseif ($item->Status=="NW") {
						$output .= "\t\t<TD valign=top><font color=red>Pending<font></TD>";
					}
					$output .= "\t</TR>\n";


					if ($prod->PULocFlag) {
						$puloc = DB::table('tblPULoc')
									->select('Description')
									->where('PULoc', $item->PULoc)
									->get()->first();
						if ($puloc) {
							$puloc_output .= "\tItem $item_no Pick-up location ($item->PULoc): $puloc->Description<BR>\n";
						}
					}

					//** Added by Michel Mimar **//
					$cancel_table = $this->cancelRule($item->ProdCode,$item->DepDate);
					//echo "$item->ProdCode,$item->DepDate, $cancel_table"; exit;
					$display_cxl_policy="NO";
					if ($cancel_table == "NonRefundable")
					{
						$displayCancel = "<LI><STRONG>CANCELLATION PENALTIES:</STRONG><UL>";
						$displayCancel .= "\t<LI>Non refundable - full cancellation penalty at any time.\n</LI></UL>";
					}
					else if ($cancel_table <> "No cancellation policy") {
						$cancel_row = DB::table($cancel_table)
										->select(DB::raw('days_prior1,type1,amount1,days_prior2,type2,amount2,days_prior3,type3,amount3'))
										->where('ProdCode', $item->ProdCode);
										
						if ($cancel_table=="tblDefaultException" || $cancel_table=="tblDefaultPrice") {
							$cancel_row = $cancel_row->where([['Begin', '<=', $item->DepDate], ['End', '>=', $item->DepDate]]);
						}
						else if ($cancel_table=="tblPriceNEW") { //not used
							$cancel_query = "SELECT days_prior1,type1,amount1,days_prior2,type2,amount2,days_prior3,type3,amount3 FROM ";
							$cancel_query .= $cancel_table.", tblAllotmentNEW WHERE ProdCode='$prodcode'";
							$cancel_query .= " AND AllotDate = '$date'";
						}

						$cancel_row = $cancel_row->get()->first();
						$display_cxl_policy="YES";
					}

					//** End Michel Mimar **//

					if ($prod->FlightItinFlag && ($prod->Flight1 || $prod->Flight2 || $prod->Flight3 || $prod->Flight4)) {
						$flight_output .= "\tInformation for Item $item_no: (flight times are subject to schedule changes)<UL>\n";
						if ($prod->Flight1) $flight_output .= "\t<LI> $prod->Flight1\n";
						if ($prod->Flight2) $flight_output .= "\t<LI> $prod->Flight2\n";
						if ($prod->Flight3) $flight_output .= "\t<LI> $prod->Flight3\n";
						if ($prod->Flight4) $flight_output .= "\t<LI> $prod->Flight4\n";
						if ($cancel_table == "NonRefundable")
						{
							$flight_output .= $displayCancel;
						}
						else
						if ($display_cxl_policy=="YES") $flight_output .= $this->Display_Cancel($cancel_row->days_prior1,$cancel_row->type1,$cancel_row->amount1,$cancel_row->days_prior2,$cancel_row->type2,$cancel_row->amount2,$cancel_row->days_prior3,$cancel_row->type3,$cancel_row->amount3);
						
						if ($flight_output) $flight_output .= "</UL>";
					}
					$item_no ++;
					
					$item_ind++;
				} while (isset($items[$item_ind]) && $item = $items[$item_ind]);  // Get the next item
			}
			$output .= "</TABLE><BR>\n";

			$output .= $puloc_output . "<BR>";
			$output .= $flight_output;

			// Output Acct Rec info
			if ($bIncludePrice) {

				if($ag->ShowCommission==1) {
					$output .= "\t<TABLE border=0><TR><TD width=150>Total: \$" . number_format ($price->gross_total, 2) . "</TD>" .
							"<TD width=150>Paid: \$" . number_format ($price->paid, 2) . "</TD><TD nowrap><B>Balance due: \$" .
							number_format ($price->gross_due, 2) . "</B></TD></TR></TABLE>"
					;
				} else {
					$output .= "\t<TABLE border=0><TR><TD width=200>Total: \$" . number_format ($price->net_due, 2) . "</TD>" .
							"<TD width=150>Paid: \$" . number_format ($price->paid, 2) . "</TD><TD nowrap><B>Balance due: \$" .
							number_format ($price->net_due, 2) . "</B></TD></TR></TABLE>"
					;
				}

				//Payment Summary Info
				if ($bIncludePrice) {
					$qrows = DB::table('tblAcctRec as A')
								->select(DB::raw('A.*, P.LastName, P.FirstName, F.Description'))	
								->join('tblPax as P', 'P.PaxID', '=', 'A.PaxID')
								->join('tblFOP as F', 'F.FOPCode', '=', 'A.FOP')
								->where('A.resnum', $res)
								->get();
					
					$output .= "\t<TABLE border=0 cellpadding=1><TR><TD nowrap colspan=5><b>Payment Summary</b></TD></TR>";
					foreach ($qrows as $qrow) {
						$dt=$this->ISO_to_MMDDYYYY($qrow->DateRcd);
						if ($qrow->FOP == "VI" or $qrow->FOP == "MC" or $qrow->FOP == "DI" or $qrow->FOP == "DC" or $qrow->FOP == "AX" or $qrow->FOP =="DP" or $qrow->FOP =="DA") {
							$ccnum=PaymRepo::cc_decrypt($qrow->CCNum);
							if (strlen($ccnum > 0)) {
								$cc="xxxxxxxxxxxx".substr($ccnum,strlen($ccnum)-4);
							} else {
								$cc="";
							}
							$output .= "<TR><TD>$dt</TD><TD>$qrow->Description</TD><TD>$cc</TD><TD align=right>$$qrow->Amount</TD><td width=10>&nbsp;</td><TD>$qrow->LastName/$qrow->FirstName</td></TR>";
						} else {
							$output .= "<TR><TD>$dt</TD><TD>$qrow->Description</TD><TD>$qrow->CheckNum</TD><TD align=right>$$qrow->Amount</TD><td width=10>&nbsp;</td><td>$qrow->LastName/$qrow->FirstName</td></TR>";
						}
					}
					$output .=  "</TABLE><br>";
				}

				// Payment Schedule info:
				$book_sec = $this->ISO_to_system_time ($book->DateBooked);  // Represents the date booked in system time.
				$departure_date_sec = $this->ISO_to_system_time ($departure_date);
				switch ($conf->PaySchedType) {
					case 0:
						if ($conf->DepositDays) {
							$depos_sec = $book_sec + $conf->DepositDays * 86400;  // Represents the deposit due-date in system time.
							if ($book_sec > ($departure_date_sec - $conf->FinalPayDays * 86400)) { // If date of booking is past final payment deadline
								$full_date_sec = $book_sec + $conf->PastDeadlineDays * 86400;
								$output .= "Full payment is due by " . date ("m-d-Y", $full_date_sec) . ".";
							} else {
								$depos_date = date ("m-d-Y", $depos_sec);
								$output .= "A deposit of \$$conf->DeposAmt per person is due by $depos_date.  Final payment is\n" .
										"due by " .  date ("m-d-Y", $departure_date_sec - $conf->FinalPayDays * 86400) . "."
								;
							}
						}
						break;
					case 1:
						if ($conf->DepositDays) {
							$depos_sec = $book_sec + $conf->DepositDays * 86400;  // Represents the deposit due-date in system time.
							if ($book_sec > $this->ISO_to_system_time ($conf->Deadline)) {  // If date of booking is past final payment deadline
								$full_date_sec = $book_sec + $conf->PastDeadlineDays * 86400;
								$output .= "Full payment is due by " . date ("m-d-Y", $full_date_sec) . ".";
							} else {
								$depos_date = date ("m-d-Y", $depos_sec);
								$output .= "A deposit of \$$conf->DeposAmt per person is due by $depos_date.  Final payment is due by " .
										$this->ISO_to_MMDDYYYY ($conf->Deadline) . ".";
							}
						}
						break;
					case 2:
						//$full_sec = $book_sec + $conf->FullPayDays * 86400; //commented out *TA(22nd May, 2013)
						$reslt = DB::table('tblItem')
									->select(DB::raw('MAX(DepDate) as DepDate'))
									->where('ResNum', $res)
									->get()->first();
						
						$dep_date = $reslt->DepDate;
						$dep_date_sec = strtotime($dep_date);
						//$dep_date = $departure_date;
						//$dep_date_sec = $departure_date_sec;
						
						if($ag->PmtStatus=="PP") {
							$full_sec = $departure_date_sec - 14 * 86400; //for pre-pay agency 14 days before arrival
						} elseif($ag->PmtStatus=="DB") {
							$days = 30;
							$month = date('m', strtotime($dep_date));
							$dep_day = date('d', strtotime($dep_date));
								
							//for summer season (Jun to Sept)
							if($month>'05' && $month<'10') {                                
								if($dep_day>='01' && $dep_day<'16') {
									$inv_day = '16';
									$inv_mon = $month;
									$inv_year = date('Y');
								} else {
									$inv_day = '01';
									$inv_mon = date('m', strtotime("$dep_date +1 month"));
									$inv_year = date('Y');
								}
								$invoiced_date = strtotime("$inv_year-$inv_mon-$inv_day");
							} else { //regular monthly invoice (Oct to May)
									
								$inv_day = '01';
								$inv_mon = date('m', strtotime("$dep_date +1 month"));
								$inv_year = date('Y');
								$invoiced_date = strtotime("$inv_year-$inv_mon-$inv_day"); 
							}
								
							//for direct billing agency the day we invoiced them + 30days
							$full_sec = $invoiced_date + $days * 86400; 
								
						} else {
							$full_sec = $book_sec + $conf->FullPayDays * 86400;
						}
						$full_date = date ("m-d-Y", $full_sec);
						$output .= "Payment is due on $full_date.";
						break;
					case 3:
						$datedue=$departure_date_sec - $conf->FullPayDays * 86400;
						$datedue=date("m-d-Y",$datedue);
						$output .= "Full payment is due $conf->FullPayDays prior to travel: $datedue";
						break;
					case 4:
						$output .= "Full payment is due $conf->FullPayDays after travel begins";
						break;
				}
			} // end if (bIncludePrice)

			$output .= "<BR><BR>" . nl2br ($conf->Body) . "<BR><BR>Comments: $book->ExternalRem<HR>";

			if ($bAgencyConf) { // Agency-oriented conf

				if($ag->ShowCommission==0) $total_price = $price->net_due;
				else $total_price = $price->gross_due;

				$output .= nl2br ($conf->Footer) . "<BR>$res" . ($bIncludePrice ? " / \$" . number_format ($total_price, 2) : "");

				if ($bIncludePrice && $price->ag_comm_level > 0) {

					if($ag->ShowCommission==1) {
						$output .= "\t<TABLE border=0><TR><TD width=200>Commission rate: " . number_format ($price->ag_comm_level, 2) . "%</TD>" .
							"<TD width=150>Commission: \$" . number_format ($price->ag_comm_amt, 2) .
							"</TD><TD nowrap><B>Net due: \$" . number_format ($price->net_due, 2) . "</B></TD></TR></TABLE>" .
							"Res: $res"
						;
					} else {
						$output .= "\t<TABLE border=0><TR><TD width=200>Commission rate: 0%</TD>" .
							"<TD width=150>Commission: 0</TD><TD><B>Net due: \$" . number_format ($price->net_due, 2) . "</B></TD></TR></TABLE>" .
							"Res: $res";
					}

				}
			} else { // Pax-oriented conf
				$output .= nl2br ($conf->Footer) . "<BR>$res" . ($bIncludePrice ? " / \$" . number_format ($price->gross_due, 2) : "");
			}
			file_put_contents("$filename-$res.htm",$output);
		}
	}
	
	/**
	 * To calculate both tax and price
	 */
	public function calc_tax_n_price($ProdCode, $CurrentDate) {
		
		//check if using adhoc tax
		$UseAdhocTax = false;
			
		$row = DB::table('tblVendor as v')
					->select(DB::raw('v.UseAdhocTax, v.AdhocTax1, v.AdhocTaxType1, v.AdhocTax2, v.AdhocTaxType2, p.isHotel'))
					->join('tblProduct as p', 'p.Vendor', 'v.VendorID')
					->where('p.ProdCode', $ProdCode)
					->get()->first();
					
		$UseAdhocTax = $row->UseAdhocTax;
		$AdhocTax1 = $row->AdhocTax1;
		$AdhocTaxType1 = $row->AdhocTaxType1;
		$AdhocTax2 = $row->AdhocTax2;
		$AdhocTaxType2 = $row->AdhocTaxType2;
		$IsHotel = $row->isHotel;

		#TA(20141208) get the markup for child price calculation
		$mrow = DB::table('tblTeamPlayersMarkup')
					->select('amount')
					->where('ProdCode', $ProdCode)
					->get()->first();

		if (!$mrow)
		{
				$MarkupCityCode=substr($ProdCode,0,3);
				$mrow = DB::table('tblMarkup')
								->where('CityCode', $MarkupCityCode)
								->get()->first();
				if (!$mrow) {
					$mrow = DB::table('tblMarkup')
								->where('CityCode', '000')
								->get()->first();
				}			
		}

		//all pricing tables to be updated
		$tables = array('promo' => 'tblPromoPrice', 
						'except' => 'tblDefaultException', 
						'default' => 'tblDefaultPrice');
		
		foreach ($tables as $type => $table) {
		
			//recalcualte all net+tax and selling prices
			$rows = DB::table("$table as d")
						->select(DB::raw('d.*, p.*'))
						->leftJoin('tblPriceDays as p', function($join) use ($type)
                         {
                             $join->on('p.id', '=', 'd.PriceID');
                             $join->on('p.type', '=', DB::raw("'$type'"));
                         })
						->where([['d.ProdCode', $ProdCode], ['d.End', '>=', $CurrentDate]])
						->get();
			
			foreach ($rows as $row) {
				//print_r($row); exit;
				$PriceID = $row->PriceID;
				$m_up = $row->markup;
				
				$Begin = $row->Begin;
				$End = $row->End;

				$OurCost1 = $row->OurCost1 ? $row->OurCost1 : 'NULL';
				$OurCost2 = $row->OurCost2 ? $row->OurCost2 : 'NULL';
				$OurCost3 = $row->OurCost3 ? $row->OurCost3 : 'NULL';
				$OurCost4 = $row->OurCost4 ? $row->OurCost4 : 'NULL';
				
				$ChildTax = $row->ChildTax ? $row->ChildTax : 'NULL';	
				$ChildTax1 = $row->ChildCost1 ? $row->ChildCost1 : 'NULL';	
				$ChildTax2 = $row->ChildCost2 ? $row->ChildCost2 : 'NULL';	
				$ChildTax3 = $row->ChildCost3 ? $row->ChildCost3 : 'NULL';
				
				$Price1 = $row->Price1 ? $row->Price1 : 'NULL';
				$Price2 = $row->Price2 ? $row->Price2 : 'NULL';
				$Price3 = $row->Price3 ? $row->Price3 : 'NULL';
				$Price4 = $row->Price4 ? $row->Price4 : 'NULL';	
				
				$ChildPrice = $row->ChildPrice ? $row->ChildPrice : 'NULL';
				$ChildPrice1 = $row->ChildPrice1 ? $row->ChildPrice1 : 'NULL';
				$ChildPrice2 = $row->ChildPrice2 ? $row->ChildPrice2 : 'NULL';
				$ChildPrice3 = $row->ChildPrice3 ? $row->ChildPrice3 : 'NULL';
				
				$OurNet1 = $row->OurNet1;
				$OurNet2 = $row->OurNet2;
				$OurNet3 = $row->OurNet3;
				$OurNet4 = $row->OurNet4;
				
				$OurNetFee1 = $row->OurNetFee1;
				$OurNetFee2 = $row->OurNetFee2;
				$OurNetFee3 = $row->OurNetFee3;
				$OurNetFee4 = $row->OurNetFee4;
				
				$ChildNetFee = $row->ChildNetFee;
				$ChildNetFee1 = $row->ChildNetFee1;
				$ChildNetFee2 = $row->ChildNetFee2;
				$ChildNetFee3 = $row->ChildNetFee3;	

				$net_mon1 = $row->net_mon1;
				$net_mon2 = $row->net_mon2;
				$net_mon3 = $row->net_mon3;
				$net_mon4 = $row->net_mon4;
				$net_tue1 = $row->net_tue1;
				$net_tue2 = $row->net_tue2;
				$net_tue3 = $row->net_tue3;
				$net_tue4 = $row->net_tue4;
				$net_wed1 = $row->net_wed1;
				$net_wed2 = $row->net_wed2;
				$net_wed3 = $row->net_wed3;
				$net_wed4 = $row->net_wed4;
				$net_thu1 = $row->net_thu1;
				$net_thu2 = $row->net_thu2;
				$net_thu3 = $row->net_thu3;
				$net_thu4 = $row->net_thu4;
				$net_fri1 = $row->net_fri1;
				$net_fri2 = $row->net_fri2;
				$net_fri3 = $row->net_fri3;
				$net_fri4 = $row->net_fri4;
				$net_sat1 = $row->net_sat1;
				$net_sat2 = $row->net_sat2;
				$net_sat3 = $row->net_sat3;
				$net_sat4 = $row->net_sat4;
				$net_sun1 = $row->net_sun1;
				$net_sun2 = $row->net_sun2;
				$net_sun3 = $row->net_sun3;
				$net_sun4 = $row->net_sun4;
				
				$tax_mon1 = $row->tax_mon1;
				$tax_mon2 = $row->tax_mon2;
				$tax_mon3 = $row->tax_mon3;
				$tax_mon4 = $row->tax_mon4;
				$tax_tue1 = $row->tax_tue1;
				$tax_tue2 = $row->tax_tue2;
				$tax_tue3 = $row->tax_tue3;
				$tax_tue4 = $row->tax_tue4;
				$tax_wed1 = $row->tax_wed1;
				$tax_wed2 = $row->tax_wed2;
				$tax_wed3 = $row->tax_wed3;
				$tax_wed4 = $row->tax_wed4;
				$tax_thu1 = $row->tax_thu1;
				$tax_thu2 = $row->tax_thu2;
				$tax_thu3 = $row->tax_thu3;
				$tax_thu4 = $row->tax_thu4;
				$tax_fri1 = $row->tax_fri1;
				$tax_fri2 = $row->tax_fri2;
				$tax_fri3 = $row->tax_fri3;
				$tax_fri4 = $row->tax_fri4;
				$tax_sat1 = $row->tax_sat1;
				$tax_sat2 = $row->tax_sat2;
				$tax_sat3 = $row->tax_sat3;			
				$tax_sat4 = $row->tax_sat4;
				$tax_sun1 = $row->tax_sun1;
				$tax_sun2 = $row->tax_sun2;
				$tax_sun3 = $row->tax_sun3;
				$tax_sun4 = $row->tax_sun4;
				
				$mon1 = $row->mon1;
				$mon2 = $row->mon2;
				$mon3 = $row->mon3;
				$mon4 = $row->mon4;
				$tue1 = $row->tue1;
				$tue2 = $row->tue2;
				$tue3 = $row->tue3;
				$tue4 = $row->tue4;
				$wed1 = $row->wed1;
				$wed2 = $row->wed2;
				$wed3 = $row->wed3;
				$wed4 = $row->wed4;
				$thu1 = $row->thu1;
				$thu2 = $row->thu2;
				$thu3 = $row->thu3;
				$thu4 = $row->thu4;
				$fri1 = $row->fri1;
				$fri2 = $row->fri2;
				$fri3 = $row->fri3;
				$fri4 = $row->fri4;
				$sat1 = $row->sat1;
				$sat2 = $row->sat2;
				$sat3 = $row->sat3;
				$sat4 = $row->sat4;
				$sun1 = $row->sun1;
				$sun2 = $row->sun2;
				$sun3 = $row->sun3;
				$sun4 = $row->sun4;
				
				//recalculate net+tax + additional charge first		
				if ($UseAdhocTax) {
					if ($OurNetFee1 > 0) $OurCost1=$this->netplusadhoctax($OurNetFee1,$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2); 
					elseif ($OurNet1 > 0) $OurCost1=$this->netplusadhoctax($OurNet1,$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($OurNetFee2 > 0) $OurCost2=$this->netplusadhoctax($OurNetFee2,$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					elseif ($OurNet2 > 0) $OurCost2=$this->netplusadhoctax($OurNet2,$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($OurNetFee3 > 0) $OurCost3=$this->netplusadhoctax($OurNetFee3,$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					elseif ($OurNet3 > 0) $OurCost3=$this->netplusadhoctax($OurNet3,$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($OurNetFee4 > 0) $OurCost4=$this->netplusadhoctax($OurNetFee4,$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					elseif ($OurNet4 > 0) $OurCost4=$this->netplusadhoctax($OurNet4,$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);

					if ($ChildNetFee > 0) $ChildTax=$this->netplusadhoctax($ChildNetFee,$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					
					if ($ChildNetFee1 > 0) $ChildTax1=$this->netplusadhoctax($ChildNetFee1,$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($ChildNetFee2 > 0) $ChildTax2=$this->netplusadhoctax($ChildNetFee2,$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($ChildNetFee3 > 0) $ChildTax3=$this->netplusadhoctax($ChildNetFee3,$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					
					if ($net_mon1 > 0) $tax_mon1=$this->netplusadhoctax($net_mon1,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_mon2 > 0) $tax_mon2=$this->netplusadhoctax($net_mon2,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_mon3 > 0) $tax_mon3=$this->netplusadhoctax($net_mon3,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_mon4 > 0) $tax_mon4=$this->netplusadhoctax($net_mon4,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_tue1 > 0) $tax_tue1=$this->netplusadhoctax($net_tue1,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_tue2 > 0) $tax_tue2=$this->netplusadhoctax($net_tue2,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_tue3 > 0) $tax_tue3=$this->netplusadhoctax($net_tue3,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_tue4 > 0) $tax_tue4=$this->netplusadhoctax($net_tue4,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_wed1 > 0) $tax_wed1=$this->netplusadhoctax($net_wed1,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_wed2 > 0) $tax_wed2=$this->netplusadhoctax($net_wed2,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_wed3 > 0) $tax_wed3=$this->netplusadhoctax($net_wed3,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_wed4 > 0) $tax_wed4=$this->netplusadhoctax($net_wed4,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_thu1 > 0) $tax_thu1=$this->netplusadhoctax($net_thu1,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_thu2 > 0) $tax_thu2=$this->netplusadhoctax($net_thu2,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_thu3 > 0) $tax_thu3=$this->netplusadhoctax($net_thu3,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_thu4 > 0) $tax_thu4=$this->netplusadhoctax($net_thu4,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_fri1 > 0) $tax_fri1=$this->netplusadhoctax($net_fri1,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_fri2 > 0) $tax_fri2=$this->netplusadhoctax($net_fri2,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_fri3 > 0) $tax_fri3=$this->netplusadhoctax($net_fri3,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_fri4 > 0) $tax_fri4=$this->netplusadhoctax($net_fri4,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_sat1 > 0) $tax_sat1=$this->netplusadhoctax($net_sat1,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_sat2 > 0) $tax_sat2=$this->netplusadhoctax($net_sat2,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_sat3 > 0) $tax_sat3=$this->netplusadhoctax($net_sat3,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_sat4 > 0) $tax_sat4=$this->netplusadhoctax($net_sat4,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_sun1 > 0) $tax_sun1=$this->netplusadhoctax($net_sun1,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_sun2 > 0) $tax_sun2=$this->netplusadhoctax($net_sun2,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_sun3 > 0) $tax_sun3=$this->netplusadhoctax($net_sun3,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
					if ($net_sun4 > 0) $tax_sun4=$this->netplusadhoctax($net_sun4,$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				}
				else {
					if ($OurNetFee1 > 0) $OurCost1=$this->netplustax($OurNetFee1,$ProdCode,"NO");
					elseif ($OurNet1 > 0) $OurCost1=$this->netplustax($OurNet1,$ProdCode,"NO");
					if ($OurNetFee2 > 0) $OurCost2=$this->netplustax($OurNetFee2,$ProdCode,"NO");
					elseif ($OurNet2 > 0) $OurCost2=$this->netplustax($OurNet2,$ProdCode,"NO");
					if ($OurNetFee3 > 0) $OurCost3=$this->netplustax($OurNetFee3,$ProdCode,"NO");
					elseif ($OurNet3 > 0) $OurCost3=$this->netplustax($OurNet3,$ProdCode,"NO");
					if ($OurNetFee4 > 0) $OurCost4=$this->netplustax($OurNetFee4,$ProdCode,"NO");
					elseif ($OurNet4 > 0) $OurCost4=$this->netplustax($OurNet4,$ProdCode,"NO");
					
					if ($ChildNetFee > 0) $ChildTax=$this->netplustax($ChildNetFee,$ProdCode,"NO");
					
					if ($ChildNetFee1 > 0) $ChildTax1=$this->netplustax($ChildNetFee1,$ProdCode,"NO");
					if ($ChildNetFee2 > 0) $ChildTax2=$this->netplustax($ChildNetFee2,$ProdCode,"NO");
					if ($ChildNetFee3 > 0) $ChildTax3=$this->netplustax($ChildNetFee3,$ProdCode,"NO");
					
					if ($net_mon1 > 0) $tax_mon1=$this->netplustax($net_mon1,$ProdCode,"YES");
					if ($net_mon2 > 0) $tax_mon2=$this->netplustax($net_mon2,$ProdCode,"YES");
					if ($net_mon3 > 0) $tax_mon3=$this->netplustax($net_mon3,$ProdCode,"YES");
					if ($net_mon4 > 0) $tax_mon4=$this->netplustax($net_mon4,$ProdCode,"YES");
					if ($net_tue1 > 0) $tax_tue1=$this->netplustax($net_tue1,$ProdCode,"YES");
					if ($net_tue2 > 0) $tax_tue2=$this->netplustax($net_tue2,$ProdCode,"YES");
					if ($net_tue3 > 0) $tax_tue3=$this->netplustax($net_tue3,$ProdCode,"YES");
					if ($net_tue4 > 0) $tax_tue4=$this->netplustax($net_tue4,$ProdCode,"YES");
					if ($net_wed1 > 0) $tax_wed1=$this->netplustax($net_wed1,$ProdCode,"YES");
					if ($net_wed2 > 0) $tax_wed2=$this->netplustax($net_wed2,$ProdCode,"YES");
					if ($net_wed3 > 0) $tax_wed3=$this->netplustax($net_wed3,$ProdCode,"YES");
					if ($net_wed4 > 0) $tax_wed4=$this->netplustax($net_wed4,$ProdCode,"YES");
					if ($net_thu1 > 0) $tax_thu1=$this->netplustax($net_thu1,$ProdCode,"YES");
					if ($net_thu2 > 0) $tax_thu2=$this->netplustax($net_thu2,$ProdCode,"YES");
					if ($net_thu3 > 0) $tax_thu3=$this->netplustax($net_thu3,$ProdCode,"YES");
					if ($net_thu4 > 0) $tax_thu4=$this->netplustax($net_thu4,$ProdCode,"YES");
					if ($net_fri1 > 0) $tax_fri1=$this->netplustax($net_fri1,$ProdCode,"YES");
					if ($net_fri2 > 0) $tax_fri2=$this->netplustax($net_fri2,$ProdCode,"YES");
					if ($net_fri3 > 0) $tax_fri3=$this->netplustax($net_fri3,$ProdCode,"YES");
					if ($net_fri4 > 0) $tax_fri4=$this->netplustax($net_fri4,$ProdCode,"YES");
					if ($net_sat1 > 0) $tax_sat1=$this->netplustax($net_sat1,$ProdCode,"YES");
					if ($net_sat2 > 0) $tax_sat2=$this->netplustax($net_sat2,$ProdCode,"YES");
					if ($net_sat3 > 0) $tax_sat3=$this->netplustax($net_sat3,$ProdCode,"YES");
					if ($net_sat4 > 0) $tax_sat4=$this->netplustax($net_sat4,$ProdCode,"YES");
					if ($net_sun1 > 0) $tax_sun1=$this->netplustax($net_sun1,$ProdCode,"YES");
					if ($net_sun2 > 0) $tax_sun2=$this->netplustax($net_sun2,$ProdCode,"YES");
					if ($net_sun3 > 0) $tax_sun3=$this->netplustax($net_sun3,$ProdCode,"YES");
					if ($net_sun4 > 0) $tax_sun4=$this->netplustax($net_sun4,$ProdCode,"YES");
				}
				
				//convert currency for services
				if (isset($IsHotel) && $IsHotel == 0) {
					$OrigOurCost1 = $OurCost1;
					$OrigOurCost2 = $OurCost2;
					$OrigOurCost3 = $OurCost3;
					$OrigOurCost4 = $OurCost4;
					$OrigChildTax = $ChildTax;
					$OurCost1=$this->convertcurrency($OurCost1,$ProdCode);
					$OurCost2=$this->convertcurrency($OurCost2,$ProdCode);
					$OurCost3=$this->convertcurrency($OurCost3,$ProdCode);
					$OurCost4=$this->convertcurrency($OurCost4,$ProdCode);
					$ChildTax=$this->convertcurrency($ChildTax,$ProdCode,"NO");
				}
				
				//re calcuate selling prices
				if ($m_up==1) {
					$rate1=$Price1;
					$rate2=$Price2;
					$rate3=$Price3;
					$rate4=$Price4;

					$child_price=$ChildPrice;
					
					$ch_price1 = $ChildPrice1;
					$ch_price2 = $ChildPrice2;
					$ch_price3 = $ChildPrice3;
					
					$ratemon1=$mon1;
					$ratemon2=$mon2;
					$ratemon3=$mon3;
					$ratemon4=$mon4;
					$ratetue1=$tue1;
					$ratetue2=$tue2;
					$ratetue3=$tue3;
					$ratetue4=$tue4;
					$ratewed1=$wed1;
					$ratewed2=$wed2;
					$ratewed3=$wed3;
					$ratewed4=$wed4;
					$ratethu1=$thu1;
					$ratethu2=$thu2;
					$ratethu3=$thu3;
					$ratethu4=$thu4;
					$ratefri1=$fri1;
					$ratefri2=$fri2;
					$ratefri3=$fri3;
					$ratefri4=$fri4;
					$ratesat1=$sat1;
					$ratesat2=$sat2;
					$ratesat3=$sat3;
					$ratesat4=$sat4;
					$ratesun1=$sun1;
					$ratesun2=$sun2;
					$ratesun3=$sun3;
					$ratesun4=$sun4;
					
					$this->calc_price($ProdCode,$OurCost1,$OurCost2,$OurCost3,$OurCost4,$rate1,$rate2,$rate3,$rate4,0,$mrow,$ChildTax,$child_price,$tax_mon1,$tax_mon2,$tax_mon3,$tax_mon4,$tax_tue1,$tax_tue2,$tax_tue3,$tax_tue4,$tax_wed1,$tax_wed2,$tax_wed3,$tax_wed4,$tax_thu1,$tax_thu2,$tax_thu3,$tax_thu4,$tax_fri1,$tax_fri2,$tax_fri3,$tax_fri4,$tax_sat1,$tax_sat2,$tax_sat3,$tax_sat4,$tax_sun1,$tax_sun2,$tax_sun3,$tax_sun4,$ratemon1,$ratemon2,$ratemon3,$ratemon4,$ratetue1,$ratetue2,$ratetue3,$ratetue4,$ratewed1,$ratewed2,$ratewed3,$ratewed4,$ratethu1,$ratethu2,$ratethu3,$ratethu4,$ratefri1,$ratefri2,$ratefri3,$ratefri4,$ratesat1,$ratesat2,$ratesat3,$ratesat4,$ratesun1,$ratesun2,$ratesun3,$ratesun4,$Begin,$End);
					$Price1=$rate1;
					$Price2=$rate2;
					$Price3=$rate3;
					$Price4=$rate4;
					
					$ChildPrice=$child_price;

					if($ChildTax1 <> "NULL") $ch_price1=$this->calc_rate("hotel",$ChildTax1,$mrow); elseif (strlen($ChildTax1)>1) $ChildPrice1="NULL"; else $ChildPrice1=0;
					if($ChildTax2 <> "NULL") $ch_price2=$this->calc_rate("hotel",$ChildTax2,$mrow); elseif (strlen($ChildTax2)>1) $ChildPrice1="NULL"; else $ChildPrice1=0;
					if($ChildTax3 <> "NULL") $ch_price3=$this->calc_rate("hotel",$ChildTax3,$mrow); elseif (strlen($ChildTax3)>1) $ChildPrice1="NULL"; else $ChildPrice1=0;
					$ChildPrice1 = $ch_price1;
					$ChildPrice2 = $ch_price2;
					$ChildPrice3 = $ch_price3;
					
					$mon1=$ratemon1;
					$mon2=$ratemon2;
					$mon3=$ratemon3;
					$mon4=$ratemon4;
					$tue1=$ratetue1;
					$tue2=$ratetue2;
					$tue3=$ratetue3;
					$tue4=$ratetue4;
					$wed1=$ratewed1;
					$wed2=$ratewed2;
					$wed3=$ratewed3;
					$wed4=$ratewed4;
					$thu1=$ratethu1;
					$thu2=$ratethu2;
					$thu3=$ratethu3;
					$thu4=$ratethu4;
					$fri1=$ratefri1;
					$fri2=$ratefri2;
					$fri3=$ratefri3;
					$fri4=$ratefri4;
					$sat1=$ratesat1;
					$sat2=$ratesat2;
					$sat3=$ratesat3;
					$sat4=$ratesat4;
					$sun1=$ratesun1;
					$sun2=$ratesun2;
					$sun3=$ratesun3;
					$sun4=$ratesun4;
				} //automarkup			
				
				//echo "$ProdCode===$row->Begin===$OurNetFee1===$OurNetFee2===$OurNetFee3===$OurNetFee4===$ChildNetFee===$ChildNetFee1===$ChildNetFee2===$ChildNetFee3===$net_mon1<br><br>";
				//echo "$ProdCode===$row->Begin===$OurCost1===$OurCost2===$OurCost3===$OurCost4===$ChildTax===$ChildTax1===$ChildTax2===$ChildTax3===$tax_mon1<br><br>"; exit;
				
				if (isset($IsHotel) && $IsHotel == 0) {
					if (isset($OrigOurCost1))
						$OurCost1 = $OrigOurCost1;
					if (isset($OrigOurCost2))
						$OurCost2 = $OrigOurCost2;
					if (isset($OrigOurCost3))
						$OurCost3 = $OrigOurCost3;
					if (isset($OrigOurCost4))
						$OurCost4 = $OrigOurCost4;
					if (isset($OrigChildTax))
						$ChildTax = $OrigChildTax;
				}
					
				DB::table($table)
					->where('PriceID', $PriceID)
					->update([
						'OurCost1' => $OurCost1,
						'OurCost2' => $OurCost2,
						'OurCost3' => $OurCost3,
						'OurCost4' => $OurCost4,
						'ChildTax' => $ChildTax,
						'ChildCost1' => $ChildTax1,
						'ChildCost2' => $ChildTax2,
						'ChildCost3' => $ChildTax3,
						'Price1' => $Price1,
						'Price2' => $Price2,
						'Price3' => $Price3,
						'Price4' => $Price4,
						'ChildPrice' => $ChildPrice,
						'ChildPrice1' => $ChildPrice1,
						'ChildPrice2' => $ChildPrice2,
						'ChildPrice3' => $ChildPrice3
					]);
					
				DB::table('tblPriceDays')
					->where([['id', $PriceID], ['type', $type]])
					->update([
						'tax_mon1' => $tax_mon1,
						'tax_mon2' => $tax_mon2,
						'tax_mon3' => $tax_mon3,
						'tax_mon4' => $tax_mon4,
						'tax_tue1' => $tax_tue1,
						'tax_tue2' => $tax_tue2,
						'tax_tue3' => $tax_tue3,
						'tax_tue4' => $tax_tue4,
						'tax_wed1' => $tax_wed1,
						'tax_wed2' => $tax_wed2,
						'tax_wed3' => $tax_wed3,
						'tax_wed4' => $tax_wed4,
						'tax_thu1' => $tax_thu1,
						'tax_thu2' => $tax_thu2,
						'tax_thu3' => $tax_thu3,
						'tax_thu4' => $tax_thu4,
						'tax_fri1' => $tax_fri1,
						'tax_fri2' => $tax_fri2,
						'tax_fri3' => $tax_fri3,
						'tax_fri4' => $tax_fri4,
						'tax_sat1' => $tax_sat1,
						'tax_sat2' => $tax_sat2,
						'tax_sat3' => $tax_sat3,
						'tax_sat4' => $tax_sat4,
						'tax_sun1' => $tax_sun1,
						'tax_sun2' => $tax_sun2,
						'tax_sun3' => $tax_sun3,
						'tax_sun4' => $tax_sun4,
						'mon1' => $mon1,
						'mon2' => $mon2,
						'mon3' => $mon3,
						'mon4' => $mon4,
						'tue1' => $tue1,
						'tue2' => $tue2,
						'tue3' => $tue3,
						'tue4' => $tue4,
						'wed1' => $wed1,
						'wed2' => $wed2,
						'wed3' => $wed3,
						'wed4' => $wed4,
						'thu1' => $thu1,
						'thu2' => $thu2,
						'thu3' => $thu3,
						'thu4' => $thu4,
						'fri1' => $fri1,
						'fri2' => $fri2,
						'fri3' => $fri3,
						'fri4' => $fri4,
						'sat1' => $sat1,
						'sat2' => $sat2,
						'sat3' => $sat3,
						'sat4' => $sat4,
						'sun1' => $sun1,
						'sun2' => $sun2,
						'sun3' => $sun3,
						'sun4' => $sun4
					]);
			}
		
		}
	}
	
	public function convertcurrency ($amt, $prodcode) {
		$cit=substr($prodcode,0,3);
		$crow = DB::table('tblCity')->where('citycode', $cit)->get()->first();
		
		if ($crow and isset($crow->CurrencyID)) {
			$cu_row = DB::table('tblCurrency')->where('CurrencyID', $crow->CurrencyID)->get()->first();
			$new_amt=$amt*$cu_row->ExchangeRate;
		}
		
		return $new_amt;
	}
	
	/**
	 * To find the net amount plus tax
	 * @param float $amt Amount
	 * @param string $prodcode Product Code
	 * @param string $weekend Week End [Yes/No]
	 * @return float
	 */
	public function netplustax ($amt,$prodcode,$weekend) {
		$cit=substr($prodcode,0,3);
		$crow = DB::table('tblCity')
					->where('citycode', $cit)
					->get()->first();

		// Retrieve the Product Charge		
		$charge_row = DB::table('tblProduct')
							->select('ProductCharge')
							->where('ProdCode', $prodcode)
							->get()->first();		
		$product_charge = floatval($charge_row->ProductCharge);

		$new_amt=$amt;
		if ($crow->tax1 > 0) {
			if ($crow->type1=="Percent") {
				$new_amt+=$amt*($crow->tax1/100);
			} else {
				if ($weekend=="NO") $new_amt+=$crow->tax1;
			}
		}

		if ($crow->tax2 > 0) {
			if ($crow->type2=="Percent") {
				$new_amt+=$amt*($crow->tax2/100);
			} else {
				if ($weekend=="NO") $new_amt+=$crow->tax2;
			}
		}

		if ($crow->tax3 > 0) {
			if ($crow->type3=="Percent") {
				$new_amt+=$amt*($crow->tax3/100);
			} else {
				if ($weekend=="NO") $new_amt+=$crow->tax3;
			}
		}

		//Adjust for alternate Currencies
		if ($crow and isset($crow->CurrencyID))
		{			
			$cu_row = DB::table('tblCurrency')
						->where('CurrencyID', $crow->CurrencyID)
						->get()->first();
			$new_amt=$new_amt*$cu_row->ExchangeRate;
		}

		// Add the Product Charge
		if ($weekend=="NO")
			$new_amt += $product_charge;

		return round($new_amt,2);
	}

	/**
	 * To find the net amount plus adhoc tax
	 * @param float $amt Amount
	 * @param string $prodcode Product Code
	 * @param string $weekend Week End [Yes/No]
	 * @return float
	 */
	public function netplusadhoctax ($amt,$prodcode,$weekend, $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2) {
		$cit=substr($prodcode,0,3);
		$crow = DB::table('tblCity')
					->where('citycode', $cit)
					->get()->first();

		// Retrieve the Product Charge		
		$charge_row = DB::table('tblProduct')
							->select('ProductCharge')
							->where('ProdCode', $prodcode)
							->get()->first();		
		$product_charge = floatval($charge_row->ProductCharge);

		$new_amt=$amt;
		if ($AdhocTax1 > 0) {
			if ($AdhocTaxType1=="Percentage") {
				$new_amt+=$amt*($AdhocTax1/100);
			} else {
				if ($weekend=="NO") $new_amt+=$AdhocTax1;
			}
		}
		
		if ($AdhocTax2 > 0) {
			if ($AdhocTaxType2=="Percentage") {
				$new_amt+=$amt*($AdhocTax2/100);
			} else {
				if ($weekend=="NO") $new_amt+=$AdhocTax2;
			}
		}

		//Adjust for alternate Currencies
		if ($crow and isset($crow->CurrencyID))
		{		
			$cu_row = DB::table('tblCurrency')
						->where('CurrencyID', $crow->CurrencyID)
						->get()->first();
			$new_amt=$new_amt*$cu_row->ExchangeRate;
		}

		// Add the Product Charge
		if ($weekend=="NO")
			$new_amt += $product_charge;

		return round($new_amt,2);
	}
	
	public function calc_price($ProdCode,$OurCost1,$OurCost2,$OurCost3,$OurCost4,&$rate1,&$rate2,&$rate3,&$rate4,$i,$mrow,$childcost,&$childrate,$OurCostmon1,$OurCostmon2,$OurCostmon3,$OurCostmon4,$OurCosttue1,$OurCosttue2,$OurCosttue3,$OurCosttue4,$OurCostwed1,$OurCostwed2,$OurCostwed3,$OurCostwed4,$OurCostthu1,$OurCostthu2,$OurCostthu3,$OurCostthu4,$OurCostfri1,$OurCostfri2,$OurCostfri3,$OurCostfri4,$OurCostsat1,$OurCostsat2,$OurCostsat3,$OurCostsat4,$OurCostsun1,$OurCostsun2,$OurCostsun3,$OurCostsun4,&$ratemon1,&$ratemon2,&$ratemon3,&$ratemon4,&$ratetue1,&$ratetue2,&$ratetue3,&$ratetue4,&$ratewed1,&$ratewed2,&$ratewed3,&$ratewed4,&$ratethu1,&$ratethu2,&$ratethu3,&$ratethu4,&$ratefri1,&$ratefri2,&$ratefri3,&$ratefri4,&$ratesat1,&$ratesat2,&$ratesat3,&$ratesat4,&$ratesun1,&$ratesun2,&$ratesun3,&$ratesun4) {

		//MH Oct 10, 2010 - Changed
		//$mrow parameter will be ignored
		//Country specific markup may apply based on the city code

		$mrow = DB::table('tblTeamPlayersMarkup')
					->select('amount')
					->where('ProdCode', $ProdCode)
					->get()->first();
		
		if (!$mrow)
		{
			$MarkupCityCode=substr($ProdCode,0,3);
			$mrow = DB::table('tblMarkup')
						->where('CityCode', $MarkupCityCode)
						->get()->first();
			if (!$mrow) {
				//No specific markup was found, use the regular one
				$mrow = DB::table('tblMarkup')
						->where('CityCode', '000') //000 represents the default values
						->get()->first();
			}
		}

		$type=substr($ProdCode,3,1);
		switch ($type) {
			case "H":
			//Hotel
				if ($OurCost1 <> "NULL") $rate1=$this->calc_rate("hotel",$OurCost1,$mrow); elseif (strlen($OurCost1)>1) $rate1="NULL"; else $rate1=0;
				if ($OurCost2 <> "NULL") $rate2=$this->calc_rate("hotel",$OurCost2,$mrow); elseif (strlen($OurCost2)>1) $rate2="NULL"; else $rate2=0;
				if ($OurCost3 <> "NULL") $rate3=$this->calc_rate("hotel",$OurCost3,$mrow); elseif (strlen($OurCost3)>1) $rate3="NULL"; else $rate3=0;
				if ($OurCost4 <> "NULL") $rate4=$this->calc_rate("hotel",$OurCost4,$mrow); elseif (strlen($OurCost4)>1) $rate4="NULL"; else $rate4=0;
				if ($childcost <> "NULL") $childrate=$this->calc_rate("hotel",$childcost,$mrow); elseif (strlen($childcost)>1) $childrate="NULL"; else $childrate=0;
				if ($OurCostmon1 > 0) $ratemon1=$this->calc_rate("hotel",$OurCostmon1,$mrow); else $ratemon1=0;
				if ($OurCostmon2 > 0) $ratemon2=$this->calc_rate("hotel",$OurCostmon2,$mrow); else $ratemon2=0;
				if ($OurCostmon3 > 0) $ratemon3=$this->calc_rate("hotel",$OurCostmon3,$mrow); else $ratemon3=0;
				if ($OurCostmon4 > 0) $ratemon4=$this->calc_rate("hotel",$OurCostmon4,$mrow); else $ratemon4=0;
				if ($OurCosttue1 > 0) $ratetue1=$this->calc_rate("hotel",$OurCosttue1,$mrow); else $ratetue1=0;
				if ($OurCosttue2 > 0) $ratetue2=$this->calc_rate("hotel",$OurCosttue2,$mrow); else $ratetue2=0;
				if ($OurCosttue3 > 0) $ratetue3=$this->calc_rate("hotel",$OurCosttue3,$mrow); else $ratetue3=0;
				if ($OurCosttue4 > 0) $ratetue4=$this->calc_rate("hotel",$OurCosttue4,$mrow); else $ratetue4=0;
				if ($OurCostwed1 > 0) $ratewed1=$this->calc_rate("hotel",$OurCostwed1,$mrow); else $ratewed1=0;
				if ($OurCostwed2 > 0) $ratewed2=$this->calc_rate("hotel",$OurCostwed2,$mrow); else $ratewed2=0;
				if ($OurCostwed3 > 0) $ratewed3=$this->calc_rate("hotel",$OurCostwed3,$mrow); else $ratewed3=0;
				if ($OurCostwed4 > 0) $ratewed4=$this->calc_rate("hotel",$OurCostwed4,$mrow); else $ratewed4=0;
				if ($OurCostthu1 > 0) $ratethu1=$this->calc_rate("hotel",$OurCostthu1,$mrow); else $ratethu1=0;
				if ($OurCostthu2 > 0) $ratethu2=$this->calc_rate("hotel",$OurCostthu2,$mrow); else $ratethu2=0;
				if ($OurCostthu3 > 0) $ratethu3=$this->calc_rate("hotel",$OurCostthu3,$mrow); else $ratethu3=0;
				if ($OurCostthu4 > 0) $ratethu4=$this->calc_rate("hotel",$OurCostthu4,$mrow); else $ratethu4=0;
				if ($OurCostfri1 > 0) $ratefri1=$this->calc_rate("hotel",$OurCostfri1,$mrow); else $ratefri1=0;
				if ($OurCostfri2 > 0) $ratefri2=$this->calc_rate("hotel",$OurCostfri2,$mrow); else $ratefri2=0;
				if ($OurCostfri3 > 0) $ratefri3=$this->calc_rate("hotel",$OurCostfri3,$mrow); else $ratefri3=0;
				if ($OurCostfri4 > 0) $ratefri4=$this->calc_rate("hotel",$OurCostfri4,$mrow); else $ratefri4=0;
				if ($OurCostsat1 > 0) $ratesat1=$this->calc_rate("hotel",$OurCostsat1,$mrow); else $ratesat1=0;
				if ($OurCostsat2 > 0) $ratesat2=$this->calc_rate("hotel",$OurCostsat2,$mrow); else $ratesat2=0;
				if ($OurCostsat3 > 0) $ratesat3=$this->calc_rate("hotel",$OurCostsat3,$mrow); else $ratesat3=0;
				if ($OurCostsat4 > 0) $ratesat4=$this->calc_rate("hotel",$OurCostsat4,$mrow); else $ratesat4=0;
				if ($OurCostsun1 > 0) $ratesun1=$this->calc_rate("hotel",$OurCostsun1,$mrow); else $ratesun1=0;
				if ($OurCostsun2 > 0) $ratesun2=$this->calc_rate("hotel",$OurCostsun2,$mrow); else $ratesun2=0;
				if ($OurCostsun3 > 0) $ratesun3=$this->calc_rate("hotel",$OurCostsun3,$mrow); else $ratesun3=0;
				if ($OurCostsun4 > 0) $ratesun4=$this->calc_rate("hotel",$OurCostsun4,$mrow); else $ratesun4=0;
				break;
			case "R":
			//restaurant
				if ($OurCost1 <> "NULL") $rate1=$this->calc_rate("restaurant",$OurCost1,$mrow); elseif (strlen($OurCost1)>1) $rate1="NULL"; else $rate1=0;
				if ($OurCost2 <> "NULL") $rate2=$this->calc_rate("restaurant",$OurCost2,$mrow); elseif (strlen($OurCost2)>1) $rate2="NULL"; else $rate2=0;
				if ($OurCost3 <> "NULL") $rate3=$this->calc_rate("restaurant",$OurCost3,$mrow); elseif (strlen($OurCost3)>1) $rate3="NULL"; else $rate3=0;
				if ($OurCost4 <> "NULL") $rate4=$this->calc_rate("restaurant",$OurCost4,$mrow); elseif (strlen($OurCost4)>1) $rate4="NULL"; else $rate4=0;
				if ($childcost <> "NULL") $childrate=$this->calc_rate("restaurant",$childcost,$mrow); elseif (strlen($childcost)>1) $childrate="NULL"; else $childrate=0;
				if ($OurCostmon1 > 0) $ratemon1=$this->calc_rate("restaurant",$OurCostmon1,$mrow); else $ratemon1=0;
				if ($OurCostmon2 > 0) $ratemon2=$this->calc_rate("restaurant",$OurCostmon2,$mrow); else $ratemon2=0;
				if ($OurCostmon3 > 0) $ratemon3=$this->calc_rate("restaurant",$OurCostmon3,$mrow); else $ratemon3=0;
				if ($OurCostmon4 > 0) $ratemon4=$this->calc_rate("restaurant",$OurCostmon4,$mrow); else $ratemon4=0;
				if ($OurCosttue1 > 0) $ratetue1=$this->calc_rate("restaurant",$OurCosttue1,$mrow); else $ratetue1=0;
				if ($OurCosttue2 > 0) $ratetue2=$this->calc_rate("restaurant",$OurCosttue2,$mrow); else $ratetue2=0;
				if ($OurCosttue3 > 0) $ratetue3=$this->calc_rate("restaurant",$OurCosttue3,$mrow); else $ratetue3=0;
				if ($OurCosttue4 > 0) $ratetue4=$this->calc_rate("restaurant",$OurCosttue4,$mrow); else $ratetue4=0;
				if ($OurCostwed1 > 0) $ratewed1=$this->calc_rate("restaurant",$OurCostwed1,$mrow); else $ratewed1=0;
				if ($OurCostwed2 > 0) $ratewed2=$this->calc_rate("restaurant",$OurCostwed2,$mrow); else $ratewed2=0;
				if ($OurCostwed3 > 0) $ratewed3=$this->calc_rate("restaurant",$OurCostwed3,$mrow); else $ratewed3=0;
				if ($OurCostwed4 > 0) $ratewed4=$this->calc_rate("restaurant",$OurCostwed4,$mrow); else $ratewed4=0;
				if ($OurCostthu1 > 0) $ratethu1=$this->calc_rate("restaurant",$OurCostthu1,$mrow); else $ratethu1=0;
				if ($OurCostthu2 > 0) $ratethu2=$this->calc_rate("restaurant",$OurCostthu2,$mrow); else $ratethu2=0;
				if ($OurCostthu3 > 0) $ratethu3=$this->calc_rate("restaurant",$OurCostthu3,$mrow); else $ratethu3=0;
				if ($OurCostthu4 > 0) $ratethu4=$this->calc_rate("restaurant",$OurCostthu4,$mrow); else $ratethu4=0;
				if ($OurCostfri1 > 0) $ratefri1=$this->calc_rate("restaurant",$OurCostfri1,$mrow); else $ratefri1=0;
				if ($OurCostfri2 > 0) $ratefri2=$this->calc_rate("restaurant",$OurCostfri2,$mrow); else $ratefri2=0;
				if ($OurCostfri3 > 0) $ratefri3=$this->calc_rate("restaurant",$OurCostfri3,$mrow); else $ratefri3=0;
				if ($OurCostfri4 > 0) $ratefri4=$this->calc_rate("restaurant",$OurCostfri4,$mrow); else $ratefri4=0;
				if ($OurCostsat1 > 0) $ratesat1=$this->calc_rate("restaurant",$OurCostsat1,$mrow); else $ratesat1=0;
				if ($OurCostsat2 > 0) $ratesat2=$this->calc_rate("restaurant",$OurCostsat2,$mrow); else $ratesat2=0;
				if ($OurCostsat3 > 0) $ratesat3=$this->calc_rate("restaurant",$OurCostsat3,$mrow); else $ratesat3=0;
				if ($OurCostsat4 > 0) $ratesat4=$this->calc_rate("restaurant",$OurCostsat4,$mrow); else $ratesat4=0;
				if ($OurCostsun1 > 0) $ratesun1=$this->calc_rate("restaurant",$OurCostsun1,$mrow); else $ratesun1=0;
				if ($OurCostsun2 > 0) $ratesun2=$this->calc_rate("restaurant",$OurCostsun2,$mrow); else $ratesun2=0;
				if ($OurCostsun3 > 0) $ratesun3=$this->calc_rate("restaurant",$OurCostsun3,$mrow); else $ratesun3=0;
				if ($OurCostsun4 > 0) $ratesun4=$this->calc_rate("restaurant",$OurCostsun4,$mrow); else $ratesun4=0;
				break;
			case "S":
			//service
				if ($OurCost1 <> "NULL") $rate1=$this->calc_rate("service",$OurCost1,$mrow); elseif (strlen($OurCost1)>1) $rate1="NULL"; else $rate1=0;
				if ($OurCost2 <> "NULL") $rate2=$this->calc_rate("service",$OurCost2,$mrow); elseif (strlen($OurCost2)>1) $rate2="NULL"; else $rate2=0;
				if ($OurCost3 <> "NULL") $rate3=$this->calc_rate("service",$OurCost3,$mrow); elseif (strlen($OurCost3)>1) $rate3="NULL"; else $rate3=0;
				if ($OurCost4 <> "NULL") $rate4=$this->calc_rate("service",$OurCost4,$mrow); elseif (strlen($OurCost4)>1) $rate4="NULL"; else $rate4=0;
				if ($childcost <> "NULL") $childrate=$this->calc_rate("service",$childcost,$mrow); elseif (strlen($childcost)>1) $childrate="NULL"; else $childrate=0;
				if ($OurCostmon1 > 0) $ratemon1=$this->calc_rate("service",$OurCostmon1,$mrow); else $ratemon1=0;
				if ($OurCostmon2 > 0) $ratemon2=$this->calc_rate("service",$OurCostmon2,$mrow); else $ratemon2=0;
				if ($OurCostmon3 > 0) $ratemon3=$this->calc_rate("service",$OurCostmon3,$mrow); else $ratemon3=0;
				if ($OurCostmon4 > 0) $ratemon4=$this->calc_rate("service",$OurCostmon4,$mrow); else $ratemon4=0;
				if ($OurCosttue1 > 0) $ratetue1=$this->calc_rate("service",$OurCosttue1,$mrow); else $ratetue1=0;
				if ($OurCosttue2 > 0) $ratetue2=$this->calc_rate("service",$OurCosttue2,$mrow); else $ratetue2=0;
				if ($OurCosttue3 > 0) $ratetue3=$this->calc_rate("service",$OurCosttue3,$mrow); else $ratetue3=0;
				if ($OurCosttue4 > 0) $ratetue4=$this->calc_rate("service",$OurCosttue4,$mrow); else $ratetue4=0;
				if ($OurCostwed1 > 0) $ratewed1=$this->calc_rate("service",$OurCostwed1,$mrow); else $ratewed1=0;
				if ($OurCostwed2 > 0) $ratewed2=$this->calc_rate("service",$OurCostwed2,$mrow); else $ratewed2=0;
				if ($OurCostwed3 > 0) $ratewed3=$this->calc_rate("service",$OurCostwed3,$mrow); else $ratewed3=0;
				if ($OurCostwed4 > 0) $ratewed4=$this->calc_rate("service",$OurCostwed4,$mrow); else $ratewed4=0;
				if ($OurCostthu1 > 0) $ratethu1=$this->calc_rate("service",$OurCostthu1,$mrow); else $ratethu1=0;
				if ($OurCostthu2 > 0) $ratethu2=$this->calc_rate("service",$OurCostthu2,$mrow); else $ratethu2=0;
				if ($OurCostthu3 > 0) $ratethu3=$this->calc_rate("service",$OurCostthu3,$mrow); else $ratethu3=0;
				if ($OurCostthu4 > 0) $ratethu4=$this->calc_rate("service",$OurCostthu4,$mrow); else $ratethu4=0;
				if ($OurCostfri1 > 0) $ratefri1=$this->calc_rate("service",$OurCostfri1,$mrow); else $ratefri1=0;
				if ($OurCostfri2 > 0) $ratefri2=$this->calc_rate("service",$OurCostfri2,$mrow); else $ratefri2=0;
				if ($OurCostfri3 > 0) $ratefri3=$this->calc_rate("service",$OurCostfri3,$mrow); else $ratefri3=0;
				if ($OurCostfri4 > 0) $ratefri4=$this->calc_rate("service",$OurCostfri4,$mrow); else $ratefri4=0;
				if ($OurCostsat1 > 0) $ratesat1=$this->calc_rate("service",$OurCostsat1,$mrow); else $ratesat1=0;
				if ($OurCostsat2 > 0) $ratesat2=$this->calc_rate("service",$OurCostsat2,$mrow); else $ratesat2=0;
				if ($OurCostsat3 > 0) $ratesat3=$this->calc_rate("service",$OurCostsat3,$mrow); else $ratesat3=0;
				if ($OurCostsat4 > 0) $ratesat4=$this->calc_rate("service",$OurCostsat4,$mrow); else $ratesat4=0;
				if ($OurCostsun1 > 0) $ratesun1=$this->calc_rate("service",$OurCostsun1,$mrow); else $ratesun1=0;
				if ($OurCostsun2 > 0) $ratesun2=$this->calc_rate("service",$OurCostsun2,$mrow); else $ratesun2=0;
				if ($OurCostsun3 > 0) $ratesun3=$this->calc_rate("service",$OurCostsun3,$mrow); else $ratesun3=0;
				if ($OurCostsun4 > 0) $ratesun4=$this->calc_rate("service",$OurCostsun4,$mrow); else $ratesun4=0;
				break;
			case "P":
			//package
				if ($OurCost1 <> "NULL") $rate1=$this->calc_rate("package",$OurCost1,$mrow); elseif (strlen($OurCost1)>1) $rate1="NULL"; else $rate1=0;
				if ($OurCost2 <> "NULL") $rate2=$this->calc_rate("package",$OurCost2,$mrow); elseif (strlen($OurCost2)>1) $rate2="NULL"; else $rate2=0;
				if ($OurCost3 <> "NULL") $rate3=$this->calc_rate("package",$OurCost3,$mrow); elseif (strlen($OurCost3)>1) $rate3="NULL"; else $rate3=0;
				if ($OurCost4 <> "NULL") $rate4=$this->calc_rate("package",$OurCost4,$mrow); elseif (strlen($OurCost4)>1) $rate4="NULL"; else $rate4=0;
				if ($childcost <> "NULL") $childrate=$this->calc_rate("package",$childcost,$mrow); elseif (strlen($childcost)>1) $childrate="NULL"; else $childrate=0;
				if ($OurCostmon1 > 0) $ratemon1=$this->calc_rate("package",$OurCostmon1,$mrow); else $ratemon1=0;
				if ($OurCostmon2 > 0) $ratemon2=$this->calc_rate("package",$OurCostmon2,$mrow); else $ratemon2=0;
				if ($OurCostmon3 > 0) $ratemon3=$this->calc_rate("package",$OurCostmon3,$mrow); else $ratemon3=0;
				if ($OurCostmon4 > 0) $ratemon4=$this->calc_rate("package",$OurCostmon4,$mrow); else $ratemon4=0;
				if ($OurCosttue1 > 0) $ratetue1=$this->calc_rate("package",$OurCosttue1,$mrow); else $ratetue1=0;
				if ($OurCosttue2 > 0) $ratetue2=$this->calc_rate("package",$OurCosttue2,$mrow); else $ratetue2=0;
				if ($OurCosttue3 > 0) $ratetue3=$this->calc_rate("package",$OurCosttue3,$mrow); else $ratetue3=0;
				if ($OurCosttue4 > 0) $ratetue4=$this->calc_rate("package",$OurCosttue4,$mrow); else $ratetue4=0;
				if ($OurCostwed1 > 0) $ratewed1=$this->calc_rate("package",$OurCostwed1,$mrow); else $ratewed1=0;
				if ($OurCostwed2 > 0) $ratewed2=$this->calc_rate("package",$OurCostwed2,$mrow); else $ratewed2=0;
				if ($OurCostwed3 > 0) $ratewed3=$this->calc_rate("package",$OurCostwed3,$mrow); else $ratewed3=0;
				if ($OurCostwed4 > 0) $ratewed4=$this->calc_rate("package",$OurCostwed4,$mrow); else $ratewed4=0;
				if ($OurCostthu1 > 0) $ratethu1=$this->calc_rate("package",$OurCostthu1,$mrow); else $ratethu1=0;
				if ($OurCostthu2 > 0) $ratethu2=$this->calc_rate("package",$OurCostthu2,$mrow); else $ratethu2=0;
				if ($OurCostthu3 > 0) $ratethu3=$this->calc_rate("package",$OurCostthu3,$mrow); else $ratethu3=0;
				if ($OurCostthu4 > 0) $ratethu4=$this->calc_rate("package",$OurCostthu4,$mrow); else $ratethu4=0;
				if ($OurCostfri1 > 0) $ratefri1=$this->calc_rate("package",$OurCostfri1,$mrow); else $ratefri1=0;
				if ($OurCostfri2 > 0) $ratefri2=$this->calc_rate("package",$OurCostfri2,$mrow); else $ratefri2=0;
				if ($OurCostfri3 > 0) $ratefri3=$this->calc_rate("package",$OurCostfri3,$mrow); else $ratefri3=0;
				if ($OurCostfri4 > 0) $ratefri4=$this->calc_rate("package",$OurCostfri4,$mrow); else $ratefri4=0;
				if ($OurCostsat1 > 0) $ratesat1=$this->calc_rate("package",$OurCostsat1,$mrow); else $ratesat1=0;
				if ($OurCostsat2 > 0) $ratesat2=$this->calc_rate("package",$OurCostsat2,$mrow); else $ratesat2=0;
				if ($OurCostsat3 > 0) $ratesat3=$this->calc_rate("package",$OurCostsat3,$mrow); else $ratesat3=0;
				if ($OurCostsat4 > 0) $ratesat4=$this->calc_rate("package",$OurCostsat4,$mrow); else $ratesat4=0;
				if ($OurCostsun1 > 0) $ratesun1=$this->calc_rate("package",$OurCostsun1,$mrow); else $ratesun1=0;
				if ($OurCostsun2 > 0) $ratesun2=$this->calc_rate("package",$OurCostsun2,$mrow); else $ratesun2=0;
				if ($OurCostsun3 > 0) $ratesun3=$this->calc_rate("package",$OurCostsun3,$mrow); else $ratesun3=0;
				if ($OurCostsun4 > 0) $ratesun4=$this->calc_rate("package",$OurCostsun4,$mrow); else $ratesun4=0;
				break;
			case "T":
			//Transfer
				if ($OurCost1 <> "NULL") $rate1=$this->calc_rate("transfer",$OurCost1,$mrow); elseif (strlen($OurCost1)>1) $rate1="NULL"; else $rate1=0;
				if ($OurCost2 <> "NULL") $rate2=$this->calc_rate("transfer",$OurCost2,$mrow); elseif (strlen($OurCost2)>1) $rate2="NULL"; else $rate2=0;
				if ($OurCost3 <> "NULL") $rate3=$this->calc_rate("transfer",$OurCost3,$mrow); elseif (strlen($OurCost3)>1) $rate3="NULL"; else $rate3=0;
				if ($OurCost4 <> "NULL") $rate4=$this->calc_rate("transfer",$OurCost4,$mrow); elseif (strlen($OurCost4)>1) $rate4="NULL"; else $rate4=0;
				if ($childcost <> "NULL") $childrate=$this->calc_rate("transfer",$childcost,$mrow); elseif (strlen($childcost)>1) $childrate="NULL"; else $childrate=0;
				if ($OurCostmon1 > 0) $ratemon1=$this->calc_rate("transfer",$OurCostmon1,$mrow); else $ratemon1=0;
				if ($OurCostmon2 > 0) $ratemon2=$this->calc_rate("transfer",$OurCostmon2,$mrow); else $ratemon2=0;
				if ($OurCostmon3 > 0) $ratemon3=$this->calc_rate("transfer",$OurCostmon3,$mrow); else $ratemon3=0;
				if ($OurCostmon4 > 0) $ratemon4=$this->calc_rate("transfer",$OurCostmon4,$mrow); else $ratemon4=0;
				if ($OurCosttue1 > 0) $ratetue1=$this->calc_rate("transfer",$OurCosttue1,$mrow); else $ratetue1=0;
				if ($OurCosttue2 > 0) $ratetue2=$this->calc_rate("transfer",$OurCosttue2,$mrow); else $ratetue2=0;
				if ($OurCosttue3 > 0) $ratetue3=$this->calc_rate("transfer",$OurCosttue3,$mrow); else $ratetue3=0;
				if ($OurCosttue4 > 0) $ratetue4=$this->calc_rate("transfer",$OurCosttue4,$mrow); else $ratetue4=0;
				if ($OurCostwed1 > 0) $ratewed1=$this->calc_rate("transfer",$OurCostwed1,$mrow); else $ratewed1=0;
				if ($OurCostwed2 > 0) $ratewed2=$this->calc_rate("transfer",$OurCostwed2,$mrow); else $ratewed2=0;
				if ($OurCostwed3 > 0) $ratewed3=$this->calc_rate("transfer",$OurCostwed3,$mrow); else $ratewed3=0;
				if ($OurCostwed4 > 0) $ratewed4=$this->calc_rate("transfer",$OurCostwed4,$mrow); else $ratewed4=0;
				if ($OurCostthu1 > 0) $ratethu1=$this->calc_rate("transfer",$OurCostthu1,$mrow); else $ratethu1=0;
				if ($OurCostthu2 > 0) $ratethu2=$this->calc_rate("transfer",$OurCostthu2,$mrow); else $ratethu2=0;
				if ($OurCostthu3 > 0) $ratethu3=$this->calc_rate("transfer",$OurCostthu3,$mrow); else $ratethu3=0;
				if ($OurCostthu4 > 0) $ratethu4=$this->calc_rate("transfer",$OurCostthu4,$mrow); else $ratethu4=0;
				if ($OurCostfri1 > 0) $ratefri1=$this->calc_rate("transfer",$OurCostfri1,$mrow); else $ratefri1=0;
				if ($OurCostfri2 > 0) $ratefri2=$this->calc_rate("transfer",$OurCostfri2,$mrow); else $ratefri2=0;
				if ($OurCostfri3 > 0) $ratefri3=$this->calc_rate("transfer",$OurCostfri3,$mrow); else $ratefri3=0;
				if ($OurCostfri4 > 0) $ratefri4=$this->calc_rate("transfer",$OurCostfri4,$mrow); else $ratefri4=0;
				if ($OurCostsat1 > 0) $ratesat1=$this->calc_rate("transfer",$OurCostsat1,$mrow); else $ratesat1=0;
				if ($OurCostsat2 > 0) $ratesat2=$this->calc_rate("transfer",$OurCostsat2,$mrow); else $ratesat2=0;
				if ($OurCostsat3 > 0) $ratesat3=$this->calc_rate("transfer",$OurCostsat3,$mrow); else $ratesat3=0;
				if ($OurCostsat4 > 0) $ratesat4=$this->calc_rate("transfer",$OurCostsat4,$mrow); else $ratesat4=0;
				if ($OurCostsun1 > 0) $ratesun1=$this->calc_rate("transfer",$OurCostsun1,$mrow); else $ratesun1=0;
				if ($OurCostsun2 > 0) $ratesun2=$this->calc_rate("transfer",$OurCostsun2,$mrow); else $ratesun2=0;
				if ($OurCostsun3 > 0) $ratesun3=$this->calc_rate("transfer",$OurCostsun3,$mrow); else $ratesun3=0;
				if ($OurCostsun4 > 0) $ratesun4=$this->calc_rate("transfer",$OurCostsun4,$mrow); else $ratesun4=0;
				break;
			default:
			//all other
				if ($OurCost1 <> "NULL") $rate1=$this->calc_rate("other",$OurCost1,$mrow); elseif (strlen($OurCost1)>1) $rate1="NULL"; else $rate1=0;
				if ($OurCost2 <> "NULL") $rate2=$this->calc_rate("other",$OurCost2,$mrow); elseif (strlen($OurCost2)>1) $rate2="NULL"; else $rate2=0;
				if ($OurCost3 <> "NULL") $rate3=$this->calc_rate("other",$OurCost3,$mrow); elseif (strlen($OurCost3)>1) $rate3="NULL"; else $rate3=0;
				if ($OurCost4 <> "NULL") $rate4=$this->calc_rate("other",$OurCost4,$mrow); elseif (strlen($OurCost4)>1) $rate4="NULL"; else $rate4=0;
				if ($childcost <> "NULL") $childrate=$this->calc_rate("other",$childcost,$mrow); elseif (strlen($childcost)>1) $childrate="NULL"; else $childrate=0;
				if ($OurCostmon1 > 0) $ratemon1=$this->calc_rate("other",$OurCostmon1,$mrow); else $ratemon1=0;
				if ($OurCostmon2 > 0) $ratemon2=$this->calc_rate("other",$OurCostmon2,$mrow); else $ratemon2=0;
				if ($OurCostmon3 > 0) $ratemon3=$this->calc_rate("other",$OurCostmon3,$mrow); else $ratemon3=0;
				if ($OurCostmon4 > 0) $ratemon4=$this->calc_rate("other",$OurCostmon4,$mrow); else $ratemon4=0;
				if ($OurCosttue1 > 0) $ratetue1=$this->calc_rate("other",$OurCosttue1,$mrow); else $ratetue1=0;
				if ($OurCosttue2 > 0) $ratetue2=$this->calc_rate("other",$OurCosttue2,$mrow); else $ratetue2=0;
				if ($OurCosttue3 > 0) $ratetue3=$this->calc_rate("other",$OurCosttue3,$mrow); else $ratetue3=0;
				if ($OurCosttue4 > 0) $ratetue4=$this->calc_rate("other",$OurCosttue4,$mrow); else $ratetue4=0;
				if ($OurCostwed1 > 0) $ratewed1=$this->calc_rate("other",$OurCostwed1,$mrow); else $ratewed1=0;
				if ($OurCostwed2 > 0) $ratewed2=$this->calc_rate("other",$OurCostwed2,$mrow); else $ratewed2=0;
				if ($OurCostwed3 > 0) $ratewed3=$this->calc_rate("other",$OurCostwed3,$mrow); else $ratewed3=0;
				if ($OurCostwed4 > 0) $ratewed4=$this->calc_rate("other",$OurCostwed4,$mrow); else $ratewed4=0;
				if ($OurCostthu1 > 0) $ratethu1=$this->calc_rate("other",$OurCostthu1,$mrow); else $ratethu1=0;
				if ($OurCostthu2 > 0) $ratethu2=$this->calc_rate("other",$OurCostthu2,$mrow); else $ratethu2=0;
				if ($OurCostthu3 > 0) $ratethu3=$this->calc_rate("other",$OurCostthu3,$mrow); else $ratethu3=0;
				if ($OurCostthu4 > 0) $ratethu4=$this->calc_rate("other",$OurCostthu4,$mrow); else $ratethu4=0;
				if ($OurCostfri1 > 0) $ratefri1=$this->calc_rate("other",$OurCostfri1,$mrow); else $ratefri1=0;
				if ($OurCostfri2 > 0) $ratefri2=$this->calc_rate("other",$OurCostfri2,$mrow); else $ratefri2=0;
				if ($OurCostfri3 > 0) $ratefri3=$this->calc_rate("other",$OurCostfri3,$mrow); else $ratefri3=0;
				if ($OurCostfri4 > 0) $ratefri4=$this->calc_rate("other",$OurCostfri4,$mrow); else $ratefri4=0;
				if ($OurCostsat1 > 0) $ratesat1=$this->calc_rate("other",$OurCostsat1,$mrow); else $ratesat1=0;
				if ($OurCostsat2 > 0) $ratesat2=$this->calc_rate("other",$OurCostsat2,$mrow); else $ratesat2=0;
				if ($OurCostsat3 > 0) $ratesat3=$this->calc_rate("other",$OurCostsat3,$mrow); else $ratesat3=0;
				if ($OurCostsat4 > 0) $ratesat4=$this->calc_rate("other",$OurCostsat4,$mrow); else $ratesat4=0;
				if ($OurCostsun1 > 0) $ratesun1=$this->calc_rate("other",$OurCostsun1,$mrow); else $ratesun1=0;
				if ($OurCostsun2 > 0) $ratesun2=$this->calc_rate("other",$OurCostsun2,$mrow); else $ratesun2=0;
				if ($OurCostsun3 > 0) $ratesun3=$this->calc_rate("other",$OurCostsun3,$mrow); else $ratesun3=0;
				if ($OurCostsun4 > 0) $ratesun4=$this->calc_rate("other",$OurCostsun4,$mrow); else $ratesun4=0;
				break;
		}
	}
	
	/**
	 * To calculate the rate
	 * @param string $type Type
	 * @param float $net Net Amount
	 * @param object $mrow MarkUp row object
	 * @return float
	 */
	public function calc_rate($type,$net,$mrow) {
		$mval1=$type . "1";
		$mval2=$type . "2";
		$mval3=$type . "3";
		$mval4=$type . "4";

		if ($mrow->max1==350) {
			$max1=99999999.00;
		} else {
			$max1=$mrow->max1;
		}
		if ($mrow->max2==350) {
			$max2=99999999.00;
		} else {
			$max2=$mrow->max2;
		}
		if ($mrow->max3==350) {
			$max3=99999999.00;
		} else {
			$max3=$mrow->max3;
		}
		if ($mrow->max4==350) {
			$max4=99999999.00;
		} else {
			$max4=$mrow->max4;
		}
		if ($net >= $mrow->min1 and $net < $max1) {
			$rate=round(((($mrow->$mval1/100)+1)*$net),0);
		}
		if ($net >= $mrow->min2 and $net < $max2) {
			$rate=round(((($mrow->$mval2/100)+1)*$net),0);
		}
		if ($net >= $mrow->min3 and $net < $max3) {
			//$rate=round(((($mrow->$mval3/100)+1)*$net)+.5,0);
			$rate=round(((($mrow->$mval3/100)+1)*$net),0);
		}
		if ($net >= $mrow->min4 and $net < $max4) {
			//$rate=round(((($mrow->$mval3/100)+1)*$net)+.5,0);
			$rate=round(((($mrow->$mval4/100)+1)*$net),0);
		}
		
		if (isset($mrow->amount))
		{
			 $rate=round(((($mrow->amount/100)+1)*$net),0);
		}
		
		return $rate;
	}
	
	public function getAgencyWebAccess($ProdCode, $AgencyCode)
	{
		$prodRow = DB::table('tblProduct')
						->select('web_restricted', 'web_access')
						->where('ProdCode', $ProdCode)
						->get()->first();

		if ($prodRow->web_access == "ALLOW ALL")
		{
			return TRUE;
		}
		
		if ($prodRow->web_access == "DENY ALL")
		{
			return FALSE;
		}
		
		return $this->getAgencyWebAccessExceptions($ProdCode, $AgencyCode, $prodRow->web_access);
		
	}
	
	public function getAgencyWebAccessExceptions($ProdCode, $AgencyCode, $web_access)
	{
		if ($web_access == "EXCLUDE ON" || $web_access == "ALLOW ONLY")
		{
			$sql = "select w.* from tblWebBook as w 
				JOIN tblAgency as a ON (a.AgCode='$AgencyCode') 
				LEFT JOIN tblCountry as c ON (c.CountryID = a.Country)
				LEFT JOIN tblRegionGroupings as rg ON (rg.CountryID = c.CountryID)
				LEFT JOIN tblRegions as r ON (r.RegionID = rg.RegionID)  
				WHERE w.prodcode='$ProdCode' AND ((w.Type='Country' and c.CountryCode = w.Value)
				or (w.Type='Agency' and w.Value=a.AgCode) or (w.Type='Region' and r.RegionID = w.Value)) GROUP by w.Type, w.Include";
				
			$rows = DB::select($sql);
			
			if ($web_access == "EXCLUDE ON") 
				$included = TRUE;
			else
				$included = FALSE;
				
			$includedTypes = array();
			
			foreach ($rows as $row)
			{
				if (!$row->Include)
				{
					$includedTypes[$row->Type] = FALSE;
				}
				else
				{
					$includedTypes[$row->Type] = TRUE;
				}
			}
			
			//return based on exception rules
			if (isset($includedTypes['Agency'])) return $includedTypes['Agency'];
			if (isset($includedTypes['Country'])) return $includedTypes['Country'];
			if (isset($includedTypes['Region'])) return $includedTypes['Region'];
			
			//no exception rule found, return default
			return $included;
		}
		
		return FALSE;
	}
	
	/**
	 * To get the cancel policy
	 * @param string $prodcode Product Code
	 * @param string $date Date
	 * @return string
	 */
	public function cancelPolicy($prodcode,$date) {
		$cancel_table = $this->cancelRule($prodcode,$date);
		$policies = array();
		$today=$this->todays_date();
		
		if ($cancel_table == "NonRefundable")
		{
			return "Non refundable - full cancellation penalty at any time.";
		}
		else if ($cancel_table <> "No cancellation policy") {
			$cancel_query  = "SELECT days_prior1,type1,amount1,days_prior2,type2,amount2,days_prior3,type3,amount3 FROM ";
			$cancel_query .= $cancel_table;
			$cancel_query .= " WHERE ProdCode='$prodcode'";
			if ($cancel_table=="tblDefaultException" || $cancel_table=="tblDefaultPrice")
			{
				$cancel_query .= " AND Begin<='$date' AND End>='$date'";
			}    
			$days_prior1="days_prior1";
			$days_prior2="days_prior2";
			$days_prior3="days_prior3";
			
			$amount1="amount1";
			$amount2="amount2";
			$amount3="amount3";
			
			$type1="type1";
			$type2="type2";
			$type3="type3";

			$cancel_result = DB::select($cancel_query);
			$cancel_row = $cancel_result[0];
		}		
		
		if(isset($cancel_row) && (float)$cancel_row->$amount1 > 0) {
			$cur_policy = $cancel_row->$days_prior1." days or less prior to travel - " . $this->fixamt($cancel_row->$amount1,$cancel_row->$type1);
			array_push($policies,$cur_policy);
			unset($cur_policy);
		}

		if(isset($cancel_row) && (float)$cancel_row->$amount2 > 0) {
			$cur_policy = $cancel_row->$days_prior2." days or less prior to travel - " . $this->fixamt($cancel_row->$amount2,$cancel_row->$type2);
			array_push($policies,$cur_policy);
			unset($cur_policy);
		}

		if(isset($cancel_row) && (float)$cancel_row->$amount3 > 0) {
			$cur_policy = $cancel_row->$days_prior3." days or less prior to travel - " . $this->fixamt($cancel_row->$amount3,$cancel_row->$type3);
			array_push($policies,$cur_policy);
			unset($cur_policy);
		}

		if(count($policies)>0)
			return implode(";",$policies);
		else
			return "";
	}
	
	/**
	 * To format the cancellation policy
	 * @param integer $days1 Days1
	 * @param string $type1 Type1
	 * @param float $amount1 Amount1
	 * @param integer $days2 Days1
	 * @param string $type2 Type2
	 * @param float $amount2 Amount2
	 * @param integer $days3 Days1
	 * @param string $type3 Type3
	 * @param float $amount3 Amount3
	 * @return string
	 */
	public function formatCancelPolicy($days1,$type1,$amount1,$days2,$type2,$amount2,$days3,$type3,$amount3) {
		$policies = array();

		if((float)$amount1 > 0) {
			$cur_policy = $days1." days or less prior to travel - " . $this->fixamt($amount1,$type1);
			array_push($policies,$cur_policy);
			unset($cur_policy);
		}

		if((float)$amount2 > 0) {
			$cur_policy = $days2." days or less prior to travel - " . $this->fixamt($amount2,$type2);
			array_push($policies,$cur_policy);
			unset($cur_policy);
		}

		if((float)$amount3 > 0) {
			$cur_policy = $days3." days or less prior to travel - " . $this->fixamt($amount3,$type3);
			array_push($policies,$cur_policy);
			unset($cur_policy);
		}

		if(count($policies)>0)
			return implode(";",$policies);
		else
			return "";
	}
	
	public function getResortFeeTypes()
	{		
		$resortFees = DB::table('tblResortFeeTypes')
						->select('ResortFeeID', 'ResortFeeType')
						->get();

		// Get all resort fees
		$allResortFees = array();
		foreach ($resortFees as $resortFee)
		{
			$allResortFees[$resortFee->ResortFeeID] = $resortFee->ResortFeeType;
		}
		return $allResortFees;
		/*
		return array("1" => "Paid Locally Per Person",
					 "2" => "Paid Locally Per Room",
					 "3" => "Paid Locally Per Day",
					 "4" => "Paid Locally Plus Tax Per Person",
					 "5" => "Paid Locally Plus Tax Per Room",
					 "6" => "Paid Locally Plus Tax Per Day",
					 "7" => "Waived For TeamAmerica", 
					 "8" => "Included In Rate");
		*/
	}
}