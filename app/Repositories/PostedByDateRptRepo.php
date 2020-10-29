<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Repositories\TBCommonRepo;
use App\Repositories\PaymRepo;

class PostedByDateRptRepo {	
	public $commonRepo, $paymRepo;

	public function __construct() {
		$this->commonRepo = new TBCommonRepo();
		$this->paymRepo = new PaymRepo();
	}
	
	public function generateRpt($request) {
		//print_r($request->all()); exit;
		//error_reporting(E_ALL);
		//ini_set('display_errors', 1);
		ini_set('max_execution_time', 600);
		ini_set('memory_limit', '1024M');
		
		$rules = [
			'FromDate' => array('regex:/^\d{4}\-\d{2}\-\d{2}$/'),
			'ToDate' => array('regex:/^\d{4}\-\d{2}\-\d{2}$/'),
			'Dept' => array('in:All,2,3,5,6,12,13,14,15,16,17,18,20,21,22,23,24,25,26,27,28')
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
		
		$department = isset($request->Dept) ? trim($request->Dept) : '';
				
		// RES-254 -> changing from fixed date search to range search
		$dtpostBegin = $request->FromDate; // RES-254
		$dtpostEnd = $request->ToDate;	// RES-254

		// RES-255 - Department filter
		if (isset($department) && $department != 'All')
		{
			$deptFilter = ' AND B.department = ' . $department;
		} else {
			// All departments
			$deptFilter = null;
		}

		$sql = "select B.ResNum, A.FOP, A.status, A.DateRcd, A.Amount, A.CCNum, A.Description, AG.AgName, P.LastName, P.FirstName, A.batch from tblAcctRec as A, tblBooking as B, tblPax as P, tblAgency as AG where A.status = 'PST' and A.DatePost BETWEEN '$dtpostBegin' AND '$dtpostEnd' and B.ResNum=A.ResNum and P.PaxID=A.PaxID and AG.AgCode=B.AgCode$deptFilter order by A.batch, FOP, ResNum, AcctRecID;" // RES-254
		;
		$rows = DB::table('tblAcctRec as A')
					->select(DB::raw('B.ResNum, A.FOP, A.status, A.DateRcd, A.Amount, A.CCNum, A.Description, AG.AgName, P.LastName, P.FirstName, A.batch'))
					->join('tblBooking as B', 'B.ResNum', '=', 'A.ResNum')
					->join('tblPax as P', 'P.PaxID', '=', 'A.PaxID')
					->join('tblAgency as AG', 'AG.AgCode', '=', 'B.AgCode')
					->where('A.status', 'PST')
					->whereBetween('A.DatePost', [$dtpostBegin, $dtpostEnd]);
					
		if (isset($department) && $department != 'All') {
			$rows = $rows->where('B.department', $department);
		}
					
		$rows = $rows->orderBy('A.batch')
					->orderBy('A.FOP')
					->orderBy('B.ResNum')
					->orderBy('A.AcctRecID')
					->get();
		
		$deptName = 'All Departments';
		if ($department && $department != 'All') {
			$dept = DB::table('tblDepartments')
						->where('DepartmentID', $department)
						->get()->first();
						
			$deptName = $dept->Department;
		}					
		
		$posted = array();		
		$grandtotal = 0.00;	
		
		if (count($rows)) {
			//$msg = $dtpostBegin==$dtpostEnd ? $dtpostBegin : "from $dtpostBegin to $dtpostEnd"; // RES-254
			//$output = "<H2 align=center>Payments Posted $msg</H2>\n";
			
			$ind = 0;
			$row = isset($rows[$ind]) ? $rows[$ind] : false;
			$has_more = true;
			
			while ($has_more && $row) {
				// Get FOP info
				$current_fop = $row->FOP;
				$current_batch=$row->batch;
				$current_ind = $current_fop.'-'.$current_batch;
				
				//get FOP name
				$descrip = '';
				if ($current_fop) {
					$fopData = DB::table('tblFOP')
								->where('FOPCode' , $current_fop)
								->get()->first();
								
					if ($fopData)
						$descrip = $fopData->Description;						
				}
				
				// Display FOP info
				/*$output .= "<B>Batch #$current_batch / Form of Payment:  $current_fop" . $descrip . NL.
					"<TABLE border=1 cellspacing=0 cellpadding=1><TR><TH>Res</TH><TH>Date Rec</TH><TH>Pax</TH><TH>Agency</TH><TH>CC Number</TH><TH>Description</TH><TH>Amount</TH></TR>\n";*/
					
				$posted[$current_ind]['batchNo'] = $current_batch;
				$posted[$current_ind]['fop'] = $current_fop;
				$posted[$current_ind]['fopDesc'] = $descrip;
					
				$subtotal = 0.00;
				$payms = array();
				$pind = 0;
				
				while ($has_more && $row && $current_fop == $row->FOP && $current_batch==$row->batch) {
					if (!$row->Description) {
						$row->Description = "-";
					}
					$name = $row->LastName;
					if ($row->FirstName) $name .= "/$row->FirstName";
					
					/*$output .= "\t<TR>\n" .
						"\t\t<TD align=right>$row->ResNum</TD>\n" .
						"\t\t<TD align=right>$row->DateRcd</TD>\n" .
						"\t\t<TD align=left>$name</TD>\n" .
						"\t\t<TD align=left>$row->AgName</TD>\n" .
						"\t\t<TD align=left>".cc_decrypt($row->CCNum)."</TD>\n" .
						"\t\t<TD align=$align>$row->Description</TD>\n" .
						"\t\t<TD align=right>\$$row->Amount</TD>\n" .
						"\t</TR>\n";*/
		
					$payms[$pind]['resNum'] = $row->ResNum;		
					$payms[$pind]['dateRec'] = $row->DateRcd;
					$payms[$pind]['pax'] = $name;
					$payms[$pind]['agBane'] = $row->AgName;
					$payms[$pind]['ccNum'] = $this->paymRepo->cc_decrypt($row->CCNum);
					$payms[$pind]['desc'] = $row->Description;
					$payms[$pind]['amt'] = $row->Amount;
		
					$subtotal += $row->Amount;
					$grandtotal += $row->Amount;						
					
					$pind++;
					
					$ind++;
					if (isset($rows[$ind])) {
						$row = $rows[$ind];
					}
					else {
						$has_more = false;
					}	
				}
				
				$posted[$current_ind]['payms'] = $payms;
				
				//$output .= "<TR><TD colspan=6 align=right>$current_fop total:</TD><TD align=right>\$" . number_format ($subtotal, 2) . "</TD></TR></TABLE>" . NL;
				$posted[$current_ind]['total'] = number_format ($subtotal, 2, '.', '');
			}
			//$output .= "<H3>Total:  \$" . number_format ($grandtotal, 2) . "</H3>\n";	
			//echo ("$output");
		}
		
		$result['status'] = 'success';
		$result['dept'] = $deptName;
		$result['paym_method'] = $posted;
		$result['grand_total'] = number_format ($grandtotal, 2, '.', '');
		
		return $result;
	}
}