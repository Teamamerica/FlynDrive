<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Repositories\TBPriceSYNCNewFunctions2Repo;

use App\ProductHistory;
use App\VendorHistory;

class NeedsPriceUpdateRepo {
	
	public $priceSyncFunc2Repo;
	
	public function __construct() {
		$this->priceSyncFunc2Repo = new TBPriceSYNCNewFunctions2Repo();
	}
	
	public function execute($prodCode, $vendorID) {					
		if ($prodCode) {
			$pidfile = '/tmp/price_sync_prod_$prodCode.pid';
		}
		else { //running all active products under vendorID
			$pidfile = '/tmp/price_sync_vend_$vendorID.pid';
		}
		
		//if (file_exists($pidfile)) unlink($pidfile); //for testing only
		
		if (file_exists($pidfile) == true) {
			$pid = file_get_contents($pidfile);
			$data['status'] = 'failed';
			$data['message'] = "Process ID $pid is already running ";		
			if ($prodCode)
				$data['message'] .= "for product $prodCode.";
			else
				$data['message'] .= "for vendor $vendorID.";
			return $data;
		}
			
		$pid = getmypid();
		file_put_contents($pidfile, $pid, LOCK_EX);
		
		if ($prodCode) {
			$rows = DB::table('tblProduct')
						->select('ProdCode', 'Vendor')
						->where('ProdCode', $prodCode)
						->get();		
			
			DB::table('tblProduct')
				->where('ProdCode', $prodCode)
				->update([
					'NeedsPriceUpdate' => 1
				]);
		}
		else { //get all active products under vendorID
			$today = date('Y-m-d');
			$rows = DB::table('tblProduct')
						->select('ProdCode', 'Description')
						->where('Vendor', $vendorID)
						->get();
			foreach($rows as $k => $prow){
				if(substr($prow->ProdCode, 4,2)!='NT'){
					$row = DB::table('tblDefaultPrice')
								->select(DB::raw('max(End) as End'))
								->where('ProdCode', $prow->ProdCode)
								->get()->first();
					
					if($row){//If it has an end date in default pricing
						//If the end date is today or in the future, set it to active list
						if($row->End<$today) {
							unset($rows[$k]);
						}
					}
					else
						unset($rows[$k]);
				}
				else
					unset($rows[$k]);
			}
		}
		
		//print_r($rows); exit;
		
		//always clear dream4 cache when publish:
		$dream4_path = env('DREAM4_PATH');
		$ch = curl_init($dream4_path."/clearCache");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_exec($ch);
		curl_close($ch);
		
		$username = Auth::user()->UserName; 
		$path = env('APP_PATH');		
		
		foreach ($rows as $row) {
			//print_r($row);
			
			$this->priceSyncFunc2Repo->update_PriceForAllotmentTable($row->ProdCode);
			
			$this->priceSyncFunc2Repo->cutoff_sync($row->ProdCode);
			
			//update product history
			ProductHistory::insert([
				'ProdCode' => $row->ProdCode,
				'dtmTimeStamp' => date('Y-m-d H:i:s'),
				'Action' => 'Published To DREAM From API',
				'UserID' => $username
			]);
			
			//Update cache pricing for TUI				
			exec("/usr/bin/php $path/artisan UpdateTUIPriceCache $row->ProdCode $username > /dev/null 2>&1 &");
			
			DB::table('tblProduct')
				->where('ProdCode', $row->ProdCode)
				->update([
					'NeedsPriceUpdate' => 0
				]);		
		}
		
		if ($vendorID) {
			//update vendor history
			VendorHistory::insert([
				'VendorID' => $vendorID,
				'dtmTimeStamp' => date('Y-m-d H:i:s'),
				'Action' => 'Published Active Products To DREAM From API',
				'UserID' => $username
			]);
		}
		
		if (file_exists($pidfile) == true) {
			unlink($pidfile);
		}				
		
		$data['status'] = 'success';
		return $data;
	}
	
}