<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use App\PriceStatement;
use App\Repositories\TBCommonRepo;
use App\Repositories\PaymRepo;
use stdClass;

use App\Invoice;

class GenerateBillingRpt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GenerateBillingRpt {qid} {invoicenumber?}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Billing Report';
	
	public $commonRepo, $paymRepo;
	
	public function __construct() {
        parent::__construct();
		
		$this->commonRepo = new TBCommonRepo();
		$this->paymRepo = new PaymRepo();
    }
	
	public function handle() {
		$qid = $this->argument('qid');

		if ($qid == 'all') {
			$qids = DB::table('tblRptBillQueue')
						->where([['version', '2']])
						->where(function ($query) {
							$query->where('status', '=', 'Pending...')
								  ->orWhereNull('status');
						})
						->orderBy('qid')
						->get();
						
			//To prevent running again
			foreach($qids as $qid) {
				DB::table('tblRptBillQueue')
				->where('qid', $qid->qid)
				->update(['status' => 'Started...']);
			}
						
			foreach($qids as $qid) {
				$this->generateRpt($qid->qid);
			}
		}
		elseif ($qid == 'reprint') {
			$invoicenumber = $this->argument('invoicenumber');
			$this->generateRpt('reprint', $invoicenumber);
		}
		else
			$this->generateRpt($qid);
	}

	/**
     * Execute the console command.
     *
     * @return mixed
     */
    public function generateRpt($qid='', $invoicenumber=0) {
		ini_set('max_execution_time', 600);
		ini_set('memory_limit', '1024M');
		
		if (!$qid)
			$qid = $this->argument('qid');
		
		$html_header = '<HTML><HEAD>
							<LINK rel=stylesheet href="tourbot_frontend.css" type="text/css">
							<STYLE type="text/css">.aging {text-align: center;}
							</STYLE></HEAD><BODY bgcolor="white">';

		$html_footer = '</BODY></HTML>';

		if($qid=="reprint") {
			// Assign random values to non relevant variables to avoid error messages
			$BM="01";
			$BD="01";
			$BY="2001";
			$EM="01";
			$ED="01";
			$EY="2002";
	
			$rpt_type = 'reprint';
			$bkgs = '';
			$num_ag = '';
			$dept = '';

			// Show all the items
			$items="a";

			$i_result = Invoice::select(DB::raw('StartDate, EndDate, DateType'))
								->where('Number', $invoicenumber)
								->get();

			foreach ($i_result as $i_row) {
				$begin = $i_row->StartDate;
				$end = $i_row->EndDate;
				list($BY, $BM, $BD) = explode("-", $i_row->StartDate);
				list($EY, $EM, $ED) = explode("-", $i_row->EndDate);
				$date_type = $i_row->DateType;
			}
		}
		else {		
			DB::table('tblRptBillQueue')
				->where('qid', $qid)
				->update(['status' => 'Started...']);
				
			$queue = DB::table('tblRptBillQueue')
					->where('qid', $qid)
					->get()->first();
					
			if (!$queue) {
				return false;
			}
			
			$tblArgs = explode(' ', $queue->args);
			
			foreach ($tblArgs as $tblArg) {
				$data = explode('=', $tblArg);
				${$data[0]} = urldecode($data[1]);
			}
			
			$begin = $queue->begin_date;
			$end = $queue->end_date;
		}		
		
		if (isset($netpaid) && $netpaid == 'TRUE') {
			$netpaid=1;
		} 
		else {
			$netpaid=0;
		}
		
		$agcode = '';
		
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
						DB::table('tblRptBillQueue')
							->where('qid', $qid)
							->update(['status' => 'No Results']);
							
						return false;
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
						->whereNotIn('i.Status', ['CX', 'NW', 'XR', 'PX']);
						
				if ($dept == 'ii') {
					$res = $res->whereIn('department', [2, 12]);
				}
				elseif ($dept != 'kk') {
					$res = $res->where('department', $dept);
				}
				
				if ($prevposted != 1) 
					$res = $res->where('InvPostedFlag', '<>', '1');
				
				if ($agcode) {
					$res = $res->where('b.AgCode', $agcode);
				}
				
				if ($num_ag==3) { //only prepaid agencies
					if ($autobilling == '1') {
						$res = $res->where([['a.PmtStatus', 'PP'], ['a.Bill_Flag', '1']]);
					}
					else {
						$res = $res->where('a.PmtStatus', 'PP');
					}
				} 
				else {
					if ($excludeOnHold == 1)
						$res = $res->where('a.PmtStatus', '<>', 'HL');
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
							
				if ($dept == 'ii') {
					$res = $res->whereIn('department', [2, 12]);
				}
				elseif ($dept != 'kk') {
					$res = $res->where('department', $dept);
				}
				
				if (!isset($prevposted) || $prevposted != 1) 
					$res = $res->where('InvPostedFlag', '<>', '1');
				
				if ($agcode) {
					$res = $res->where('b.AgCode', $agcode);
				}
				
				if ($num_ag==3) { //only prepaid agencies
					if ($autobilling == '1') {
						$res = $res->where([['a.PmtStatus', 'PP'], ['a.Bill_Flag', '1']]);
					}
					else {
						$res = $res->where('a.PmtStatus', 'PP');
					}
				} 
				else {
					if ($excludeOnHold == 1)
						$res = $res->where('a.PmtStatus', '<>', 'HL');
				}
						
				if (!isset($includecanceled) || !$includecanceled) {
					$res = $res->where([['b.Status', '<>', 'CX']])
								->whereNotIn('i.Status', ['XX', 'NW', 'CX', 'XR', 'PX']);						
				}
				else {
					$res = $res->where([['b.Status', '<>', 'QU'], ['i.Status', '<>', 'NW']]);					
				}
						
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
						->where([['b.datebooked', '>=', "$BY-$BM-$BD"], ['b.datebooked', '<=', "$EY-$EM-$ED"], ['b.status', 'QU']]);
						
				if ($dept == 'ii') {
					$res = $res->whereIn('department', [2, 12]);
				}
				elseif ($dept != 'kk') {
					$res = $res->where('department', $dept);
				}
				
				if ($prevposted != 1) 
					$res = $res->where('InvPostedFlag', '<>', '1');
				
				if ($agcode) {
					$res = $res->where('b.AgCode', $agcode);
				}
				
				if ($num_ag==3) { //only prepaid agencies
					if ($autobilling == '1') {
						$res = $res->where([['a.PmtStatus', 'PP'], ['a.Bill_Flag', '1']]);
					}
					else {
						$res = $res->where('a.PmtStatus', 'PP');
					}
				} 
				else {
					if ($excludeOnHold == 1)
						$res = $res->where('a.PmtStatus', '<>', 'HL');
				}
						
				$res = $res->orderBy('b.agcode')
							->orderBy('resnum')
							->get();	

				//echo count($res); exit;
			}
			else { //others
				$res = DB::table('tblBooking as b')
						->join('tblAgency as a', 'a.agcode', '=', 'b.agcode')
						->where([['b.datebooked', '>=', "$BY-$BM-$BD"], ['b.datebooked', '<=', "$EY-$EM-$ED"], ['b.status', '<>', 'QU']]);
				
				if (!isset($includecanceled) || !$includecanceled) {
					$res = $res->whereNotIn('b.status', ['CX', 'QU']);						
				}
						
				if ($dept == 'ii') {
					$res = $res->whereIn('department', [2, 12]);
				}
				elseif ($dept != 'kk') {
					$res = $res->where('department', $dept);
				}
				
				if ($prevposted != 1) 
					$res = $res->where('InvPostedFlag', '<>', '1');
				
				if ($agcode) {
					$res = $res->where('b.AgCode', $agcode);
				}
				
				if ($num_ag==3) { //only prepaid agencies
					if ($autobilling == '1') {
						$res = $res->where([['a.PmtStatus', 'PP'], ['a.Bill_Flag', '1']]);
					}
					else {
						$res = $res->where('a.PmtStatus', 'PP');
					}
				} 
				else {
					if ($excludeOnHold == 1)
						$res = $res->where('a.PmtStatus', '<>', 'HL');
				}
						
				$res = $res->orderBy('b.agcode')
							->orderBy('resnum')
							->get();
			}
		}
		
		$resList = array();		
		
		//print_r($res); exit;
		
		foreach ($res as $r) {		
			$pri = new PriceStatement($r->ResNum);
			
			switch ($bkgs) {
				case "a":
				case "q":
					$resList[] = $r->ResNum;
					break;
				case "c":
					if ($pri->paid == 0) {
						$resList[] = $r->ResNum;
					} // end if
					break;
				case "p":
					if ($pri->paid > 0) {
						if (($pri->paid+.01 < $pri->gross_total - $pri->ag_comm_amt) || ($netpaid==1 && $pri->paid < $pri->gross_total)) {
							$resList[] = $r->ResNum;
						} // end if
					} // end if
					break;
				case "b":
					$netamt=$pri->gross_total - $pri->ag_comm_amt;
					if (($pri->paid+.01 < $netamt) or ($netpaid==1 and ($pri->paid < $pri->gross_total))) {
						$resList[] = $r->ResNum;
					} // end if
			} // end switch
		}
		
		if ($rpt_type=="reprint") {
			$resData = DB::table('tblItem as i')
							->select(DB::raw('b.ResNum, b.AgCode, b.DateBooked, b.ClientRef, b.AgentName, b.Source, b.AgEscortedComm, a.AgName, a.ShowCommission, a.PmtStatus, a.Add1, a.Add2, a.City, a.State, a.ZIP, a.Country, i.ItemID, i.DepDate, i.ProdCode, i.ClientRef as ClientRef2, i.Description, i.Qty, i.Price, i.Occupancy, i.PricingType, i.Nights, i.AgCommFlag'))
							->join('tblBooking as b', 'b.ResNum', '=', 'i.ResNum')
							->join('tblAgency as a', 'a.AgCode', '=', 'b.AgCode')
							->where([['b.Status', '<>', 'CX'], ['b.invnum', $invoicenumber]])
							->whereNotIn('i.Status', ['XX', 'CX', 'XR', 'PX', 'NW'])
							->orderBy('b.AgCode')
							->orderBy('b.ResNum')
							->orderBy('i.DepDate')
							->get();
		}
		elseif ($bkgs=="q") {			
			$resData = DB::table('tblItem as i')
							->select(DB::raw('b.ResNum, b.AgCode, b.DateBooked, b.ClientRef, b.AgentName, b.Source, b.AgEscortedComm, a.AgName, a.ShowCommission, a.PmtStatus, a.Add1, a.Add2, a.City, a.State, a.ZIP, a.Country, i.ItemID, i.DepDate, i.ProdCode, i.ClientRef as ClientRef2, i.Description, i.Qty, i.Price, i.Occupancy, i.PricingType, i.Nights, i.AgCommFlag'))
							->join('tblBooking as b', 'b.ResNum', '=', 'i.ResNum')
							->join('tblAgency as a', 'a.AgCode', '=', 'b.AgCode')
							->where([['b.Status', 'QU']])
							->whereNotIn('i.Status', ['CX', 'NW', 'XR', 'PX'])
							->whereIn('i.ResNum', $resList);
							
			if ($agcode) {
				$resData = $resData->where('b.AgCode', $agcode);
			}
			
			if ($num_ag==3) { //only prepaid agencies
				if ($autobilling == '1') {
					$resData = $resData->where([['a.PmtStatus', 'PP'], ['a.Bill_Flag', '1']]);
				}
				else {
					$resData = $resData->where('a.PmtStatus', 'PP');
				}
			} 
			else {
				if ($excludeOnHold == 1)
					$resData = $resData->where('a.PmtStatus', '<>', 'HL');
			}
							
			$resData = $resData->orderBy('b.AgCode')
						->orderBy('b.ResNum')
						->orderBy('i.DepDate')
						->get();
		}
		else {
			$resData = DB::table('tblItem as i')
							->select(DB::raw('b.ResNum, b.AgCode, b.DateBooked, b.ClientRef, b.AgentName, b.Source, b.AgEscortedComm, a.AgName, a.ShowCommission, a.PmtStatus, a.Add1, a.Add2, a.City, a.State, a.ZIP, a.Country, i.ItemID, i.DepDate, i.ProdCode, i.ClientRef as ClientRef2, i.Description, i.Qty, i.Price, i.Occupancy, i.PricingType, i.Nights, i.AgCommFlag'))
							->join('tblBooking as b', 'b.ResNum', '=', 'i.ResNum')
							->join('tblAgency as a', 'a.AgCode', '=', 'b.AgCode')
							->whereIn('i.ResNum', $resList);
							
			if ($agcode) {
				$resData = $resData->where('b.AgCode', $agcode);
			}
			
			if ($num_ag==3) { //only prepaid agencies
				if ($autobilling == '1') {
					$resData = $resData->where([['a.PmtStatus', 'PP'], ['a.Bill_Flag', '1']]);
				}
				else {
					$resData = $resData->where('a.PmtStatus', 'PP');
				}
			} 
			else {
				if ($excludeOnHold == 1)
					$resData = $resData->where('a.PmtStatus', '<>', 'HL');
			}
					
			if (!isset($includecanceled) || !$includecanceled) {
				$resData = $resData->where([['b.Status', '<>', 'CX']])
									->whereNotIn('i.Status', ['XX', 'NW', 'CX', 'XR', 'PX']);						
			}
			else {
				$resData = $resData->where([['b.Status', '<>', 'QU'], ['i.Status', '<>', 'NW']]);					
			}
							
			$resData = $resData->orderBy('b.AgCode')
						->orderBy('b.ResNum')
						->orderBy('i.DepDate')
						->get();
		}
		
		//print_r($resData); exit;
		
		$agBuffer = '';
		$footer_total = '';
		$pdf_suffix = '';
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
				$ag_gross_total= 0.0;
				$ag_gross_due = 0.0;
				$ag_net_due = 0.0;
				$ag_comm_amt=0.0;
				$ag_amt_paid=0.0;
				$res_array = array();
				$pdf_suffix = '';
				$mess = '';
				if ($rpt_type=="posting") {
					$bquery="SELECT message FROM tblAutoReports WHERE report='billing' LIMIT 1";
					$bresult = DB::select($bquery);
					if (count($bresult)==1) {
						$com_msg=$bresult[0];
					} else {
						$com_msg = new stdClass();
						$com_msg->message="";
					}
					$mess=$com_msg->message;
				} elseif ($rpt_type=="pre-billing") {
					$bquery="SELECT message FROM tblAutoReports WHERE report='pre-billing' LIMIT 1";
					$bresult = DB::select($bquery);
					if (count($bresult)==1) {
						$com_msg=$bresult[0];
					} else {
						$com_msg = new stdClass();
						$com_msg->message="";
					}
					$mess=$com_msg->message;
				}				

				if ($rpt_type == "pre-billing") {
					$ag_output = "<br><br>" .  $this->commonRepo->cf_header_string() . "<B>Reservation Report</B>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Printed:&nbsp;";
					$inv_out="";
				} elseif($rpt_type=="posting") { // Report type: POSTING
					$ag_output = "<br><br>" . $this->commonRepo->cf_header_string() . "<center><H1>INVOICE</h1></center><br>Printed:&nbsp;";
					$curdate=$this->commonRepo->todays_date();
					
					$invsql = DB::table('tblInvoice')
								->insert(
									['date' => $curdate, 'status' => 'OPEN']
								);
								
					$invnum = DB::getPdo()->lastInsertId();
					$inv_out="<br><b>Invoice Number: $invnum</b>";
				} elseif($rpt_type=="reprint") {
					$ag_output = $this->commonRepo->cf_header_string() . "<br><br>" . "<center><H1>INVOICE</h1></center><br>Printed:&nbsp;";
					$curdate=$this->commonRepo->todays_date();
					$inv_out="<br><b>Invoice Number: $invoicenumber</b>";
				}			
				
				if ($date_type=="travel") {
					$ag_output.= (date ("m/d/y H:i", time ()) . "<BR>\n" . "Reservations Traveled From:  $BM/$BD/$BY&nbsp;&nbsp; To:  $EM/$ED/$EY" . "<BR>\n");
				} else {
					if(strlen($date_type) > 1 and strlen($BY) > 1 and strlen($EY) > 1){
						$ag_output.= (date ("m/d/y H:i", time ()) . "<BR>\n" . "Reservations Booked From:  $BM/$BD/$BY&nbsp;&nbsp; To:  $EM/$ED/$EY" . "<BR>\n");	
						//echo "Reservations Booked From:  $BM/$BD/$BY&nbsp;&nbsp; To:  $EM/$ED/$EY";
					}
				}
				if ($dept=="kk") {
					$ag_output .= "ALL Departments<br>";
				} elseif ($dept=="ii") { 
					$ag_output .= "Elite + FIT<br>";
				} else {
					$mmrow = DB::table('tblDepartments')
								->where('departmentid', $dept)
								->get()->first();
					if ($mmrow)
						$ag_output .= "$mmrow->Department Department<br>";
				}
				
				// HTML filename for PDF generation
				if (isset($invoicenumber)) // When the invoice is updated
					$invnum = $invoicenumber;
					
				$html_file  = trim(strtoupper($current_ag));
				$invnum = isset($invnum) ? trim($invnum) : '';
				$html_file .= $invnum;
				
				
				$ag_output.= $inv_out;
				
				//create header info for agency
				$country = DB::table('tblCountry')
							->where('CountryID', $row->Country)
							->get()->first();
							
				#TA(20131226) for different billing address if the flag is checked on				
				$billAddrRow = DB::table('tblAgency')
								->where('AgCode', $row->AgCode)
								->get()->first();
								
				//print_r($billAddrRow); exit; 
								
				if($billAddrRow->BillingAddrFlag==1) {
					$billingAgName = trim($billAddrRow->BillingAgName);
					if(strlen($billingAgName)<1) $billingAgName = $row->AgName;
					$ag_output .= "<br><B>$billingAgName</B><br>";
					
					$bcountry = DB::table('tblCountry')
									->where('CountryID', $billAddrRow->BillingCountry)
									->get()->first();
									
					if (strlen($billAddrRow->BillingAdd1) > 0) $ag_output .= $billAddrRow->BillingAdd1 . "<BR>\n";
					if (strlen($billAddrRow->BillingAdd2) > 0) $ag_output .= $billAddrRow->BillingAdd2 . "<BR>\n";
					if (strlen($billAddrRow->BillingCity) > 0) $ag_output .= "$billAddrRow->BillingCity $billAddrRow->BillingState" . "<BR>\n";
					$ag_output .= "$billAddrRow->BillingZIP  $bcountry->country" . "<BR>\n";
					if($billAddrRow->BillingTaxPayerNumber) $ag_output .= "Tax Payer Number: $billAddrRow->BillingTaxPayerNumber<BR><br>";
				} else {
					$ag_output .= "<br><B>$row->AgName</B><br>";
					if (strlen($row->Add1) > 0) $ag_output .= $row->Add1 . "<BR>\n";
					if (strlen($row->Add2) > 0) $ag_output .= $row->Add2 . "<BR>\n";
					if (strlen($row->City) > 0) $ag_output .= "$row->City $row->State" . "<BR>\n";
					$ag_output .= "$row->ZIP  $country->country" . "<BR>\n";
					if($billAddrRow->TaxPayerNumber) $ag_output .= "Tax Payer Number: $billAddrRow->TaxPayerNumber<BR><br>";
				}
				
				//print_r($row);
				$ag_name_stored=$row->AgName;
				$ag_code_stored=$row->AgCode;
				
				while ($has_more && $row && ($current_ag == trim(strtoupper($row->AgCode)))) { // Loop 2 - Iterates once per reservation
					$current_res = $row->ResNum;
					$pri = new PriceStatement ($current_res);

					// Get Pax for this res:
					$paxs = DB::table('tblPax')
								->where([['ResNum', $current_res], ['Status', '!=', 'CX']])
								->orderBy('PaxID')
								->get();
					$last = (count($paxs) ? $paxs[0]->LastName : "");
					$first = (count($paxs) ? $paxs[0]->FirstName : "");

					$res_output = "<HR align=left width='10%' noshade>&nbsp;&nbsp; ". env('CO_SUFIX') ."-Res: $row->ResNum &nbsp;&nbsp;&nbsp;&nbsp; $first $last &nbsp;&nbsp;&nbsp;&nbsp; Booked:  " . $this->commonRepo->ISO_to_MMDDYYYY ($row->DateBooked) . "  &nbsp; &nbsp; Agent: $row->AgentName &nbsp; &nbsp; Ref#:$row->ClientRef &nbsp; Ref Item:$row->ClientRef2";					
					
					if (env('CO_NAME')=="Vagabond Tours" and $row->AgCode=="QUOIZEL") $res_output .= "&nbsp; &nbsp; Purpose: $row->Source";
					if (isset($display_names) && $display_names=="YES") {
						$i=1;
						$pax_out="<table width=100%><tr><td width=10></td><td><table>";
						if (count($paxs) > 1) {
							foreach ($paxs as $pax) {							
								$last = ($pax ? $pax->LastName : "");
								$first = ($pax ? $pax->FirstName : "");
								$pax_out.="<tr><td>$i.</td><td>$first</td><td>$last</td></tr>";
								$i+=1;
							}
						}
						$pax_out.= "</table></td></tr></table>";
						
						if ($i > 1) {
							$res_output.=$pax_out;
						}
					}					
							
					$res_output .= "<TABLE>";
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
					
								$res_output .= "<TR>" .
										"<TD nowrap>".$row->ClientRef2 .' </TD> <TD nowrap>'. $this->commonRepo->ISO_to_MMDDYYYY($row->DepDate) . "</TD>" .
										"<TD nowrap>$row->Description </TD>";
					
								// Occupancy:
								if ($bDisplay_Occ) {
									$res_output .= "<TD nowrap align=center>";
									$res_output .= $this->commonRepo->numeral_to_roomtype ($row->Occupancy, 0);
									$res_output .= "</TD>";
								} else {
									$res_output.="<td>&nbsp;</td>";
								}
					
								// Nights & Qty:
								if ($bDisplay_Nights) {
									$res_output .= "<TD nowrap align=center>";
									$res_output .= $this->commonRepo->clean4html ($row->Nights);
									$res_output .= " Nts</TD><td nowrap>$row->Qty Rms</td>";
								} else {
									$res_output.="<td>&nbsp;</td>";
									$res_output .= "<TD nowrap align=right>$row->Qty</TD>";
								}
					
								if($row->ShowCommission==0 && $row->AgCommFlag==1) {
									#TA(20150210) apply escorted commission level on escorted items
									if($row->AgEscortedComm>0 && substr($row->ProdCode,3,1)=='E') {
										$price_without_comm = $row->Price - (($row->Price * $row->AgEscortedComm)/100);
									} else {
										$price_without_comm = $row->Price - (($row->Price * $pri->ag_comm_level)/100);
									}
									$row->Price = $price_without_comm;
								}

								if ($row->Price != 0) {
									if ($row->Occupancy < 0) { //family plan #s
										if ($row->Occupancy==-3) $row->Occupancy=2;
										else $row->Occupancy=abs($row->Occupancy);
									}
									if ($row->PricingType == 1) { // Per Person - Hotel Only
										if (isset($netonly) && $netonly==1 and $row->AgCommFlag==1) {
											$res_output .= "<td nowrap align=right>@ $" . number_format($row->Price*(100-$pri->ag_comm_level)/100,2) . "</td><td>=</td><TD nowrap align=right>\$" . number_format ($row->Nights * $row->Occupancy * $row->Qty * $row->Price * (100-$pri->ag_comm_level)/100, 2) . "</TD>";
										} else {
											$res_output .= "<td nowrap align=right>@ $$row->Price</td><td>=</td><TD nowrap align=right>\$" . number_format ($row->Nights * $row->Occupancy * $row->Qty * $row->Price, 2) . "</TD>";
										}
									} else { // Per Room **OR** Per Person - Inclusive Package
										if (isset($netonly) && $netonly==1 && $row->AgCommFlag==1) {
											$res_output .= "<td nowrap align=right>@ $" . number_format($row->Price*(100-$pri->ag_comm_level)/100,2) ."</td><td>=</td><TD nowrap align=right>\$" . number_format ($row->Nights * $row->Qty * $row->Price * (100-$pri->ag_comm_level)/100, 2) . "</TD>";
										} else {
											$res_output .= "<td nowrap align=right>@ $$row->Price</td><td>=</td><TD nowrap align=right>\$" . number_format ($row->Nights * $row->Qty * $row->Price, 2) . "</TD>";
										}
									}
								} else {
									$res_output .= "<TD align=center>&nbsp;</TD>";
								}
					
								$res_output .= "</TR>";


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
										
					if (env('CO_NAME')=="Vagabond Tours" && $row && $row->AgCode=="QUOIZEL") {
						//Specialized entry
						$res_output .= "<TR><td width=10></td><td colspan=7>";
						
						//Show payment history		
						$acc_rows = DB::table('tblAcctRec as a')
										->select(DB::raw('a.status, c.FOPCode, c.Description, a.DateRcd, b.FirstName, b.LastName,a.FOP,a.Amount,a.Description,b.status,a.CCNum'))
										->join('tblPax as b', 'b.PaxID', '=', 'a.PaxID')
										->join('tblFOP as c', 'c.FOPCode', '=', 'a.FOP')										
										->where([['a.resnum', $last_res]])
										->get();
						$totrec=0;
						$pmt_out="<u>Payments Received for Reservation #$last_res:</u><BR>";
						$pmt_out.= "<TABLE border=0 cellPadding=0 cellSpacing=0>";
						$pmts_rcvd="NO";
						foreach ($acc_rows as $acc_row) {
							$acc_row = get_object_vars($acc_row);
							$pmts_rcvd="YES";
							if ($acc_row["FOP"]=="WB") {$pmstatus="Pending";} 
							elseif ($acc_row["FOP"]=="WD") {$pmstatus="Declined";}
							elseif ($acc_row['status']=="PST") {$pmstatus="Posted";}
							else {$pmstatus="Processing";}

							$ccnum=$this->paymRepo->cc_decrypt($acc_row["CCNum"]);
							if (strlen($ccnum > 0)) {
								$cc="xxxxxxxxxxxx".substr($ccnum,strlen($ccnum)-4);
							} else {
								$cc="";
							}

							if ($acc_row["Status"]=="CX" and $pmstatus<>"Declined") {
								$pmt_out.='<TR><TD>'.$this->commonRepo->date_to_amerdt($acc_row["DateRcd"]).' </TD><TD> - '.$pmstatus.' - </TD><TD>'.$acc_row["FirstName"]." ".$acc_row["LastName"].' </TD><TD> - '.$acc_row["FOP"].' '.$cc.' - </TD><TD> $'.$acc_row["Amount"].' </TD><TD> '.$acc_row[8]."</TD></TR>";
								$totrec+=$acc_row["Amount"];
							} elseif ($pmstatus=="Declined") {
								$pmt_out.= '<TR><TD>'.$this->commonRepo->date_to_amerdt($acc_row["DateRcd"]).' </TD><TD> - '.$pmstatus.' - </TD><TD>'.$acc_row["FirstName"]." ".$acc_row["LastName"].' </TD><TD> - '.$acc_row["FOP"].' '.$cc.' - </TD><TD> $'.$acc_row["Amount"].' </TD><TD>'.$acc_row[8]."</TD></TR>";
								//Don't inlclude in total - $totrec+=$acc_row["Amount"];
							} else {
								$pmt_out.= '<TR><TD>'.$this->commonRepo->date_to_amerdt($acc_row["DateRcd"]).' </TD><TD> - '.$pmstatus.' - </TD><TD> '.$acc_row["FirstName"]." ".$acc_row["LastName"].' </TD><TD> - '.$acc_row["FOP"].' '.$cc.' - </TD><TD> $'.$acc_row["Amount"].' </TD><TD> '.$acc_row[8]."</TD></TR>";
								$totrec+=$acc_row["Amount"];
							}
						}
						$pmt_out.= "</TABLE>";
						if ($pmts_rcvd=="NO") $pmt_out .= "None";

						$res_output.=$pmt_out."</td></tr>";
						$res_output .= "<TR><TD width=10></TD><TD nowrap colspan=7 align right><b>Booking Total: \$" . number_format ($pri->gross_total, 2) ."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Amount Paid:  \$" . number_format ($pri->paid, 2) . " &nbsp;&nbsp;&nbsp;&nbsp; Balance:  \$" . number_format ($pri->gross_due-$pri->ag_comm_amt, 2) . "</TD></TR></table>";

					} elseif($row && $row->ShowCommission==0) {					
						$res_output .= "<TR><TD width=10></TD><TD nowrap colspan=7>Booking Total: \$" . number_format ($pri->net_due, 2) ."&nbsp;&nbsp;&nbsp;&nbsp; Commission: \$0&nbsp;&nbsp;&nbsp;&nbsp; Amount Paid: \$" . number_format ($pri->paid, 2) . " &nbsp;&nbsp;&nbsp;&nbsp; Net Bal: \$" . number_format ($pri->gross_due-$pri->ag_comm_amt, 2) . "</TD></TR></TABLE>";
					} elseif (isset($netonly) && $netonly==1) {
						$res_output .= "<TR><TD width=10></TD><TD nowrap colspan=7 align right>Booking Total: \$" . number_format ($pri->gross_total, 2) ."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Amount Paid:  \$" . number_format ($pri->paid, 2) . " &nbsp;&nbsp;&nbsp;&nbsp; Net Bal:  \$" . number_format ($pri->gross_due-$pri->ag_comm_amt, 2) . "</TD></TR></TABLE>";
					} else {
						$res_output .= "<TR><TD width=10></TD><TD nowrap colspan=7>Booking Total: \$" . number_format ($pri->gross_total, 2) ."&nbsp;&nbsp;&nbsp;&nbsp; Commission:  \$" . number_format ($pri->ag_comm_amt, 2) . " &nbsp;&nbsp;&nbsp;&nbsp; Amount Paid:  \$" . number_format ($pri->paid, 2) . " &nbsp;&nbsp;&nbsp;&nbsp; Net Bal:  \$" . number_format ($pri->gross_due-$pri->ag_comm_amt, 2) . "</TD></TR></TABLE>";
					}
					
					if (($date_type=="book") || ($date_type=="travel" && ($last_item<="$EY-$EM-$ED" && $last_item>="$BY-$BM-$BD")) || $rpt_type=="reprint") {
							$ag_gross_total += round($pri->gross_total,2);
							$ag_gross_due += round($pri->gross_due,2);
							$ag_net_due += round($pri->net_due,2);
							$ag_comm_amt += round($pri->ag_comm_amt,2);
							$ag_amt_paid += round($pri->paid,2);
							$grand_paid += round($pri->paid,2);
							$ag_output .= $res_output;

							if ($rpt_type=="posting") {
								//disable temporarily for testing								
								DB::table('tblBooking')
									->where([['resnum', $current_res]])
									->update(['InvPostedFlag' => '1', 'InvNum' => $invnum]);
									
								$b_rows = DB::table('tblItem')
										->where(function ($query) use ($current_res) {
											$query->where('status', 'OK')
												  ->orWhere('status', 'BK')
												  ->orWhere('status', 'CF');
										})
										->where('resnum', $current_res)
										->get();
										
								//print_r($b_rows); exit;
									
								foreach ($b_rows as $b_row) {
									$this->commonRepo->calc_payable($b_row->ItemID);
								}
							}
							
							$bShowThisAgency = 1;						
							
							array_push($res_array,$current_res);
							//$res_array[$current_ag][] = $current_res;							
							
							//If this is for Quoizel/Vagabond keep a summary of the Source/Travel Reason Codes
							if (env('CO_NAME')=="Vagabond Tours" and $ag_code_stored=="QUOIZEL") {								
								DB::table('tblQuoizel')
									->insert([
										'source' => $row->Source, 'qty' => 1, 'amt' => $pri->gross_total
									]);
							}

					} // end if last_row
				} // end while Loop 2

				// Show Ag total
				$ag_output .= "<HR align=left width='30%'><B>Invoice Amount (net): $" . number_format($ag_gross_total-$ag_comm_amt,2) . "&nbsp; &nbsp; &nbsp; &nbsp; Payments Received: $". number_format ($ag_amt_paid,2) . "&nbsp; &nbsp; &nbsp; &nbsp; Balance Due:  \$" . number_format ($ag_net_due, 2) . "</B><br>";
				
				//Generate Subtotals for Source Code Reasons
				if (env('CO_NAME')=="Vagabond Tours" and $ag_code_stored=="QUOIZEL") {
					$srows = DB::table('tblQuoizel')
						->select(DB::raw('source,sum(qty) as SumQty, sum(amt) as SumAmt'))
						->groupBy('source')
						->get();
					//Generate Output
					$stat_output="<table cellspacing=5 cellpadding=5><tr><td colspan=3 align=center><font color=green><b><u>REASON FOR TRAVEL SUMMARY</u></b></td></tr>";
					$stat_output.="<tr><td><b>Reason</b></td><td><b># of Reservations</b></td><td><b>Amount Spent</b></td></tr>";
					foreach ($srows as $srow) {
						if (strlen($srow->source)<1) $srow->source="NONE ASSIGNED";
						$amt=number_format($srow->SumAmt,2);
						$stat_output.="<tr><td>$srow->source</td><td align=center>$srow->SumQty</td><td align=right>$$srow->SumAmt</tr>";
					}
					$ag_output .= "<hr>" . $stat_output ."</table>";
				} //Quoizel
						
				if ($bShowThisAgency && $rpt_type == 'posting') {
						// Update invoice table
						$curdate = $this->commonRepo->todays_date();
						$round_InvAmt = round ($ag_gross_due, 2);
						$round_CommAmt = round ($ag_comm_amt, 2);
						$round_AmtPaid = round ($ag_amt_paid, 2);
						$round_AgNetDue = round ($ag_net_due, 2);
						
						//disable temporarily for testing
						DB::table('tblInvoice')
							->where('Number', $invnum)
							->update(['Date' => $curdate, 'Agency' => $current_ag, 'Status' => 'OPEN', 'InvAmt' => $round_InvAmt, 'CommAmt' => $round_CommAmt, 'AmtPaid' => $round_AmtPaid, 'AmtDue' => $round_AgNetDue, 'StartDAte' => "$BY-$BM-$BD", 'EndDate' => "$EY-$EM-$ED", 'DateType' => $date_type]);
				} // end if

				if (env('ACCOUNTING_PACKAGE_RECEIVABLES')=="YES" && env('ACCOUNTING_INV_AGING')=="YES") {
					$ag_output .= "<hr><hr>";
					$ag_output .= $this->commonRepo->aging($current_ag,"INVOICE");
				}

				if($PmtStatus=="PP") {
					$ag_output .= "<hr><hr><br><br>" . env('PREPAY_INV_MESSAGE') . "<br>$ag_name_stored<br><hr align=left>";
				} else {
					$ag_output .= "<hr><hr><br><br>" . env('INV_MESSAGE') . "<br>$ag_name_stored<br><hr align=left>";
				}
				
				if ($rpt_type=="posting" || isset($invoicenumber)) {
					echo getcwd() . "\n";
				
					// Generate HTML file
					@chdir(env('TMPDIR'));
					$fp = fopen($html_file.'.htm', 'w');
					chmod($html_file.'.htm', 0777); 
					file_put_contents($html_file.'.htm', $html_header.$ag_output.$footer_total.$html_footer);
					fclose($fp);
					
					echo getcwd() . "\n";
					
					// Append the HTML files to be merged
					foreach ($res_array as $cur_res) {
						$this->commonRepo->agencyConfirmation($cur_res,$html_file);
						$pdf_suffix .= " ".$html_file."-".$cur_res.".htm";
					}
					
					$process = new Process("htmldoc --webpage --fontsize 9.0 --left 5 --right 5 --top 40 --bottom 70 --embedfonts -f $html_file.pdf $html_file.htm".$pdf_suffix); 
					$process->run();
					
					// executes after the command finishes
					if (!$process->isSuccessful()) {
						throw new ProcessFailedException($process);
						echo $process->getOutput();
						exit;
					}					
					
					//exec("htmldoc --webpage --fontsize 9.0 --left 5 --right 5 --top 40 --bottom 70 --embedfonts -f $html_file.pdf $html_file.htm".$pdf_suffix, $output, $return_var);					
					//print_r($output);
					
					if (isset($invoicenumber))
						$client_invnum = trim($invoicenumber);
					else {
						$client_invnum = trim($invnum);
						DB::table('tblPdfInvoice')
							->insert([
								'filename' => $html_file, 'inv_date' => $curdate, 'inv_num' => $client_invnum, 'agcode' => $current_ag
							]);						
					}
											
					// Delete temporary HTML files
					exec("rm ".$html_file.".htm");
					
					foreach ($res_array as $cur_res) {
						exec("rm ".$html_file."-".$cur_res.".htm");
					}
						
					$rptFilename[] = "$html_file.pdf";
				
					// Destroy variables
					unset($pdf_suffix);
					unset($res_array);
					unset($html_file);
					$pdf_suffix = '';
				}
				//***************************************

				if ($bShowThisAgency) {
					$grand_gross_due += $ag_gross_due;
					$grand_net_due += $ag_net_due;
					
					// parameter to send the output to an email address of the current adgency	
					//no email with API
					if(isset($sendemail) && $sendemail==1 && $bShowThisAgency) {
						$row = DB::table('tblAgentUsers')
									->where([['agCode', $current_ag], ['bill_in_rpt', '1']])
									->get()->first();
						
						if ($row)
							$emailTo = $emailTo.$row->email.",";
						
						if($emailTo!="") {
							$ag_output = $mess . $ag_output;
							$dte=$this->commonRepo->date_to_word("$EY-$EM-$ED");
						  $emailTo = substr($emailTo,0,strlen($emailTo)-1);
						  $mail = new mime_mail();
						  $mail->to=$emailTo;
						  $acc_to=env('ACCOUNTING_EMAIL');
						  $mail->from="$acc_to";
						  $mail->cc="$acc_to";
						  //$mail->bcc="mike@teamamericany.com,michel@teamamericany.com";
					      $s_msg=env('CO_NAME') . " Billing through $dte";
						  $mail->subject="$s_msg";
						  $mail->add_attachment("$ag_output","Billing","text/html");
						  $mail->send();
						}
					}
					else {
						/*
						if(isset($amount) && (int)$amount>0 && $ag_gross_due>=(int)$amount) {
							echo ($ag_output);
							if ($rpt_type == "pre-billing")
							{
								$agBuffer .= $ag_output;
							}
						}
						elseif(trim($amount)=='' or (int)$amount==0) 
						{
							echo ($ag_output);
							if ($rpt_type == "pre-billing")
							{
								$agBuffer .= $ag_output;
							}
						}*/
						echo ($ag_output);
						$agBuffer .= $ag_output;
					}
				}
				if (!$row) {
					break;
				} //endif
			} // end while Loop 1
						
			if(!isset($sendemail))	 {
				$footer_total = "<BR\n>" . "All Agencies Total Gross Bal:  <B>\$" . number_format ($grand_gross_due, 2) . 
					"</B>&nbsp;&nbsp;&nbsp; Total Net Bal:  <B>\$" . number_format ($grand_net_due, 2) .
					"</B>&nbsp;&nbsp;&nbsp; Total Paid:  <B>\$" . number_format ($grand_paid, 2) . "</B>";
				echo $footer_total;				
			}
		} // end if (count)
			
		if (isset($addJob) and $qid > 0) {
			if ($rpt_type == "pre-billing") {
				// Generate HTML file
				@chdir(env('TMPDIR'));
				echo getcwd() . "\n";
				
				$fp = fopen("prebilling_$qid.htm", 'w');
				chmod("prebilling_$qid.htm", 0777); 
				file_put_contents("prebilling_$qid.htm", $html_header.$agBuffer.$footer_total.$html_footer);
				fclose($fp);				
				//exec("htmldoc --webpage --fontsize 9.0 --left 5 --right 5 --top 40 --bottom 70 --embedfonts -f prebilling_$qid.pdf prebilling_$qid.htm");
				
				$process = new Process("htmldoc --webpage --fontsize 9.0 --left 5 --right 5 --top 40 --bottom 70 --embedfonts -f prebilling_$qid.pdf prebilling_$qid.htm"); 
				$process->run();
				
				// executes after the command finishes
				if (!$process->isSuccessful()) {
					throw new ProcessFailedException($process);
					echo $process->getOutput();
					exit;
				}
				
				$rptFilename = "prebilling_$qid.pdf";				
			}
			else {
				if (count($rptFilename) > 0)
				{
					$rptFilename = implode(', ', $rptFilename);
				}
			}
			
			DB::table('tblRptBillQueue')
				->where('qid', $qid)
				->update(['status' => 'Completed', 'filename' => $rptFilename, 'version' => 2]);
		}
	}
}