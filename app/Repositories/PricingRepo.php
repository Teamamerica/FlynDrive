<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Repositories\TBCommonRepo;
use App\Repositories\TBPushCommonRepo;

use App\Product;
use App\DefaultPrice;
use App\PriceHistory;
use App\PriceDays;

class PricingRepo {
	
	public function __construct() {
	}	
	
	public static function deletePriceByID($priceID, $type) { 		
		if ($type == 'default') {
			$priceObj = new DefaultPrice();
			$priceType = 'Default';
		}
		elseif ($type == 'except') {
			$priceObj = new DefaultException();
			$priceType = 'Exception';
		}
		elseif ($type == 'promo') {
			$priceObj = new PromoPrice();
			$priceType = 'Promo';
		}
		else {
			$result['status'] = 'failed';
			$result['message'] = 'Invalid Price Type.';
			return $result;
		}
		
		$row = $priceObj::where('PriceID', $priceID)->get()->first();
		
		if ($row) {
			$priceObj::where('PriceID', $priceID)->delete();
			
			$deletedPrices = "Begin Date = $row->Begin
					<br />End Date = $row->End
					<br />Sell Price 1 = $row->Price1
					<br/>Sell Price 2 = $row->Price2
					<br/>Sell Price 3 = $row->Price3
					<br/>Sell Price 4 = $row->Price4
					<br/>Net+Tax 1 = $row->OurCost1
					<br/>Net+Tax 2 = $row->OurCost2
					<br/>Net+Tax 3 = $row->OurCost3
					<br/>Net+Tax 4 = $row->OurCost4
					<br/>Net 1 = $row->OurNet1
					<br/>Net 2 = $row->OurNet2
					<br/>Net 3 = $row->OurNet3
					<br/>Net 4 = $row->OurNet4
					<br/>Net+Fee 1 = $row->OurNetFee1
					<br/>Net+Fee 2 = $row->OurNetFee2
					<br/>Net+Fee 3 = $row->OurNetFee3
					<br/>Net+Fee 4 = $row->OurNetFee4";
				
			PriceHistory::insert([
				'ProdCode' => $row->ProdCode,
				'UserID' => Auth::user()->UserName,
				'Initials' => Auth::user()->Initials,
				'Action' => "Delete $priceType Price: $row->PriceID",
				'PreviousVal' => $deletedPrices,
				'NewVal' => ""
			]);
			
			$pushCommon = new TBPushCommonRepo();			
			$pushCommon->addNewPushEvent('ALL', 'updateprice', $row->ProdCode, $row->Begin, $row->End);
			
			$result['status'] = 'success';			
		}
		else {
			$result['status'] = 'failed';
			$result['message'] = "$priceType Price ID: $priceID not found.";
		}
		
		return $result;
	}

	public static function deletePricesByDates($prodCode, $frmDate, $toDate, $type) { 
		Product::where('ProdCode', $prodCode)->update(['NeedsPriceUpdate' => 1]);
		
		$prevDate = date('Y-m-d', strtotime('-1 day', strtotime($frmDate)));
		$nextDate = date('Y-m-d', strtotime('+1 day', strtotime($toDate)));
		
		if ($type == 'default') {
			$priceObj = new DefaultPrice();
			$priceType = 'Default';
		}
		elseif ($type == 'except') {
			$priceObj = new DefaultException();
			$priceType = 'Exception';
		}
		elseif ($type == 'promo') {
			$priceObj = new PromoPrice();
			$priceType = 'Promo';
		}
		else {
			$result['status'] = 'failed';
			$result['message'] = 'Invalid Price Type.';
			return $result;
		}
		
		$prices = $priceObj::where('ProdCode', $prodCode)
								->where(function ($query) use ($frmDate, $toDate) {
									$query->where([['Begin', '>=', $frmDate], ['End', '<=', $toDate]])
											->orWhereRaw('? BETWEEN Begin and End', [$frmDate])
											->orWhereRaw('? BETWEEN Begin and End', [$toDate]);
								})
								->get();
						
		$pushCommon = new TBPushCommonRepo();
						
		foreach ($prices as $row) {
			if ($row->Begin >= $frmDate && $row->End <= $toDate) {
				$priceObj::where('PriceID', $row->PriceID)->delete();
				
				$deletedPrices = "Begin Date = $row->Begin
					<br />End Date = $row->End
					<br />Sell Price 1 = $row->Price1
					<br/>Sell Price 2 = $row->Price2
					<br/>Sell Price 3 = $row->Price3
					<br/>Sell Price 4 = $row->Price4
					<br/>Net+Tax 1 = $row->OurCost1
					<br/>Net+Tax 2 = $row->OurCost2
					<br/>Net+Tax 3 = $row->OurCost3
					<br/>Net+Tax 4 = $row->OurCost4
					<br/>Net 1 = $row->OurNet1
					<br/>Net 2 = $row->OurNet2
					<br/>Net 3 = $row->OurNet3
					<br/>Net 4 = $row->OurNet4
					<br/>Net+Fee 1 = $row->OurNetFee1
					<br/>Net+Fee 2 = $row->OurNetFee2
					<br/>Net+Fee 3 = $row->OurNetFee3
					<br/>Net+Fee 4 = $row->OurNetFee4";
				
				PriceHistory::insert([
					'ProdCode' => $prodCode,
					'UserID' => Auth::user()->UserName,
					'Initials' => Auth::user()->Initials,
					'Action' => "Delete $priceType Price: $row->PriceID",
					'PreviousVal' => $deletedPrices,
					'NewVal' => ""
				]);
				
				$pushCommon->addNewPushEvent('ALL', 'updateprice', $prodCode, $row->Begin, $row->End);
			}
			elseif ($row->Begin < $frmDate && $row->End >= $frmDate && $row->End <= $toDate) {				
				$priceObj::where('PriceID', $row->PriceID)->update(['End' => $prevDate]);
				
				PriceHistory::insert([
					'ProdCode' => $prodCode,
					'UserID' => Auth::user()->UserName,
					'Initials' => Auth::user()->Initials,
					'Action' => "$priceType Price Modify: $row->PriceID",
					'PreviousVal' => "End = $row->End",
					'NewVal' => "End = $prevDate"
				]);
				
				$pushCommon->addNewPushEvent('ALL', 'updateprice', $prodCode, $row->Begin, $prevDate);
				$pushCommon->addNewPushEvent('ALL', 'updateprice', $prodCode, $row->Begin, $row->End);
			}
			elseif ($row->End > $toDate && $row->Begin >= $frmDate && $row->Begin <= $toDate) {		
				$priceObj::where('PriceID', $row->PriceID)->update(['Begin' => $nextDate]);
				
				PriceHistory::insert([
					'ProdCode' => $prodCode,
					'UserID' => Auth::user()->UserName,
					'Initials' => Auth::user()->Initials,
					'Action' => "$priceType Price Modify: $row->PriceID",
					'PreviousVal' => "Begin = $row->Begin",
					'NewVal' => "Begin = $nextDate"
				]);
				
				$pushCommon->addNewPushEvent('ALL', 'updateprice', $prodCode, $nextDate, $row->End);
				$pushCommon->addNewPushEvent('ALL', 'updateprice', $prodCode, $row->Begin, $row->End);
			}
			elseif ($row->Begin < $frmDate && $row->End > $toDate) {
				//separate two
				//update old one first				
				$priceObj::where('PriceID', $row->PriceID)->update(['End' => $prevDate]);
				
				PriceHistory::insert([
					'ProdCode' => $prodCode,
					'UserID' => Auth::user()->UserName,
					'Initials' => Auth::user()->Initials,
					'Action' => "$priceType Price Modify: $row->PriceID",
					'PreviousVal' => "End = $row->End",
					'NewVal' => "End = $prevDate"
				]);
				
				$pushCommon->addNewPushEvent('ALL', 'updateprice', $prodCode, $row->Begin, $prevDate);
				$pushCommon->addNewPushEvent('ALL', 'updateprice', $prodCode, $row->Begin, $row->End);

				//then create new one
				$vars = $row->toArray();
				$excepts = array('PriceID', 'Begin', 'End');
				$prices = array('Price1', 'Price2', 'Price3', 'Price4', 'OurCost1', 'OurCost2', 'OurCost3', 'OurCost4', 'OurNet1', 'OurNet2', 'OurNet3', 'OurNet4', 'OurNetFee1', 'OurNetFee2', 'OurNetFee3', 'OurNetFee4', 'ChildPrice', 'ChildTax', 'ChildNetFee', 'ChildNet', 'ChildPrice1', 'ChildPrice2', 'ChildPrice3', 'ChildCost1', 'ChildCost2', 'ChildCost3', 'ChildNetFee1', 'ChildNetFee2', 'ChildNetFee3', 'ChildNet1', 'ChildNet2', 'ChildNet3');

				$newPrice = ['Begin' => $nextDate, 'End' => $row->End];				
				foreach ($vars as $key => $val) {
					if (!in_array($key, $excepts)) {
						if ($val)
							$newPrice[$key] = $val;
						elseif (in_array($key, $prices))
							$newPrice[$key] = NULL;
					}			
				}
				
				$priceObj::insert($newPrice);
				
				$newPriceID = DB::getPdo()->lastInsertId();
				
				$newPrices = "Begin Date = $nextDate
					<br />End Date = $row->End
					<br />Sell Price 1 = $row->Price1
					<br/>Sell Price 2 = $row->Price2
					<br/>Sell Price 3 = $row->Price3
					<br/>Sell Price 4 = $row->Price4
					<br/>Net+Tax 1 = $row->OurCost1
					<br/>Net+Tax 2 = $row->OurCost2
					<br/>Net+Tax 3 = $row->OurCost3
					<br/>Net+Tax 4 = $row->OurCost4
					<br/>Net 1 = $row->OurNet1
					<br/>Net 2 = $row->OurNet2
					<br/>Net 3 = $row->OurNet3
					<br/>Net 4 = $row->OurNet4
					<br/>Net+Fee 1 = $row->OurNetFee1
					<br/>Net+Fee 2 = $row->OurNetFee2
					<br/>Net+Fee 3 = $row->OurNetFee3
					<br/>Net+Fee 4 = $row->OurNetFee4";
				
				PriceHistory::insert([
					'ProdCode' => $prodCode,
					'UserID' => Auth::user()->UserName,
					'Initials' => Auth::user()->Initials,
					'Action' => "$priceType Price Added: $newPriceID",
					'NewVal' => $newPrices
				]);

				$row2 = PriceDays::where([['type', $type], ['id', $row->PriceID]])->get()->first();
				
				if ($row2) {
					$vars2 = $row2->toArray();
					$excepts2 = array('id');
					
					$newPriceDays = ['id' => $newPriceID];					
					foreach ($vars2 as $key2 => $val2) {
						if (!in_array($key2, $excepts2))
							$newPriceDays[$key2] = $val2;
					}
					
					PriceDays::insert($newPriceDays);
					
					PriceHistory::insert([
						'ProdCode' => $prodCode,
						'UserID' => Auth::user()->UserName,
						'Initials' => Auth::user()->Initials,
						'Action' => "$priceType Daily Surcharges Added: $newPriceID"
					]);
				}

				$pushCommon->addNewPushEvent('ALL', 'updateprice', $prodCode, $nextDate, $row->End);
			}
		}
		
		$result['status'] = 'success';
		return $result;
	}	
	
