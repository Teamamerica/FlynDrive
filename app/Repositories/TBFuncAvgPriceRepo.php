<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

use App\Repositories\TBCommonRepo;

class TBFuncAvgPriceRepo {
	
	public function __construct() {
		$this->commonRepo = new TBCommonRepo();
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
	 * @return string
	 */

	public function avg_price($prodcode,$pkgcode,$pkgdate,$date,$nights,$occupancy,$agcode,$PaxType,$check_all_prices,&$pprice_1,&$pprice_2,&$pprice_3,&$pprice_4,&$PromoMsg,&$NightsFree_rtn=0,&$NightsToStay_rtn=0,$vendorMessage=FALSE,$Child1Age='', $Child2Age='', &$ddays_prior1='', &$ttype1='', &$aamount1='', &$ddays_prior2='', &$ttype2='', &$aamount2='', &$ddays_prior3='', &$ttype3='', &$aamount3='', &$allCxlPolicies=''){

		//Determine the items average price over the given period
		$night_ctr=1;
		$PromoFound="NO";
		$FirstPromoNight=0;
		$DontDoThisAgain_BecauseFreeNightAlreadyGiven="NO";
		$today=$this->commonRepo->todays_date();
		$price=0;
		$pprice_1=0;
		$pprice_2=0;
		$pprice_3=0;
		$pprice_4=0;
		$Message = "PromoMsg";
		if ($vendorMessage === TRUE)
		{
			$Message = "VendorMsg";	
		}
		$PromoMsgMap = array();
		
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

		//Verify that the maximum # of occupants for this product has not been exceeded		
		$p_row = DB::table('tblProduct as p')
					->select(DB::raw('p.*, v.overlappingPromos, v.VendorID, v.ChildAgeRange1Min, v.ChildAgeRange1Max, v.ChildAgeRange2Min, v.ChildAgeRange2Max, v.ChildAgeRange3Min, v.ChildAgeRange3Max, v.ChildAgeRange4Min, v.ChildAgeRange4Max, v.ExcludeCountryDiscount'))
					->join('tblVendor as v', 'v.vendorid', 'p.vendor')
					->where('p.prodcode', $prodcode)
					->get()->first();
			
		if(substr($prodcode, 3,1) != 'M'){
			if ($check_all_prices<>"YES") {
				if ($p_row->MaxOcc > 0) {
					if ($NumOccupants > $p_row->MaxOcc) {
						//Not allowed... occupants exceeds max
						//js_alert("$NumOccupants occupants exceeds $p_row->MaxOcc which is the maximum allowed ($prodcode):");
						return "fail";
					}
				} else {
					if ($NumOccupants > 1) {
						//Not allowed... occupants exceeds max
						//js_alert("$NumOccupants occupants exceeds $p_row->MaxOcc which is the maximum allowed ($prodcode)");
						return "fail";
					}
				}
			} //verify max # occ if checkall<>yes
		}
		
		
		
		//rm:add teamplayer vendor message to promo message		
		$resultRow = DB::table('tblProduct as p')
						->select(DB::raw('v.TeamplayerClientPromoMessage, v.TeamplayerVendorPromoMessage, v.TeamplayerClientPromoMessage2, v.TeamplayerVendorPromoMessage2, v.TeamplayerMsgFromYear1, v.TeamplayerMsgFromYear2, v.TeamplayerMsgToYear1, v.TeamplayerMsgToYear2'))
						->join('tblVendor as v', 'v.VendorID', 'p.Vendor')
						->where('p.ProdCode', $prodcode)
						->get()->first();
		if ($Message == "VendorMsg")
		{
			//$teamplayerMessage = $resultRow->TeamplayerVendorPromoMessage; //**delete this line after each vendor updated with year1 & year2 value, otherwise it'd always initiated with first row.
			if($this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgFromYear1,">=") and $this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgToYear1,"<="))
				$teamplayerMessage = $resultRow->TeamplayerVendorPromoMessage;
			elseif($this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgFromYear2,">=") and $this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgToYear2,"<="))
				$teamplayerMessage = $resultRow->TeamplayerVendorPromoMessage2;
		}
		else
		{
			//$teamplayerMessage = $resultRow->TeamplayerClientPromoMessage; //**delete this line after each vendor updated with year1 & year2 value, otherwise it'd always initiated with first row.
			if($this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgFromYear1,">=") and $this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgToYear1,"<="))
				$teamplayerMessage = $resultRow->TeamplayerClientPromoMessage; 
			elseif($this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgFromYear2,">=") and $this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgToYear2,"<="))
				$teamplayerMessage = $resultRow->TeamplayerClientPromoMessage2; 
		}

		if (strlen(trim($pkgcode))>1) {
			//check if this is part of a package and if it must have a certain number of nights priced at 0
			$srow = DB::table('tblPkgItem')
						->where([['PkgCode', $pkgcode], ['ItemCode', $prodcode], ['DefaultNights', $nights]])
						->get()->first();
			if ($srow) {
				if ($srow->PullPriceFlag==0) { //don't pull price for package included nights
					if ($nights==1) { //this is probably not a hotel, don't pull price, set to 0
						$nt_price_flag[0]=0;
					} else {
						for ($i=0;$i<$nights;$i++) {
							$date_to_check=$this->commonRepo->date_plus_minus($date,"ADD",$i);
							$end_date=$this->commonRepo->date_plus_minus($pkgdate,"ADD",$srow->DefaultNights-1);
							if ($this->commonRepo->date_compare($date_to_check,$pkgdate,">=") and $this->commonRepo->date_compare($date_to_check,$end_date,"<=")) {
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

		$hasAgencyPricing = false;
		
		while ($night_ctr <= $nights) {
			$nt=$night_ctr-1;
			if ($nt_price_flag[$nt]) {

				if (env('PRICE_CATEGORIES')<>"NO") {
					//First check if this agency has a special price or if this product uses Category Pricing
					$psql="select PricingCategory from tblAgency where agcode='$agcode'";
					$presult=sql_cover($psql,"select",0,0,0,0);
					$prow=mysql_fetch_object($presult);

					$query="select * from tblSpecialException where prodcode='$prodcode' and begin<=date_add('$date', interval $nt day) and end >=date_add('$date', interval $nt day) and (occ1=$occupancy or occ2=$occupancy or occ3=$occupancy or occ4=$occupancy) and AgCode='$agcode'";
					$p_type="except";
					
					if(!($price_obj=mysql_fetch_object(sql_cover($query,"select",0,0,0,0)))) {
						$query="select * from tblSpecialPrice where prodcode='$prodcode' and begin<=date_add('$date', interval $nt day) and end >=date_add('$date', interval $nt day) and (occ1=$occupancy or occ2=$occupancy or occ3=$occupancy or occ4=$occupancy) and AgCode='$agcode'";
						$p_type="default";
						
						if(!($price_obj=mysql_fetch_object(sql_cover($query,"select",0,0,0,0)))) {
							$query="select * from tblCategoryException where prodcode='$prodcode' and begin<=date_add('$date', interval $nt day) and end >=date_add('$date', interval $nt day) and (occ1=$occupancy or occ2=$occupancy or occ3=$occupancy or occ4=$occupancy) and PricingCategory='$prow->PricingCategory'";
							$p_type="except";
							
							if(!($price_obj=mysql_fetch_object(sql_cover($query,"select",0,0,0,0)))) {
								$query="select * from tblCategoryPrice where prodcode='$prodcode' and begin<=date_add('$date', interval $nt day) and end >=date_add('$date', interval $nt day) and (occ1=$occupancy or occ2=$occupancy or occ3=$occupancy or occ4=$occupancy) and PricingCategory='$prow->PricingCategory'";
								$p_type="default";
								
								if(!($price_obj=mysql_fetch_object(sql_cover($query,"select",0,0,0,0)))) {
									$query="select * from tblDefaultException where prodcode='$prodcode' and begin<=date_add('$date', interval $nt day) and end >=date_add('$date', interval $nt day) and (occ1=$occupancy or occ2=$occupancy or occ3=$occupancy or occ4=$occupancy)";
									$p_type="except";
									
									if(!($price_obj=mysql_fetch_object(sql_cover($query,"select",0,0,0,0)))) {
										$query="select * from tblDefaultPrice where prodcode='$prodcode' and begin<=DATE_ADD('$date', INTERVAL $nt day) and end >=DATE_ADD('$date', INTERVAL $nt day) and (occ1=$occupancy or occ2=$occupancy or occ3=$occupancy or occ4=$occupancy)";
										$p_type="default";
										
										if(!($price_obj=mysql_fetch_object(sql_cover($query,"select",0,0,0,0)))) {
											return "fail";
										}
									}
								}
							}
						}
					}
				} else { //pricing_catories=NO
					$price_obj = DB::table('tblSpecialException')
									->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")], ['AgCode', $agcode]])
									->where(function ($query) use ($occupancy) {
										$query->where('occ1', '=', $occupancy)
											  ->orWhere('occ2', '=', $occupancy)
											  ->orWhere('occ3', '=', $occupancy)
											  ->orWhere('occ4', '=', $occupancy);
									})
									->get()->first();
					$p_type="special except";//$p_type="except";
					if(!$price_obj) {
						$price_obj = DB::table('tblSpecialPrice')
									->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")], ['AgCode', $agcode]])
									->where(function ($query) use ($occupancy) {
										$query->where('occ1', '=', $occupancy)
											  ->orWhere('occ2', '=', $occupancy)
											  ->orWhere('occ3', '=', $occupancy)
											  ->orWhere('occ4', '=', $occupancy);
									})
									->get()->first();
						$p_type="special default";//$p_type="default";
						
						//Nov 22 2008-MH Added Promo Pricing but this does not override special agency agency pricing or category pricing used by Vola
						if(!$price_obj) {
							$price_obj = DB::table('tblPromoPrice')
											->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")], ['book_begin', '<=', $today], ['book_end', '>=', $today], ['overrideDef', 1], ['overrideExp', 1]])
											->where(function ($query) use ($occupancy) {
												$query->where('occ1', '=', $occupancy)
													  ->orWhere('occ2', '=', $occupancy)
													  ->orWhere('occ3', '=', $occupancy)
													  ->orWhere('occ4', '=', $occupancy);
											})
											->get()->first();
							$p_type="promo";
							
							if(!$price_obj or !$this->commonRepo->overlappingPromosAllowed(false, $price_obj, $p_row, $date)) { //No Promo with DEF&EXC FOUND
								//check if either a DEF or EXC is found
								$price_obj = DB::table('tblPromoPrice')
												->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")], ['book_begin', '<=', $today], ['book_end', '>=', $today]])
												->where(function ($query) use ($occupancy) {
													$query->where('occ1', '=', $occupancy)
														  ->orWhere('occ2', '=', $occupancy)
														  ->orWhere('occ3', '=', $occupancy)
														  ->orWhere('occ4', '=', $occupancy);
												})
												->where(function ($query) {
													$query->where('overrideDef', '=', 1)
														  ->orWhere('overrideExp', '=', 1);
												})
												->get()->first();
								if (!$price_obj or !$this->commonRepo->overlappingPromosAllowed(false, $price_obj, $p_row, $date)) {
									$price_obj = DB::table('tblDefaultException')
											->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")]])
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
														->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")]])
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
														->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")]])
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
														->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")]])
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
						}
					}
					
					if ($p_type == "special except" || $p_type == "special default")
					{
						$hasAgencyPricing = true;
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
									->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")], ['book_begin', '<=', $today], ['book_end', '>=', $today]])
									->get()->first();
					
					if ($promo_row) {						
						if (!isset($promoFoundAlready))
							$promoFoundAlready = false;
						if ($this->commonRepo->overlappingPromosAllowed($promoFoundAlready,$promo_row,$p_row,$date))
						{	
							$promoFoundAlready = true;
							
							//populate promo info from promo record
							if (($p_type == "except" or $p_type == "special except") and $promo_row->overrideExpMessage) {
								if ($promo_row->$Message)
									$PromoMsgMap[$promo_row->$Message]=true;
							} else if (($p_type == "default" or $p_type == "special default") and $promo_row->overrideDefMessage) {
								if ($promo_row->$Message)
									$PromoMsgMap[$promo_row->$Message]=true;
							} if ($promo_row->NightsToStay>0 and $promo_row->NightsFree>0) {
								
								
								//check if current date + nights needed to stay for promo is within the date range of the promo
								//Added 1/10/10-MH
								$current_night_date=$this->commonRepo->date_plus_minus($date,"ADD",$nt);
								$end_night_date=$this->commonRepo->date_plus_minus($current_night_date,"ADD",($promo_row->NightsToStay+$promo_row->NightsFree-1));
								if ($this->commonRepo->date_compare($end_night_date,$promo_row->End,"<=")) {
									if ($price_obj->$Message)
										$PromoMsgMap[$price_obj->$Message]=true;
								
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

						//Next two following lines of code were implemented in order to avoid Non-Declared propery error in Dream2.
						if ($price_obj->$Message)
							$PromoMsgMap[$price_obj->$Message]=true;
						$price_obj->NightsToStay=(isset($price_obj->NightsToStay))?$price_obj->NightsToStay:0;
						//$PromoMsg = $price_obj->$Message;

						if ($price_obj->NightsToStay>0 and $price_obj->NightsFree>0) {
							//check if current date + nights needed to stay for promo is within the date range of the promo
							//Added 1/10/10-MH
							$current_night_date=$this->commonRepo->date_plus_minus($date,"ADD",$nt);
							$end_night_date=$this->commonRepo->date_plus_minus($current_night_date,"ADD",($price_obj->NightsToStay+$price_obj->NightsFree-1));
							if ($this->commonRepo->date_compare($end_night_date,$price_obj->End,"<=")) {
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
					
					$promoFoundAlready = true;
				
					///check for promo info on current record and populate
					if ($price_obj->$Message)
						$PromoMsgMap[$price_obj->$Message]=true;
						
				//if ($price_obj->arriveOnStartDate == 1 && $price_obj->Begin >= $date){	$promo_on_start_date;	} //Check the promo restriction: Must Arrive on Date or After Start Date
						
					if ($price_obj->NightsToStay>0 and $price_obj->NightsFree>0) {	//and $promo_on_start_date){
						//check if current date + nights needed to stay for promo is within the date range of the promo
						//Added 1/10/10-MH
						$current_night_date=$this->commonRepo->date_plus_minus($date,"ADD",$nt);
						$end_night_date=$this->commonRepo->date_plus_minus($current_night_date,"ADD",($price_obj->NightsToStay+$price_obj->NightsFree-1));
						if ($this->commonRepo->date_compare($end_night_date,$price_obj->End,"<=")) {
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
				
				$DiscountAmount=0;
				if ($prodcode <> "BKGFEE" and !$hasAgencyPricing and !$p_row->ExcludeCountryDiscount and substr($prodcode,3,1)<>'E' and substr($prodcode,3,1)<>'P')
				{
					if(isset($agcode) && strlen($agcode)>0) {
						//get country discount value 
						$staying_night = date('Y-m-d', strtotime("+ $nt days", strtotime($date)));
						$DiscountAmount = $this->commonRepo->get_country_discount($agcode, $staying_night);
					}
				}
				
				// CXL policy related changes
				$this->get_cxl_policies($price_obj, $p_row, $prodcode, $date, $nt, $ddays_prior1, $ttype1, $aamount1, $ddays_prior2, $ttype2, $aamount2, $ddays_prior3, $ttype3, $aamount3, $allCxlPolicies);
				// CXL policy related changes end here
				
				if ($AddPriceInThisIteration=="YES") {
					//If Child then don't check occ prices
					if ($PaxType=="C") {
						$price+=$price_obj->ChildPrice - ($price_obj->ChildPrice * $DiscountAmount/100);;
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
							$pri="Price".$i;
							$ppri="pprice_".$i;
							if ($price_obj->$occ > $MaxOccPrice and $price_obj->$pri > 0) $MaxOccPrice=$price_obj->$occ;
							if ($price_obj->$occ==$occupancy or $check_all_prices=="YES") {
								if ($price_obj->$pri=="") {
									if ($check_all_prices<>"YES") return "fail";
									else continue;
								}
								if ($price_obj->$pri >= 0) {
									$price+=$price_obj->$pri - ($price_obj->$pri * $DiscountAmount/100);
									$$ppri+=$price_obj->$pri - ($price_obj->$pri * $DiscountAmount/100);
									//check for surcharge
									if ($day_surcharges_present=="YES") {
										//determine day of week
										$curr_day=$this->commonRepo->date_plus_minus($date,"ADD",$nt);
										//ereg( "([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})", $curr_day, $datebits);
										preg_match('/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/', $curr_day, $datebits);
										$day=date("D",mktime(0,0,0,$datebits[2],$datebits[3],$datebits[1]));
										$day=strtolower($day).$i;
										if ($qqrow->$day > 0) {
											$price+=$qqrow->$day - ($qqrow->$day * $DiscountAmount/100);
											$$ppri+=$qqrow->$day - ($qqrow->$day * $DiscountAmount/100);
										}
									} //surcharge record present
								} //price_obj->$pri > 0
							} //price exists for occupancy or check_all=yes
						} //end for
						
						if($Child1Age>0 || $Child2Age>0) {
							if($Child1Age>=$p_row->ChildAgeRange1Min and $Child1Age<=$p_row->ChildAgeRange1Max) $price+=$price_obj->ChildPrice;
							elseif($Child1Age>=$p_row->ChildAgeRange2Min and $Child1Age<=$p_row->ChildAgeRange2Max) $price+=$price_obj->ChildPrice1;
							elseif($Child1Age>=$p_row->ChildAgeRange3Min and $Child1Age<=$p_row->ChildAgeRange3Max) $price+=$price_obj->ChildPrice2;
							elseif($Child1Age>=$p_row->ChildAgeRange4Min and $Child1Age<=$p_row->ChildAgeRange4Max) $price+=$price_obj->ChildPrice3;
							
							if($Child2Age>=$p_row->ChildAgeRange1Min and $Child2Age<=$p_row->ChildAgeRange1Max) $price+=$price_obj->ChildPrice;
							elseif($Child2Age>=$p_row->ChildAgeRange2Min and $Child2Age<=$p_row->ChildAgeRange2Max) $price+=$price_obj->ChildPrice1;
							elseif($Child2Age>=$p_row->ChildAgeRange3Min and $Child2Age<=$p_row->ChildAgeRange3Max) $price+=$price_obj->ChildPrice2;
							elseif($Child2Age>=$p_row->ChildAgeRange4Min and $Child2Age<=$p_row->ChildAgeRange4Max) $price+=$price_obj->ChildPrice3;
						}
					
					} //endif child price
				} else if ($NightFreeApplied == $NightsFree_rtn) { //AddPriceInThisIteration=YES
					$DontDoThisAgain_BecauseFreeNightAlreadyGiven="YES";
				}
			} else { //nt_price_flag=1
				$price+=0;
			}
			$night_ctr+=1;
		} //while looping through the nights

		if (count($PromoMsgMap))
		{
			$PromoMsg = implode(',', array_keys($PromoMsgMap)); 
		}
		
		//add teamplayer promo message
		if (isset($teamplayerMessage) && $teamplayerMessage)
		{
			$PromoMsg .= " Teamplayer extra:".$teamplayerMessage;
		}

		$price=round(($price/$nights),2);
		$pricing_type=$price_obj->PricingType;
		
		if(substr($prodcode, 3,1) == 'M'){
			$parentPriceObj = $this->get_parent_price_obj($date,$nights,$occupancy,$agcode,$PaxType,$check_all_prices,$pprice_1,$pprice_2,$pprice_3,$pprice_4,$PromoMsg,$NightsFree_rtn,$NightsToStay_rtn,$vendorMessage,$Child1Age, $Child2Age);
			if($nights >= ($parentPriceObj->NightsToStay + $parentPriceObj->NightsFree)){
				$price = round(($price*($nights-$parentPriceObj->NightsFree))/$nights, 2);
			}
		}
		
		return $price;

	} //end function avg_price
	
	public function get_parent_price_obj($date,$nights,$occupancy,$agcode,$PaxType,$check_all_prices,&$pprice_1,&$pprice_2,&$pprice_3,&$pprice_4,&$PromoMsg,&$NightsFree_rtn=0,&$NightsToStay_rtn=0,$vendorMessage=FALSE,$Child1Age='', $Child2Age='') {

		global $comboIdForParentProdCode;
		
		if(!isset($comboIdForParentProdCode) || $comboIdForParentProdCode != ""){
			$comboIdForParentProdCode = $_REQUEST['comboIdForParentProdCode2'];
		}

		$comboProductRow = DB::table('tblProductCombo')
								->select(DB::raw('ProdCode as parentCode'))
								->where('ComboID', $comboIdForParentProdCode)
								->get()->first();
		if ($comboProductRow) {
			$prodcode = $comboProductRow->parentCode;
		}

		//Determine the items average price over the given period
		$night_ctr=1;
		$PromoFound="NO";
		$FirstPromoNight=0;
		$DontDoThisAgain_BecauseFreeNightAlreadyGiven="NO";
		$today=$this->commonRepo->todays_date();
		$price=0;
		$pprice_1=0;
		$pprice_2=0;
		$pprice_3=0;
		$pprice_4=0;
		$Message = "PromoMsg";
		if ($vendorMessage === TRUE)
		{
			$Message = "VendorMsg";	
		}
		$PromoMsgMap = array();
		
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

		//Verify that the maximum # of occupants for this product has not been exceeded
		$p_row = DB::table('tblProduct as p')
					->select(DB::raw('p.*, v.overlappingPromos, v.VendorID, v.ChildAgeRange1Min, v.ChildAgeRange1Max, v.ChildAgeRange2Min, v.ChildAgeRange2Max, v.ChildAgeRange3Min, v.ChildAgeRange3Max, v.ChildAgeRange4Min, v.ChildAgeRange4Max, v.ExcludeCountryDiscount'))
					->join('tblVendor as v', 'v.vendorid', 'p.vendor')
					->where('p.prodcode', $prodcode)
					->get()->first();
			
		if ($check_all_prices<>"YES") {
			if ($p_row->MaxOcc > 0) {
				if ($NumOccupants > $p_row->MaxOcc) {
					//Not allowed... occupants exceeds max
					//js_alert("$NumOccupants occupants exceeds $p_row->MaxOcc which is the maximum allowed ($prodcode):");
					return "fail";
				}
			} else {
				if ($NumOccupants > 1) {
					//Not allowed... occupants exceeds max
					//js_alert("$NumOccupants occupants exceeds $p_row->MaxOcc which is the maximum allowed ($prodcode)");
					return "fail";
				}
			}
		} //verify max # occ if checkall<>yes		
		
		//rm:add teamplayer vendor message to promo message
		$resultRow = DB::table('tblProduct as p')
						->select(DB::raw('v.TeamplayerClientPromoMessage, v.TeamplayerVendorPromoMessage, v.TeamplayerClientPromoMessage2, v.TeamplayerVendorPromoMessage2, v.TeamplayerMsgFromYear1, v.TeamplayerMsgFromYear2, v.TeamplayerMsgToYear1, v.TeamplayerMsgToYear2'))
						->join('tblVendor as v', 'v.VendorID', 'p.Vendor')
						->where('p.ProdCode', $prodcode)
						->get()->first();
		if ($Message == "VendorMsg")
		{
			//$teamplayerMessage = $resultRow->TeamplayerVendorPromoMessage; //**delete this line after each vendor updated with year1 & year2 value, otherwise it'd always initiated with first row.
			if($this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgFromYear1,">=") and $this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgToYear1,"<="))
				$teamplayerMessage = $resultRow->TeamplayerVendorPromoMessage;
			elseif($this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgFromYear2,">=") and $this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgToYear2,"<="))
				$teamplayerMessage = $resultRow->TeamplayerVendorPromoMessage2;
		}
		else
		{
			//$teamplayerMessage = $resultRow->TeamplayerClientPromoMessage; //**delete this line after each vendor updated with year1 & year2 value, otherwise it'd always initiated with first row.
			if($this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgFromYear1,">=") and $this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgToYear1,"<="))
				$teamplayerMessage = $resultRow->TeamplayerClientPromoMessage; 
			elseif($this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgFromYear2,">=") and $this->commonRepo->date_compare($date,$resultRow->TeamplayerMsgToYear2,"<="))
				$teamplayerMessage = $resultRow->TeamplayerClientPromoMessage2; 
		}

		if (strlen(trim($pkgcode))>1) {
			//check if this is part of a package and if it must have a certain number of nights priced at 0			
			$srow = DB::table('tblPkgItem')
						->where([['PkgCode', $pkgcode], ['ItemCode', $prodcode], ['DefaultNights', $nights]])
						->get()->first();
			
			if ($srow) {
				if ($srow->PullPriceFlag==0) { //don't pull price for package included nights
					if ($nights==1) { //this is probably not a hotel, don't pull price, set to 0
						$nt_price_flag[0]=0;
					} else {
						for ($i=0;$i<$nights;$i++) {
							$date_to_check=$this->commonRepo->date_plus_minus($date,"ADD",$i);
							$end_date=$this->commonRepo->date_plus_minus($pkgdate,"ADD",$srow->DefaultNights-1);
							if ($this->commonRepo->date_compare($date_to_check,$pkgdate,">=") and $this->commonRepo->date_compare($date_to_check,$end_date,"<=")) {
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

		$hasAgencyPricing = false;
		
		while ($night_ctr <= $nights) {
			$nt=$night_ctr-1;
			if ($nt_price_flag[$nt]) {

				if (env('PRICE_CATEGORIES')<>"NO") {
					//First check if this agency has a special price or if this product uses Category Pricing
					$prow = DB::table('tblAgency')
								->select('PricingCategory')
								->where('agcode', $agcode)
								->get()->first();

					$price_obj = DB::table('tblSpecialException')
									->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")], ['AgCode', $agcode]])
									->where(function ($query) use ($occupancy) {
										$query->where('occ1', '=', $occupancy)
											  ->orWhere('occ2', '=', $occupancy)
											  ->orWhere('occ3', '=', $occupancy)
											  ->orWhere('occ4', '=', $occupancy);
									})
									->get()->first();
					$p_type="except";
					
					if(!$price_obj) {
						$price_obj = DB::table('tblSpecialPrice')
										->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")], ['AgCode', $agcode]])
										->where(function ($query) use ($occupancy) {
											$query->where('occ1', '=', $occupancy)
												  ->orWhere('occ2', '=', $occupancy)
												  ->orWhere('occ3', '=', $occupancy)
												  ->orWhere('occ4', '=', $occupancy);
										})
										->get()->first();
						$p_type="default";
						
						if(!$price_obj) {
							$price_obj = DB::table('tblCategoryException')
											->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")], ['PricingCategory', $prow->PricingCategory]])
											->where(function ($query) use ($occupancy) {
												$query->where('occ1', '=', $occupancy)
													  ->orWhere('occ2', '=', $occupancy)
													  ->orWhere('occ3', '=', $occupancy)
													  ->orWhere('occ4', '=', $occupancy);
											})
											->get()->first();
							$p_type="except";
							
							if(!$price_obj) {
								$price_obj = DB::table('tblCategoryPrice')
												->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")], ['PricingCategory', $prow->PricingCategory]])
												->where(function ($query) use ($occupancy) {
													$query->where('occ1', '=', $occupancy)
														  ->orWhere('occ2', '=', $occupancy)
														  ->orWhere('occ3', '=', $occupancy)
														  ->orWhere('occ4', '=', $occupancy);
												})
												->get()->first();
								$p_type="default";
								
								if(!$price_obj) {
									$price_obj = DB::table('tblDefaultException')
													->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")]])
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
														->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")]])
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
								}
							}
						}
					}
				} else { //pricing_catories=NO
					$price_obj = DB::table('tblSpecialException')
									->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")], ['AgCode', $agcode]])
									->where(function ($query) use ($occupancy) {
										$query->where('occ1', '=', $occupancy)
											  ->orWhere('occ2', '=', $occupancy)
											  ->orWhere('occ3', '=', $occupancy)
											  ->orWhere('occ4', '=', $occupancy);
									})
									->get()->first();
					
					$p_type="special except";//$p_type="except";
					
					if(!$price_obj) {
						$price_obj = DB::table('tblSpecialPrice')
										->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")], ['AgCode', $agcode]])
										->where(function ($query) use ($occupancy) {
											$query->where('occ1', '=', $occupancy)
												  ->orWhere('occ2', '=', $occupancy)
												  ->orWhere('occ3', '=', $occupancy)
												  ->orWhere('occ4', '=', $occupancy);
										})
										->get()->first();
						$p_type="special default";//$p_type="default";
						
						//Nov 22 2008-MH Added Promo Pricing but this does not override special agency agency pricing or category pricing used by Vola
						if(!$price_obj) {
							$price_obj = DB::table('tblPromoPrice')
											->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")], ['book_begin', '<=', $today], ['book_end', '>=', $today], ['overrideDef', 1], ['overrideExp', 1]])
											->where(function ($query) use ($occupancy) {
												$query->where('occ1', '=', $occupancy)
													  ->orWhere('occ2', '=', $occupancy)
													  ->orWhere('occ3', '=', $occupancy)
													  ->orWhere('occ4', '=', $occupancy);
											})
											->get()->first();
							$p_type="promo";
							
							if(!$price_obj or !$this->commonRepo->overlappingPromosAllowed(false, $price_obj, $p_row, $date)) { //No Promo with DEF&EXC FOUND
								//check if either a DEF or EXC is found
								$query="select * from tblPromoPrice where prodcode='$prodcode' and begin<=date_add('$date', interval $nt day) and end >=date_add('$date', interval $nt day) and (occ1=$occupancy or occ2=$occupancy or occ3=$occupancy or occ4=$occupancy) and book_begin<='$today' and book_end>='$today' and (overrideDef=1 or overrideExp=1)";
								$price_obj = DB::table('tblPromoPrice')
												->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")], ['book_begin', '<=', $today], ['book_end', '>=', $today]])
												->where(function ($query) use ($occupancy) {
													$query->where('occ1', '=', $occupancy)
														  ->orWhere('occ2', '=', $occupancy)
														  ->orWhere('occ3', '=', $occupancy)
														  ->orWhere('occ4', '=', $occupancy);
												})
												->where(function ($query) {
													$query->where('overrideDef', '=', 1)
														  ->orWhere('overrideExp', '=', 1);
												})
												->get()->first();
								if (!$price_obj or !$this->commonRepo->overlappingPromosAllowed(false, $price_obj, $p_row, $date)) {
									$price_obj = DB::table('tblDefaultException')
													->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")]])
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
														->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")]])
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
														->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")]])
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
														->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")]])
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
						}
					}
					
					if ($p_type == "special except" || $p_type == "special default")
					{
						$hasAgencyPricing = true;
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
									->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")], ['book_begin', '<=', $toady], ['book_end', '>=', $today]])
									->get()->first();
					
					if ($promo_row) {
						
						if ($this->commonRepo->overlappingPromosAllowed($promoFoundAlready,$promo_row,$p_row,$date))
						{	
							$promoFoundAlready = true;
							
							//populate promo info from promo record
							if (($p_type == "except" or $p_type == "special except") and $promo_row->overrideExpMessage) {
								if ($promo_row->$Message)
									$PromoMsgMap[$promo_row->$Message]=true;
							} else if (($p_type == "default" or $p_type == "special default") and $promo_row->overrideDefMessage) {
								if ($promo_row->$Message)
									$PromoMsgMap[$promo_row->$Message]=true;
							} if ($promo_row->NightsToStay>0 and $promo_row->NightsFree>0) {
								
								
								//check if current date + nights needed to stay for promo is within the date range of the promo
								//Added 1/10/10-MH
								$current_night_date=$this->commonRepo->date_plus_minus($date,"ADD",$nt);
								$end_night_date=$this->commonRepo->date_plus_minus($current_night_date,"ADD",($promo_row->NightsToStay+$promo_row->NightsFree-1));
								if ($this->commonRepo->date_compare($end_night_date,$promo_row->End,"<=")) {
									if ($price_obj->$Message)
										$PromoMsgMap[$price_obj->$Message]=true;
								
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

						//Next two following lines of code were implemented in order to avoid Non-Declared propery error in Dream2.
						if ($price_obj->$Message)
							$PromoMsgMap[$price_obj->$Message]=true;
						$price_obj->NightsToStay=(isset($price_obj->NightsToStay))?$price_obj->NightsToStay:0;
						//$PromoMsg = $price_obj->$Message;

						if ($price_obj->NightsToStay>0 and $price_obj->NightsFree>0) {
							//check if current date + nights needed to stay for promo is within the date range of the promo
							//Added 1/10/10-MH
							$current_night_date=$this->commonRepo->date_plus_minus($date,"ADD",$nt);
							$end_night_date=$this->commonRepo->date_plus_minus($current_night_date,"ADD",($price_obj->NightsToStay+$price_obj->NightsFree-1));
							if ($this->commonRepo->date_compare($end_night_date,$price_obj->End,"<=")) {
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
					
					$promoFoundAlready = true;
				
					///check for promo info on current record and populate
					if ($price_obj->$Message)
						$PromoMsgMap[$price_obj->$Message]=true;
						
				//if ($price_obj->arriveOnStartDate == 1 && $price_obj->Begin >= $date){	$promo_on_start_date;	} //Check the promo restriction: Must Arrive on Date or After Start Date
						
					if ($price_obj->NightsToStay>0 and $price_obj->NightsFree>0) {	//and $promo_on_start_date){
						//check if current date + nights needed to stay for promo is within the date range of the promo
						//Added 1/10/10-MH
						$current_night_date=$this->commonRepo->date_plus_minus($date,"ADD",$nt);
						$end_night_date=$this->commonRepo->date_plus_minus($current_night_date,"ADD",($price_obj->NightsToStay+$price_obj->NightsFree-1));
						if ($this->commonRepo->date_compare($end_night_date,$price_obj->End,"<=")) {
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
				
				$DiscountAmount=0;
				if ($prodcode <> "BKGFEE" and !$hasAgencyPricing and substr($prodcode,3,1)<>'E' and !$p_row->ExcludeCountryDiscount)
				{
					if(isset($agcode) && strlen($agcode)>0) {
						//get country discount value 
						$staying_night = date('Y-m-d', strtotime("+ $nt days", strtotime($date)));
						$DiscountAmount = $this->commonRepo->get_country_discount($agcode, $staying_night);
					}
				}
				
				if ($AddPriceInThisIteration=="YES") {
					//If Child then don't check occ prices
					if ($PaxType=="C") {
						$price+=$price_obj->ChildPrice - ($price_obj->ChildPrice * $DiscountAmount/100);;
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
							$pri="Price".$i;
							$ppri="pprice_".$i;
							if ($price_obj->$occ > $MaxOccPrice and $price_obj->$pri > 0) $MaxOccPrice=$price_obj->$occ;
							if ($price_obj->$occ==$occupancy or $check_all_prices=="YES") {
								if ($price_obj->$pri=="") {
									if ($check_all_prices<>"YES") return "fail";
									else continue;
								}
								if ($price_obj->$pri >= 0) {
									$price+=$price_obj->$pri - ($price_obj->$pri * $DiscountAmount/100);
									$$ppri+=$price_obj->$pri - ($price_obj->$pri * $DiscountAmount/100);
									//check for surcharge
									if ($day_surcharges_present=="YES") {
										//determine day of week
										$curr_day=$this->commonRepo->date_plus_minus($date,"ADD",$nt);
										//ereg( "([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})", $curr_day, $datebits);
										preg_match('/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/', $curr_day, $datebits);
										$day=date("D",mktime(0,0,0,$datebits[2],$datebits[3],$datebits[1]));
										$day=strtolower($day).$i;
										if ($qqrow->$day > 0) {
											$price+=$qqrow->$day - ($qqrow->$day * $DiscountAmount/100);
											$$ppri+=$qqrow->$day - ($qqrow->$day * $DiscountAmount/100);
										}
									} //surcharge record present
								} //price_obj->$pri > 0
							} //price exists for occupancy or check_all=yes
						} //end for
						
						if($Child1Age>0 || $Child2Age>0) {
							if($Child1Age>=$p_row->ChildAgeRange1Min and $Child1Age<=$p_row->ChildAgeRange1Max) $price+=$price_obj->ChildPrice;
							elseif($Child1Age>=$p_row->ChildAgeRange2Min and $Child1Age<=$p_row->ChildAgeRange2Max) $price+=$price_obj->ChildPrice1;
							elseif($Child1Age>=$p_row->ChildAgeRange3Min and $Child1Age<=$p_row->ChildAgeRange3Max) $price+=$price_obj->ChildPrice2;
							elseif($Child1Age>=$p_row->ChildAgeRange4Min and $Child1Age<=$p_row->ChildAgeRange4Max) $price+=$price_obj->ChildPrice3;
							
							if($Child2Age>=$p_row->ChildAgeRange1Min and $Child2Age<=$p_row->ChildAgeRange1Max) $price+=$price_obj->ChildPrice;
							elseif($Child2Age>=$p_row->ChildAgeRange2Min and $Child2Age<=$p_row->ChildAgeRange2Max) $price+=$price_obj->ChildPrice1;
							elseif($Child2Age>=$p_row->ChildAgeRange3Min and $Child2Age<=$p_row->ChildAgeRange3Max) $price+=$price_obj->ChildPrice2;
							elseif($Child2Age>=$p_row->ChildAgeRange4Min and $Child2Age<=$p_row->ChildAgeRange4Max) $price+=$price_obj->ChildPrice3;
						}
					
					} //endif child price
				} else if ($NightFreeApplied == $NightsFree_rtn) { //AddPriceInThisIteration=YES
					$DontDoThisAgain_BecauseFreeNightAlreadyGiven="YES";
				}
			} else { //nt_price_flag=1
				$price+=0;
			}
			$night_ctr+=1;
		} //while looping through the nights

		if (count($PromoMsgMap))
		{
			$PromoMsg = implode(',', array_keys($PromoMsgMap)); 
		}
		
		//add teamplayer promo message
		if (isset($teamplayerMessage) && $teamplayerMessage)
		{
			$PromoMsg .= " Teamplayer extra:".$teamplayerMessage;
		}

		$price=round(($price/$nights),2);
		$pricing_type=$price_obj->PricingType;

		return $price_obj;
	}
	
	//GET CXL policy 
	public function get_cxl_policies($price_obj, $p_row, $prodcode, $date, $nt, &$ddays_prior1, &$ttype1, &$aamount1, &$ddays_prior2, &$ttype2, &$aamount2, &$ddays_prior3, &$ttype3, &$aamount3, &$allCxlPolicies) {
		
		if(1 == $price_obj->UseCancel && !(empty( $price_obj->days_prior1) &&  empty($price_obj->days_prior2) && empty($price_obj->days_prior3))){
			//echo "Pricing has cxl policy - $price_obj->days_prior1, $price_obj->days_prior2, $price_obj->days_prior3<br />";
			$ddays_prior1 = $price_obj->days_prior1;
			$ddays_prior2 = $price_obj->days_prior2;
			$ddays_prior3 = $price_obj->days_prior3;

			$ttype1 = $price_obj->type1;
			$ttype2 = $price_obj->type2;
			$ttype3 = $price_obj->type3;

			$aamount1 = $price_obj->amount1;
			$aamount2 = $price_obj->amount2;
			$aamount3 = $price_obj->amount3; 
		}
		else { // No CXL fee with the price, so use product cxl policy
			$ddays_prior1 = $p_row->days_prior1;
			$ddays_prior2 = $p_row->days_prior2;
			$ddays_prior3 = $p_row->days_prior3;

			$ttype1 = $p_row->type1;
			$ttype2 = $p_row->type2;
			$ttype3 = $p_row->type3;

			$aamount1 = $p_row->amount1;
			$aamount2 = $p_row->amount2;
			$aamount3 = $p_row->amount3;
		}
		
		// Also get other prices for promo or other fall backs
		// TO-DO: also fetch use this policy wherever appropriate
		
		$allCxlPolicies = array();
		for($i=1; $i <= 4; $i++){
			$query = 'cxlQuery_' . $i;
			$cxlSource='';
			$cxlSource = $i == 1 ? 'product' : $cxlSource;
			$cxlSource = $i == 2 ? 'default' : $cxlSource;
			$cxlSource = $i == 3 ? 'except' : $cxlSource;
			$cxlSource = $i == 4 ? 'promo' : $cxlSource;
			
			if ($i == 1) 
				$cxl_obj = DB::table('tblProduct as p')
								->select(DB::raw('p.days_prior1, p.type1, p.amount1, p.days_prior2, p.type2, p.amount2, p.days_prior3, p.type3, p.amount3'))
								->where('ProdCode', $prodcode)
								->get()->first();
			elseif ($i == 2)
				$cxl_obj = DB::table('tblDefaultPrice as d')
								->select(DB::raw('d.days_prior1, d.type1, d.amount1, d.days_prior2, d.type2, d.amount2, d.days_prior3, d.type3, d.amount3'))
								->where([['d.ProdCode', $prodcode], ['d.begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['d.end', '>=', DB::raw("date_add('$date', interval $nt day)")]])
								->get()->first();
			elseif ($i == 3)
				$cxl_obj = DB::table('tblDefaultException as e')
								->select(DB::raw('e.days_prior1, e.type1, e.amount1, e.days_prior2, e.type2, e.amount2, e.days_prior3, e.type3, e.amount3'))
								->where([['e.ProdCode', $prodcode], ['e.begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['e.end', '>=', DB::raw("date_add('$date', interval $nt day)")]])
								->get()->first();
			elseif ($i == 4)
				$cxl_obj = DB::table('tblPromoPrice as p')
								->select(DB::raw('p.days_prior1, p.type1, p.amount1, p.days_prior2, p.type2, p.amount2, p.days_prior3, p.type3, p.amount3'))
								->where([['p.ProdCode', $prodcode], ['p.begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['p.end', '>=', DB::raw("date_add('$date', interval $nt day)")]])
								->get()->first();

			if ($cxl_obj){  
				for($j=1; $j <= 3; $j++){ 
					$days = 'days_prior'.$j;  
					$type = 'type'.$j;
					$amount = 'amount'.$j;

					// TO-DO have to do a usethiscxlPolicy check as well
					if($cxl_obj->$days !== NULL && $cxl_obj->$days > 0){
						$policy = new \stdClass();
						$policy->daysPrior = $cxl_obj->$days;
						$policy->type = $cxl_obj->$type;
						$policy->amount = $cxl_obj->$amount;
						$policy->cxlSource = $cxlSource;
						
						array_push($allCxlPolicies, $policy); 
					}
				}
			}
		}

		//echo $allCxlPolicies[0]->days_prior . '<br />';
	}
	
}