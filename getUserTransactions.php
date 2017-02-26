<?php
	
class ccTransaction {
	public $id;
	public $merchant;
	public $val;
	public $transactionTime;
}

date_default_timezone_set('UTC');

$DEFAULT_USER_ID = 1110590645;
$DEFAULT_AUTH_TOKEN = 'C811FEE4B14573EBC14DA2079748DEE3';
$DEFAULT_APP_TOKEN = 'AppTokenForInterview';

$IGNORE_DONUTS_MERCHANT_NAMES = array('Krispy Kreme Donuts', 'DUNKIN #336784');

function setupCurl($uid, $userToken, $apiToken) {
	$curl_handle=curl_init();
	$headr = array('Accept: application/json', 'Content-Type: application/json');
	$postArgs = array(
		'uid' => $uid,
		'token' => $userToken,
		'api-token' => $apiToken
	);
	$optArray = array(
		CURLOPT_HTTPHEADER => $headr,
		CURLOPT_POST => 1,
		CURLOPT_POSTFIELDS => json_encode(array('args'=>$postArgs)),
		CURLOPT_RETURNTRANSFER => true
	);
	curl_setopt_array($curl_handle, $optArray);
	return $curl_handle;
}

function curlPost($curl_handle, $url, $additionalPostFields = null) {
	
	curl_setopt($curl_handle, CURLOPT_URL, $url);
	$response = curl_exec($curl_handle);
	return $response;
}

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
		// For now we just exclude the transaction, but we may want to keep track and report these back to the user?
		
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

function print_month_data($monthlyData) {
	echo json_encode($monthlyData);
}

if(in_array('--ignore-donuts', $argv)) {
	$ignoreDonuts = true;
}

if(in_array('--ignore-cc-payments', $argv)) {
	$ignoreCC = true;
}

$curl_handle = setupCurl($DEFAULT_USER_ID, $DEFAULT_AUTH_TOKEN, $DEFAULT_APP_TOKEN);
	
$response = curlPost($curl_handle, 'https://2016.api.levelmoney.com/api/v2/core/get-all-transactions');
$responseObj = json_decode($response, true);

if($responseObj['error'] && $responseObj['error'] == "no-error") {
	if($responseObj['transactions']) {
		$fullTransactionList = $responseObj['transactions'];
		if($useCrystalBall) {
			$response = curlPost($curl_handle, 'https://2016.api.levelmoney.com/api/v2/core/projected-transactions-for-month');
			$responseObj = json_decode($response, true);
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
	echo "NO!";
}
curl_close($curl_handle);

?>
