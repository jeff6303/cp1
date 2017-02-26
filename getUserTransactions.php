<?php

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

function getMonthlyData($transactionList, $ignoreMerchantList = null) {
	$agregateData = array();
	foreach($transactionList as $transactionData) {
		// check if we should filter the transaction (currently ba
		if(!$ignoreMerchantList || empty($ignoreMerchantList) || !in_array($transactionData['merchant'], $ignoreMerchantList)) {
			$val = $transactionData['amount'];
			$transactionTime = $transactionData['transaction-time'];
			$ts = strtotime($transactionTime);
			$yr = date('Y', $ts);
			$month = date('n', $ts);
			$day = date('j', $ts);
			$key = $yr . '-' . $month;
			if(!isset($agregateData[$key])) {
				// I intentionally am leaving the income/spent without the $ sign for the return as this would make it simplier to
				// interpret and use from the client without needing to parse the $ out.
				$monthData = array(
					"income" => 0,
					"spent" => 0
				);
				$agregateData[$key] = $monthData;
			}
			if($val < 0) {
				$agregateData[$key]['spent'] += round($val / 10000, 2) * -1;
			} else {
				$agregateData[$key]['income'] += round($val / 10000, 2);
			}
		} else {
			echo 'ignore donuts transaction\r\n';
		}
	}
	return $agregateData;
}

function print_month_data($monthlyData) {
	echo json_encode($monthlyData);
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
		$monthData = getMonthlyData($fullTransactionList, $merchantExcludeList);
		
		print_month_data($monthData);
	}	
} else {
	echo "NO!";
}
curl_close($curl_handle);

?>
