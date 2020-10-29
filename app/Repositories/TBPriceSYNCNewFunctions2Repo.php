<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

use App\Repositories\TBCommonRepo;
use App\Repositories\TBFuncAvgPriceRepo;

class TBPriceSYNCNewFunctions2Repo {
	
	public $commonRepo;
	
	public function __construct() {
		$this->commonRepo = new TBCommonRepo();
		$this->avgPriceRepo = new TBFuncAvgPriceRepo();
	}
	
	public function update_PriceForAllotmentTable($prodcode) {
		/*This function will do the following:
		-Loop through all dates beginning from today forward (where pricing entries exist)
			-We will determine existing range of dates based on when the allotment exists for this product code or for its allotment code
		-It will call the pricing routine to get the calculated price for each day and then update tblPriceForAllotment
		-If no record exists in tblPriceForAllotment, it will create one and link it to the corresponding record in tblAllotment by referencing the RecID in tblAllotment in the AllotRecID field
		-If no corresponding record exists in tblAllotment, the record will not be created
		*/

		//Determine latest date for which pricing exists
		//First see if an allotment exists... if it does, only extend pricing through latest allotment record
		//check for proper allotment code to check
		
		$prod_row = DB::table('tblProduct')
						->select('AllotCode', 'NeedsPriceUpdate')
						->where('ProdCode', $prodcode)
						->get()->first();
		
		$NeedsPriceUpdate = $prod_row->NeedsPriceUpdate;
		
		$foundDate="NO";		
		$row = DB::table('tblAllotment')
					->select(DB::raw('max(AllotDate) as endDate'))
					->where('prodcode', $prod_row->AllotCode)
					->get()->first();
		if ($row && $row->endDate) {
			$endDate=$row->endDate;
			$foundDate="YES";
		}

		if ($foundDate=="NO") {
			//No Allotment exists for this products Allotment Code so we really do not need to update it's pricing
			//If it has no allotment, then it will not display on DREAM anyway
			return 0;
		}

		$beginDate=$this->commonRepo->todays_date();
		//OK... we have the date range      
		$currentDate=$beginDate;

		if ($NeedsPriceUpdate) {
			$this->commonRepo->calc_tax_n_price($prodcode, $currentDate);
		}

		while ($this->commonRepo->date_compare($currentDate,$endDate,"<=")) {
			//Get pricd for this date
			$ChildPrice=0;
			$price1=0;
			$price2=0;
			$price3=0;
			$price4=0;
			$PromoMessage="";
			$NightsFree="";
			$NightsToStay="";
			$daysPrior1=0;
			$type1=''; 
			$amount1=0;
			$daysPrior2=0;
			$type2='';
			$amount2=0;
			$daysPrior3=0;
			$type3='';
			$amount3=0;
			$this->avgPriceRepo->avg_price($prodcode,"","",$currentDate,1,1,"","AD","YES",$price1,$price2,$price3,$price4,$PromoMessage,$NightsFree,$NightsToStay,false, '', '',
						$daysPrior1, $type1, $amount1,
						$daysPrior2, $type2, $amount2,
						$daysPrior3, $type3, $amount3,
						$allCxlPolicies);
		
				for($i=1; $i <= 3; $i++){
						$dd = "daysPrior".$i;
						$amnt = "amount".$i;
		
						if(empty($$dd)){
								$$dd = "NULL";
								$$amnt = "NULL";
						}
				}
			//avg_price($prodcode,"","",$currentDate,1,1,"","AD","YES",$price1,$price2,$price3,$price4,$PromoMessage,$NightsFree,$NightsToStay);
			//calcuate and save child price if the product is not a hotel
			if (!isset($prod_row->isHotel) || !$prod_row->isHotel)
			{
				$ChildPrice=$this->avgPriceRepo->avg_price($prodcode,"","",$currentDate,1,1,"","C","NO",$pp1,$pp2,$pp3,$pp4,$PromoMsg);
			}
			$arow = DB::table('tblAllotment')
						->select('RecID')
						->where([['AllotDate', $currentDate], ['prodcode', $prod_row->AllotCode]])
						->get()->first();
			if ($arow) {
				//Set CumulativeNightsPromo value here
				//$CumulativeNightsPromo=isCumulativeNights($prodcode, $currentDate);
				//$default_prices = getDefaultPrice($prodcode, $currentDate);
				//$exception_prices = getExceptionPrice($prodcode, $currentDate);
				//$promo_info = getPromoInfo($prodcode,$currentDate);
				
				$price_data = $this->getPriceData($prodcode,$currentDate); //combine the above functions to reduce number of queries
				
				$CumulativeNightsPromo = $price_data['CumulativeNightsPromo'];
				$default_prices = $price_data['default_prices'];
				$exception_prices = $price_data['exception_prices'];
				$promo_info = $price_data['promo_info'];
				$allCxlPoliciesJson = json_encode($allCxlPolicies);
				
				$default_price1 = '';
				$default_price2 = '';
				$default_price3 = '';
				$default_price4 = '';
				
				if ($default_prices) {
					$default_price1 = $default_prices->price1;
					$default_price2 = $default_prices->price2;
					$default_price3 = $default_prices->price3;
					$default_price4 = $default_prices->price4;
				}
				
				$exception_price1 = '';
				$exception_price2 = '';
				$exception_price3 = '';
				$exception_price4 = '';
					
				if ($exception_prices) {
					$exception_price1 = $exception_prices->price1;
					$exception_price2 = $exception_prices->price2;
					$exception_price3 = $exception_prices->price3;
					$exception_price4 = $exception_prices->price4;
				}
				
				$promo_arriveOnStartDate = '';
				$promo_Begin = '';
				$promo_End = '';
				$promo_minimum_nights_stay = '';
				$promo_overrideDef = '';
				$promo_overrideExp = '';
				
				if ($promo_info) {
					$promo_arriveOnStartDate = $promo_info->arriveOnStartDate;
					$promo_Begin = $promo_info->Begin;
					$promo_End = $promo_info->End;
					$promo_minimum_nights_stay = $promo_info->minimum_nights_stay;
					$promo_overrideDef = $promo_info->overrideDef;
					$promo_overrideExp = $promo_info->overrideExp;
				}

				$PromoMessage = addslashes($PromoMessage);
				$query="replace into tblPriceForAllotment (ProdCode,AllotCode,AllotRecID,PriceDate,ApproxPrice1,ApproxPrice2,ApproxPrice3,ApproxPrice4,
					ApproxChildPrice,PromoMessage,NightsToStay,NightsFree,CumulativeNightsPromo,DefaultApproxPrice1,DefaultApproxPrice2,DefaultApproxPrice3,DefaultApproxPrice4,
					ExceptionApproxPrice1,ExceptionApproxPrice2,ExceptionApproxPrice3,ExceptionApproxPrice4,
					arriveOnStartDate,promoStartDate,promoEndDate,minimum_nights_stay,overrideDef,overrideExp, days_prior1, type1, amount1, days_prior2, type2, amount2, days_prior3, type3, amount3, cancellationPolicies)
				values ('$prodcode','$prod_row->AllotCode','$arow->RecID','$currentDate','$price1','$price2','$price3','$price4','$ChildPrice','$PromoMessage','$NightsToStay','$NightsFree','$CumulativeNightsPromo',
					'{$default_price1}','{$default_price2}','{$default_price3}','{$default_price4}',
					'{$exception_price1}','{$exception_price2}','{$exception_price3}','{$exception_price4}',
					'{$promo_arriveOnStartDate}','{$promo_Begin}','{$promo_End}','{$promo_minimum_nights_stay}','{$promo_overrideDef}','{$promo_overrideExp}',
					'$daysPrior1', '$type1', '$amount1', '$daysPrior2', '$type2', '$amount2', '$daysPrior3', '$type3', '$amount3', '$allCxlPoliciesJson')";
					
				DB::statement($query);
			}
			$currentDate=$this->commonRepo->date_plus_minus($currentDate,"ADD",1);
		} //end while loop
			
