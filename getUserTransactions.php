<?php

$DEFAULT_USER_ID = 1110590645;
$DEFAULT_AUTH_TOKEN = 'C811FEE4B14573EBC14DA2079748DEE3';
$DEFAULT_APP_TOKEN = 'AppTokenForInterview';

$curl_handle = setupCurl($DEFAULT_USER_ID, $DEFAULT_AUTH_TOKEN, $DEFAULT_APP_TOKEN);

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

function curlPost($curl_handle, $url) {
	curl_setopt($curl_handle, CURLOPT_URL, $url);
	$response = curl_exec($curl_handle);
	return $response;
}

	
$response = curlPost($curl_handle, 'https://2016.api.levelmoney.com/api/v2/core/get-all-transactions');
var_dump($response);

curl_close($curl_handle);

?>
