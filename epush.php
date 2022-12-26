<?php
/*
   * Plugin Name: Selcom Push WP
   * Plugin URI: https://www.aspiretz.com/
   * Description: A plugin for Selcom USSD Push to Wallet from WooCommerce website.
   * Version: 1.4.7
   * Author: Aspire Creative
   * Author URI: https://www.aspiretz.com/
   * License: GNU General Public License v3 or later
   * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
   * Text Domain: Selcom Push WP
   * GitHub Plugin URI: https://github.com/wallace-stev/selcom-push-wp
   * Domain Path: /i18n
*/

if ( ! defined( 'ABSPATH' )){
   // Exit if accessed directly
   exit("Unauthorized access");
}

require_once 'apiController.php';

/* WooCommerce: Removing some checkout fields to simplify the payment process */
add_filter('woocommerce_checkout_fields' , 'custom_override_checkout_fields');

function custom_override_checkout_fields($fields) {
   unset($fields['billing']['billing_company']);
   unset($fields['billing']['billing_address_1']);
   unset($fields['billing']['billing_address_2']);
   unset($fields['billing']['billing_postcode']);
   unset($fields['billing']['billing_state']);
   unset($fields['account']['account_username']);
   unset($fields['account']['account_password']);
   unset($fields['account']['account_password-2']);
   $fields['billing']['billing_country']['label'] = 'Country';
   return $fields;
}
 
/* Initializing the Selcom Push Plugin */
add_action('plugins_loaded', 'selcomInit');

