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
                    'default'       => 'Pay directly from your mobile phone using Tigopesa or Airtel Money.',
                    'description'   => 'Pay directly from your mobile phone using Tigopesa or Airtel Money.',
                )
            );
        }
        
        function process_payment( $order_id ) {
            global $woocommerce;

            $order = new WC_Order( $order_id );

            /****

                Here is where you need to call your payment gateway API to process the payment
                You can use cURL or wp_remote_get()/wp_remote_post() to send data and receive response from your API.

            ****/

            //Based on the response from your payment gateway, you can set the the order status to processing or completed if successful:
            $order->update_status('on-hold', __( 'FAIL', 'woocommerce' ));

            //Error handling
            wc_add_notice( __('Payment error: ', 'woothemes') . 'Transaction failed', 'error' );
            
            //once the order is updated clear the cart and reduce the stock
            $woocommerce->cart->empty_cart();
            $order->reduce_order_stock();

            //if the payment processing was successful, return an array with result as success and redirect to the order-received/thank you page.
            return array(
                'result' => 'SUCCESS',
                'redirect' => $this->get_return_url( $order )
            );
        }
    }
}

function add_selcom( $methods ) {
    $methods[] = 'SelcomGateway'; 
    return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_selcom' );

?>
