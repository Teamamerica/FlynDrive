<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

use App\Repositories\TBCommonRepo;

class TBFuncAvgCostRepo {
	
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
	 
	function avg_cost($prodcode,$pkgcode,$pkgdate,$date,$nights,$occupancy,$Qty,$agcode,$PaxType,$check_all_prices,&$pprice_1,&$pprice_2,&$pprice_3,&$pprice_4,&$PromoMsg,&$NightsFree_rtn=0,&$NightsToStay_rtn=0,$ReturnValue="TOTAL",$Child1Age='', $Child2Age='') {
		//Determine the items average price over the given period
		$night_ctr=1;
		$PromoFound="NO";
		$FirstPromoNight=0;
		$today=$this->commonRepo->todays_date();
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
		
		//Verify that the maximum # of occupants for this product has not been exceeded		
		$p_row = DB::table('tblProduct as p')
					->select(DB::raw('p.*, v.overlappingPromos, v.VendorID, v.ChildAgeRange1Min, v.ChildAgeRange1Max, v.ChildAgeRange2Min, v.ChildAgeRange2Max, v.ChildAgeRange3Min, v.ChildAgeRange3Max, v.ChildAgeRange4Min, v.ChildAgeRange4Max'))
					->join('tblVendor as v', 'v.vendorid', 'p.vendor')
					->where('p.prodcode', $prodcode)
					->get()->first();

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
		

		if (strlen(trim($pkgcode))>1) {
			//check if this is part of a package and if it must have a certain number of nights priced at 0
			$srow = DB::table('tblPkgItem')
						->where([['PkgCode', $pkgcode], ['ItemCode', $prodcode]])
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

		while ($night_ctr <= $nights) {
			$nt=$night_ctr-1;
			if ($nt_price_flag[$nt]) {

				//if (PRICE_CATEGORIES<>"NO") { Price Categories Not Needed for Costing
				if (1==0) { 
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
					/*
					$query="select * from tblSpecialException where prodcode='$prodcode' and begin<=date_add('$date', interval $nt day) and end >=date_add('$date', interval $nt day) and (occ1=$occupancy or occ2=$occupancy or occ3=$occupancy or occ4=$occupancy) and AgCode='$agcode'";
					$p_type="except";
					if(!($price_obj=mysql_fetch_object(sql_cover($query,"select",0,0,0,0)))) {
						$query="select * from tblSpecialPrice where prodcode='$prodcode' and begin<=date_add('$date', interval $nt day) and end >=date_add('$date', interval $nt day) and (occ1=$occupancy or occ2=$occupancy or occ3=$occupancy or occ4=$occupancy) and AgCode='$agcode'";
						$p_type="default";
						//Nov 22 2008-MH Added Promo Pricing but this does not override special agency agency pricing or category pricing used by Vola
						if(!($price_obj=mysql_fetch_object(sql_cover($query,"select",0,0,0,0)))) {
							*/
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
							if(!($price_obj=mysql_fetch_object(sql_cover($query,"select",0,0,0,0))) or !$this->commonRepo->overlappingPromosAllowed(false, $price_obj, $p_row, $date)) { //No Promo with DEF&EXC FOUND
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
								if (!$price_obj  or !$this->commonRepo->overlappingPromosAllowed(false, $price_obj, $p_row, $date)) {
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
	/*
						} 
					}
	*/
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
					$query="select * from tblPromoPrice where prodcode='$prodcode' and begin<=date_add('$date', interval $nt day) and end >=date_add('$date', interval $nt day) and book_begin<='$today' and book_end>='$today'";
					$promo_result=sql_cover($query,"select",0,0,0,0);
					$promo_row = DB::table('tblPromoPrice')
									->where([['prodcode', $prodcode], ['begin', '<=', DB::raw("date_add('$date', interval $nt day)")], ['end', '>=', DB::raw("date_add('$date', interval $nt day)")], ['book_begin', '<=', $today], ['book_end', '>=', $today]])
									->get()->first();
					if ($promo_row) {						
						if ($this->commonRepo->overlappingPromosAllowed($promoFoundAlready,$promo_row,$p_row,$date))
						{
							$promoFoundAlready = true;
							//populate promo info from promo record
							$PromoMsg = $promo_row->PromoMsg;
							if ($promo_row->NightsToStay>0 and $promo_row->NightsFree>0) {
								
								//check if current date + nights needed to stay for promo is within the date range of the promo
								//Added 1/10/10-MH
								$current_night_date=$this->commonRepo->date_plus_minus($date,"ADD",$nt);
								$end_night_date=$this->commonRepo->date_plus_minus($current_night_date,"ADD",($promo_row->NightsToStay+$promo_row->NightsFree-1));
								if ($this->commonRepo->date_compare($end_night_date,$promo_row->End,"<=")) {
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
					///check for promo info on current record and populate
					$PromoMsg = $price_obj->PromoMsg;
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
										$curr_day=$this->commonRepo->date_plus_minus($date,"ADD",$nt);
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
			$parentCostObj = $this->get_parent_cost_obj($date,$nights,$occupancy,$Qty,$agcode,$PaxType,$check_all_prices,$pprice_1,$pprice_2,$pprice_3,$pprice_4,$PromoMsg,$NightsFree_rtn,$NightsToStay_rtn,$ReturnValue,$Child1Age, $Child2Age);
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
	
	public function get_parent_cost_obj($date,$nights,$occupancy,$Qty,$agcode,$PaxType,$check_all_prices,$pprice_1,$pprice_2,$pprice_3,$pprice_4,&$PromoMsg,&$NightsFree_rtn=0,&$NightsToStay_rtn=0,$ReturnValue="TOTAL",$Child1Age='', $Child2Age='') {
		
		global $comboIdForParentProdCode;
		
		if(!isset($comboIdForParentProdCode) || $comboIdForParentProdCode != ""){
			$comboIdForParentProdCode = $_REQUEST['comboIdForParentProdCode2'];
		}
		
		$comboProductRow = DB::table('tblProductCombo as tpc')
								->select(DB::raw('tpc.ProdCode as parentCode'))
								->where('tpc.ComboID', $comboIdForParentProdCode)
								->get()->first();
		if (comboProductRow) {
			$prodcode = $comboProductRow->parentCode;
		}
		
		//Determine the items average price over the given period
		$night_ctr=1;
		$PromoFound="NO";
		$FirstPromoNight=0;
		$today=todays_date();
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
		
		//Verify that the maximum # of occupants for this product has not been exceeded		
		$p_row = DB::table('tblProduct as p')
					->select(DB::raw('p.*, v.overlappingPromos, v.VendorID, v.ChildAgeRange1Min, v.ChildAgeRange1Max, v.ChildAgeRange2Min, v.ChildAgeRange2Max, v.ChildAgeRange3Min, v.ChildAgeRange3Max, v.ChildAgeRange4Min, v.ChildAgeRange4Max'))
					->join('tblVendor as v', 'v.vendorid', 'p.vendor')
					->where('p.prodcode', $prodcode)
					->get()->first();

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
		

		if (strlen(trim($pkgcode))>1) {
			//check if this is part of a package and if it must have a certain number of nights priced at 0
			$srow = DB::table('tblPkgItem')
						->where([['PkgCode', $pkgcode], ['ItemCode', $prodcode]])
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

		while ($night_ctr <= $nights) {
			$nt=$night_ctr-1;
			if ($nt_price_flag[$nt]) {

				//if (PRICE_CATEGORIES<>"NO") { Price Categories Not Needed for Costing
				if (1==0) { 
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
					/*
					$query="select * from tblSpecialException where prodcode='$prodcode' and begin<=date_add('$date', interval $nt day) and end >=date_add('$date', interval $nt day) and (occ1=$occupancy or occ2=$occupancy or occ3=$occupancy or occ4=$occupancy) and AgCode='$agcode'";
					$p_type="except";
					if(!($price_obj=mysql_fetch_object(sql_cover($query,"select",0,0,0,0)))) {
						$query="select * from tblSpecialPrice where prodcode='$prodcode' and begin<=date_add('$date', interval $nt day) and end >=date_add('$date', interval $nt day) and (occ1=$occupancy or occ2=$occupancy or occ3=$occupancy or occ4=$occupancy) and AgCode='$agcode'";
						$p_type="default";
						//Nov 22 2008-MH Added Promo Pricing but this does not override special agency agency pricing or category pricing used by Vola
						if(!($price_obj=mysql_fetch_object(sql_cover($query,"select",0,0,0,0)))) {
							*/
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
	/*
						} 
					}
	*/
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
						
						if ($this->commonRepo->overlappingPromosAllowed($promoFoundAlready,$promo_row,$p_row,$date))
						{
							$promoFoundAlready = true;
							//populate promo info from promo record
							$PromoMsg = $promo_row->PromoMsg;
							if ($promo_row->NightsToStay>0 and $promo_row->NightsFree>0) {
								
								//check if current date + nights needed to stay for promo is within the date range of the promo
								//Added 1/10/10-MH
								$current_night_date=$this->commonRepo->date_plus_minus($date,"ADD",$nt);
								$end_night_date=$this->commonRepo->date_plus_minus($current_night_date,"ADD",($promo_row->NightsToStay+$promo_row->NightsFree-1));
								if ($this->commonRepo->date_compare($end_night_date,$promo_row->End,"<=")) {
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
					///check for promo info on current record and populate
					$PromoMsg = $price_obj->PromoMsg;
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
		
				if ($AddPriceInThisIteration=="YES") { 
					//If Child then don't check occ prices
					if ($PaxType=="C") {
						$price+=$price_obj->ChildTax;
					} else {
						//Get the correct price for the occupancy required and check for day schg
						$MaxOccPrice=1;
						$qq="select * from tblPriceDays where id=$price_obj->PriceID and type='$p_type'";
						$qqresult=sql_cover($qq,"select",0,0,0,0);
						if ($qqrow=mysql_fetch_object($qqresult)) $day_surcharges_present="YES";
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
										$curr_day=$this->commonRepo->date_plus_minus($date,"ADD",$nt);
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

		return $price_obj;
	}
	
}