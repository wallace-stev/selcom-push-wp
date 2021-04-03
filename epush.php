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

            //load the settings
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
                    'description'   => 'Pay directly from your mobile phone using Tigopesa or Airtel Money. Conveniently, securely & efficiently.',
                )
            );
        }
        
        function process_payment( $order_id ) {
            global $woocommerce;

            // Get an instance of the WC_Order object
            $order = new WC_Order( $order_id );

            //Process payment via Selcom API using cURL and receive API.            
            $api_key = 'xxxxxxxxxxxxxxxxxxx';
            $api_secret = 'xxxxxxxxxxxxxxxxxxxxx';
            
            $base_url = "https://example.com/v1";
            $api_endpoint = "/testpay/makepay";
            $url = $base_url.$api_endpoint;
            
            $isPost =1;
            $req = array("transid"=>$order->get_id(), "utilityref"=>"xxxxxxx", "amount"=>$order->get_total(), "vendor"=>"xxxxxxxx", "msisdn"=>$order->get_billing_phone()());
            $authorization = base64_encode($api_key);
            $timestamp = date('c');
            
            $signed_fields  = implode(',', array_keys($req));
            $digest = computeSignature($req, $signed_fields, $timestamp, $api_secret);
            
            $response = sendJSONPost($url, $isPost, json_encode($req), $authorization, $digest, $signed_fields, $timestamp);
            
            //Based on the response, you can set the the order status to processing or completed if successful:
            if ($response == 'FAIL') {
                $order->update_status('Failed', __( 'Payment failed', 'woocommerce' ));
                //Error handling
                wc_add_notice( __('Payment error: ', 'woothemes') . 'Transaction failed', 'error' );
            }
            else if ($response == 'SUCCESS') {
                //Payment successful
                $woocommerce->cart->empty_cart();
                $order->reduce_order_stock();
                
                //Update order status
                $order->update_status('Processing', __( 'Payment successful, awaiting delivery', 'woocommerce' ));
                
                //Return to order-received/thank you page
                return array(
                    'result' => 'SUCCESS',
                    'redirect' => $this->get_return_url( $order )
                );
            }
            else {
                //Default order status update
                $order->update_status('On-Hold', __( 'Order could not be completed', 'woocommerce' ));
                //Error handling
                wc_add_notice( __('Payment error: ', 'woothemes') . 'Something went wrong with the order during payment', 'error' );
            }
        }
        
        function sendJSONPost($url, $isPost, $json, $authorization, $digest, $signed_fields, $timestamp) {
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
            return json_decode($result, true);
         }

         function computeSignature($parameters, $signed_fields, $request_timestamp, $api_secret){
            $fields_order = explode(',', $signed_fields);
            $sign_data = "timestamp=$request_timestamp";
            foreach ($fields_order as $key) {
              $sign_data .= "&$key=".$parameters[$key];
            }
        
            //HS256 Signature Method
            return base64_encode(hash_hmac('sha256', $sign_data, $api_secret, true));
         }

    }
}

//Finally, add Selcom payment to list of payment methods
function add_selcom( $methods ) {
    $methods[] = 'SelcomGateway'; 
    return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_selcom' );

?>