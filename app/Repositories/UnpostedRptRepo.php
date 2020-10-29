<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Repositories\TBCommonRepo;
use App\Repositories\PaymRepo;

class UnpostedRptRepo {	
	public $commonRepo, $paymRepo;

	public function __construct() {
		$this->commonRepo = new TBCommonRepo();
		$this->paymRepo = new PaymRepo();
	}
	
	public function generateRpt($request, $PNC_only=false) {
		//print_r($request->all()); exit;
		//error_reporting(E_ALL);
		//ini_set('display_errors', 1);
		ini_set('max_execution_time', 600);
		ini_set('memory_limit', '1024M');
		
		$rules = [
			'FOP' => array('in:All,*R,AX,BD,BR,CA,CB,CD,CE,CF,CG,CK,CL,CM,CO,CP,CR,CT,CV,DA,DC,DI,DP,EL,ET,EU,FD,GC,GP,GW,HK,LL,MC,MK,OP,OR,OV,PA,PE,PP,PV,SD,TA,UP,VI,WB,WD,XF')
		];
		
		$messages = [
		];
		
		$validator = Validator::make($request->all(), $rules, $messages);

		//if any more validations
		$validator->after(function($validator) use ($request) {				
		});
		
		if ($validator->fails()) {
			//print_r($validator->errors()->all()); exit;			
			$result['status'] = 'failed';
			$result['message'] = $validator->errors()->first();
			return $result;
		}
		
		$fop = isset($request->FOP) ? $request->FOP : '';
		
		//Test variable to check sub totals of each section
		//$html_sub="";
		
		//and (B.department!='3' && AG.PmtStatus!='PP')
		//Can't query B.department!='3' and AG.PmtStatus!='PP', this will affect the other report table;
		//Check for wire transfer case: ($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer")
		//Then check for department!=3 and PmtStatus!=PP
		//If case should be: ($current_fop=="*R" or $current_fop=="WI" or $current_fop=="WT" or $descrip=="Wire Transfer") and ($row->department!='3' && $row->PmtStatus!='PP')
		
		if ($PNC_only) {			
			$ar_payms = DB::table('tblAcctRec as A')
						->select(DB::raw('DISTINCT A.AcctRecID, B.ResNum, A.FOP, A.status, A.DateRcd, A.Amount, A.CCNum, A.Description, AG.AgName, P.LastName, P.FirstName, B.department, AG.PmtStatus, A.DestinationBank, A.ValueDate'))
						->join('tblBooking as B', 'B.ResNum', '=', 'A.ResNum')
						->join('tblItem as I', 'I.ResNum', '=', 'B.ResNum')
						->join('tblPax as P', 'P.PaxID', '=', 'A.PaxID')
						->join('tblAgency as AG', 'AG.AgCode', '=', 'B.AgCode')
						->where('A.status', 'PNC')
						->where(function ($query) {
							$query->where('I.Status', 'BK')
								->orWhere('I.Status', 'OK')
								->orWhere('I.Status', 'CF');
						})
						->orderBy('FOP')
						->orderBy('AG.AgCode')
						->orderBy('InvNum')
						->orderBy('ResNum')
						->orderBy('AcctRecID')
						->get();
		}
		else {
			$ar_payms = DB::table('tblAcctRec as A')
						->select(DB::raw('DISTINCT A.AcctRecID, B.ResNum, A.FOP, A.status, A.DateRcd, A.Amount, A.CCNum, A.Description, AG.AgName, P.LastName, P.FirstName, B.department, AG.PmtStatus, A.DestinationBank, A.ValueDate'))
						->join('tblBooking as B', 'B.ResNum', '=', 'A.ResNum')
						->join('tblItem as I', 'I.ResNum', '=', 'B.ResNum')
						->join('tblPax as P', 'P.PaxID', '=', 'A.PaxID')
						->join('tblAgency as AG', 'AG.AgCode', '=', 'B.AgCode')
						->where(function ($query) {
							$query->where('A.status', 'PNO')
								->orWhere('A.status', 'PNW');
						})
						->where(function ($query) {
							$query->where('I.Status', 'BK')
								->orWhere('I.Status', 'OK')
								->orWhere('I.Status', 'CF');
						});
						
			if ($fop && $fop != 'All')
				$ar_payms = $ar_payms->where('A.FOP', $fop);
			
			$ar_payms = $ar_payms->orderBy('FOP')
								->orderBy('AG.AgCode')
								->orderBy('InvNum')
								->orderBy('ResNum')
								->orderBy('AcctRecID')
								->get();
		}
									
		//$output = "<H2 align=center>Unposted Payment Report</H2>\n";
		
		$results = array();
		$grandtotal = 0.00;	
		
		if (count($ar_payms)) {			
			$ind = 0;
			$row = isset($ar_payms[$ind]) ? $ar_payms[$ind] : false;
			$has_more = true;
			//print_r($row); exit;

			//OsTicket#968068 Using $TuiAirtgePAEntries to transfer TUI/AIRTGE reservations from PA to TA payment table
			$TuiAirtgePAEntries=array();
			while ($has_more && $row) { // FOP loop
				
				// Get FOP info
				$current_fop = $row->FOP;
				$results[$current_fop] = array();
				
				$foprow = DB::table('tblFOP')
								->select(DB::raw('FOPCode, Description'))
								->where('FOPCode', $current_fop)
								->get()->first();
								
				if ($foprow) {
					$descrip = $foprow->Description;
				} else {
					$descrip = "";
				}
				
				// Display FOP info
				$note = '';
				$post = 'N';
				if ($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") {
					$title = "Form of Payment:  $current_fop" . $descrip;
					$note = '*<strong><u>Late</u></strong> column indicates last paid more than <strong><u>8 days</u></strong> ago.*';
					
					/*$output .= "<B>Form of Payment:  $current_fop" . $descrip . "</B>&nbsp&nbsp" . NL .							
							"<TABLE border=1 cellspacing=0 cellpadding=1><TR><TH>Res</TH><TH>Date Rec</TH><TH>Pax</TH><TH>Agency</TH><TH>CC Number</TH><TH>Description</TH><TH>Destination Bank</TH><TH>Value Date</TH><TH>Amount</TH></TR>\n";*/
				} else {
					if ($current_fop=="EC") {
						$title = "Form of Payment:  $current_fop - <a href='https://res.teamamericany.com/team/res/echeck.php?action=start'>Process E-Check Batch</a>";
						
						/*$output .= "<B>Form of Payment:  $current_fop - <a href=echeck.php?action=start>Process E-Check Batch</a>" . NL .
						"<TABLE border=1 cellspacing=0 cellpadding=1><TR><TH>Res</TH><TH>Date Rec</TH><TH>Pax</TH><TH>Agency</TH><TH>CC Number</TH><TH>Description</TH><TH>Destination Bank</TH><TH>Value Date</TH><TH>Amount</TH></TR>\n";*/
					} else {
						$post = 'Y' ;
						$title = "Form of Payment:  $current_fop" . $descrip;
						
						if($current_fop=="TA")
							$note = "*TUIGE/AIRTGE entries colored in <green><b><u>GREEN</u></b></green> were transferred from Past Adjustment table.*";

						/*$output .= "<B>Form of Payment:  $current_fop" . $descrip . "</B>&nbsp&nbsp POST: <INPUT type=checkbox name='chk[]' value='$current_fop'>" . NL . $TAHighlightInfo .
						"<TABLE border=1 cellspacing=0 cellpadding=1><TR><TH>Res</TH><TH>Date Rec</TH><TH>Pax</TH><TH>Agency</TH><TH>CC Number</TH><TH>Description</TH><TH>Destination Bank</TH><TH>Value Date</TH><TH>Amount</TH></TR>\n";*/
					}
				}
				
				//$results[$current_fop]['title'] = $title;
				$results[$current_fop]['fop'] = $current_fop;
				$results[$current_fop]['fopDesc'] = $descrip;
				$results[$current_fop]['note'] = $note;
				$results[$current_fop]['show_post_chkbox'] = $post;
				if ($post == 'Y')
					$results[$current_fop]['post_chkbox_val'] = $current_fop;
				else
					$results[$current_fop]['post_chkbox_val'] = '';
				$results[$current_fop]['payms'] = array();
				
				//print_r($results); exit;
				
				$subtotal = 0.00;
				$chasetotal=0.0;
				$cititotal=0.0;
				$metrototal=0.0;
				$canadatotal=0.0;
				$otherstotal=0.0;
				$extra_column="NO";

				//OsTicket#968068 Import TUI/AIRTGE into TA payment table here
				if($current_fop=="TA"){
					//If current FOP is TA, read the TuiAirtgePAEntries array and enter the Tui/Airtage reservations form PA table into this table
					foreach ($TuiAirtgePAEntries as $k => $v) {						
						
						//comment out to hide passed payments
						$paym_ind = count($results[$current_fop]['payms']);
						
						$results[$current_fop]['payms'][$paym_ind]['resNum'] = 'O-'.$v['resNum'];
						$results[$current_fop]['payms'][$paym_ind]['dateRcd'] = $v['dateRcd'];
						$results[$current_fop]['payms'][$paym_ind]['pax'] = $v['name'];
						$results[$current_fop]['payms'][$paym_ind]['agName'] = $v['agName'];
						$results[$current_fop]['payms'][$paym_ind]['ccNum'] = $this->paymRepo->cc_decrypt($v['ccNum']);
						$results[$current_fop]['payms'][$paym_ind]['desc'] = $v['desc'];
						$results[$current_fop]['payms'][$paym_ind]['destinationBank'] = $v['DestinationBank'];
						$results[$current_fop]['payms'][$paym_ind]['valueDate'] = $row->ValueDate ? date('m/d/Y', strtotime($v['ValueDate'])) : '';
						$results[$current_fop]['payms'][$paym_ind]['amt'] = $v['amt'];
						$results[$current_fop]['payms'][$paym_ind]['tuigeGreen'] = 'Y';
						
						/*$output .= "\t<TR>\n";
						$output .=	"\t\t<TD align=left><FONT color=green>O-".$v['resNum']."</TD>\n" .
									"\t\t<TD align=left><FONT color=green>".$v['dateRcd']."</TD>\n" .
									"\t\t<TD align=left><FONT color=green>".$v['name']."</TD>\n" .
									"\t\t<TD align=left><FONT color=green>".$v['agName']."</TD>\n" .
									(($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") ? '' : "\t\t<TD align=left><FONT color=green>".cc_decrypt($v['ccNum'])."</TD>\n") .
									"\t\t<TD align=left><FONT color=green>".$v['desc']."</TD>\n" .
									"\t\t<TD align=left><FONT color=green>$".$v['DestinationBank']."</TD>\n" .
									"\t\t<TD align=left><FONT color=green>$". ($row->ValueDate != null ? date('m/d/Y', strtotime($v['ValueDate'])): null) ."</TD>\n" .
									"\t\t<TD align=right><FONT color=green>$".$v['amt']."</TD>\n" .
									"\t</TR>\n";*/

						$subtotal += $v['amt'];
						
						if ($row->DestinationBank == 'CHASE')
							$chasetotal += $v['amt'];
						elseif ($row->DestinationBank == 'CITIBANK')
							$cititotal += $v['amt'];
						elseif ($row->DestinationBank == 'METROPOLITAN')
							$metrototal += $v['amt'];
						elseif ($row->DestinationBank == 'TD-CANADA TRUST')
							$canadatotal += $v['amt'];
						else	
							$otherstotal += $v['amt'];
					}
				}
				
				while ($has_more && $row && $current_fop == $row->FOP) { // res loop
					$paym_ind = count($results[$current_fop]['payms']);
					$align = "left";
					if (!$row->Description) {
						$row->Description = "";
						$align = "center";
					}
					$name = $row->LastName;
					if ($row->FirstName) $name .= "/$row->FirstName";
					if ($row->status=="PNO" or $row->status=="PNC" or $row->status="PNW") {

					//OsTicket#968068 Move all TUI/AIRTGE related Past Adjustments into Tuige Adjustments, use TuiAirtgePAEntries array to transfer the items.
					$rowclass="";
					if($row->FOP=='PA' && ($row->AgName=="AIRTOURS - TUI LUXURY" || $row->AgName=="TUI Deutschland GmbH")){
						//Hide row if it's TUI/Airtge and store their values into the transfer array as an array
						$rowclass="hiddenrow";
						$currRes=array(
								"resNum"  	=> $row->ResNum, 
								"dateRcd" 	=> $row->DateRcd, 
								"name"		=> $name, 
								"agName"	=> $row->AgName, 
								"ccNum"		=> $row->CCNum, 
								"desc"		=> $row->Description, 
								"DestinationBank"	=> $row->DestinationBank, 
								"ValueDate"	=> ($row->ValueDate != null ? date('m/d/Y', strtotime($row->ValueDate)): null), 
								"amt"		=> $row->Amount);
						array_push($TuiAirtgePAEntries, $currRes);							
					}
					
					//$output .= "\t<TR class=\"$rowclass\">\n";
					
					$invnum = 0;
					if ((($current_fop=="*R" or $current_fop=="WI" or $current_fop=="WT" or $descrip=="Wire Transfer") and ($row->department!='3' && $row->PmtStatus!='PP'))) {
						$query = "select InvNum from tblBooking where resnum='$row->ResNum'";
						
						$invData = DB::table('tblBooking')
									->select('InvNum')
									->where('resnum', $row->ResNum)
									->get()->first();
						if ($invData)
							$invnum = $invData->InvNum;

						if ($invnum) {
							//$output .= "\t\t<td><input type=checkbox name='chk[]' value='$current_fop-Inv$invnum'></td>\n";
							$results[$current_fop]['payms'][$paym_ind]['show_post_chkbox'] = 'Y';
							$results[$current_fop]['payms'][$paym_ind]['post_chkbox_val'] = "$current_fop-Inv$invnum";
						}
						else {
							//$output .= "\t\t<td><input type=checkbox name='chk[]' value='$current_fop-$row->AcctRecID'></td>\n";
							$results[$current_fop]['payms'][$paym_ind]['show_post_chkbox'] = 'Y';
							$results[$current_fop]['payms'][$paym_ind]['post_chkbox_val'] = "$current_fop-$row->AcctRecID";
						}
					} 					
					
					$amount=0;						
					if ($invnum) { // for wire transfers with invoices, only display invoice #
						$inv_row = DB::table('tblInvoice')
									->select('Comments')
									->where('Number', $invnum)
									->get()->first();

						//If comments are empty, search all reservation paid amount that is related to current invoice # and add them up
						if($inv_row && $inv_row->Comments==""){
							$cRow = DB::table('tblAcctRec as R')
										->select(DB::raw('R.Description'))
										->join('tblBooking as B', 'B.ResNum', '=', 'R.ResNum')
										->where('B.InvNum', $invnum)
										->get()->first();
							$camount=0;
							if($cRow){
								$inv_row->Comments = $cRow->Description;
							}
						}

						$extra_column="YES";								
						$max_date="2000-01-01";
						$t_agname=$row->AgName;
						$destinationBank = $row->DestinationBank ? $row->DestinationBank : '';
						$valueDate = $row->ValueDate;
						
						do {
							$amount += $row->Amount;
							if ($this->commonRepo->date_compare($row->DateRcd,$max_date,">")) $max_date=$row->DateRcd;
							
							$ind += 1;
							if (isset($ar_payms[$ind])) {
								$row = $ar_payms[$ind];
								
								$inv_result = DB::table('tblBooking')
												->select('InvNum')
												->where('ResNum', $row->ResNum)
												->get()->first();
								if ($inv_result) {
									$curinvnum=$inv_result->InvNum;
								} else {
									$curinvnum=0;
								}
							}
							else {
								$curinvnum=0;
								$has_more = false;
							}														
						} while ($invnum == $curinvnum);							
						
						//$output .= "\t\t<td><span style='display:none;'>$max_date</span>Invoice #$invnum";
						
						$results[$current_fop]['payms'][$paym_ind]['invNum'] = $invnum;
						
						$orig_max_date = $max_date;
						$max_date=$this->commonRepo->date_to_amerdt($max_date);					
						
						//see if the payment is more than 8 days late
						$dRcd = strtotime($max_date);
						$diffInSeconds = time() - $dRcd;
						$dDiff = floor($diffInSeconds / 86400);
						
						$td_class = '';
						if ($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") {
							if ($destinationBank == 'TD-CANADA TRUST')
								$td_class = 't1_canada';
							else
								$td_class = $destinationBank ? 't1_'.strtolower($destinationBank) : 't1_others';
						}
						
						$valueDate = ($valueDate ? date('m/d/Y', strtotime($valueDate)) : '');
						//$output .= "&nbsp&nbsp&nbsp <b>Last Paid:</b> $max_date</td>".(($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") ? "<td style='display:none;'>$max_date</td>" : '')."<td align=center>". ($dDiff > 8 ? 'x' : '') ."</td><td>$t_agname</td>".($inv_row->Comments ? "<td align=left>$inv_row->Comments" : '<td align=center>-')."</td><td align=left class=\"$td_class\">$destinationBank</td><td align=left>$valueDate</td><td align=right>$".number_format($amount,2,'.','')."</td>\n\t</tr>\n";
						
						$results[$current_fop]['payms'][$paym_ind]['lastPaid'] = $orig_max_date;
						$results[$current_fop]['payms'][$paym_ind]['8DaysLate'] = ($dDiff > 8) ? 'Y' : 'N';
						$results[$current_fop]['payms'][$paym_ind]['agName'] = $t_agname;
						$results[$current_fop]['payms'][$paym_ind]['desc'] = $inv_row->Comments;
						$results[$current_fop]['payms'][$paym_ind]['destinationBank'] = $destinationBank;
						$results[$current_fop]['payms'][$paym_ind]['valueDate'] = $valueDate;
						$results[$current_fop]['payms'][$paym_ind]['amt'] = number_format($amount,2,'.','');

						$subtotal += $amount;
						
						if ($destinationBank == 'CHASE')
							$chasetotal += $amount;
						elseif ($destinationBank == 'CITIBANK')
							$cititotal += $amount;
						elseif ($destinationBank == 'METROPOLITAN')
							$metrototal += $amount;
						elseif ($destinationBank == 'TD-CANADA TRUST')
							$canadatotal += $amount;
						else	
							$otherstotal += $amount;
					} 					
					else {		
						//see if the payment is more than 8 days late
						$dRcd = strtotime($row->DateRcd);
						$diffInSeconds = time() - $dRcd;
						$dDiff = floor($diffInSeconds / 86400);
				
						if ($row->status=="PNO" or $row->status=="PNC") {
							if((($current_fop=="*R" or $current_fop=="WI" or $current_fop=="WT" or $descrip=="Wire Transfer") and ($row->department!='3' && $row->PmtStatus!='PP')) || $current_fop!="*R"){
								if($rowclass!="hiddenrow"){ //If the row is not hidden for PA entries that are TUI/Airtge
								
									$td_class = '';
									if ($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") {
										if ($row->DestinationBank == 'TD-CANADA TRUST')
											$td_class = 't1_canada';
										else
											$td_class = $row->DestinationBank ? 't1_'.strtolower($row->DestinationBank) : 't1_others';
									}
								
									/*$output .=
									"\t\t<TD align=left><span style='display:none;'>$row->DateRcd</span>O-$row->ResNum".(($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") ? "&nbsp;&nbsp;&nbsp;<b>Date Received: </b> ".date_to_amerdt($row->DateRcd) : '')."</TD>\n" .
									(($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") ? "\t\t<TD align=left style='display:none;'>$row->DateRcd</TD>\n" : "\t\t<TD align=left>$row->DateRcd</TD>\n") .
									(($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") ? "\t\t<td align=center>". ($dDiff > 8 ? 'x' : '') ." </td>\n" : "\t\t<TD align=left>$name</TD>\n") .
									"\t\t<TD align=left>$row->AgName</TD>\n" .
									(($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") ? '' : "\t\t<TD align=left>".cc_decrypt($row->CCNum)."</TD>\n") .
									"\t\t<TD align=$align>$row->Description</TD>\n" .
									"\t\t<TD align=left class=\"$td_class\">$row->DestinationBank</TD>\n" .
									"\t\t<TD align=left>" . ($row->ValueDate != null ? date('m/d/Y', strtotime($row->ValueDate)): null) . "</TD>\n" .
									"\t\t<TD align=right>\$$row->Amount</TD>\n" .
									"\t</TR>\n";*/
									
									$results[$current_fop]['payms'][$paym_ind]['resNum'] = "O-$row->ResNum";
									$results[$current_fop]['payms'][$paym_ind]['dateRcd'] = $row->DateRcd;
									$results[$current_fop]['payms'][$paym_ind]['lastPaid'] = $row->DateRcd;
									$results[$current_fop]['payms'][$paym_ind]['8DaysLate'] = (($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") && $dDiff > 8) ? 'Y' : 'N';
									$results[$current_fop]['payms'][$paym_ind]['pax'] = $name;
									$results[$current_fop]['payms'][$paym_ind]['agName'] = $row->AgName;
									$results[$current_fop]['payms'][$paym_ind]['ccNum'] = ($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") ? '' : $this->paymRepo->cc_decrypt($row->CCNum);
									$results[$current_fop]['payms'][$paym_ind]['desc'] = $row->Description;
									$results[$current_fop]['payms'][$paym_ind]['destinationBank'] = $row->DestinationBank ? $row->DestinationBank : '';
									$results[$current_fop]['payms'][$paym_ind]['valueDate'] = $row->ValueDate ? date('m/d/Y', strtotime($row->ValueDate)) : '';
									$results[$current_fop]['payms'][$paym_ind]['amt'] = $row->Amount;

									$subtotal += $row->Amount;
									
									if ($row->DestinationBank == 'CHASE')
										$chasetotal += $row->Amount;
									elseif ($row->DestinationBank == 'CITIBANK')
										$cititotal += $row->Amount;
									elseif ($row->DestinationBank == 'METROPOLITAN')
										$metrototal += $row->Amount;
									elseif ($row->DestinationBank == 'TD-CANADA TRUST')
										$canadatotal += $row->Amount;
									else	
										$otherstotal += $row->Amount;
								}
							}
						}
						
						if ($row->status=="PNW") {
							if ((($current_fop=="*R" or $current_fop=="WI" or $current_fop=="WT" or $descrip=="Wire Transfer") and ($row->department!='3' && $row->PmtStatus!='PP')) || $current_fop!="*R"){
								if($rowclass!="hiddenrow"){ //If the row is not hidden for PA entries that are TUI/Airtge
									$td_class = '';
									if ($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") {
										if ($row->DestinationBank == 'TD-CANADA TRUST')
											$td_class = 't1_canada';
										else
											$td_class = $row->DestinationBank ? 't1_'.strtolower($row->DestinationBank) : 't1_others';
									}
								
									/*$output .= 
									"\t\t<TD align=left><span style='display:none;'>$row->DateRcd</span><FONT color=green>W-$row->ResNum</TD>\n" .
									(($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") ? "\t\t<TD align=left style='display:none;'><FONT color=green>$row->DateRcd</TD>\n" : "\t\t<TD align=left><FONT color=green>$row->DateRcd</TD>\n") .
									(($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") ? "\t\t<td align=center><FONT color=green>". ($dDiff > 8 ? 'x' : '') ." </td>\n" : "\t\t<TD align=left><FONT color=green>$name</TD>\n") .
									"\t\t<TD align=left><FONT color=green>$row->AgName</TD>\n" .
									(($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") ? '' : "\t\t<TD align=left><FONT color=green>".cc_decrypt($row->CCNum)."</TD>\n") .
									"\t\t<TD align=$align><FONT color=green>$row->Description</TD>\n" .
									"\t\t<TD align=left class=\"$td_class\"><FONT color=green>$row->DestinationBank</TD>\n" .
									"\t\t<TD align=left><FONT color=green>" . ($row->ValueDate != null ? date('m/d/Y', strtotime($row->ValueDate)): null) . "</TD>\n" .
									"\t\t<TD align=right><FONT color=green>\$$row->Amount</TD>\n" .
									"\t</TR>\n";*/
									
									$results[$current_fop]['payms'][$paym_ind]['resNum'] = "O-$row->ResNum";
									$results[$current_fop]['payms'][$paym_ind]['dateRcd'] = $row->DateRcd;
									$results[$current_fop]['payms'][$paym_ind]['lastPaid'] = $row->DateRcd;
									$results[$current_fop]['payms'][$paym_ind]['8DaysLate'] = (($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") && $dDiff > 8) ? 'Y' : 'N';
									$results[$current_fop]['payms'][$paym_ind]['pax'] = $name;
									$results[$current_fop]['payms'][$paym_ind]['agName'] = $row->AgName;
									$results[$current_fop]['payms'][$paym_ind]['ccNum'] = ($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer") ? '' : $this->paymRepo->cc_decrypt($row->CCNum);
									$results[$current_fop]['payms'][$paym_ind]['desc'] = $row->Description;
									$results[$current_fop]['payms'][$paym_ind]['destinationBank'] = $row->DestinationBank;
									$results[$current_fop]['payms'][$paym_ind]['valueDate'] = $row->ValueDate ? date('m/d/Y', strtotime($row->ValueDate)) : '';
									$results[$current_fop]['payms'][$paym_ind]['amt'] = $row->Amount;
									$results[$current_fop]['payms'][$paym_ind]['tuigeGreen'] = 'Y';

									$subtotal += $row->Amount;
									
									if ($row->DestinationBank == 'CHASE')
										$chasetotal += $row->Amount;
									elseif ($row->DestinationBank == 'CITIBANK')
										$cititotal += $row->Amount;
									elseif ($row->DestinationBank == 'METROPOLITAN')
										$metrototal += $row->Amount;
									elseif ($row->DestinationBank == 'TD-CANADA TRUST')
										$canadatotal += $row->Amount;
									else	
										$otherstotal += $row->Amount;
								}
							}
						}
					} 
					/*if ((($current_fop=="*R" or $current_fop=="WI" or $current_fop=="WT" or $descrip=="Wire Transfer") and ($row->department!='3' && $row->PmtStatus!='PP')) || $current_fop!="*R"){
						if ($amount > 0 ) {						
							$subtotal += $amount;
							$grandtotal += $amount;
						} else {
							$subtotal += $row->Amount;
							$grandtotal += $row->Amount;
						}
					}*/
					if (!$invnum) { // invoiced res are already incremented
						//$row = mysql_fetch_object ($result);
						
						$ind += 1;
						if (isset($ar_payms[$ind])) {
							$row = $ar_payms[$ind];
						}
						else {
							$has_more = false;
						}	
					}
							
					} // end if ($row->status=="PNO" or $row->status=="PNC" or $row->status="PNW")
				} // end res loop
				
				$grandtotal+=$subtotal;
				
				//If current FOP is wire, generate the separate table for group and pre pay
				if ($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer"){
					$wireGroup = $this->generateWirePrePayGroup($grandtotal, $current_fop);
					$results['*RG'] = $wireGroup;
				}
				if($extra_column=="YES") {
					/*$output .= "<TBODY class='avoid-sort'><TR><TD colspan=7 align=right>$current_fop total:</TD><TD align=right>\$" . number_format ($subtotal, 2) . "</TD></TR>" . NL;
					$output .= "<TR><TD colspan=7 align=right>Chase Total:<br>Citibank Total:<br>Metropolitan Total:<br>TD-Canada Total:<br>Others Total:</TD><TD align=right>\$" . number_format ($chasetotal, 2) . "<br>$".number_format ($cititotal, 2)."<br>$".number_format ($metrototal, 2)."<br>$".number_format ($canadatotal, 2)."<br>$".number_format ($otherstotal, 2)."</TD></TR></TBODY></TABLE>" . NL;
					
					$output .= "
					<script>					
						$(function () {
							$('#myTable1 tr').each(function () {
								if ($(this).find('td').length == 0 && $(this).find('th').length == 0)
									$(this).remove();
							});
							
							$('#myTable1').tablesorter({
								sortList:[[6, 1], [2, 0]],
								sortAppend: {
									7 : [[2, 0]]
								},
								sortForce: [[6,1]],
								cssInfoBlock : 'avoid-sort', 
								headers: { 0: { sorter: false }, 1: { sorter: 'text' } }
							})
							.bind('sortEnd', function(e, t) {
								$('.t1_bank_total').remove();
								
								$('<tr class=\"t1_bank_total\" style=\"background-color:#ffe7d5;\"><td colspan=7 align=right style=\"padding-right:5px;height:40px;\"><b>Citibank Total:</b></td><td align=right><b>$".number_format($cititotal, 2)."</b></td></tr>').insertAfter($('.t1_citibank').last().parent());
								
								$('<tr class=\"t1_bank_total\" style=\"background-color:#ffe7d5;\"><td colspan=7 align=right style=\"padding-right:5px;height:40px;\"><b>Chase Total:</b></td><td align=right><b>$".number_format($chasetotal, 2)."</b></td></tr>').insertAfter($('.t1_chase').last().parent());
								
								$('<tr class=\"t1_bank_total\" style=\"background-color:#ffe7d5;\"><td colspan=7 align=right style=\"padding-right:5px;height:40px;\"><b>Metropolitan Total:</b></td><td align=right><b>$".number_format($metrototal, 2)."</b></td></tr>').insertAfter($('.t1_metropolitan').last().parent());
								
								$('<tr class=\"t1_bank_total\" style=\"background-color:#ffe7d5;\"><td colspan=7 align=right style=\"padding-right:5px;height:40px;\"><b>TD-Canada Total:</b></td><td align=right><b>$".number_format($canadatotal, 2)."</b></td></tr>').insertAfter($('.t1_canada').last().parent());
								
								$('<tr class=\"t1_bank_total\" style=\"background-color:#ffe7d5;\"><td colspan=7 align=right style=\"padding-right:5px;height:40px;\"><b>Others Total:</b></td><td align=right><b>$".number_format($otherstotal, 2)."</b></td></tr>').insertAfter($('.t1_others').last().parent());
							});
						});
					</script>
					";*/
					
					$results[$current_fop]['total'] = number_format ($subtotal, 2, '.', '');
					$results[$current_fop]['citi_total'] = number_format ($cititotal, 2, '.', '');
					$results[$current_fop]['chase_total'] = number_format ($chasetotal, 2, '.', '');
					$results[$current_fop]['metro_total'] = number_format ($metrototal, 2, '.', '');
					$results[$current_fop]['canada_total'] = number_format ($canadatotal, 2, '.', '');
					$results[$current_fop]['others_total'] = number_format ($otherstotal, 2, '.', '');
					
				} else {
					//$output .= "<TR><TD colspan=8 align=right>$current_fop total:</TD><TD align=right>\$" . number_format ($subtotal, 2) . "</TD></TR></TABLE>" . NL;
					
					$results[$current_fop]['total'] = number_format ($subtotal, 2, '.', '');
				}
				
				//$html_sub .= $subtotal . "</br>";
				
				//If current table is Wire transfer, generate the separate table after for Pre pay and Group
				if ($current_fop=="*R" or $current_fop=="WI" or $descrip=="Wire Transfer"){
					//$output .= $tempOutput;
				}
				
			} // end FOP loop
			
			//$output .= "<H3>Total:  \$" . number_format ($grandtotal, 2) . "</H3>\n";				
		}
		
		//echo ("$output");		
		
		//sort payments by destination bank desc and last paid/date received asc and agency asc
		foreach ($results as $paym_method => &$rs) {
			if (isset($rs['payms']) && in_array($paym_method, array('*R', '*RG'))) { 
				usort($rs['payms'], function($a, $b) {
					if ($a['destinationBank'] < $b['destinationBank'])
						return 1;
					elseif ($a['destinationBank'] > $b['destinationBank'])
						return -1;
					if ($a['destinationBank'] == $b['destinationBank']) {
						if ($a['lastPaid'] < $b['lastPaid'])
							return -1;
						elseif ($a['lastPaid'] > $b['lastPaid'])
							return 1;
						elseif ($a['lastPaid'] == $b['lastPaid']) {
							if ($a['agName'] < $b['agName'])
								return -1;
							elseif ($a['agName'] > $b['agName'])
								return 1;
							else
								return 0;
						}
					}					
				});
			}
		}
		
		$result['status'] = 'success';
		$result['paym_method'] = $results;
		$result['grand_total'] = number_format ($grandtotal, 2, '.', '');
		
		return $result;		
	}	
	
	///////////////////////////////////////////////////////////////////////////////
	// generateWirePrePayGroup()							 		 
	// Custom method to generate table for pre pay and group wire transfers only 
	///////////////////////////////////////////////////////////////////////////////
	//Pre conditions: 	Pass in $current_fop for checkbox value
	//
	//Post conditions: Will return html output of the table.
	public function generateWirePrePayGroup(&$grandtotalReference, $current_fop){

		date_default_timezone_set('America/New_York');
		//$today = date('Y-m-d');
		$end_date = date('Y-m-d', strtotime("+3 days"));
		$start_date = date('Y-m-d', strtotime("-7 days"));
		
		$results = array();

		/*$html_output .= "<B>Form of Payment:  *R - Wire Transfer - PrePay and Group Payments</B>";
		$html_output .= '<br><br>*Payment entries with arrival dates within <strong><u>3 days</u></strong> or past <strong><u>7 days</u></strong> are <mark>highlighted in yellow</mark>.*</br>';
		$html_output .= '*Payment entries with arrival dates more than <strong><u>7 days</u></strong> ago are <red>highlighted in red</red>.*</br>';
		$html_output .= '*<strong><u>Late</u></strong> column indicates date received more than <strong><u>8 days</u></strong> ago.*</br><br>';
		$html_output .= "<table id='myTable2' class='tablesorter' border=1 cellspacing=0 cellpadding=1 width='100%'>";
		$html_output .= "<thead>";
		$html_output .= "<th></th>";
		$html_output .= "<th>Res Number</th>";
		$html_output .= "<th>Date Received</th>";
		$html_output .= "<th>Date Arrival</th>";
		$html_output .= "<th>Late</th>";
		$html_output .= "<th>Agency</th>";
		$html_output .= "<th>Description</th>";
		$html_output .= "<th>Destination Bank</th>";
		$html_output .= "<th>Value Date</th>";
		$html_output .= "<th>Amount</th>";
		$html_output .= "</thead>";*/
		
		//$results['title'] = 'Form of Payment:  *R - Wire Transfer - PrePay and Group Payments';
		$results['fop'] = '*R';
		$results['fopDesc'] = 'Wire Transfer - PrePay and Group Payments';
		$results['note'] = '*Payment entries with arrival dates within <strong><u>3 days</u></strong> or past <strong><u>7 days</u></strong> are <mark>highlighted in yellow</mark>.*</br>*Payment entries with arrival dates more than <strong><u>7 days</u></strong> ago are <red>highlighted in red</red>.*</br>*<strong><u>Late</u></strong> column indicates date received more than <strong><u>8 days</u></strong> ago.*';
		$results['show_post_chkbox'] = 'N';
		$results['post_chkbox_val'] = '';
						
		$rows = DB::table('tblAcctRec as A')
					->select(DB::raw('DISTINCT A.ResNum, A.AcctRecID, B.ResNum, A.DateRcd, A.PaxID, B.AgCode, A.Description, A.Amount, A.FOP, A.Status, B.department, P.LastName, P.FirstName, AG.AgName, AG.PmtStatus, MIN(I.DepDate) AS DepDate, A.DestinationBank, A.ValueDate')) 
					->join('tblBooking as B', 'B.ResNum', '=', 'A.ResNum')
					->join('tblPax as P', 'P.PaxID', '=', 'A.PaxID')
					->join('tblItem as I', 'I.ResNum', '=', 'A.ResNum')
					->join('tblAgency as AG', 'AG.AgCode', '=', 'B.AgCode')
					->where([['A.FOP', '*R']])
					->where(function ($query) {
						$query->where('A.Status', 'PNO')
							->orWhere('A.Status', 'PNW');
					})
					->where(function ($query) {
						$query->where('B.department', '3')
							->orWhere('AG.PmtStatus', 'PP');
					})
					->where(function ($query) {
						$query->where('I.Status', 'BK')
							->orWhere('I.Status', 'OK')
							->orWhere('I.Status', 'CF');
					})
					->groupBy('A.AcctRecID')
					->orderBy('A.DestinationBank', 'desc')
					->orderBy('A.DateRcd', 'asc')
					->get();
		
		$subtotal=0.0;
		$chasetotal=0.0;
		$cititotal=0.0;
		$metrototal=0.0;
		$canadatotal=0.0;
		$otherstotal=0.0;
		
		$payms = array();
		
		foreach($rows as $pind => $row){
			//$bgColor='';
			$paym = array();
			$late_yellow = 'N';
			$late_red = 'N';
			
			//If within date range, yellow highlight
			if($row->DepDate>=$start_date && $row->DepDate<=$end_date) {
				//$bgColor='#FFFF00';
				$late_yellow = 'Y';
			}

			//If it's more than past 7 days, highlight red
			if($row->DepDate<$start_date) {
				//$bgColor='#FF0000';
				$late_red = 'Y';
			}

			$invnum = 0;
			
			if ($row->DestinationBank == 'TD-CANADA TRUST')
				$row_class = 't2_canada';
			else
				$row_class = $row->DestinationBank ? 't2_'.strtolower($row->DestinationBank) : 't2_others';
			
			//$html_output .= "<tr class='row $row_class' bgcolor=$bgColor>";
			
			$invData = DB::table('tblBooking')
							->select('InvNum')
							->where('ResNum', $row->ResNum)
							->get()->first();
							
			if ($invData)
				$invnum = $invData->InvNum;

			$paym['show_post_chkbox'] = 'Y';
			
			if ($invnum) {				
				$paym['post_chkbox_val'] = "$current_fop-Inv$invnum";
				//$html_output .= "\t\t<td><input type=checkbox name='chk[]' value='$current_fop-Inv$invnum'></td>\n";
			}
			else {
				$paym['post_chkbox_val'] = "$current_fop-$row->AcctRecID";
				//$html_output .= "\t\t<td><input type=checkbox name='chk[]' value='$current_fop-$row->AcctRecID'></td>\n";
			}
			
			$dRcd = strtotime($row->DateRcd);
			$diffInSeconds = time() - $dRcd;
			$dDiff = floor($diffInSeconds / 86400);

			/*$html_output .= "<td>". $row->ResNum	."</td>";
			$html_output .= "<td>". $row->DateRcd	."</td>";
			$html_output .= "<td>". $row->DepDate	."</td>";
			$html_output .= "<td align=center>". ($dDiff > 8 ? 'x' : '') ." </td>";
			$html_output .= "<td>". $row->AgName	."</td>";
			$html_output .= "<td>". $row->Description	."</td>";
			$html_output .= "<td>". $row->DestinationBank	."</td>";
			$html_output .= "<td>". ($row->ValueDate != null ? date('m/d/Y', strtotime($row->ValueDate)): null) ."</td>";
			$html_output .= "<td align=right>$". $row->Amount 	."</td>";
			$html_output .= "</tr>";*/
			
			$paym['resNum'] = $row->ResNum;
			$paym['dateRcd'] = $row->DateRcd;
			$paym['lastPaid'] = $row->DateRcd;
			$paym['depDate'] = $row->DepDate;
			$paym['8DaysLate'] = $dDiff > 8 ? 'Y' : 'N';
			$paym['lateYellow'] = $late_yellow;
			$paym['lateRed'] = $late_red;
			$paym['agName'] = $row->AgName;
			$paym['desc'] = $row->Description;
			$paym['destinationBank'] = $row->DestinationBank;
			$paym['valueDate'] = $row->ValueDate ? date('m/d/Y', strtotime($row->ValueDate)): '';
			$paym['amt'] = $row->Amount;
			
			$subtotal+=$row->Amount;
			
			if ($row->DestinationBank == 'CHASE') {
				$chasetotal += $row->Amount;
			}
			elseif ($row->DestinationBank == 'CITIBANK') {
				$cititotal += $row->Amount;
			}
			elseif ($row->DestinationBank == 'METROPOLITAN') {
				$metrototal += $row->Amount;
			}
			elseif ($row->DestinationBank == 'TD-CANADA TRUST') {
				$canadatotal += $row->Amount;
			}
			else {
				$otherstotal += $row->Amount;
			}
			
			$payms[$pind] = $paym;
		}
		
		$results['payms'] = $payms;
		
		$results['total'] = number_format($subtotal, 2, '.', '');
		$results['citi_total'] = number_format($cititotal, 2, '.', '');
		$results['chase_total'] = number_format($chasetotal, 2, '.', '');
		$results['metro_total'] = number_format($metrototal, 2, '.', '');
		$results['canada_total'] = number_format($canadatotal, 2, '.', '');
		$results['others_total'] = number_format($otherstotal, 2, '.', '');
		
		//$wiretotal-=$subtotal;
		
		/*$html_output .= "<TBODY class='avoid-sort'><TR><TD colspan=9 align=right>*R PrePay & Group Total:</TD><TD align=right>\$" . number_format ($subtotal, 2) . "</TD></TR>";
		$html_output .= "<TR><TD colspan=9 align=right>Chase Total:<br>Citibank Total:<br>Metropolitan Total:<br>TD-Canada Total:<br>Others Total:</TD><TD align=right>\$" . number_format ($chasetotal, 2) . "<br>$".number_format ($cititotal, 2)."<br>$".number_format ($metrototal, 2)."<br>$".number_format ($canadatotal, 2)."<br>$".number_format ($otherstotal, 2)."</TD></TR></TBODY></TABLE>";
						
		$html_output .= "
		<script>			
			$(function () {
				$('#myTable2').tablesorter({
					sortList:[[7, 1], [2, 0]],
					sortAppend: {
						7 : [[2, 0]]
					},
					sortForce: [[7,1]],
					cssInfoBlock : 'avoid-sort', 
					headers: { 0: { sorter: false} }
				})
				.bind('sortEnd', function(e, t) {
					$('.t2_bank_total').remove();
					
					$('<tr class=\"t2_bank_total\" style=\"background-color:#ffe7d5;\"><td colspan=9 align=right style=\"padding-right:5px;height:40px;\"><b>Citibank Total:</b></td><td align=right><b>$".number_format($cititotal, 2)."</b></td></tr>').insertAfter($('.t2_citibank').last());
					
					$('<tr class=\"t2_bank_total\" style=\"background-color:#ffe7d5;\"><td colspan=9 align=right style=\"padding-right:5px;height:40px;\"><b>Chase Total:</b></td><td align=right><b>$".number_format($chasetotal, 2)."</b></td></tr>').insertAfter($('.t2_chase').last());
					
					$('<tr class=\"t2_bank_total\" style=\"background-color:#ffe7d5;\"><td colspan=9 align=right style=\"padding-right:5px;height:40px;\"><b>Metropolitan Total:</b></td><td align=right><b>$".number_format($metrototal, 2)."</b></td></tr>').insertAfter($('.t2_metropolitan').last());
					
					$('<tr class=\"t2_bank_total\" style=\"background-color:#ffe7d5;\"><td colspan=9 align=right style=\"padding-right:5px;height:40px;\"><b>TD-Canada Total:</b></td><td align=right><b>$".number_format($canadatotal, 2)."</b></td></tr>').insertAfter($('.t2_canada').last());
					
					$('<tr class=\"t2_bank_total\" style=\"background-color:#ffe7d5;\"><td colspan=9 align=right style=\"padding-right:5px;height:40px;\"><b>Others Total:</b></td><td align=right><b>$".number_format($otherstotal, 2)."</b></td></tr>').insertAfter($('.t2_others').last());
				});
			});
		</script>
		";*/
		
		$grandtotalReference+=$subtotal;
		
		return $results;
	}//End generateWirePrePayGroup();
	
	public function postPaym($request) {
		//print_r($request->all()); exit;
		
		$rules = [
			'Paym' => array('required', 'array'),
			'Paym.*' => array('required'),
		];
		
		$messages = [
		];
		
		$validator = Validator::make($request->all(), $rules, $messages);

		//if any more validations
		$validator->after(function($validator) use ($request) {			
			foreach ($request->Paym as $k => $paym) {
				$paym_arr = explode('-', $paym);
				if (!in_array($paym_arr[0], array('*R','AX','BD','BR','CA','CB','CD','CE','CF','CG','CK','CL','CM','CO','CP','CR','CT','CV','DA','DC','DI','DP','EL','ET','EU','FD','GC','GP','GW','HK','LL','MC','MK','OP','OR','OV','PA','PE','PP','PV','SD','TA','UP','VI','WB','WD','XF'))) {
					$validator->errors()->add("Paym.$k", "The Paym.$k field has invalid Payment method: $paym_arr[0].");
				}
			}
		});
		
		if ($validator->fails()) {
			//print_r($validator->errors()->all()); exit;			
			$result['status'] = 'failed';
			$result['message'] = $validator->errors()->first();
			return $result;
		}
		
		$today=getdate(time());
		$today=$today["year"].'-'.$today["mon"].'-'.$today["mday"];
		$batchno=time();
		
		$chk = array_unique($request->Paym);
		reset($chk);
		//print_r($chk); exit;
		
		$data = array();
		
		foreach ($chk as $fop_to_post) {
			if (strlen($fop_to_post)>2) {
				if (substr($fop_to_post, 3, 3) == 'Inv') {
					$invnum = substr($fop_to_post, 6);
					$fops = DB::table('tblAcctRec as A')
								->select(DB::raw('AcctRecID'))
								->join('tblBooking as B', 'B.ResNum', '=', 'A.ResNum')
								->where([['InvNum', $invnum], ['A.Status', 'PNO']])
								->where(function ($query) {
										$query->where('FOP', 'WI')
											->orWhere('FOP', '*R')
											->orWhere('FOP', 'WT');
									})
								->get();
					
					$data[$fop_to_post] = $this->post_i($fop_to_post, $today, $batchno, $fops);
				} else
					$data[$fop_to_post] = $this->post_i($fop_to_post, $today, $batchno);
			} else {
				$data[$fop_to_post] = $this->post_p($fop_to_post, $today, $batchno);
			}
		}
		
		$result['status'] = 'success';
		$result['posted'] = $data;
		
		return $result;
	}
	
	public function post_i($curr_fop, $today, $batchno, $fops=array()) {
		//get FOP name
		$fopName = 'Wire Transfer';
		
		$data = array();
		$data['batchNo'] = $batchno;
		$data['fop'] = $curr_fop;
		$data['fopDesc'] = $fopName;
		$data['datePost'] = $today;
		$data['postList'] = array();
		$data['total'] = '0.00';

		if (count($fops)) {
			$ttl = 0;
			foreach ($fops as $i => $fop) {
				
				DB::table('tblAcctRec')
					->where('AcctRecID', $fop->AcctRecID)
					->update([
						'status' => 'PST',
						'DatePost' => $today,
						'batch' => $batchno
					]);

				$update = DB::table('tblAcctRec')
								->where([['AcctRecID', $fop->AcctRecID]])
								->get()->first();
								
				$data['postList'][$i]['acctRecID'] = $update->AcctRecID;
				$data['postList'][$i]['dateRcd'] = $update->DateRcd;
				$data['postList'][$i]['resNum'] = $update->ResNum;
				$data['postList'][$i]['amount'] = $update->Amount;
				$ttl += $update->Amount;	
			}
			$data['total'] = number_format($ttl, 2, '.', '');
		}
		elseif (substr($curr_fop, 3, 3) != 'Inv') { 
			$AcctRecID=substr($curr_fop,3,strlen($curr_fop));
			
			DB::table('tblAcctRec')
				->where('AcctRecID', $AcctRecID)
				->update([
					'status' => 'PST',
					'DatePost' => $today,
					'batch' => $batchno
				]);

			$update = DB::table('tblAcctRec')
							->where([['AcctRecID', $AcctRecID]])
							->get()->first();
						
			$data['postList'][0]['acctRecID'] = $update->AcctRecID;
			$data['postList'][0]['dateRcd'] = $update->DateRcd;
			$data['postList'][0]['resNum'] = $update->ResNum;
			$data['postList'][0]['amount'] = $update->Amount;
			$data['total'] = $update->Amount;
		}

		return $data;
	}
	
	public function post_p($curr_fop, $today, $batchno) {
		//get FOP name
		$fopName = '';
		if ($curr_fop) {
			$fopData = DB::table('tblFOP')
						->where('FOPCode' , $curr_fop)
						->get()->first();
						
			if ($fopData)
				$fopName = $fopData->Description;						
		}
		
		$data = array();
		$data['batchNo'] = $batchno;
		$data['fop'] = $curr_fop;
		$data['fopDesc'] = $fopName;
		$data['datePost'] = $today;
		$data['postList'] = array();
		
		DB::table('tblAcctRec')
			->where('FOP', $curr_fop)
			->where(function ($query) {
				$query->where('status', 'PNO')
					->orWhere('status', 'PNW');
			})
			->update([
				'status' => 'PST',
				'DatePost' => $today,
				'batch' => $batchno
			]);
	
		$updates = DB::table('tblAcctRec')
						->where([['batch', $batchno], ['FOP', $curr_fop]])
						->get();
						
		$ttl = 0;
		foreach ($updates as $i => $update) {
			$data['postList'][$i]['acctRecID'] = $update->AcctRecID;
			$data['postList'][$i]['dateRcd'] = $update->DateRcd;
			$data['postList'][$i]['resNum'] = $update->ResNum;
			$data['postList'][$i]['amount'] = $update->Amount;			
			$ttl += $update->Amount;	
		}
		$data['total'] = number_format($ttl, 2, '.', '');
	
		return $data;
	}
}