		//Return true if updated successfully to prevent email alerts from triggering in price_booking_window_update.php
		return true;
	}
	
	public function cutoff_sync($ProdCode) {
		$today=$this->commonRepo->todays_date();
		
		$rows = DB::table('tblAllotment as a')
					->select(DB::raw('a.RecID,a.ProdCode,a.AllotDate,v.ChildAge'))
					->join('tblProduct as p', 'p.prodcode', 'a.prodcode')
					->join('tblVendor as v', 'v.vendorid', 'p.vendor')
					->where([['a.prodcode', $ProdCode], ['allotdate', '>=', $today]])
					->get();
		
		foreach ($rows as $row) {
			$crow = DB::table('tblCutOff')
						->select('Days')
						->where([['prodcode', $row->ProdCode], ['begin', '<=', $row->AllotDate], ['end', '>=', $row->AllotDate]])
						->get()->first();
			if ($crow) {
				if ($row->ChildAge<1) 
					DB::table('tblAllotment')
						->where('RecID', $row->RecID)
						->update([
							'cutoff' => $crow->Days,
							'ChildAge' => null
						]);
				else 
					DB::table('tblAllotment')
						->where('RecID', $row->RecID)
						->update([
							'cutoff' => $crow->Days,
							'ChildAge' => $row->ChildAge
						]);
			}
		}
	} //end function cutoff_sync
	
	public function getPriceData($prodcode, $date) {
		$isCumulativeNights=0;

		$currentDate=date('Y-m-d');
		$prow = DB::table('tblPromoPrice')
					->select(DB::raw('CumulativeNightsPromo, overrideDef, overrideExp, arriveOnStartDate, price1, price2, price3, price4, `Begin`, `End`, minimum_nights_stay'))
					->where([['Prodcode', $prodcode], ['Begin', '<=', $date], ['End', '>=', $date]])
					->where(DB::raw("'$currentDate' BETWEEN book_begin AND book_end"))
					->get()->first();

		$erow = DB::table('tblDefaultException')
					->select(DB::raw('CumulativeNightsPromo, price1, price2, price3, price4'))
					->where([['ProdCode', $prodcode], ['Begin', '<=', $date], ['End', '>=', $date]])
					->get()->first();

		$drow = DB::table('tblDefaultPrice')
					->select(DB::raw('CumulativeNightsPromo, price1, price2, price3, price4'))
					->where([['ProdCode', $prodcode], ['Begin', '<=', $date], ['End', '>=', $date]])
					->get()->first();

		//Set to default.
		if ($drow)
			$isCumulativeNights=$drow->CumulativeNightsPromo;

		//If promo overrides default.
		if($prow && $prow->overrideDef)
			$isCumulativeNights=$prow->CumulativeNightsPromo;

		//If there is exception.
		if($erow)
			$isCumulativeNights=$erow->CumulativeNightsPromo;

		//If promo overrides exception.
		if($prow && $prow->overrideExp)
			$isCumulativeNights=$prow->CumulativeNightsPromo;
		
		//for exception_prices
		if (!$erow) {
			$erow= new \stdClass();
			$erow->price1 = 0;
			$erow->price2 = 0;
			$erow->price3 = 0;
			$erow->price4 = 0;
		}	
		
		//for promo_info
		if (!$prow) {
			$prow = new \stdClass();
			$prow->arriveOnStartDate = '';
			$prow->price1 = 0;
			$prow->price2 = 0;
			$prow->price3 = 0;
			$prow->price4 = 0;
			$prow->Begin = '';
			$prow->End = '';
			$prow->overrideDef = 0;
			$prow->overrideExp = 0;
			$prow->minimum_nights_stay=0;
		}
		
		$price_data = array();
		$price_data['CumulativeNightsPromo'] = $isCumulativeNights;
		$price_data['default_prices'] = $drow;
		$price_data['exception_prices'] = $erow;
		$price_data['promo_info'] = $prow;

		return $price_data;
	}
	
}