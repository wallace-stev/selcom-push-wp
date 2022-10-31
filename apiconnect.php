<?php
//Creating digest hash as per Selcom API docs
function computeSignature($parameters, $signedFields, $requestTimestamp, $apiSecret){
  $fieldsOrder = explode(',',$signedFields);
  $signData = "timestamp=$requestTimestamp";
  foreach ($fieldsOrder as $key) {
     $signData .= "&$key=".$parameters[$key];
  }

  //Base64 encode of HS256 Signature
  return base64_encode(hash_hmac('sha256', $signData, $apiSecret, true));
}

//Sending HTTP POST Request to SELCOM API
function sendHTTPRequest($url, $isPost, $json, $authorization, $digest, $signedFields, $timestamp) {
    $headers = array(
      "Content-type: application/json;charset=\"utf-8\"", "Accept: application/json", "Cache-Control: no-cache",
      "Authorization: SELCOM $authorization",
      "Digest-Method: HS256",
      "Digest: $digest",
      "Timestamp: $timestamp",
      "Signed-Fields: $signedFields",
    );
    $curlObj = curl_init();
    curl_setopt($curlObj, CURLOPT_URL, $url);
    if($isPost){
      curl_setopt($curlObj, CURLOPT_POST, 1);
      curl_setopt($curlObj, CURLOPT_POSTFIELDS, $json);
    }
    curl_setopt($curlObj, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curlObj, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curlObj,CURLOPT_TIMEOUT,90);
    $result = curl_exec($curlObj);
    curl_close($curlObj);
    return json_decode($result);
}
?>