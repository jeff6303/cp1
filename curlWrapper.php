<?php
	
//class to make curl easier to adapt and modify once curl is set up.  Should be more full-funcitonal and wrap
//the curl functionality but for now it just allows us a method so we can modify curl fields easier.
class curlWrapper {
	public $optArray;
	public $curlHandle;
	public $defaultPostFields;
	
	// preforms a post call to curl wrapper.  Currently doesnt check for valid curl we should expand this.
	function curlPost($url, $additionalPostFields = null) {
		$curl_handle = $this->curlHandle;
		curl_setopt($curl_handle, CURLOPT_URL, $url);
		if($additionalPostFields) {
			$mergePost = array_merge($this->defaultPostFields, $additionalPostFields);
			curl_setopt($curl_handle, CURLOPT_POSTFIELDS, json_encode($mergePost));
		}
	
		$response = curl_exec($curl_handle);
		return $response;
	}
}

?>