	public static function savePricing($request) {
		$common = new TBCommonRepo();
		$pushCommon = new TBPushCommonRepo();		
		
		$rules = [
			'Type' => array('required', 'in:default,except,promo'),
			'Begin' => array('required', 'regex:/^\d{4}\-\d{2}\-\d{2}$/'),
			'End' => array('required', 'regex:/^\d{4}\-\d{2}\-\d{2}$/'),
			'markup' => array('in:0,1'),
			'PricingType' => array('in:0,1,2'),
			'Price1' => array('numeric'),
			'OurCost1' => array('numeric'),
			'OurNetFee1' => array('numeric'),
			'OurNet1' => array('numeric'),
			'Price2' => array('numeric'),
			'OurCost2' => array('numeric'),
			'OurNetFee2' => array('numeric'),
			'OurNet2' => array('numeric'),
			'Price3' => array('numeric'),
			'OurCost3' => array('numeric'),
			'OurNetFee3' => array('numeric'),
			'OurNet3' => array('numeric'),
			'Price4' => array('numeric'),
			'OurCost4' => array('numeric'),
			'OurNetFee4' => array('numeric'),
			'OurNet4' => array('numeric'),
			'ChildPrice' => array('numeric'),
			'ChildTax' => array('numeric'),
			'ChildNetFee' => array('numeric'),
			'ChildNet' => array('numeric'),
			'usepromo' => array('in:0,1'),
			'NightsToStay' => array('numeric'),
			'NightsFree' => array('numeric'),
			'CumulativeNightsPromo' => array('in:0,1'),
			'fee_amount' => array('numeric'),
			'fee_rate' => array('in:none,per_person_percent,per_person_dollars,per_room_percent,per_room_dollars'),
			'fee_ch' => array('in:0,1'),
			'mon1' => array('numeric'),
			'tax_mon1' => array('numeric'),
			'net_mon1' => array('numeric'),
			'mon2' => array('numeric'),
			'tax_mon2' => array('numeric'),
			'net_mon2' => array('numeric'),
			'mon3' => array('numeric'),
			'tax_mon3' => array('numeric'),
			'net_mon3' => array('numeric'),
			'mon4' => array('numeric'),
			'tax_mon4' => array('numeric'),
			'net_mon4' => array('numeric'),
			'tue1' => array('numeric'),
			'tax_tue1' => array('numeric'),
			'net_tue1' => array('numeric'),
			'tue2' => array('numeric'),
			'tax_tue2' => array('numeric'),
			'net_tue2' => array('numeric'),
			'tue3' => array('numeric'),
			'tax_tue3' => array('numeric'),
			'net_tue3' => array('numeric'),
			'tue4' => array('numeric'),
			'tax_tue4' => array('numeric'),
			'net_tue4' => array('numeric'),
			'wed1' => array('numeric'),
			'tax_wed1' => array('numeric'),
			'net_wed1' => array('numeric'),
			'wed2' => array('numeric'),
			'tax_wed2' => array('numeric'),
			'net_wed2' => array('numeric'),
			'wed3' => array('numeric'),
			'tax_wed3' => array('numeric'),
			'net_wed3' => array('numeric'),
			'wed4' => array('numeric'),
			'tax_wed4' => array('numeric'),
			'net_wed4' => array('numeric'),
			'thu1' => array('numeric'),
			'tax_thu1' => array('numeric'),
			'net_thu1' => array('numeric'),
			'thu2' => array('numeric'),
			'tax_thu2' => array('numeric'),
			'net_thu2' => array('numeric'),
			'thu3' => array('numeric'),
			'tax_thu3' => array('numeric'),
			'net_thu3' => array('numeric'),
			'thu4' => array('numeric'),
			'tax_thu4' => array('numeric'),
			'net_thu4' => array('numeric'),
			'fri1' => array('numeric'),
			'tax_fri1' => array('numeric'),
			'net_fri1' => array('numeric'),
			'fri2' => array('numeric'),
			'tax_fri2' => array('numeric'),
			'net_fri2' => array('numeric'),
			'fri3' => array('numeric'),
			'tax_fri3' => array('numeric'),
			'net_fri3' => array('numeric'),
			'fri4' => array('numeric'),
			'tax_fri4' => array('numeric'),
			'net_fri4' => array('numeric'),
			'sat1' => array('numeric'),
			'tax_sat1' => array('numeric'),
			'net_sat1' => array('numeric'),
			'sat2' => array('numeric'),
			'tax_sat2' => array('numeric'),
			'net_sat2' => array('numeric'),
			'sat3' => array('numeric'),
			'tax_sat3' => array('numeric'),
			'net_sat3' => array('numeric'),
			'sat4' => array('numeric'),
			'tax_sat4' => array('numeric'),
			'net_sat4' => array('numeric'),
			'sun1' => array('numeric'),
			'tax_sun1' => array('numeric'),
			'net_sun1' => array('numeric'),
			'sun2' => array('numeric'),
			'tax_sun2' => array('numeric'),
			'net_sun2' => array('numeric'),
			'sun3' => array('numeric'),
			'tax_sun3' => array('numeric'),
			'net_sun3' => array('numeric'),
			'sun4' => array('numeric'),
			'tax_sun4' => array('numeric'),
			'net_sun4' => array('numeric'),
			'UseCancel' => array('in:0,1'),
			'days_prior1' => array('numeric'),
			'amount1' => array('numeric'),
			'type1' => array('in:Nights,Percent,Dollars'),
			'days_prior2' => array('numeric'),
			'amount2' => array('numeric'),
			'type2' => array('in:Nights,Percent,Dollars'),
			'days_prior3' => array('numeric'),
			'amount3' => array('numeric'),
			'type3' => array('in:Nights,Percent,Dollars'),
		];
		
		$messages = [
		];
		
		$validator = Validator::make($request->all(), $rules, $messages);		
		
		//if any more validations
		$validator->after(function($validator) use ($request, $common) {	
			if ((!isset($request->PriceID) || !$request->PriceID) && (!isset($request->ProdCode) || !$request->ProdCode))
				$validator->errors()->add('ProdCode', 'ProdCode must be provided for new pricing.');
			
			$pass="YES";
			$error="";
			
			// Check if there is a cost
			for ($i=1; $i<=4; $i++) {
				$price_varname = "Price$i";
				$cost_varname = "OurCost$i";
				$net_varname = "OurNet$i";
				
				if (floatval($request->{$price_varname}) > 0) {
					if (($request->{$cost_varname}!="0" and floatval($request->{$cost_varname})==0) and ($request->{$net_varname}!="0" and floatval($request->{$net_varname})==0)) {
						$validator->errors()->add("OurNet$i", "You have to enter a cost for the Occupancy $i.");
					}
				}
			}
			
			//check that dates are valid
			//convert 2 digit years to 4 digits			
			if (isset($request->Begin) && isset($request->End)) {
				$BeginData = explode('-', $request->Begin);
				$BYear = $BeginData[0];
				$BMonth = $BeginData[1];
				$BDayOfMonth = $BeginData[2];
				
				$EndData = explode('-', $request->End);
				$EYear = $EndData[0];
				$EMonth = $EndData[1];
				$EDayOfMonth = $EndData[2];
			
				if (strlen($BYear)==2) $BYear+=2000;
				if (strlen($EYear)==2) $EYear+=2000;
				if (!(checkdate($BMonth,$BDayOfMonth,$BYear))) {
					$validator->errors()->add("OurNet$i", "Invalid Begin Date.");
				}
				if (!(checkdate($EMonth,$EDayOfMonth,$EYear))) {
					$validator->errors()->add("End", "Invalid End Date.");
				}
		
				//make sure that this new date range does not overlap any other one that is currently entered
				$begin="$BYear-$BMonth-$BDayOfMonth";
				$end="$EYear-$EMonth-$EDayOfMonth";

				if ($common->date_compare($begin,$end,">")) {
					$validator->errors()->add("End", "End date is before begin date.");
				}		
			}
		});
		
		if ($validator->fails()) {
			//print_r($validator->errors()->all()); exit;			
			$result['status'] = 'failed';
			$result['message'] = $validator->errors()->first();
			return $result;
		}		
		
		if ($request->Type == 'default') {	
			$priceObj = new DefaultPrice();
			$priceType = 'Default';
			$ptype = 'default';
			$ptable = 'tblDefaultPrice';
		}
		elseif ($request->Type == 'except') {	
			$priceObj = new DefaultException();
			$priceType = 'Exception';
			$ptype = 'except';
			$ptable = 'tblDefaultException';
		}
		elseif ($request->Type == 'promo') {	
			$priceObj = new PromoPrice();
			$priceType = 'Promo';
			$ptype = 'promo';
			$ptable = 'tblPromoPrice';
		}
		
		$updates = array();		
		$updates['Begin'] = isset($request->Begin) ? $request->Begin : '';
		$updates['End'] = isset($request->End) ? $request->End : '';
		$updates['markup'] = isset($request->markup) && $request->markup ? $request->markup : 0;
		$updates['PricingType'] = isset($request->PricingType) && $request->PricingType ? $request->PricingType : 0;
		$updates['Price1'] = isset($request->Price1) && $request->Price1 ? $request->Price1 : 0.00;
		$updates['Price2'] = isset($request->Price2) && $request->Price2 ? $request->Price2 : 0.00;
		$updates['Price3'] = isset($request->Price3) && $request->Price3 ? $request->Price3 : 0.00;
		$updates['Price4'] = isset($request->Price4) && $request->Price4 ? $request->Price4 : 0.00;
		$updates['OurCost1'] = isset($request->OurCost1) && $request->OurCost1 ? $request->OurCost1 : 0.00;
		$updates['OurCost2'] = isset($request->OurCost2) && $request->OurCost2 ? $request->OurCost2 : 0.00;
		$updates['OurCost3'] = isset($request->OurCost3) && $request->OurCost3 ? $request->OurCost3 : 0.00;
		$updates['OurCost4'] = isset($request->OurCost4) && $request->OurCost4 ? $request->OurCost4 : 0.00;
		$updates['OurNet1'] = isset($request->OurNet1) && $request->OurNet1 ? $request->OurNet1 : 0.00;
		$updates['OurNet2'] = isset($request->OurNet2) && $request->OurNet2 ? $request->OurNet2 : 0.00;
		$updates['OurNet3'] = isset($request->OurNet3) && $request->OurNet3 ? $request->OurNet3 : 0.00;
		$updates['OurNet4'] = isset($request->OurNet4) && $request->OurNet4 ? $request->OurNet4 : 0.00;
		$updates['OurNetFee1'] = isset($request->OurNetFee1) && $request->OurNetFee1 ? $request->OurNetFee1 : 0.00;
		if ($updates['OurNetFee1'] <= 0)
			$updates['OurNetFee1'] = $updates['OurNet1'];
		$updates['OurNetFee2'] = isset($request->OurNetFee2) && $request->OurNetFee2 ? $request->OurNetFee2 : 0.00;
		if ($updates['OurNetFee2'] <= 0)
			$updates['OurNetFee2'] = $updates['OurNet2'];
		$updates['OurNetFee3'] = isset($request->OurNetFee3) && $request->OurNetFee3 ? $request->OurNetFee3 : 0.00;
		if ($updates['OurNetFee3'] <= 0)
			$updates['OurNetFee3'] = $updates['OurNet3'];
		$updates['OurNetFee4'] = isset($request->OurNetFee4) && $request->OurNetFee4 ? $request->OurNetFee4 : 0.00;
		if ($updates['OurNetFee4'] <= 0)
			$updates['OurNetFee4'] = $updates['OurNet4'];			
		$updates['ChildPrice'] = isset($request->ChildPrice) && $request->ChildPrice ? $request->ChildPrice : 0.00;
		$updates['ChildTax'] = isset($request->ChildTax) && $request->ChildTax ? $request->ChildTax : 0.00;
		$updates['ChildNet'] = isset($request->ChildNet) && $request->ChildNet ? $request->ChildNet : 0.00;
		$updates['ChildNetFee'] = isset($request->ChildNetFee) && $request->ChildNetFee ? $request->ChildNetFee : 0.00;
		if ($updates['ChildNetFee'] <= 0)
			$updates['ChildNetFee'] = $updates['ChildNet'];	
		
		//Apply Hotel Fees
		if ($request->fee_amount > 0) {
			if ($request->fee_rate == 'per_room_dollars') {
				for ($n=1; $n<=4; $n++) {
					if ($updates["OurNet$n"] > 0)
						$updates["OurNetFee$n"] = $updates["OurNet$n"] + $request->fee_amount;
				}
				
				if ($request->fee_ch == 1 && $updates['ChildNet'] > 0)
					$updates["ChildNetFee"] = $updates["ChildNet"] + $request->fee_amount;
			}
			elseif ($request->fee_rate == 'per_person_dollars') {
				for ($n=1; $n<=4; $n++) {
					if ($updates["OurNet$n"] > 0)
						$updates["OurNetFee$n"] = $updates["OurNet$n"] + ($request->fee_amount * $n);
				}
				
				if ($request->fee_ch == 1 && $updates['ChildNet'] > 0)
					$updates["ChildNetFee"] = $updates["ChildNet"] + $request->fee_amount;
			}
			elseif ($request->fee_rate == 'per_room_percent') {
				for ($n=1; $n<=4; $n++) {
					if ($updates["OurNet$n"] > 0) {
						$updates["OurNetFee$n"] = $updates["OurNet$n"] + $updates["OurNet$n"] * $request->fee_amount / 100;
						$updates["OurNetFee$n"] = number_format((float)$updates["OurNetFee$n"], 2, '.', '');
					}
				}
				
				if ($request->fee_ch == 1 && $updates['ChildNet'] > 0)
					$updates["ChildNetFee"] = $updates["ChildNet"] + $updates["ChildNet"] * $request->fee_amount / 100;
			}
			elseif ($request->fee_rate == 'per_person_percent') {
				for ($n=1; $n<=4; $n++) {
					if ($updates["OurNet$n"] > 0) {
						$updates["OurNetFee$n"] = $updates["OurNet$n"] + $updates["OurNet$n"] * $request->fee_amount / 100 * $n;
						$updates["OurNetFee$n"] = number_format((float)$updates["OurNetFee$n"], 2, '.', '');
					}
				}
				
				if ($request->fee_ch == 1 && $updates['ChildNet'] > 0)
					$updates["ChildNetFee"] = $updates["ChildNet"] + $updates["ChildNet"] * $request->fee_amount / 100;
			}
		}
		
		if ($request->usepromo == 1) {
			$updates['NightsToStay'] = isset($request->NightsToStay) && $request->NightsToStay ? $request->NightsToStay : '';
			$updates['NightsFree'] = isset($request->NightsFree) && $request->NightsFree ? $request->NightsFree : '';
			$updates['CumulativeNightsPromo'] = isset($request->CumulativeNightsPromo) && $request->CumulativeNightsPromo ? $request->CumulativeNightsPromo  : 0;
		}
		else {
			$updates['NightsToStay'] = '';
			$updates['NightsFree'] = '';
			$updates['CumulativeNightsPromo'] = 0;
		}
		$updates['PromoMsg'] = isset($request->PromoMsg) ? $request->PromoMsg : '';
		$updates['VendorMsg'] = isset($request->VendorMsg) ? $request->VendorMsg : '';
		$updates['fee_amount'] = isset($request->fee_amount) && $request->fee_amount ? $request->fee_amount : 0.00;
		$updates['fee_rate'] = isset($request->fee_rate) && $request->fee_rate ? $request->fee_rate : 'none';
		$updates['UseCancel'] = isset($request->UseCancel) && $request->UseCancel ? $request->UseCancel : 0;
		if ($updates['UseCancel'] == 1) {
			$updates['days_prior1'] = isset($request->days_prior1) && $request->days_prior1 ? $request->days_prior1 : NULL;
			$updates['amount1'] = isset($request->amount1) && $request->amount1 ? $request->amount1 : NULL;
			$updates['type1'] = isset($request->type1) && $request->type1 ? $request->type1 : 'Nights';
			$updates['days_prior2'] = isset($request->days_prior2) && $request->days_prior2 ? $request->days_prior2 : NULL;
			$updates['amount2'] = isset($request->amount2) && $request->amount2 ? $request->amount2 : NULL;
			$updates['type2'] = isset($request->type2) && $request->type2 ? $request->type2 : 'Nights';
			$updates['days_prior3'] = isset($request->days_prior3) && $request->days_prior3 ? $request->days_prior3 : NULL;
			$updates['amount3'] = isset($request->amount3) && $request->amount3 ? $request->amount3 : NULL;
			$updates['type3'] = isset($request->type3) && $request->type3 ? $request->type3 : 'Nights';
		}
		else {
			$updates['days_prior1'] = NULL;
			$updates['amount1'] = NULL;
			$updates['type1'] = 'Nights';
			$updates['days_prior2'] = NULL;
			$updates['amount2'] = NULL;
			$updates['type2'] = 'Nights';
			$updates['days_prior3'] = NULL;
			$updates['amount3'] = NULL;
			$updates['type3'] = 'Nights';
		}
		
		//daily surcharge updates
		$daily_updates = array();
		$daily_updates['mon1'] = isset($request->mon1) && $request->mon1 ? $request->mon1 : 0.00;
		$daily_updates['tax_mon1'] = isset($request->tax_mon1) && $request->tax_mon1 ? $request->tax_mon1 : 0.00;
		$daily_updates['net_mon1'] = isset($request->net_mon1) && $request->net_mon1 ? $request->net_mon1 : 0.00;
		$daily_updates['mon2'] = isset($request->mon2) && $request->mon2 ? $request->mon2 : 0.00;
		$daily_updates['tax_mon2'] = isset($request->tax_mon2) && $request->tax_mon2 ? $request->tax_mon2 : 0.00;
		$daily_updates['net_mon2'] = isset($request->net_mon2) && $request->net_mon2 ? $request->net_mon2 : 0.00;
		$daily_updates['mon3'] = isset($request->mon3) && $request->mon3 ? $request->mon3 : 0.00;
		$daily_updates['tax_mon3'] = isset($request->tax_mon3) && $request->tax_mon3 ? $request->tax_mon3 : 0.00;
		$daily_updates['net_mon3'] = isset($request->net_mon3) && $request->net_mon3 ? $request->net_mon3 : 0.00;
		$daily_updates['mon4'] = isset($request->mon4) && $request->mon4 ? $request->mon4 : 0.00;
		$daily_updates['tax_mon4'] = isset($request->tax_mon4) && $request->tax_mon4 ? $request->tax_mon4 : 0.00;
		$daily_updates['net_mon4'] = isset($request->net_mon4) && $request->net_mon4 ? $request->net_mon4 : 0.00;
		$daily_updates['tue1'] = isset($request->tue1) && $request->tue1 ? $request->tue1 : 0.00;
		$daily_updates['tax_tue1'] = isset($request->tax_tue1) && $request->tax_tue1 ? $request->tax_tue1 : 0.00;
		$daily_updates['net_tue1'] = isset($request->net_tue1) && $request->net_tue1 ? $request->net_tue1 : 0.00;
		$daily_updates['tue2'] = isset($request->tue2) && $request->tue2 ? $request->tue2 : 0.00;
		$daily_updates['tax_tue2'] = isset($request->tax_tue2) && $request->tax_tue2 ? $request->tax_tue2 : 0.00;
		$daily_updates['net_tue2'] = isset($request->net_tue2) && $request->net_tue2 ? $request->net_tue2 : 0.00;
		$daily_updates['tue3'] = isset($request->tue3) && $request->tue3 ? $request->tue3 : 0.00;
		$daily_updates['tax_tue3'] = isset($request->tax_tue3) && $request->tax_tue3 ? $request->tax_tue3 : 0.00;
		$daily_updates['net_tue3'] = isset($request->net_tue3) && $request->net_tue3 ? $request->net_tue3 : 0.00;
		$daily_updates['tue4'] = isset($request->tue4) && $request->tue4 ? $request->tue4 : 0.00;
		$daily_updates['tax_tue4'] = isset($request->tax_tue4) && $request->tax_tue4 ? $request->tax_tue4 : 0.00;
		$daily_updates['net_tue4'] = isset($request->net_tue4) && $request->net_tue4 ? $request->net_tue4 : 0.00;
		$daily_updates['wed1'] = isset($request->wed1) && $request->wed1 ? $request->wed1 : 0.00;
		$daily_updates['tax_wed1'] = isset($request->tax_wed1) && $request->tax_wed1 ? $request->tax_wed1 : 0.00;
		$daily_updates['net_wed1'] = isset($request->net_wed1) && $request->net_wed1 ? $request->net_wed1 : 0.00;
		$daily_updates['wed2'] = isset($request->wed2) && $request->wed2 ? $request->wed2 : 0.00;
		$daily_updates['tax_wed2'] = isset($request->tax_wed2) && $request->tax_wed2 ? $request->tax_wed2 : 0.00;
		$daily_updates['net_wed2'] = isset($request->net_wed2) && $request->net_wed2 ? $request->net_wed2 : 0.00;
		$daily_updates['wed3'] = isset($request->wed3) && $request->wed3 ? $request->wed3 : 0.00;
		$daily_updates['tax_wed3'] = isset($request->tax_wed3) && $request->tax_wed3 ? $request->tax_wed3 : 0.00;
		$daily_updates['net_wed3'] = isset($request->net_wed3) && $request->net_wed3 ? $request->net_wed3 : 0.00;
		$daily_updates['wed4'] = isset($request->wed4) && $request->wed4 ? $request->wed4 : 0.00;
		$daily_updates['tax_wed4'] = isset($request->tax_wed4) && $request->tax_wed4 ? $request->tax_wed4 : 0.00;
		$daily_updates['net_wed4'] = isset($request->net_wed4) && $request->net_wed4 ? $request->net_wed4 : 0.00;
		$daily_updates['thu1'] = isset($request->thu1) && $request->thu1 ? $request->thu1 : 0.00;
		$daily_updates['tax_thu1'] = isset($request->tax_thu1) && $request->tax_thu1 ? $request->tax_thu1 : 0.00;
		$daily_updates['net_thu1'] = isset($request->net_thu1) && $request->net_thu1 ? $request->net_thu1 : 0.00;
		$daily_updates['thu2'] = isset($request->thu2) && $request->thu2 ? $request->thu2 : 0.00;
		$daily_updates['tax_thu2'] = isset($request->tax_thu2) && $request->tax_thu2 ? $request->tax_thu2 : 0.00;
		$daily_updates['net_thu2'] = isset($request->net_thu2) && $request->net_thu2 ? $request->net_thu2 : 0.00;
		$daily_updates['thu3'] = isset($request->thu3) && $request->thu3 ? $request->thu3 : 0.00;
		$daily_updates['tax_thu3'] = isset($request->tax_thu3) && $request->tax_thu3 ? $request->tax_thu3 : 0.00;
		$daily_updates['net_thu3'] = isset($request->net_thu3) && $request->net_thu3 ? $request->net_thu3 : 0.00;
		$daily_updates['thu4'] = isset($request->thu4) && $request->thu4 ? $request->thu4 : 0.00;
		$daily_updates['tax_thu4'] = isset($request->tax_thu4) && $request->tax_thu4 ? $request->tax_thu4 : 0.00;
		$daily_updates['net_thu4'] = isset($request->net_thu4) && $request->net_thu4 ? $request->net_thu4 : 0.00;
		$daily_updates['fri1'] = isset($request->fri1) && $request->fri1 ? $request->fri1 : 0.00;
		$daily_updates['tax_fri1'] = isset($request->tax_fri1) && $request->tax_fri1 ? $request->tax_fri1 : 0.00;
		$daily_updates['net_fri1'] = isset($request->net_fri1) && $request->net_fri1 ? $request->net_fri1 : 0.00;
		$daily_updates['fri2'] = isset($request->fri2) && $request->fri2 ? $request->fri2 : 0.00;
		$daily_updates['tax_fri2'] = isset($request->tax_fri2) && $request->tax_fri2 ? $request->tax_fri2 : 0.00;
		$daily_updates['net_fri2'] = isset($request->net_fri2) && $request->net_fri2 ? $request->net_fri2 : 0.00;
		$daily_updates['fri3'] = isset($request->fri3) && $request->fri3 ? $request->fri3 : 0.00;
		$daily_updates['tax_fri3'] = isset($request->tax_fri3) && $request->tax_fri3 ? $request->tax_fri3 : 0.00;
		$daily_updates['net_fri3'] = isset($request->net_fri3) && $request->net_fri3 ? $request->net_fri3 : 0.00;
		$daily_updates['fri4'] = isset($request->fri4) && $request->fri4 ? $request->fri4 : 0.00;
		$daily_updates['tax_fri4'] = isset($request->tax_fri4) && $request->tax_fri4 ? $request->tax_fri4 : 0.00;
		$daily_updates['net_fri4'] = isset($request->net_fri4) && $request->net_fri4 ? $request->net_fri4 : 0.00;
		$daily_updates['sat1'] = isset($request->sat1) && $request->sat1 ? $request->sat1 : 0.00;
		$daily_updates['tax_sat1'] = isset($request->tax_sat1) && $request->tax_sat1 ? $request->tax_sat1 : 0.00;
		$daily_updates['net_sat1'] = isset($request->net_sat1) && $request->net_sat1 ? $request->net_sat1 : 0.00;
		$daily_updates['sat2'] = isset($request->sat2) && $request->sat2 ? $request->sat2 : 0.00;
		$daily_updates['tax_sat2'] = isset($request->tax_sat2) && $request->tax_sat2 ? $request->tax_sat2 : 0.00;
		$daily_updates['net_sat2'] = isset($request->net_sat2) && $request->net_sat2 ? $request->net_sat2 : 0.00;
		$daily_updates['sat3'] = isset($request->sat3) && $request->sat3 ? $request->sat3 : 0.00;
		$daily_updates['tax_sat3'] = isset($request->tax_sat3) && $request->tax_sat3 ? $request->tax_sat3 : 0.00;
		$daily_updates['net_sat3'] = isset($request->net_sat3) && $request->net_sat3 ? $request->net_sat3 : 0.00;
		$daily_updates['sat4'] = isset($request->sat4) && $request->sat4 ? $request->sat4 : 0.00;
		$daily_updates['tax_sat4'] = isset($request->tax_sat4) && $request->tax_sat4 ? $request->tax_sat4 : 0.00;
		$daily_updates['net_sat4'] = isset($request->net_sat4) && $request->net_sat4 ? $request->net_sat4 : 0.00;
		$daily_updates['sun1'] = isset($request->sun1) && $request->sun1 ? $request->sun1 : 0.00;
		$daily_updates['tax_sun1'] = isset($request->tax_sun1) && $request->tax_sun1 ? $request->tax_sun1 : 0.00;
		$daily_updates['net_sun1'] = isset($request->net_sun1) && $request->net_sun1 ? $request->net_sun1 : 0.00;
		$daily_updates['sun2'] = isset($request->sun2) && $request->sun2 ? $request->sun2 : 0.00;
		$daily_updates['tax_sun2'] = isset($request->tax_sun2) && $request->tax_sun2 ? $request->tax_sun2 : 0.00;
		$daily_updates['net_sun2'] = isset($request->net_sun2) && $request->net_sun2 ? $request->net_sun2 : 0.00;
		$daily_updates['sun3'] = isset($request->sun3) && $request->sun3 ? $request->sun3 : 0.00;
		$daily_updates['tax_sun3'] = isset($request->tax_sun3) && $request->tax_sun3 ? $request->tax_sun3 : 0.00;
		$daily_updates['net_sun3'] = isset($request->net_sun3) && $request->net_sun3 ? $request->net_sun3 : 0.00;
		$daily_updates['sun4'] = isset($request->sun4) && $request->sun4 ? $request->sun4 : 0.00;
		$daily_updates['tax_sun4'] = isset($request->tax_sun4) && $request->tax_sun4 ? $request->tax_sun4 : 0.00;
		$daily_updates['net_sun4'] = isset($request->net_sun4) && $request->net_sun4 ? $request->net_sun4 : 0.00;
		
		//check if apply adhoc tax
		$UseAdhocTax = false;
		if (isset($request->PriceID) && $request->PriceID) {							
			$row = DB::table('tblVendor as v')
						->select(DB::raw('v.UseAdhocTax, v.AdhocTax1, v.AdhocTaxType1, v.AdhocTax2, v.AdhocTaxType2, p.ProdCode, p.IsHotel'))
						->join('tblProduct as p', 'p.Vendor', 'v.VendorID')
						->join("$ptable as pr", 'pr.ProdCode', 'p.ProdCode')
						->where('pr.PriceID', $request->PriceID)
						->get()->first();
						
			$ProdCode = $row ? $row->ProdCode : '';
		}
		else {
			$ProdCode = $request->ProdCode;
			$row = DB::table('tblVendor as v')
						->select(DB::raw('v.UseAdhocTax, v.AdhocTax1, v.AdhocTaxType1, v.AdhocTax2, v.AdhocTaxType2, p.IsHotel'))
						->join('tblProduct as p', 'p.Vendor', 'v.VendorID')
						->where('p.ProdCode', $ProdCode)
						->get()->first();			
		}	
		
		if ($row) {
			//check if date range already exists.
			$rows2 = $priceObj::where('ProdCode', $ProdCode)->get();
			foreach ($rows2 as $row2) {
				if ($row2->PriceID <> $request->PriceID) {
					if ($common->date_compare($request->Begin,$row2->Begin,">=") and $common->date_compare($request->Begin,$row2->End,"<=")) {
						$result['status'] = 'failed';
						$result['message'] = "Prices in this date range already exist.";
						return $result;
					}
					elseif ($common->date_compare($request->End,$row2->Begin,">=") and $common->date_compare($request->End,$row2->End,"<=")) {
						$result['status'] = 'failed';
						$result['message'] = "Prices in this date range already exist.";
						return $result;
					}
					elseif ($common->date_compare($row2->Begin,$request->Begin,">=") and $common->date_compare($row2->Begin,$request->End,"<=")) {
						$result['status'] = 'failed';
						$result['message'] = "Prices in this date range already exist.";
						return $result;
					}
					elseif ($common->date_compare($row2->End,$request->Begin,">=") and $common->date_compare($row2->End,$request->End,"<=")) {
						$result['status'] = 'failed';
						$result['message'] = "Prices in this date range already exist.";
						return $result;
					}
				} 
			}
			
			$IsHotel = $row ? $row->IsHotel : '';
			$UseAdhocTax = $row ? $row->UseAdhocTax : 0;
			$AdhocTax1 = $row ? $row->AdhocTax1 : 0.00;
			$AdhocTaxType1 = $row ? $row->AdhocTaxType1 : 'Percentage';
			$AdhocTax2 = $row ? $row->AdhocTax2 : 0.00;
			$AdhocTaxType2 = $row ? $row->AdhocTaxType2 : 'Percentage';
			
			//DC: for testing only
			/*$UseAdhocTax = 1;
			$AdhocTax1 = '1000';
			$AdhocType1 = 'Amount';*/		
			
			if ($UseAdhocTax) {
				if ($updates['OurNet1'] > 0) $updates['OurCost1']=$common->netplusadhoctax($updates['OurNetFee1'],$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2); 				
				if ($updates['OurNet2'] > 0) $updates['OurCost2']=$common->netplusadhoctax($updates['OurNetFee2'],$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($updates['OurNet3'] > 0) $updates['OurCost3']=$common->netplusadhoctax($updates['OurNetFee3'],$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($updates['OurNet4'] > 0) $updates['OurCost4']=$common->netplusadhoctax($updates['OurNetFee4'],$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);

				if ($updates['ChildNet'] > 0) $updates['ChildTax']=$common->netplusadhoctax($updates['ChildNetFee'],$ProdCode,"NO", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);

				if ($daily_updates['net_mon1'] > 0) $updates['tax_mon1']=$common->netplusadhoctax($updates['net_mon1'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_mon2'] > 0) $updates['tax_mon2']=$common->netplusadhoctax($updates['net_mon2'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_mon3'] > 0) $updates['tax_mon3']=$common->netplusadhoctax($updates['net_mon3'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_mon4'] > 0) $updates['tax_mon4']=$common->netplusadhoctax($updates['net_mon4'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_tue1'] > 0) $updates['tax_tue1']=$common->netplusadhoctax($updates['net_tue1'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_tue2'] > 0) $updates['tax_tue2']=$common->netplusadhoctax($updates['net_tue2'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_tue3'] > 0) $updates['tax_tue3']=$common->netplusadhoctax($updates['net_tue3'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_tue4'] > 0) $updates['tax_tue4']=$common->netplusadhoctax($updates['net_tue4'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_wed1'] > 0) $updates['tax_wed1']=$common->netplusadhoctax($updates['net_wed1'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_wed2'] > 0) $updates['tax_wed2']=$common->netplusadhoctax($updates['net_wed2'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_wed3'] > 0) $updates['tax_wed3']=$common->netplusadhoctax($updates['net_wed3'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_wed4'] > 0) $updates['tax_wed4']=$common->netplusadhoctax($updates['net_wed4'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_thu1'] > 0) $updates['tax_thu1']=$common->netplusadhoctax($updates['net_thu1'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_thu2'] > 0) $updates['tax_thu2']=$common->netplusadhoctax($updates['net_thu2'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_thu3'] > 0) $updates['tax_thu3']=$common->netplusadhoctax($updates['net_thu3'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_thu4'] > 0) $updates['tax_thu4']=$common->netplusadhoctax($updates['net_thu4'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_fri1'] > 0) $updates['tax_fri1']=$common->netplusadhoctax($updates['net_fri1'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_fri2'] > 0) $updates['tax_fri2']=$common->netplusadhoctax($updates['net_fri2'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_fri3'] > 0) $updates['tax_fri3']=$common->netplusadhoctax($updates['net_fri3'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_fri4'] > 0) $updates['tax_fri4']=$common->netplusadhoctax($updates['net_fri4'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_sat1'] > 0) $updates['tax_sat1']=$common->netplusadhoctax($updates['net_sat1'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_sat2'] > 0) $updates['tax_sat2']=$common->netplusadhoctax($updates['net_sat2'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_sat3'] > 0) $updates['tax_sat3']=$common->netplusadhoctax($updates['net_sat3'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_sat4'] > 0) $updates['tax_sat4']=$common->netplusadhoctax($updates['net_sat4'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_sun1'] > 0) $updates['tax_sun1']=$common->netplusadhoctax($updates['net_sun1'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_sun2'] > 0) $updates['tax_sun2']=$common->netplusadhoctax($updates['net_sun2'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_sun3'] > 0) $updates['tax_sun3']=$common->netplusadhoctax($updates['net_sun3'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
				if ($daily_updates['net_sun4'] > 0) $updates['tax_sun4']=$common->netplusadhoctax($updates['net_sun4'],$ProdCode,"YES", $AdhocTax1, $AdhocTaxType1, $AdhocTax2, $AdhocTaxType2);
			}
			else {
				if ($updates['OurNet1'] > 0) $updates['OurCost1']=$common->netplustax($updates['OurNetFee1'],$ProdCode,"NO"); 	
				if ($updates['OurNet2'] > 0) $updates['OurCost2']=$common->netplustax($updates['OurNetFee2'],$ProdCode,"NO");
				if ($updates['OurNet3'] > 0) $updates['OurCost3']=$common->netplustax($updates['OurNetFee3'],$ProdCode,"NO");
				if ($updates['OurNet4'] > 0) $updates['OurCost4']=$common->netplustax($updates['OurNetFee4'],$ProdCode,"NO");

				if ($updates['ChildNet'] > 0) $updates['ChildTax']=$common->netplustax($updates['ChildNetFee'],$ProdCode,"NO");

				if ($daily_updates['net_mon1'] > 0) $daily_updates['tax_mon1']=$common->netplustax($daily_updates['net_mon1'],$ProdCode,"YES");
				if ($daily_updates['net_mon2'] > 0) $daily_updates['tax_mon2']=$common->netplustax($daily_updates['net_mon2'],$ProdCode,"YES");
				if ($daily_updates['net_mon3'] > 0) $daily_updates['tax_mon3']=$common->netplustax($daily_updates['net_mon3'],$ProdCode,"YES");
				if ($daily_updates['net_mon4'] > 0) $daily_updates['tax_mon4']=$common->netplustax($daily_updates['net_mon4'],$ProdCode,"YES");
				if ($daily_updates['net_tue1'] > 0) $daily_updates['tax_tue1']=$common->netplustax($daily_updates['net_tue1'],$ProdCode,"YES");
				if ($daily_updates['net_tue2'] > 0) $daily_updates['tax_tue2']=$common->netplustax($daily_updates['net_tue2'],$ProdCode,"YES");
				if ($daily_updates['net_tue3'] > 0) $daily_updates['tax_tue3']=$common->netplustax($daily_updates['net_tue3'],$ProdCode,"YES");
				if ($daily_updates['net_tue4'] > 0) $daily_updates['tax_tue4']=$common->netplustax($daily_updates['net_tue4'],$ProdCode,"YES");
				if ($daily_updates['net_wed1'] > 0) $daily_updates['tax_wed1']=$common->netplustax($daily_updates['net_wed1'],$ProdCode,"YES");
				if ($daily_updates['net_wed2'] > 0) $daily_updates['tax_wed2']=$common->netplustax($daily_updates['net_wed2'],$ProdCode,"YES");
				if ($daily_updates['net_wed3'] > 0) $daily_updates['tax_wed3']=$common->netplustax($daily_updates['net_wed3'],$ProdCode,"YES");
				if ($daily_updates['net_wed4'] > 0) $daily_updates['tax_wed4']=$common->netplustax($daily_updates['net_wed4'],$ProdCode,"YES");
				if ($daily_updates['net_thu1'] > 0) $daily_updates['tax_thu1']=$common->netplustax($daily_updates['net_thu1'],$ProdCode,"YES");
				if ($daily_updates['net_thu2'] > 0) $daily_updates['tax_thu2']=$common->netplustax($daily_updates['net_thu2'],$ProdCode,"YES");
				if ($daily_updates['net_thu3'] > 0) $daily_updates['tax_thu3']=$common->netplustax($daily_updates['net_thu3'],$ProdCode,"YES");
				if ($daily_updates['net_thu4'] > 0) $daily_updates['tax_thu4']=$common->netplustax($daily_updates['net_thu4'],$ProdCode,"YES");
				if ($daily_updates['net_fri1'] > 0) $daily_updates['tax_fri1']=$common->netplustax($daily_updates['net_fri1'],$ProdCode,"YES");
				if ($daily_updates['net_fri2'] > 0) $daily_updates['tax_fri2']=$common->netplustax($daily_updates['net_fri2'],$ProdCode,"YES");
				if ($daily_updates['net_fri3'] > 0) $daily_updates['tax_fri3']=$common->netplustax($daily_updates['net_fri3'],$ProdCode,"YES");
				if ($daily_updates['net_fri4'] > 0) $daily_updates['tax_fri4']=$common->netplustax($daily_updates['net_fri4'],$ProdCode,"YES");
				if ($daily_updates['net_sat1'] > 0) $daily_updates['tax_sat1']=$common->netplustax($daily_updates['net_sat1'],$ProdCode,"YES");
				if ($daily_updates['net_sat2'] > 0) $daily_updates['tax_sat2']=$common->netplustax($daily_updates['net_sat2'],$ProdCode,"YES");
				if ($daily_updates['net_sat3'] > 0) $daily_updates['tax_sat3']=$common->netplustax($daily_updates['net_sat3'],$ProdCode,"YES");
				if ($daily_updates['net_sat4'] > 0) $daily_updates['tax_sat4']=$common->netplustax($daily_updates['net_sat4'],$ProdCode,"YES");
				if ($daily_updates['net_sun1'] > 0) $daily_updates['tax_sun1']=$common->netplustax($daily_updates['net_sun1'],$ProdCode,"YES");
				if ($daily_updates['net_sun2'] > 0) $daily_updates['tax_sun2']=$common->netplustax($daily_updates['net_sun2'],$ProdCode,"YES");
				if ($daily_updates['net_sun3'] > 0) $daily_updates['tax_sun3']=$common->netplustax($daily_updates['net_sun3'],$ProdCode,"YES");
				if ($daily_updates['net_sun4'] > 0) $daily_updates['tax_sun4']=$common->netplustax($daily_updates['net_sun4'],$ProdCode,"YES");
			}
			
			//convert currency for services
			if (isset($IsHotel) && $IsHotel == 0) {
				$OrigOurCost1 = $updates['OurCost1'];
				$OrigOurCost2 = $updates['OurCost2'];
				$OrigOurCost3 = $updates['OurCost3'];
				$OrigOurCost4 = $updates['OurCost4'];
				$OrigChildTax = $updates['ChildTax'];
				$updates['OurCost1']=$common->convertcurrency($updates['OurCost1'],$ProdCode);
				$updates['OurCost2']=$common->convertcurrency($updates['OurCost2'],$ProdCode);
				$updates['OurCost3']=$common->convertcurrency($updates['OurCost3'],$ProdCode);
				$updates['OurCost4']=$common->convertcurrency($updates['OurCost4'],$ProdCode);
				$updates['ChildTax']=$common->convertcurrency($updates['ChildTax'],$ProdCode,"NO");
			}
			
			//if auto markup is set, recalculate markup
			if ($updates['markup']==1) {				
				$rate1=$updates['Price1'];
				$rate2=$updates['Price2'];
				$rate3=$updates['Price3'];
				$rate4=$updates['Price4'];

				$child_price=$updates['ChildPrice'];
				
				$ratemon1=$daily_updates['mon1'];
				$ratemon2=$daily_updates['mon2'];
				$ratemon3=$daily_updates['mon3'];
				$ratemon4=$daily_updates['mon4'];
				$ratetue1=$daily_updates['tue1'];
				$ratetue2=$daily_updates['tue2'];
				$ratetue3=$daily_updates['tue3'];
				$ratetue4=$daily_updates['tue4'];
				$ratewed1=$daily_updates['wed1'];
				$ratewed2=$daily_updates['wed2'];
				$ratewed3=$daily_updates['wed3'];
				$ratewed4=$daily_updates['wed4'];
				$ratethu1=$daily_updates['thu1'];
				$ratethu2=$daily_updates['thu2'];
				$ratethu3=$daily_updates['thu3'];
				$ratethu4=$daily_updates['thu4'];
				$ratefri1=$daily_updates['fri1'];
				$ratefri2=$daily_updates['fri2'];
				$ratefri3=$daily_updates['fri3'];
				$ratefri4=$daily_updates['fri4'];
				$ratesat1=$daily_updates['sat1'];
				$ratesat2=$daily_updates['sat2'];
				$ratesat3=$daily_updates['sat3'];
				$ratesat4=$daily_updates['sat4'];
				$ratesun1=$daily_updates['sun1'];
				$ratesun2=$daily_updates['sun2'];
				$ratesun3=$daily_updates['sun3'];
				$ratesun4=$daily_updates['sun4'];						

				$common->calc_price($ProdCode,$updates['OurCost1'],$updates['OurCost2'],$updates['OurCost3'],$updates['OurCost4'],$rate1,$rate2,$rate3,$rate4,0,'',$updates['ChildTax'],$child_price,$daily_updates['tax_mon1'],$daily_updates['tax_mon2'],$daily_updates['tax_mon3'],$daily_updates['tax_mon4'],$daily_updates['tax_tue1'],$daily_updates['tax_tue2'],$daily_updates['tax_tue3'],$daily_updates['tax_tue4'],$daily_updates['tax_wed1'],$daily_updates['tax_wed2'],$daily_updates['tax_wed3'],$daily_updates['tax_wed4'],$daily_updates['tax_thu1'],$daily_updates['tax_thu2'],$daily_updates['tax_thu3'],$daily_updates['tax_thu4'],$daily_updates['tax_fri1'],$daily_updates['tax_fri2'],$daily_updates['tax_fri3'],$daily_updates['tax_fri4'],$daily_updates['tax_sat1'],$daily_updates['tax_sat2'],$daily_updates['tax_sat3'],$daily_updates['tax_sat4'],$daily_updates['tax_sun1'],$daily_updates['tax_sun2'],$daily_updates['tax_sun3'],$daily_updates['tax_sun4'],$ratemon1,$ratemon2,$ratemon3,$ratemon4,$ratetue1,$ratetue2,$ratetue3,$ratetue4,$ratewed1,$ratewed2,$ratewed3,$ratewed4,$ratethu1,$ratethu2,$ratethu3,$ratethu4,$ratefri1,$ratefri2,$ratefri3,$ratefri4,$ratesat1,$ratesat2,$ratesat3,$ratesat4,$ratesun1,$ratesun2,$ratesun3,$ratesun4,$updates['Begin'],$updates['End']);
				
				$updates['Price1']=$rate1;
				$updates['Price2']=$rate2;
				$updates['Price3']=$rate3;
				$updates['Price4']=$rate4;
				
				$updates['ChildPrice']=$child_price;
				
				$daily_updates['mon1']=$ratemon1;
				$daily_updates['mon2']=$ratemon2;
				$daily_updates['mon3']=$ratemon3;
				$daily_updates['mon4']=$ratemon4;
				$daily_updates['tue1']=$ratetue1;
				$daily_updates['tue2']=$ratetue2;
				$daily_updates['tue3']=$ratetue3;
				$daily_updates['tue4']=$ratetue4;
				$daily_updates['wed1']=$ratewed1;
				$daily_updates['wed2']=$ratewed2;
				$daily_updates['wed3']=$ratewed3;
				$daily_updates['wed4']=$ratewed4;
				$daily_updates['thu1']=$ratethu1;
				$daily_updates['thu2']=$ratethu2;
				$daily_updates['thu3']=$ratethu3;
				$daily_updates['thu4']=$ratethu4;
				$daily_updates['fri1']=$ratefri1;
				$daily_updates['fri2']=$ratefri2;
				$daily_updates['fri3']=$ratefri3;
				$daily_updates['fri4']=$ratefri4;
				$daily_updates['sat1']=$ratesat1;
				$daily_updates['sat2']=$ratesat2;
				$daily_updates['sat3']=$ratesat3;
				$daily_updates['sat4']=$ratesat4;
				$daily_updates['sun1']=$ratesun1;
				$daily_updates['sun2']=$ratesun2;
				$daily_updates['sun3']=$ratesun3;
				$daily_updates['sun4']=$ratesun4;
			} //automarkup
			
			if (isset($IsHotel) && $IsHotel == 0) {
				if (isset($OrigOurCost1))
					$updates['OurCost1'] = $OrigOurCost1;
				if (isset($OrigOurCost2))
					$updates['OurCost2'] = $OrigOurCost2;
				if (isset($OrigOurCost3))
					$updates['OurCost3'] = $OrigOurCost3;
				if (isset($OrigOurCost4))
					$updates['OurCost4'] = $OrigOurCost4;
				if (isset($OrigChildTax))
					$updates['ChildTax'] = $OrigChildTax;
			}
		}
		
		if (isset($request->PriceID) && $request->PriceID) {
			$data = $priceObj::find($request->PriceID);		
			if ($data) {		
				$pushCommon->addNewPushEvent('ALL', 'updateprice', $ProdCode, $data->Begin, $data->End);
			
				//For Log History
				$history = new PriceHistory();
				
				foreach ($updates as $field => $value){		
					//Log Changes
					$history->compare($data->{$field}, $updates[$field], $field); 
				
					$data->{$field} = $value;
				}
				
				$data->save();
				
				$username = Auth::user()->UserName; 
				$initials = Auth::user()->Initials;
				
				//Log History
				$history->logUpdates([
					'ProdCode' => $data->ProdCode,
					'Action' => "$priceType Price Modify: ".$request->PriceID,
					'UserID' => $username,
					'Initials' => $initials
				]);		
				
				//update daily surcharge
				$daily = PriceDays::where([['id', $request->PriceID], ['type', $ptype]])->get()->first();
				
				//For Log History
				$history = new PriceHistory();
				
				foreach ($daily_updates as $field => $value){		
					//Log Changes
					$history->compare($daily->{$field}, $daily_updates[$field], $field); 
				
					$daily->{$field} = $value;
				}
				
				$daily->save();
				
				//Log History
				$history->logUpdates([
					'ProdCode' => $data->ProdCode,
					'Action' => "$priceType Daily Surcharges Changed: ".$request->PriceID,
					'UserID' => $username,
					'Initials' => $initials
				]);	
			}
			else {
				$result['status'] = 'failed';
				$result['message'] = "$priceType Price ID: $request->PriceID not found.";
				return $result;
			}
		}
		else { //new entry		
			//check if ProdCode exists
			$prod = Product::where('ProdCode', $ProdCode)
							->get()->first();
							
			if (!$prod) {
				$result['status'] = 'failed';
				$result['message'] = "Product Code: $ProdCode not found.";
				return $result;
			}
		
			$data = $priceObj;
			
			$updates['ProdCode'] = $ProdCode;
			$updates['Occ1'] = 1;
			$updates['Occ2'] = 2;
			$updates['Occ3'] = 3;
			$updates['Occ4'] = 4;
			
			foreach ($updates as $field => $value){	
				$data->{$field} = $value;			
			}
			
			$data->save();			
						
			$newPriceID = $data->PriceID;
			
			$row = $priceObj::where('PriceID', $newPriceID)->get()->first();
				
			$newPrices = "Begin Date = $row->Begin
				<br />End Date = $row->End
				<br />Sell Price 1 = $row->Price1
				<br/>Sell Price 2 = $row->Price2
				<br/>Sell Price 3 = $row->Price3
				<br/>Sell Price 4 = $row->Price4
				<br/>Net+Tax 1 = $row->OurCost1
				<br/>Net+Tax 2 = $row->OurCost2
				<br/>Net+Tax 3 = $row->OurCost3
				<br/>Net+Tax 4 = $row->OurCost4
				<br/>Net 1 = $row->OurNet1
				<br/>Net 2 = $row->OurNet2
				<br/>Net 3 = $row->OurNet3
				<br/>Net 4 = $row->OurNet4
				<br/>Net+Fee 1 = $row->OurNetFee1
				<br/>Net+Fee 2 = $row->OurNetFee2
				<br/>Net+Fee 3 = $row->OurNetFee3
				<br/>Net+Fee 4 = $row->OurNetFee4";
			
			PriceHistory::insert([
				'ProdCode' => $ProdCode,
				'UserID' => Auth::user()->UserName,
				'Initials' => Auth::user()->Initials,
				'Action' => "$priceType Price Added: $newPriceID",
				'NewVal' => $newPrices
			]);			
			
			//save daily surcharge
			$daily = new PriceDays();
			
			$daily->id = $newPriceID;
			$daily->type = $ptype;
			
			$newDailyPrices = '';
			foreach ($daily_updates as $field => $value){	
				$daily->{$field} = $value;		

				if ($value > 0) {
					$newDailyPrices .= "$field = $value<br>";
				}
			}
			
			$daily->save();
			
			PriceHistory::insert([
				'ProdCode' => $ProdCode,
				'UserID' => Auth::user()->UserName,
				'Initials' => Auth::user()->Initials,
				'Action' => "$priceType Daily Surcharge Added: $newPriceID",
				'NewVal' => $newDailyPrices
			]);	
		}		
		
		Product::where('ProdCode', $ProdCode)->update(['NeedsPriceUpdate' => 1]);			
		$pushCommon->addNewPushEvent('ALL', 'updateprice', $ProdCode, $request->Begin, $request->End);
	
		$result['status'] = 'success';
		return $result;
	}
}