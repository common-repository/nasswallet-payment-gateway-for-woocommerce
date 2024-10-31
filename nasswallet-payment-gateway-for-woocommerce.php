<?php
/**  
Plugin Name: NassWallet Payment Gateway for WooCommerce
description: Accept payments on your WooCommerce store with NassWallet Payment Gateway.
Version: 1.1
Author:<a href="https://nw.iq">NassWallet</a>
 */

defined( 'ABSPATH' ) || exit;

require 'vendor/autoload.php';

use GuzzleHttp\Client;

add_filter('woocommerce_payment_gateways', 'nasswallet_add_gateway_class');

add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), 'nw_settings_link' );

function nw_settings_link( $links ) {
	// Build and escape the URL.
	$url = esc_url( add_query_arg(
		'page',
		'wc-settings',
		get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=nasswallet' 
	) );
	// Create the link.
	$settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';
	 
	array_unshift(
		$links,
		$settings_link
		
	);
	return $links;
}//end nw_settings_link()



function nasswallet_add_gateway_class($gateways)
{
    $gateways[] = 'NassWallet_Payment_Gateway';
    return $gateways;
} 


add_action('plugins_loaded', 'nasswallet_init_gateway_class');



function nasswallet_init_gateway_class()
{

    class NassWallet_Payment_Gateway extends WC_Payment_Gateway
    {

        public  $id, $icon, $has_fileds, $method_title, $method_description, $title="NassWallet", $description, $enabled, $test_mode, $merchant_id, $merchant_password, 
        $merchant_mpin, $authorization_key, $grant_type, $language_code, $base_url, $redirect_url, $exchange_rate_enabled, $exchange_rate_value,$redirection_url, $client, $settings_link ;
       
        
        public function __construct()
        {

            $this->id = 'nasswallet';
            $this->icon = '';
            $this->has_fields = false;
            $this->method_title = 'NassWallet';
            $this->method_description = 'Accept payments with NassWallet Payment Gateway for WooCommerce';
			

            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
			$this->settings_link = '<a href="' . esc_url( get_admin_url( null, 'options-general.php?page=your-plugin-settings' ) ) . '">' . __( 'Settings', 'textdomain' ) . '</a>';
            $this->title = "NassWallet";
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->test_mode = 'yes' === $this->get_option('test_mode');
            $this->merchant_id = $this->test_mode ? $this->get_option('test_merchant_id') : $this->get_option('merchant_id');
            $this->merchant_password = $this->test_mode ? $this->get_option('test_merchant_password') : $this->get_option('merchant_password');
            $this->merchant_mpin = $this->test_mode ? $this->get_option('test_merchant_mpin') : $this->get_option('merchant_mpin');
            $this->authorization_key = "Basic TUVSQ0hBTlRfUEFZTUVOVF9HQVRFV0FZOk1lcmNoYW50R2F0ZXdheUBBZG1pbiMxMjM=";
            $this->grant_type = "password";
            $this->language_code = "en";
            $this->base_url =  $this->test_mode ? 'https://uatgw1.nasswallet.com/payment/transaction/' : 'https://gw-api.nasswallet.com/phase3/payment/transaction/';
            $this->redirect_url = $this->test_mode ? 'https://uatcheckout1.nasswallet.com/payment-gateway' : 'https://checkout.nasswallet.com/payment-gateway';
            $this->exchange_rate_enabled = 'yes' === $this->get_option('multi_currency_enabled');
            $this->exchange_rate_value =   $this->get_option('exchange_rate');
			$this->redirection_url = $this->get_option('redirection_url');
			
			
			
            // This action hook saves the settings
           			
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            add_action('woocommerce_api_nasswallet-callback', array($this, 'callback_handler'));
			

            $this->client = new Client([
                'base_uri' => $this->base_url,
                'timeout' => '3000'
            ]);
			
        }


        public function init_form_fields()

        {

            $this->form_fields = array(

                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable NassWallet Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),

                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'text',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with NassWallet',
                    'disabled'    => false
                ),
                'test_mode' => array(
                    'title'       => 'Test Mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using NassWallet test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),

                'test_merchant_id' => array(
                    'title'       => 'Merchant/Branch Identifier (Test)',
                    'type'        => 'text',
                    'default'     => '',
                    'description' => 'Account Identifier',
                    'desc_tip'    => true,
                ),
                'test_merchant_password' => array(
                    'title'       => 'Password (Test)',
                    'type'        => 'password',
                    'default'     => '',
                    'description' => 'Merchant/Branch password which provided by NassWallet',
                ),

                'test_merchant_mpin' => array(
                    'title'       => 'MPIN (Test)',
                    'type'        => 'password',
                    'description' => 'Test Merchant MPIN code',
                    'default'     => ''
                ),

                'merchant_id' => array(
                    'title'       => 'Permanent Merchant/Branch Identifier',
                    'type'        => 'text',
                    'description' => "Merchant's permanent wallet identifier.",
                    'desc_tip'    => true,
                ),
                'merchant_password' => array(
                    'title' => 'Password',
                    'type' => 'password',
                    'description' => "Merchant/Branch's permanent wallet Password.",
                    'desc_tip'    => true,
                ),
                'merchant_mpin' => array(
                    'title' => 'MPIN',
                    'type' => 'password',
                    'description' => "Merchant/Branch's permanent wallet MPIN code.",
                    'desc_tip'    => true,
                ),
				 'redirection_url' => array(
                    'title'       => 'Redirection URL',
                    'type'        => 'text',
                    'description' => 'In case the payment failed, by default the customer will be redirected to the home page you can override this behaviour from here',
                    'default'     => ''
                ),
               
                'multi_currency_enabled' => array(
                    'title'       => 'Multi currecny Support',
                    'label'       => 'Enable Multi currecny Support',
                    'type'        => 'checkbox',
                    'description' => 'Enable Exchange rate for your default store currecny',
                    'default'     => 'no',
                    'desc_tip'    => true,
                ),

                'exchange_rate' => array(
                    'title'       => 'Exchange Rate',
                    'type'        => 'number',
                    'default'     => '0.00',
                    'description' => 'Enter your currency exchange rate carefully, by default NassWallet accepts only IQD Currency.',
                    'desc_tip'    => true,
                ),
            );
        }
		
		

		/* Store initial transaction data from NassWallet APIs temporarily using transients
           as it is required for validating the payment in callback handler (webhook) */

        public function store_nasswallet_transaction_details($transaction_details)
        {
            Set_transient($transaction_details['inital_id'], $transaction_details, 3000);
        }

		
        public function set_checkout_url($url)
        {
		 
            Set_transient('url', $url, 3000);	
		 
        }




        //stpes to process the paymetn

        public function process_payment($order_id)
        {

            global $woocommerce;

            $order = new WC_Order($order_id);

            $order_currency = $order->get_currency();


        /* Checking the currency as NassWallet can accept only IQD
           othwersie we will use the exchange rate to proceed the payments. */

            if ($order_currency == "IQD") {

                $order->update_status('wc-pending', __('Awaiting cheque payment', 'woocommerce'));

                $amount = $order->get_total();
				
                $nasswallet_checkout_page = $this->generate_nasswallet_checkout_page($order_id, $amount);

                return array(
                    'result' => 'success',
                    'redirect' =>  $nasswallet_checkout_page
                );

                }

            elseif($this->exchange_rate_enabled == true && $this->exchange_rate_value > 0) 

                {

                $order->update_status('on-hold', __('Awaiting cheque payment', 'woocommerce'));

                $amount = $order->get_total() *  $this->exchange_rate_value;

                $nasswallet_checkout_page = $this->generate_nasswallet_checkout_page($order_id, $amount);

                return array(
                    'result' => 'success',
                    'redirect' =>  $nasswallet_checkout_page
                );
                } 
            else 
                {
                wc_add_notice('Payment error: ' . $order_currency . ' currency is not supported', 'error');
            }
        }


         //Initiate the transaction and generate unique NassWallet checkout url
         
         public function generate_nasswallet_checkout_page($order_id, $amount)
         {
 
             $access_token =  $this->get_nasswallet_access_token();
 
             $body = [
                 'data' => [
                     'userIdentifier' => $this->merchant_id,
                     'transactionPin' => $this->merchant_mpin,
                     'orderId' => "{$order_id}",
                     'amount' => "{$amount}",
                     'languageCode' => $this->language_code
                 ]
             ];
 
             try {
                 $response = json_decode($this->client->request('POST', 'initTransaction', [
                     "headers" => ['authorization' => "Bearer $access_token"],
                     "json" => $body
                 ])->getBody());
                 } catch (ClientException $e) {
                 wc_add_notice('Sorry! Something went wrong.', 'error');
                 }
 
 
             if ($response->responseCode == 0 && $response->data->transactionId) {
 
                 //Store initial transction details temporarily for verifying payment in callback handler

                 $transaction_details = array(
                     'inital_id'     => $response->data->transactionId,
                     'order_amount'  => $amount,
                     'order_id'  => $order_id,
                 );
     
                 $this->store_nasswallet_transaction_details($transaction_details);				
 
                 return "{$this->redirect_url}?={$response->data->transactionId}&token={$response->data->token}&userIdentifier={$this->merchant_id}";
             } 
             else 
             {
                 wc_add_notice('Sorry! Something went wrong. Error Code: ' . $response->responseCode, 'error');
             }
         }

        
        public function get_nasswallet_access_token()
        {
            $body = [
                'data' => [
                    'username' => $this->merchant_id,
                    'password' => $this->merchant_password,
                    'grantType' => $this->grant_type
                ]
            ];

            try 
            {
                $response = json_decode($this->client->request('POST', 'login', [
                    "headers" => ['authorization' => "$this->authorization_key"],
                    'json' => $body

                ])->getBody());
            } 
            catch (ClientException $e) 
            {
                wc_add_notice('Sorry! Something went wrong.', 'error');
            }

            if ($response->responseCode == 0 && $response->data->access_token) {
                return $response->data->access_token;
            } 
            else 
            {
                wc_add_notice('Sorry! Something went wrong.', 'error');
            }
        }


        //validate the payment and change order status

        public function callback_handler()
        {
        
			global $woocommerce;
		
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {

                $returned_callback_data = json_decode(file_get_contents('php://input'), TRUE);
                $returned_order_id = $returned_callback_data['data']['orderId'];
                $returned_order_amount = $returned_callback_data['data']['amount'];
                $returned_init_id = $returned_callback_data['data']['InitTransactionId'];
                $returned_transaction_status = $returned_callback_data['data']['transactionStatus'];
                $transient = get_transient($returned_init_id);
			 	
                try {
                    if ($returned_transaction_status == 0 && $returned_init_id == $transient['inital_id'] && $returned_order_id == $transient['order_id'] && $returned_order_amount == $transient['order_amount']) {
 
                        $order = wc_get_order($transient['order_id']);

                        $order->payment_complete();

//   						$url = $this->get_return_url( $order );
                        
//  						$this->set_checkout_url($url);
						
					    delete_transient($returned_init_id);
						
                        echo "SUCCESS";

                        die();
                    }
                } catch (Exception $e) {
                    echo "Verification Failed: $e";
                    die();
                }
            }

            /*NassWallet will redirect the user to woocommerce checkout page 
              by making GET request to callback handler method imedaitly after proceeding the payment. */

            /* For each payment, there are two requests by NassWallet for the Callback Handler
              (POST: to validate the payment) and (GET: to show the final checkout to the customer) */

//             if ($_SERVER['REQUEST_METHOD'] === 'GET') {
					
 					
//          		    $url = get_transient('url');
				   
// 					if (!empty($url)) {
// 						header("Location: $url");	
// 					} else {
// 						$url = empty($this->redirection_url) ? site_url() : $this->redirection_url;
// 						header("Location: $url");
// 					}
				
// 					delete_transient('url');

//                 die();
//             }
         }
    }
}