function selcomInit() {
   class SelcomGateway extends WC_Payment_Gateway {
      public function __construct(){
         $this->id                 = 'wc_selcompay';
         $this->icon               = apply_filters('wp_selcom_icon', plugins_url('assets/selcomlogo.png', __FILE__));
         $this->method_title       = 'Selcom USSD Push';
         $this->title              = 'Selcom USSD Push';
         $this->has_fields         = true;
         $this->method_description = 'Direct payments via mobile phone using USSD Push.';

         //Load plugin on WP Dashboard settings
         $this->init_form_fields();
         $this->init_settings();
         $this->enabled = $this->get_option('enabled');
         $this->title = $this->get_option('title');
         $this->description = $this->get_option('description');
         $this->vendor  = $this->get_option('merchant_id');
			$this->api_key      = $this->get_option('api_key');
			$this->api_secret   = $this->get_option('secret_key');
         $this->api_url   = $this->get_option('api_url');
         $this->prefix   = $this->get_option('prefix');

         //process settings with parent method
         add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
      }

      public function init_form_fields(){
         $this->form_fields = array(
            'enabled' => array(
               'title'         => __('Enable/Disable', 'woocommerce'),
               'type'          => 'checkbox',
               'label'         => __('Enable Selcom Payments', 'woocommerce'),
               'default'       => 'yes'
            ),
            'title' => array(
               'title'         => __('Title', 'woocommerce'),
               'type'          => 'text',
               'description'   => __('This controls the title which the user sees during checkout.', 'woocommerce'),
               'default'       => __('Mobile phone payment \u{1F1F9}\u{1F1FF}', 'woocommerce'),
               'desc_tip'      => true,
            ),
            'description' => array(
               'title'         => __('Customer Message', 'woocommerce'),
               'type'          => 'textarea',
               'default'       => 'Pay directly from mobile phone (Tigo/Airtel).',
               'description'   => 'This controls the description which the user sees during checkout.',
            ),
            'merchant_id' => array(
					'title'       => __('Vendor/Merchant ID', 'woocommerce'),
					'type'        => 'text',
					'description' => __('Your merchant id received from Selcom.', $this->id),
					'default'     => '',
					'desc_tip'    => true,
				),
				'api_key'         => array(
					'title'       => __('API Key', 'woocommerce'),
					'type'        => 'text',
					'description' => __('Your API Key received from Selcom.', $this->id),
					'default'     => '',
					'desc_tip'    => true,
				),
				'secret_key'      => array(
					'title'       => __('Secret Key', 'woocommerce'),
					'type'        => 'text',
					'description' => __('Your Secret Key received from Selcom.', $this->id),
					'default'     => '',
					'desc_tip'    => true,
            ),
            'api_url'      => array(
					'title'       => __('API URL', 'woocommerce'),
					'type'        => 'text',
					'description' => __('Base URL for Selcom API.', $this->id),
					'default'     => 'https://apigwtest.selcommobile.com/v1',
					'desc_tip'    => true,
				),
            'prefix'      => array(
					'title'       => __('Unique Vendor Prefix', 'woocommerce'),
					'type'        => 'text',
					'description' => __('Vendor prefix for auto-generated transaction IDs.', $this->id),
					'default'     => 'ASPIRE',
					'desc_tip'    => true,
				)
         );
      }
      
      //Overriding WooCommerce Payment Function
      public function process_payment($order_id) {
         global $woocommerce;

         // Get an instance of the WC_Order object
         $order = new WC_Order($order_id);

         //Selcom API prerequisites
         $apiKey = $this->api_key;
         $apiSecret = $this->api_secret;
         $baseUrl = $this->api_url;
         $vendorPrefix = $this->prefix;
         date_default_timezone_set('Africa/Dar_es_Salaam');

         //Minimal order array
         $minOrder = array(
            "vendor"=> $this->vendor,
            "order_id"=>$order->get_id(),
            "buyer_email"=>$order->get_billing_email(),
            "buyer_name"=>$order->get_billing_first_name() ." ". $order->get_billing_last_name(),
            "buyer_phone"=>$order->get_billing_phone(),
            "amount"=>$order->get_total(),
            "webhook"=>get_site_url().'/wp-json/selcom-push/v2/selcomstat',
            "currency"=>"TZS",
            "no_of_items"=>$woocommerce->cart->cart_contents_count
         );
            
         //Send Minimal Order Request
         $orderResponse = sendMinOrder($minOrder, $apiKey, $apiSecret, $baseUrl);

         //Based on the order response, continue implementing USSD Push
         if ($orderResponse->result == 'FAIL') {
            //Update order status
            $order->update_status('failed', __('Order not created.', 'woocommerce'));

            //Display error message
            wc_add_notice( __('Error: ', 'woothemes') . 'Order could not be created.', 'error');
         }
         elseif ($orderResponse->result == 'SUCCESS') {
            //Update order status
            $order->update_status('pending', __('Order has been received, waiting for payment.', 'woocommerce'));

            //Display error message
            wc_add_notice( __('Update: ', 'woothemes') . 'Order has been created, waiting for payment completion.', 'success');

            //Order is sent, set Push USSD Request Variables
            // Generating a random encrypted transid with vendor prefix
            $transid = substr(strtoupper(substr($vendorPrefix,0,3).md5(time().$order->get_id().rand (10,1000))),0,12);
            $pushRequest = array(
               "transid"=> $transid,
               "order_id"=>$order->get_id(),
               "msisdn"=>"255".substr($order->get_billing_phone(), -9),
            );

            //Send Push USSD Request
            $paymentResponse = sendUSSDPush($pushRequest, $apiKey, $apiSecret, $baseUrl);

            if ($paymentResponse->result == 'FAIL') {
               //Update order status
               $order->update_status('failed', __('Payment failed.', 'woocommerce'));

               //Display error message
               wc_add_notice( __('Payment error: ', 'woothemes') . 'Payment not completed.', 'error');
            }
            elseif ($paymentResponse->result == 'PENDING' || $paymentResponse->result=='SUCCESS') {
               //Payment successfully initiated, update order status
               $order->update_status('on-hold', __('Payment is being processed or not yet confirmed.', 'woocommerce'));

               //Display error message
               wc_add_notice( __('Update: ', 'woothemes') . 'Payment has been sent to mobile for completion.', 'success');

               //Update cart information
               WC()->cart->empty_cart();

               //Return to thank you page
               return array(
                  'result' => 'success',
                  'redirect' => $this->get_return_url($order)
               );
            }
            else {
               //Update order status
               $order->update_status('failed', __('Payment could not be completed.', 'woocommerce'));

               //Display error message
               wc_add_notice( __('Payment error: ', 'woothemes') . 'Something went wrong during payment.', 'error');
            }
         }
         else {
            //Update order status
            $order->update_status('failed', __('Order could not be completed due to unknown reasons.', 'woocommerce'));

            //Display error message
            wc_add_notice( __('Order error: ', 'woothemes') . 'Something went wrong with the order.', 'error');
         }
      }
   }
}

//Finally, add Selcom payment to list of payment methods
function addSelcom( $methods ) {
   $methods[] = 'SelcomGateway';
   return $methods;
}

add_filter('woocommerce_payment_gateways', 'addSelcom');

add_filter('plugin_row_meta',  'Register_Plugins_Links', 10, 2);
function Register_Plugins_Links ($links, $file) {
   $base = plugin_basename(__FILE__);
   if ($file == $base) {
      $links[] = '<a href="https://github.com/wallace-stev/selcom-push-wp">' . __('📦 View on Github') . '</a>';
      $links[] = '<a href="https://github.com/wallace-stev/selcom-push-wp/issues">' . __('📝 Report an Issue') . '</a>';
   }
   return $links;
}
