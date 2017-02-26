<?php

//small class to just keep info we use to return back for cc transaction	
class ccTransaction {
	public $id;
	public $merchant;
	public $val;
	public $transactionTime;
}

//class to make curl easier to adapt and modify once set up.  Should be more full-funcitonal and wrap
//the open/close functionality, but for now this works.
class curlWrapper {
	public $optArray;
	public $curlHandle;
	public $defaultPostFields;
}

date_default_timezone_set('UTC');

// Data for user info.  Would look to expand this to make it flexible via login to get user info/token/ect.
$DEFAULT_USER_ID = 1110590645;
$DEFAULT_AUTH_TOKEN = 'C811FEE4B14573EBC14DA2079748DEE3';
$DEFAULT_APP_TOKEN = 'AppTokenForInterview';

// Default info for no donuts.  That said, we can exclude transactions from any merchant these are just the ones called out.
$IGNORE_DONUTS_MERCHANT_NAMES = array('Krispy Kreme Donuts', 'DUNKIN #336784');

// Sets up a curl wrapper and inits the handle.
function setupCurl($uid, $userToken, $apiToken) {
	$myCurl = new curlWrapper();
	$curl_handle=curl_init();
	$headr = array('Accept: application/json', 'Content-Type: application/json');
	$postArgs = array(
		'uid' => $uid,
		'token' => $userToken,
		'api-token' => $apiToken
	);
	$myCurl->defaultPostFields = array('args'=>$postArgs);
	$optArray = array(
		CURLOPT_HTTPHEADER => $headr,
		CURLOPT_POST => 1,
		CURLOPT_POSTFIELDS => json_encode($myCurl->defaultPostFields),
		CURLOPT_RETURNTRANSFER => true
	);
	curl_setopt_array($curl_handle, $optArray);
	$myCurl->optArray = $optArray;
	$myCurl->curlHandle = $curl_handle;
	return $myCurl;
}

// preforms a post call to curl wrapper.  Currently doesnt check for valid curl we should expand this.
function curlPost($curlWrapper, $url, $additionalPostFields = null) {
	$curl_handle = $curlWrapper->curlHandle;
	curl_setopt($curl_handle, CURLOPT_URL, $url);
	if($additionalPostFields) {
		$mergePost = array_merge($curlWrapper->defaultPostFields, $additionalPostFields);
		curl_setopt($curl_handle, CURLOPT_POSTFIELDS, json_encode($mergePost));
	}

	$response = curl_exec($curl_handle);
	return $response;
}

// Main function to obtain the monthly summary data and prepare the return.
// the return contains the month/year summary for income/spent.  It also can contain cc transactions we exclude
function getMonthlyData($transactionList, $ignoreCC = false, $ignoreMerchantList = null) {
	$agregateData = array();
	$fullTransactions = array();
	$ccTransactions = array();
	foreach($transactionList as $transactionData) {
		$transactionTime = $transactionData['transaction-time'];
		$val = $transactionData['amount'];
			
		$ts = strtotime($transactionTime);
		$yr = date('Y', $ts);
		$month = date('n', $ts);
		$day = date('j', $ts);
		$yrMonthKey = $yr . '-' . $month;
		
		// check if we should filter the transaction
		if(!$ignoreMerchantList || empty($ignoreMerchantList) || !in_array($transactionData['merchant'], $ignoreMerchantList)) {
			
			if(!isset($agregateData[$yrMonthKey])) {
				// I intentionally am leaving the income/spent without the $ sign for the return as this would make it simplier to
				// interpret and use from the client without needing to parse the $ out.
				$monthData = array(
					"income" => 0,
					"spent" => 0
				);
				$agregateData[$yrMonthKey] = $monthData;
			}
			if($val < 0) {
				$agregateData[$yrMonthKey]['spent'] += round($val / 10000, 2) * -1;
			} else {
				$agregateData[$yrMonthKey]['income'] += round($val / 10000, 2);
			}
		} 
		
		// We exclude the transaction and add it to the return.  Right now we are just maintaining one of the transactions
		// but we may want to report both back to the user.
		if($ignoreCC) {
			//check if there is a transaction + or - 1 day with same val, if so assume its CC payment
			if(isset($fullTransactions[$yrMonthKey][$day][$val * -1]) ||
			isset($fullTransactions[$yrMonthKey][$day - 1][$val * -1]) ||
			isset($fullTransactions[$yrMonthKey][$day + 1][$val * -1])) {
				$ccTransaction = new ccTransaction();
				$ccTransaction->id = $transactionData['trasaction-id'];
				$ccTransaction->merchant = $transactionData['merchant'];
				$ccTransaction->val = round($val / 10000, 2);
				$ccTransaction->transactionTime = $transactionTime;
				
				//since we have already added this amt into both catagories, just take it back out then track it for output.
				$rawVal = abs(round($val / 10000, 2));
				$agregateData[$yrMonthKey]['spent'] -= $rawVal ;
				$agregateData[$yrMonthKey]['income'] -= $rawVal;
				array_push($ccTransactions, $ccTransaction);
			} 
			//Keep track of the charge so we can check if another transaction occurs with same amt later.
			else {
				$fullTransactions[$yrMonthKey][$day][$val] = true;
			}
		}
	}

	$agregateData = array('MonthSummaryData'=>$agregateData);
	if($ignoreCC) {
		$agregateData['ccTransactionExcludeData'] = $ccTransactions;
	}
	return $agregateData;
}

// We just display as a json object.  Asusmption is that this would be returned to the client and then consumed to a 
// more readible form.
function print_month_data($monthlyData) {
	echo json_encode($monthlyData);
}

// Main logic flow here
// check for the flags
if(in_array('--ignore-donuts', $argv)) {
	$ignoreDonuts = true;
}

if(in_array('--ignore-cc-payments', $argv)) {
	$ignoreCC = true;
}
if(in_array('--crystal-ball', $argv)) {
	$useCrystal = true;
}

$curlWrap = setupCurl($DEFAULT_USER_ID, $DEFAULT_AUTH_TOKEN, $DEFAULT_APP_TOKEN);
	
$response = curlPost($curlWrap, 'https://2016.api.levelmoney.com/api/v2/core/get-all-transactions');
$responseObj = json_decode($response, true);

// ensure the response is valid
if($responseObj['error'] && $responseObj['error'] == "no-error") {
	if($responseObj['transactions']) {
		$fullTransactionList = $responseObj['transactions'];
		if($useCrystal) {
			$time = time();
			$curYr = intval(date("Y"));
			$curMonth = intval(date("n"));
			$adPostInfo = array('year'=>$curYr, 'month'=>$curMonth);
			$response = curlPost($curlWrap, 'https://2016.api.levelmoney.com/api/v2/core/projected-transactions-for-month', $adPostInfo);
			$responseObj = json_decode($response, true);
			$additionalTransactionData = $responseObj['transactions'];
			$fullTransactionList = array_merge($fullTransactionList, $additionalTransactionData);
		}

		if($ignoreDonuts) {
			$merchantExcludeList = $IGNORE_DONUTS_MERCHANT_NAMES;
		} else {
			$merchantExcludeList = null;
		}
		$monthData = getMonthlyData($fullTransactionList, $ignoreCC, $merchantExcludeList);
		
		print_month_data($monthData);
	}	
} else {
	echo(json_encode(array('error'=>'invalid response for get-all-transactions', 'error-details'=>$responseObj['error'])));
}
curl_close($curlWrap->curlHandle);

?>
