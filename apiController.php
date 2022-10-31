<?php
require_once 'apiconnect.php';

/* Setting a custom WP-REST API endpoint to listen to Selcom callbacks */
add_action('rest_api_init', function() {
    register_rest_route(
       'selcom-push/v2', //Namespace
       '/selcomstat', //Callback endpoint
        array (
          'methods'  => 'POST',
          'callback' => 'receiveApiCallback',
          'permission_callback' => '__return_true',
        )
    );
});

//Creating a minimal order to send to Checkout API before initiating payment
function sendMinOrder ($minOrder, $apiKey, $apiSecret, $baseUrl) {
    //Set API endpoint
    $apiEndpoint = "/checkout/create-order-minimal";
    $url = $baseUrl.$apiEndpoint;
 
    //Set POST request variables
    $isPost =1;
    $timestamp = date('c');
    $authorization = base64_encode($apiKey);
    $signedFields  = implode(',', array_keys($minOrder));
    $digest = computeSignature($minOrder, $signedFields, $timestamp, $apiSecret);
 
    //Make HTTP POST Request for Minimal Order
    return sendHTTPRequest($url, $isPost, json_encode($minOrder), $authorization, $digest, $signedFields, $timestamp);
 }
 
 //Initiating payment via Push USSD
 function sendUSSDPush ($pushRequest, $apiKey, $apiSecret, $baseUrl) {
    //Set API endpoint
    $apiEndpoint = "/checkout/wallet-payment";
    $url = $baseUrl.$apiEndpoint;
 
    //Set POST request variables
    $isPost =1;
    $timestamp = date('c');
    $authorization = base64_encode($apiKey);
    $signedFields  = implode(',', array_keys($pushRequest));
    $digest = computeSignature($pushRequest, $signedFields, $timestamp, $apiSecret);
 
    //Make HTTP POST Request for USSD Push to Wallet
    return sendHTTPRequest($url, $isPost, json_encode($pushRequest), $authorization, $digest, $signedFields, $timestamp);
 }
 
/* Receive final callback from Selcom API */
function receiveApiCallback (WP_REST_Request $request) {
    //Creating an array to hold callback parameters
    $finalCallback = array();
 
    //Create JSON data object from request object
    $params = json_decode(stripslashes($request->get_body()));
     
    //Validate variables and set response
    if (isset($params)) {
        //Assign request params
        $finalCallback['resultCode'] = $params->resulcode;
        $finalCallback['result'] = $params->result;
        $finalCallback['paymentStatus'] = $params->payment_status;
        $finalCallback['orderId'] = $params->order_id;
        $finalCallback['transactionId'] = $params->transid;
        $finalCallback['reference'] = $params->reference;
 
        return array("error"=>200,"result"=>'Success',"order_id"=>$params->order_id,"message"=>'Callback successful');
    }
    else {
       return array("error"=>424,"result"=>'Failed',"message"=>'Callback failed');
    }
}

 //Getting order status to complete inventory updates & process delivery
 function fetchOrderStatus($minOrder,$baseUrl) {
    //Set API endpoint
    $apiEndpoint = '/checkout/order-status?order_id='.$minOrder->order_id;
    $url = $baseUrl.$apiEndpoint;

    //Set GET request variables
    $isPost =0;
    $timestamp = date('c');
    $authorization = base64_encode($apiKey);
    $signedFields  = implode(',', array_keys(array('order_id' => $minOrder->order_id)));
    $digest = computeSignature($pushRequest, $signedFields, $timestamp, $apiSecret);
 
    // TODO: Set to GET Request to Fetch Order Status
    //Make HTTP GET Request to fetch order status
    return sendHTTPRequest($url, $isPost, json_encode($pushRequest), $authorization, $digest, $signedFields, $timestamp);
    
    return json_decode($result);
 }

?>