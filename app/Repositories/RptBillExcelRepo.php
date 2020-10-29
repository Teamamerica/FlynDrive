<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\PriceStatement;
use App\Repositories\TBCommonRepo;

class RptBillExcelRepo {	
	public $commonRepo;

	public function __construct() {
		$this->commonRepo = new TBCommonRepo();
	}
	
	public function generateExcel($request) {
		//print_r($request->all()); exit;
		//error_reporting(E_ALL);
		//ini_set('display_errors', 1);
		ini_set('max_execution_time', 600);
		ini_set('memory_limit', '1024M');
		
		$result['status'] = 'failed';
		
		//validate fields
		$rules = [
				'fromDate' => array('required', 'regex:/^([12]\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01]))$/'),
				'toDate' => array('required', 'regex:/^([12]\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01]))$/'),
				'rptType' => array('required', 'in:pre-billing,posting'),
				//'dept' => array('required', 'in:kk,ii,2,3,5,6,12,13,14,15,16,17,18,20,21,22,23,24,25'),
				'bookings' => array('required', 'in:c,p,b,q,a'),
				'netPaid' => array('in:0,1'),
				'netOnly' => array('in:0,1'),
				'canceled' => array('in:0,1'),
				'prevPosted' => array('in:0,1'),
				'items' => array('required', 'in:a,f'),
				'displayNames' => array('in:0,1'),
				'agencies' => array('required', 'in:1,2,3'),
				'autoBill' => array('in:0,1'),
				'dateType' => array('required', 'in:travel,book'),
				'amount' => array('regex:/^\d*\.?\d*$/'),
				'agency' => array(),
		];
				
		$messages = [
		];
		
		$validator = Validator::make($request->all(), $rules, $messages);
		
		if ($validator->fails()) {
			//print_r($validator->errors()->all()); exit;
			
			$result['status'] = 'failed';
			$result['message'] = $validator->errors()->first();
			
			return $result;	
		}
		
		$num_ag = $request->agencies;
		$ag = $request->agency;
		$bkgs = $request->bookings;
		$date_type = $request->dateType;
		$begin = $request->fromDate;
		$end = $request->toDate;
		$begin_date = $this->commonRepo->ISO_to_MMDDYYYY($begin);
		$end_date = $this->commonRepo->ISO_to_MMDDYYYY($end);
		$items = $request->items;
		$rpt_type = $request->rptType;
		$netonly = $request->netOnly;
		$netpaid = $request->netPaid;
		$agcode = '';
		
		if (isset($netpaid) && $netpaid) {
			$netpaid=1;
		} 
		else {
			$netpaid=0;
		}
		
		if ($num_ag == 1) { 
			$agdata = DB::table('tblAgency')
					->where('AgCode', $ag)
					->get()->first();
					
			if (!$agdata) {
				$agdata = DB::table('tblAgency')
						->where('AgName', $ag)
						->get()->first();	
						
				if (!$agdata) { 
					$agdata = DB::table('tblAgency')
							->where('AgName', 'like', "%$ag%")
							->get()->first();
					
					if (!$agdata) { 
						$result['message'] = 'Agency not found.';							
						return $result;
					}
				}
			}			
			
			//echo $agdata->AgCode; exit;
			$agcode = $agdata->AgCode;
		}
		
		if ($date_type=="travel") {
			if ($bkgs=="q"){				
				$res = DB::table('tblItem as i')
						->select(DB::raw('i.ResNum'))
						->join('tblBooking as b', 'b.ResNum', '=', 'i.ResNum')
						->join('tblAgency as a', 'a.AgCode', '=', 'b.AgCode')
						->where([['b.Status', 'QU'], ['i.DepDate', '>=', $begin], ['i.DepDate', '<=', $end]])
						->whereNotIn('i.Status', ['CX', 'XR', 'PX']);
				
				if ($agcode) {
					$res = $res->where('b.AgCode', $agcode);
				}
				
				if ($num_ag==3) { //only prepaid agencies
					$res = $res->where('a.PmtStatus', 'PP');					
				} 
						
				$res = $res->groupBy('i.ResNum')
						->orderBy('b.AgCode')
						->orderBy('i.DepDate')
						->get();				
				
				//print_r($res); exit;
			}
			else { //others
				$res = DB::table('tblItem as i')
							->join('tblBooking as b', 'b.ResNum', '=', 'i.ResNum')
							->join('tblAgency as a', 'a.AgCode', '=', 'b.AgCode')
							->where([['i.DepDate', '>=', $begin], ['i.DepDate', '<=', $end]]);
				
				if ($agcode) {
					$res = $res->where('b.AgCode', $agcode);
				}
				
				if ($num_ag==3) { //only prepaid agencies
					$res = $res->where('a.PmtStatus', 'PP');					
				} 
						
				$res = $res->where([['b.Status', '<>', 'CX']])
							->whereNotIn('i.Status', ['XX', 'CX', 'XR', 'PX']);							
						
				$res = $res->groupBy('i.ResNum')
						->orderBy('b.AgCode')
						->orderBy('i.DepDate')
						->get();
			}
		}
		else { //date_type==book
			if ($bkgs=="q") {		
				$res = DB::table('tblBooking as b')
						->join('tblAgency as a', 'a.agcode', '=', 'b.agcode')
						->where([['b.datebooked', '>=', $begin], ['b.datebooked', '<=', $end], ['b.status', 'QU']]);
										
				if ($agcode) {
					$res = $res->where('b.AgCode', $agcode);
				}
				
				if ($num_ag==3) { //only prepaid agencies
					$res = $res->where('a.PmtStatus', 'PP');					
				} 
						
				$res = $res->orderBy('b.agcode')
							->orderBy('resnum')
							->get();	

				//echo count($res); exit;
			}
			else { //others
				$res = DB::table('tblBooking as b')
						->join('tblAgency as a', 'a.agcode', '=', 'b.agcode')
						->where([['b.datebooked', '>=', $begin], ['b.datebooked', '<=', $end], ['b.status', '<>', 'QU']]);
				
				$res = $res->whereNotIn('b.status', ['CX']);				
				
				if ($agcode) {
					$res = $res->where('b.AgCode', $agcode);
				}
				
				if ($num_ag==3) { //only prepaid agencie
					$res = $res->where('a.PmtStatus', 'PP');					
				} 
						
				$res = $res->orderBy('b.agcode')
							->orderBy('resnum')
							->get();
			}
		}
		
		//print_r($res); exit;
		
		foreach ($res as $r) {					
			switch ($bkgs) {
				case "a":
				case "q":
				case "c":
				case "p":
				case "b":
					$resList[] = $r->ResNum;
					break;
			} // end switch
		}
		
		//print_r($resList); exit;
		
		if ($bkgs=="q") {			
			$resData = DB::table('tblItem as i')
							->select(DB::raw('b.ResNum, b.AgCode, b.DateBooked, b.ClientRef, b.AgentName, b.Source, b.AgEscortedComm, a.AgName, a.ShowCommission, a.PmtStatus, a.Add1, a.Add2, a.City, a.State, a.ZIP, a.Country, i.ItemID, i.DepDate, i.ProdCode, i.ClientRef as ClientRef2, i.Description, i.Qty, i.Price, i.Occupancy, i.PricingType, i.Nights, i.AgCommFlag, i.SupplierRef'))
							->join('tblBooking as b', 'b.ResNum', '=', 'i.ResNum')
							->join('tblAgency as a', 'a.AgCode', '=', 'b.AgCode')
							->where([['b.Status', 'QU']])
							->whereNotIn('i.Status', ['CX', 'XR', 'PX'])
							->whereIn('i.ResNum', $resList);
							
			if ($agcode) {
				$resData = $resData->where('b.AgCode', $agcode);
			}
			
			if ($num_ag==3) { //only prepaid agencies
				$resData = $resData->where('a.PmtStatus', 'PP');				
			} 
							
			$resData = $resData->orderBy('b.AgCode')
						->orderBy('b.ResNum')
						->orderBy('i.DepDate')
						->get();
		}
		else {
			$resData = DB::table('tblItem as i')
							->select(DB::raw('b.ResNum, b.AgCode, b.DateBooked, b.ClientRef, b.AgentName, b.Source, b.AgEscortedComm, a.AgName, a.ShowCommission, a.PmtStatus, a.Add1, a.Add2, a.City, a.State, a.ZIP, a.Country, i.ItemID, i.DepDate, i.ProdCode, i.ClientRef as ClientRef2, i.Description, i.Qty, i.Price, i.Occupancy, i.PricingType, i.Nights, i.AgCommFlag, i.SupplierRef'))
							->join('tblBooking as b', 'b.ResNum', '=', 'i.ResNum')
							->join('tblAgency as a', 'a.AgCode', '=', 'b.AgCode')
							->whereIn('i.ResNum', $resList);
							
			if ($agcode) {
				$resData = $resData->where('b.AgCode', $agcode);
			}
			
			if ($num_ag==3) { //only prepaid agencies
				$resData = $resData->where('a.PmtStatus', 'PP');				
			} 
					
			$resData = $resData->where([['b.Status', '<>', 'CX']])
								->whereNotIn('i.Status', ['XX', 'CX', 'XR', 'PX']);		
							
			$resData = $resData->orderBy('b.AgCode')
						->orderBy('b.ResNum')
						->orderBy('i.DepDate')
						->get();
		}
		
		//print_r($resData); exit;
		
		$actual_excel_output = '';
		
		if ($date_type=="travel") {
			//echo (date ("m/d/y H:i", time ()) . NL . "Reservations Traveled From:  $BM/$BD/$BY&nbsp;&nbsp To:  $EM/$ED/$EY" . NL);
			$actual_excel_output = (date ("m/d/y H:i", time ()) . "\nReservations Traveled From:  $begin_date&nbsp;&nbsp To:  $end_date \n");
		} else {
			//echo (date ("m/d/y \at H:i", time ()) . NL . "Reservations Booked From:  $BM/$BD/$BY&nbsp;&nbsp To:  $EM/$ED/$EY" . NL);
			$actual_excel_output = (date ("m/d/y \at H:i", time ()) . "\nReservations Booked From:  $begin_date&nbsp;&nbsp To:  $end_date \n");
		}
		$actual_excel_output .= "Agency Code,Agency Name,GoWest Res#,Pax Name,Date Booked,Client Ref#,Date of Svc,Description,Occupancy,# Nights,Nts Indicator,Quantity,Rooms Indicator,@ sign,Unit Cost,= sign,Subtotal,Supplier Conf# \n";
		
		if(count($resData)) {
			$grand_gross_due = 0.0;
			$grand_net_due = 0.0;
			$grand_paid = 0.0;
			$ind = 0;
			
			$row = isset($resData[$ind]) ? $resData[$ind] : false;
			$has_more = true;
			
			while ($has_more && $row) { // Loop 1 - Iterates once per agency
				$bShowThisAgency = 0; // Global boolean flag
				$row->AgCode = (String) $row->AgCode;
				$current_ag = trim(strtoupper($row->AgCode));
				$PmtStatus = $row->PmtStatus;
				$ag_gross_due = 0.0;
				$ag_net_due = 0.0;
				$ag_output = "<BR>\n" . "<B>$row->AgCode - $row->AgName</B>";
				
				while ($has_more && $row && ($current_ag == trim(strtoupper($row->AgCode)))) { // Loop 2 - Iterates once per reservation
					$excel_info="";
					$res_excel_line="$row->AgCode,$row->AgName,";
					$current_res = $row->ResNum;
					$pri = new PriceStatement ($current_res);
					
					// Get Pax for this res:
					$paxs = DB::table('tblPax')
								->where([['ResNum', $current_res], ['Status', '!=', 'CX']])
								->orderBy('PaxID')
								->get();
					$last = (count($paxs) ? $paxs[0]->LastName : "");
					$first = (count($paxs) ? $paxs[0]->FirstName : "");

					$res_excel_line.="$row->ResNum,$first $last,$row->DateBooked,$row->ClientRef,";
					$res_output = "<HR align=left width='10%' noshade>&nbsp;&nbsp; Res: $row->ResNum &nbsp;&nbsp;&nbsp;&nbsp; $first $last &nbsp;&nbsp;&nbsp;&nbsp; Booked:  " . $this->commonRepo->ISO_to_MMDDYYYY ($row->DateBooked) . "  &nbsp; &nbsp; Ref#:$row->ClientRef <TABLE>";			

					$first_item = 1;
					$last_item= '0000-00-00';
					while ($has_more && $row && ($current_res == $row->ResNum)) { // Loop 3 - Iterates once per item
						if ($items == "a" || $first_item) {								
								$p_row = DB::table('tblProduct')
											->where('prodcode', $row->ProdCode)
											->get()->first();
								
								// Output Item info
								$bDisplay_Occ = $p_row ? $p_row->DisplayOccFlag : '';
								$bDisplay_Nights = $p_row ? $p_row->DisplayNightsFlag : '';
								$bIncludePrice = 1;
					
								$excel_line=$res_excel_line . "$row->DepDate,$row->Description,";
								$res_output .= "\t<TR>\n" .
										'\t\t<TD>'. $this->commonRepo->ISO_to_MMDDYYYY($row->DepDate) . "</TD>\n" .
										"\t\t<TD>$row->Description </TD>\n";
					
								// Occupancy:
								if ($bDisplay_Occ) {
									$res_output .= "\t\t<TD align=center>";
									$res_output .= $this->commonRepo->numeral_to_roomtype ($row->Occupancy, 0);
									$res_output .= "</TD>\n";
									$excel_line.=$this->commonRepo->numeral_to_roomtype($row->Occupancy,0) . ",";
								} else {
									$res_output.="<td>&nbsp;</td>";
									$excel_line.=",";
								}
					
								// Nights & Qty:
								if ($bDisplay_Nights) {
									$res_output .= "\t\t\t<TD align=center>";
									$res_output .= $this->commonRepo->clean4html ($row->Nights);
									$res_output .= " Nts</TD><td>$row->Qty Rms</td>\n";
									$excel_line.= "$row->Nights,Nts,$row->Qty,Rms,";
								} else {
									$res_output.="<td>&nbsp;</td>";
									$res_output .= "\t\t<TD align=right>$row->Qty</TD>\n";
									$excel_line.= ",,$row->Qty,,";
								}
								
								if ($row->Price != 0) {
									if ($row->Occupancy < 0) { //family plan #s
										if ($row->Occupancy==-3) $row->Occupancy=2;
										else $row->Occupancy=abs($row->Occupancy);
									}
									if ($row->PricingType == 1) { // Per Person - Hotel Only
										if (isset($netonly) && $netonly==1 && $row->AgCommFlag==1) {
											$res_output .= "<td align=right>@ $" . number_format($row->Price*(100-$pri->ag_comm_level)/100,2) . "</td><td>=</td><TD align=right>\$" . number_format ($row->Nights * $row->Occupancy * $row->Qty * $row->Price * (100-$pri->ag_comm_level)/100, 2) . "</TD>\n";
											$excel_line .= "@," . number_format($row->Price*(100-$pri->ag_comm_level)/100,2,".","") . ",=," . number_format ($row->Nights * $row->Occupancy * $row->Qty * $row->Price * (100-$pri->ag_comm_level)/100, 2, ".", "") . ",";
										} else {
											$res_output .= "<td align=right>@ $$row->Price</td><td>=</td><TD align=right>\$" . number_format ($row->Nights * $row->Occupancy * $row->Qty * $row->Price, 2) . "</TD>\n";
											$excel_line .= "@,$row->Price,=," . number_format ($row->Nights * $row->Occupancy * $row->Qty * $row->Price, 2,".","") . ",";
										}
									} else { // Per Room **OR** Per Person - Inclusive Package
										if (isset($netonly) && $netonly==1 && $row->AgCommFlag==1) {
											$res_output .= "<td align=right>@ $" . number_format($row->Price*(100-$pri->ag_comm_level)/100,2) ."</td><td>=</td><TD align=right>\$" . number_format ($row->Nights * $row->Qty * $row->Price * (100-$pri->ag_comm_level)/100, 2) . "</TD>\n";
											$excel_line.="@," . number_format($row->Price*(100-$pri->ag_comm_level)/100,2,".","") .",=," . number_format ($row->Nights * $row->Qty * $row->Price * (100-$pri->ag_comm_level)/100, 2,".","") . ",";
										} else {
											$res_output .= "<td nowrap align=right>@ $$row->Price</td><td>=</td><TD nowrap align=right>\$" . number_format ($row->Nights * $row->Qty * $row->Price, 2) . "</TD>\n";
											$excel_line .= "@,$row->Price,=," . number_format ($row->Nights * $row->Qty * $row->Price, 2,".","") . ",";
										}
									}
								} else {
									$res_output .= "<TD align=center>&nbsp;</TD>\n";
									$excel_line .= "";
								}
					
								$res_output .= "\t</TR>\n";
								$excel_line .=", $row->SupplierRef";
								$excel_info .= $excel_line . "\n";

								$first_item = 0;
						} // end if
						
						//use date of first item for prepay agency invoices
						if ($last_item == '0000-00-00' or $PmtStatus <> "PP") {
							$last_item = $row->DepDate;
						}
						
						$last_res = $row->ResNum;
						$ind += 1;
						if (isset($resData[$ind])) {
							$row = $resData[$ind];
						}
						else {
							$has_more = false;
						}	
					} // end while Loop 3
					
					if (isset($netonly) && $netonly==1) {
						$res_output .= "<TR><TD width=10></TD><TD colspan=7 align right>Booking Total: \$" . number_format ($pri->gross_total, 2) ."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Amount Paid:  \$" . number_format ($pri->paid, 2) . " &nbsp;&nbsp;&nbsp;&nbsp; Net Bal:  \$" . number_format ($pri->gross_due-$pri->ag_comm_amt, 2) . "</TD></TR></TABLE>";
					} else {
						$res_output .= "<TR><TD width=10></TD><TD colspan=7>Booking Total: \$" . number_format ($pri->gross_total, 2) ."&nbsp;&nbsp;&nbsp;&nbsp; Commission:  \$" . number_format ($pri->ag_comm_amt, 2) . " &nbsp;&nbsp;&nbsp;&nbsp; Amount Paid:  \$" . number_format ($pri->paid, 2) . " &nbsp;&nbsp;&nbsp;&nbsp; Net Bal:  \$" . number_format ($pri->gross_due-$pri->ag_comm_amt, 2) . "</TD></TR></TABLE>";
					}

					if (($date_type=="book") or ($date_type=="travel" and ($last_item<="$end" and $last_item>="$begin"))) {
						switch ($bkgs) {
							case "a":
								$this->include_res($ag_gross_due, $ag_net_due, $grand_paid, $ag_output, $res_output, $pri, $bShowThisAgency);
								$actual_excel_output.=$excel_info . "\n";
								break;
							case "q":
								$this->include_res($ag_gross_due, $ag_net_due, $grand_paid, $ag_output, $res_output, $pri, $bShowThisAgency);
								$actual_excel_output.=$excel_info . "\n";
								break;
							case "c":
								if ($pri->paid == 0) {
									$this->include_res($ag_gross_due, $ag_net_due, $grand_paid, $ag_output, $res_output, $pri, $bShowThisAgency);
									$actual_excel_output.=$excel_info . "\n";
								} // end if
								break;
							case "p":
								if ($pri->paid > 0) {
									if (($pri->paid+.01 < $pri->gross_total - $pri->ag_comm_amt) || (isset($netpaid) && $netpaid==1 && $pri->paid < $pri->gross_total)) {
										$this->include_res($ag_gross_due, $ag_net_due, $grand_paid, $ag_output, $res_output, $pri, $bShowThisAgency);
										$actual_excel_output.=$excel_info . "\n";
									} // end if
								} // end if
								break;
							case "b":
								$netamt=$pri->gross_total - $pri->ag_comm_amt;
								if (($pri->paid+.01 < $netamt) or ($netpaid==1 and ($pri->paid < $pri->gross_total))) {
									$this->include_res($ag_gross_due, $ag_net_due, $grand_paid, $ag_output, $res_output, $pri, $bShowThisAgency);
									$actual_excel_output.=$excel_info . "\n";
								} // end if
						} // end switch
					} // end if last_row

				} // end while Loop 2
				
				// Show Ag total
				$ag_output .= "<HR align=left width='30%'></B>Total Net Bal:  <B>\$" . number_format ($ag_net_due, 2) . "</B><HR align=left>\n";
				if ($bShowThisAgency) {
					//echo ($ag_output); 
					$grand_gross_due += $ag_gross_due;
					$grand_net_due += $ag_net_due;
				} // end if
				
			} // end while Loop 1
			
		} // end if count($resData)
		
		//echo $actual_excel_output; exit;
		
		$fileName = 'prebilling_'.date('YmdHis').'.csv';
		$file = env('APP_URL')."/file/$fileName";
		
		@chdir(env('TMPDIR'));
		$fp = fopen($fileName, 'w');
		chmod($fileName, 0777); 
		file_put_contents($fileName, $actual_excel_output);
		fclose($fp);
		
		//remove old csv files
		if ($handle = opendir('.')) {
			while (false !== ($entry = readdir($handle))) {
				if ($entry != "." && $entry != "..") {
					if (substr($entry, 0, 11) == 'prebilling_' && substr($entry, -4) == '.csv') {
						$entry_arr = explode('_', $entry);
						$entry_date = substr($entry_arr[1], 0, 8);
						if ($entry_date < date('Ymd')) {							
							unlink($entry);
						}
					}
				}
			}
			closedir($handle);
		}
		
		$result['status'] = 'success';
		$result['file'] = $file;
		
		return $result;
	}	
	
	public function include_res (&$ag_gross_due, &$ag_net_due, &$grand_paid, &$ag_output, &$res_output, &$pri, &$bShowThisAgency) {
		$ag_gross_due += round($pri->gross_due,2);
		$ag_net_due += round($pri->net_due,2);
		$grand_paid += round($pri->paid,2);
		$ag_output .= round($res_output,2);
		$bShowThisAgency = 1;
	}
}