<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

use App\Repositories\TBFuncAvgPriceRepo;
use App\Repositories\TBFuncAvgCostRepo;

class DMGrpFuncNewRepo {
	
	public function __construct() {
	}
	
	function get_price($prodcode,$date,$nights,$occupancy,$type,$agcode,&$ChildPrice,&$pprice_1,&$pprice_2,&$pprice_3,&$pprice_4,&$PromoMsg,&$NightsFree,&$NightsToStay,$RateType) {
		/* This function will determince the price of the product selected
			if the type is P (package) or S (service), it will check the price
			on the arrival date.  If it is H (hotel) it will return return the
			average price for the duration of the stay.
		*/
		
		$avgPriceRepo = TBFuncAvgPriceRepo();

		if ($type=="H") {
			$pprice_1="0";
			$pprice_2="0";
			$pprice_3="0";
			$pprice_4="0";
			
			if ($RateType=="selling_price") {
				$retval = $avgPriceRepo->avg_price($prodcode,"","0000-00-00","$date",$nights,$occupancy,$agcode,"A","YES",$pprice_1,$pprice_2,$pprice_3,$pprice_4,$PromoMsg,$NightsFree,$NightsToStay);
			} elseif ($RateType=="cost") {
				$avgCostRepo = TBFuncAvgCostRepo();
				$retval = $avgCostRepo->avg_cost($prodcode,"","0000-00-00","$date",$nights,$occupancy,1,$agcode,"A","YES",$pprice_1,$pprice_2,$pprice_3,$pprice_4,$PromoMsg,$NightsFree,$NightsToStay);
			}

			return $retval;
		} else {
			if ($type=="P") {
				//check if the package prodcode has occupancy based pricing
				$row = DB::table('tblProduct')
							->where('prodcode', $prodcode)
							->get()->first();
				if ($row->DisplayOccFlag==1) {
					$retval=$avgPriceRepo->avg_price($prodcode,"","0000-00-00","$date",1,$occupancy,$agcode,"A","NO",$pp1,$pp2,$pp3,$pp4,$PromoMsg);
					$ChildPrice=$avgPriceRepo->avg_price($prodcode,"","0000-00-00","$date",1,$occupancy,$agcode,"C","NO",$pp1,$pp2,$pp3,$pp4,$PromoMsg);					
				} else {
					$retval=$avgPriceRepo->avg_price($prodcode,"","0000-00-00","$date",1,1,$agcode,"A","NO",$pp1,$pp2,$pp3,$pp4,$PromoMsg);
					$ChildPrice=$avgPriceRepo->avg_price($prodcode,"","0000-00-00","$date",1,1,$agcode,"C","NO",$pp1,$pp2,$pp3,$pp4,$PromoMsg);					
				}
			} elseif ($type=="E") {
				$pprice_1="0";
				$pprice_2="0";
				$pprice_3="0";
				$pprice_4="0";
				
				#TA(20120221) added avg_price_escort() for faster search
				$val=$avgPriceRepo->avg_price($prodcode,"","0000-00-00","$date",1,$occupancy,$agcode,"A","NO",$pprice_1,$pprice_2,$pprice_3,$pprice_4,$PromoMsg);
				#$val=avg_price_escort($prodcode,"","0000-00-00","$date",1,$occupancy,$agcode,"A","NO",$pprice_1,$pprice_2,$pprice_3,$pprice_4,$PromoMsg);			
				$pp="pprice_$occupancy";
				$retval=$$pp;
				#$retval=$val;
				$ChildPrice=$avgPriceRepo->avg_price($prodcode,"","0000-00-00","$date",1,$occupancy,$agcode,"C","NO",$pp1,$pp2,$pp3,$pp4,$PromoMsg);				
			} else {
				$retval=$avgPriceRepo->avg_price($prodcode,"","0000-00-00","$date",1,1,$agcode,"A","NO",$pp1,$pp2,$pp3,$pp4,$PromoMsg);
				$ChildPrice=$avgPriceRepo->avg_price($prodcode,"","0000-00-00","$date",1,1,$agcode,"C","NO",$pp1,$pp2,$pp3,$pp4,$PromoMsg);
				
			}
			return $retval;
		}
	} //end funtion get_price
	
}