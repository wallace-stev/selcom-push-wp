<?php
/*
    * Plugin Name: e-Push
    * Plugin URI: https://www.aspiretz.com/
    * Description: A plugin to enable Selcom USSD Push to Wallet from WooCommerce website. Currently available for Tigopesa & Airtel Money only.
    * Version: 1.0.2
    * Author: Aspire Creative
    * Author URI: https://www.aspiretz.com/
    * License: GNU General Public License v3 or later
    * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
    * Text Domain: e-Push
    * GitHub Plugin URI: https://github.com/wallace-stev/epush-selcom-wp 
    * Domain Path: /i18n
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_action( 'plugins_loaded', 'selcom_init' );

function selcom_init() {
    class SelcomGateway extends WC_Payment_Gateway {
        function __construct(){
            $this->id                 = 'wc_selcompay';
            $this->method_title       = 'Selcom USSD Push';
            $this->title              = 'Selcom USSD Push';
            $this->has_fields         = true;
            $this->method_description = 'Pay directly from using Tigopesa or Airtel Money from your mobile phone.';

            //Load plugin on WP Dashboard settings
            $this->init_form_fields();
            $this->init_settings();
            $this->enabled = $this->get_option('enabled');
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option('description');

            //process settings with parent method
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        public function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title'         => __( 'Enable/Disable', 'woocommerce' ),
                    'type'          => 'checkbox',
                    'label'         => __( 'Enable Selcom payments', 'woocommerce' ),
                    'default'       => 'yes'
                ),
                'title' => array(
                    'title'         => __( 'Title', 'woocommerce' ),
                    'type'          => 'text',
                    'description'   => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default'       => __( 'Pay via Tigopesa / Airtel Money', 'woocommerce' ),
                    'desc_tip'      => true,
                ),
                'description' => array(
                    'title'         => __( 'Customer Message', 'woocommerce' ),
                    'type'          => 'textarea',
                    'default'       => 'Pay directly from your mobile phone using Tigopesa or Airtel Money. Conveniently, securely & efficiently.',
                    'description'   => 'This controls the description which the user sees during checkout.',
                )
            );
        }
        
        function process_payment( $order_id ) {
            global $woocommerce;

            // Get an instance of the WC_Order object
            $order = new WC_Order( $order_id );
            
            //Selcom API prerequisites
            $api_key = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
            $api_secret = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
            $vendor = 'xxxxxxxxxx';
            $base_url = "http://example.com/v1";
            
            //Minimal order array
            $min_order = array(
                "vendor"=>$vendor,
                "order_id"=>$order->get_id(),
                "buyer_email"=>$order->get_billing_email(),
                "buyer_name"=>$order->get_billing_first_name() ." ". $order->get_billing_last_name(),
                "buyer_phone"=>$order->get_billing_phone(),
                "amount"=>$order->get_total(),
                "currency"=>"TZS",
                "no_of_items"=>$woocommerce->cart->cart_contents_count
            );
            
            //Send Minimal Order Request
            $order_resp = sendMinOrder($min_order, $api_key, $api_secret, $base_url);
            
            //Based on the order response, continue implementing USSD Push
            if ($order_resp->result == 'FAIL') {
                //Update order status
                $order->update_status('failed', __( 'Order not created', 'woocommerce' ));

                //Display error message
                wc_add_notice( __('Error: ', 'woothemes') . 'Order could not be created.', 'error' );
            }
            else if ($order_resp->result == 'SUCCESS') {
                //Order is sent, set Push USSD Request Variables
                // TODO: Generate a random encrypted transid here
                $transid = random_function();
                $push_req = array(
                    "transid"=> $transid,
                    "order_id"=>$order->get_id(),
                    "msisdn"=>$order->get_billing_phone(),
                );
                
                //Send Push USSD Request
                $pay_resp = sendUSSDPush($push_req, $api_key, $api_secret, $base_url);
                
                if ($pay_resp->result == 'FAIL') {
                    //Update order status
                    $order->update_status('failed', __( 'Payment failed', 'woocommerce' ));

                    //Display error message
                    wc_add_notice( __('Error: ', 'woothemes') . 'Payment not completed.', 'error' );
                }
                else if ($pay_resp->result == 'SUCCESS') {
                    //Payment successful, update order status
                    $order->update_status('processing', __( 'Payment successful, awaiting delivery', 'woocommerce' ));
                    
                    //Update update stock & cart information
                    WC()->cart->empty_cart();
                    wc_reduce_stock_levels($order);

                    //Return to thank you page
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order )
                    );
                }
                else {
                    //Update order status
                    $order->update_status('on-hold', __( 'Payment could not be completed.', 'woocommerce' ));

                    //Display error message
                    wc_add_notice( __('Payment error: ', 'woothemes') . 'Something went wrong during payment.', 'error' );
                }
            }
            else {
                //Update order status
                $order->update_status('on-hold', __( 'Order could not be completed', 'woocommerce' ));

                //Display error message
                wc_add_notice( __('Order error: ', 'woothemes') . 'Something went wrong with the order.', 'error' );
            }
        }
    }
}

//Creating a minimal order to send to Checkout API before initiating payment
function sendMinOrder ($min_order, $api_key, $api_secret, $base_url) {
    //Set API endpoint
    $api_endpoint = "/checkout/create-order-minimal";
    $url = $base_url.$api_endpoint;
    
    //Set POST request variables
    $isPost =1;
    $timestamp = date('c');
    $authorization = base64_encode($api_key);
    $signed_fields  = implode(',', array_keys($min_order));
    $digest = computeSignature($min_order, $signed_fields, $timestamp, $api_secret);
    
    //Make HTTP POST Request for Minimal Order
    return sendHTTPRequest($url, $isPost, json_encode($min_order), $authorization, $digest, $signed_fields, $timestamp);
}

function sendUSSDPush ($push_req, $api_key, $api_secret, $base_url) {
    //Set API endpoint
    $api_endpoint = "/checkout/wallet-payment";
    $url = $base_url.$api_endpoint;
    
    //Set POST request variables
    $isPost =1;
    $timestamp = date('c');
    $authorization = base64_encode($api_key);
    $signed_fields  = implode(',', array_keys($push_req));
    $digest = computeSignature($push_req, $signed_fields, $timestamp, $api_secret);
    
    //Make HTTP POST Request for USSD Push to Wallet
    return sendHTTPRequest($url, $isPost, json_encode($push_req), $authorization, $digest, $signed_fields, $timestamp);
}

//Encrypting parameter values
function computeSignature($parameters, $signed_fields, $request_timestamp, $api_secret){
    $fields_order = explode(',', $signed_fields);
    $sign_data = "timestamp=$request_timestamp";
    foreach ($fields_order as $key) {
      $sign_data .= "&$key=".$parameters[$key];
    }

    //HS256 Signature Method
    return base64_encode(hash_hmac('sha256', $sign_data, $api_secret, true));
}

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

//Finally, add Selcom payment to list of payment methods
function add_selcom( $methods ) {
    $methods[] = 'SelcomGateway'; 
    return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_selcom' );

?>