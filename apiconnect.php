<?php

//Sending HTTP POST Request to SELCOM API
function sendHTTPRequest($url, $isPost, $json, $authorization, $digest, $signed_fields, $timestamp) {
    $headers = array(
      "Content-type: application/json;charset=\"utf-8\"", "Accept: application/json", "Cache-Control: no-cache",
      "Authorization: SELCOM $authorization",
      "Digest-Method: HS256",
      "Digest: $digest",
      "Timestamp: $timestamp",
      "Signed-Fields: $signed_fields",
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if($isPost){
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch,CURLOPT_TIMEOUT,90);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result);
}

?>