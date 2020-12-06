<?php
/**
 * Plugin Name: PiratePay WooCommerce Plugin
 * Plugin URI: https://cryptocurrencycheckout.com/
 * Description: This plugin allows you to connect your WooCommerce store to the PiratePay API so you can start accepting PirateChain (ARRR) as a payment on your store. 
 * Version: 1.0.2
 * Author: cryptocurrencycheckout
 * Text Domain: piratepay-wc-gateway
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2018-2020 CryptocurrencyCheckout (support@cryptocurrencycheckout.com) and WooCommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-PiratePay-Gateway
 * @author    CryptocurrencyCheckout
 * @category  Admin
 * @copyright Copyright (c) 2018-2020 CryptocurrencyCheckout and WooCommerce
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 */
 
defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}


/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + piratepay gateway
 */
function piratepay_add_to_gateways( $gateways ) {
	$gateways[] = 'PiratePay_WC_Gateway';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'piratepay_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function piratepay_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=piratepay_gateway' ) . '">' . __( 'Configure', 'piratepay-wc-gateway' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'piratepay_gateway_plugin_links' );


/**
 * PiratePay Payment Gateway
 * This plugin allows you to connect your WooCommerce store to the PiratePay API so you can start accepting PirateChain (ARRR) as a payment on your store.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		PiratePay_WC_Gateway
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		CryptocurrencyCheckout
 */
add_action( 'plugins_loaded', 'piratepay_gateway_init', 11 );

function piratepay_gateway_init() {

	class PiratePay_WC_Gateway extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'piratepay_gateway';
			$this->icon               = apply_filters('woocommerce_piratepay_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'PiratePay', 'piratepay-wc-gateway' );
			$this->method_description = __( 'PiratePay Platform allows you to start accepting PirateChain (ARRR) on your store.', 'piratepay-wc-gateway' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title 				= $this->get_option( 'title' );
			$this->emailEnabled			= $this->get_option( 'emailEnabled' );
			$this->emailInstructions	= $this->get_option( 'emailInstructions' );
			$this->apiUrl 				= $this->get_option( 'apiUrl' );
			$this->apiToken 			= $this->get_option( 'apiToken' );
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		  
			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

		}

		
	
		/**
		 * Initialize Gateway Settings Form Fields
		 * This is where the Store will enter all of their PiratePay Settings.
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'piratepay_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable:', 'piratepay-wc-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable PiratePay Payment Option', 'piratepay-wc-gateway' ),
					'default' => 'yes'
				),

				'title' => array(
					'title'       => __( 'Title:', 'piratepay-wc-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'piratepay-wc-gateway' ),
					'default'     => __( 'PiratePay', 'piratepay-wc-gateway' ),
					'desc_tip'    => true,
				),

				'emailEnabled' => array(
					'title'   => __( 'Email Payment Option:', 'piratepay-wc-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'This option adds a fallback PiratePay option to the order email, in case the customer needs to attempt to make payment a 2nd time.', 'piratepay-wc-gateway' ),
					'default' => 'yes'

				),

				'emailInstructions' => array(
					'title'       => __( 'Email Payment Instructions:', 'piratepay-wc-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'If the above Email Payment Button is enabled these instructions will display in the Order Email, instructing the customer they can attempt to pay again if they failed to pay during checkout.', 'piratepay-wc-gateway' ),
					'default'     => __( 'Please note: if you were unable to complete your PirateChain (ARRR) payment during the checkout process, you can use the PiratePay information below to finalize your payment.', 'piratepay-wc-gateway' ),
					'desc_tip'    => true,
				),
				
				'apiUrl' => array(
					'title'       => __( 'PiratePay API URL:', 'piratepay-wc-gateway' ),
					'type'        => 'text',
					'description' => __( 'Enter your PiratePay API Link/URL: (Generated in PiratePay Dashboard, Settings Page.)' ),
					'default'     => __( '', 'piratepay-wc-gateway' ),
					'desc_tip'    => true,
				),

				'apiToken' => array(
					'title'       => __( 'PiratePay API Token Keys:', 'piratepay-wc-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'Enter your PiratePay API Token Keys: (Generated in PiratePay Dashboard, API Keys Section)' ),
					'default'     => __( '', 'piratepay-wc-gateway' ),
					'desc_tip'    => true,
				),

			) );
		}

		/**
		 * Output for the order received page.
		 *
		 * @since 1.0.0
		 * @access public
		 * @param int $order_id
		 */

		public function thankyou_page( $order_id ) {

			$order = wc_get_order( $order_id );

			$response = wp_remote_post( $this->apiUrl . '/initiate', array(
				'method'    => 'POST',
				'headers' => array(
					'Authorization'	=> 'Bearer ' . $this->apiToken,
				),
				'body' => array(

					'store_currency'   		=> $order->get_currency(),
					'store_order_price'   	=> $order->get_total(),
					'store_order_id'   		=> $order->get_id(),
					'store_buyer_name' 		=> $order->get_billing_first_name(),
					'store_buyer_email'		=> $order->get_billing_email(),

				)
			));

			$response_code = wp_remote_retrieve_response_code( $response );
			
			if ( $response_code == 200 || $response_code == 201 ) {

				$api_response = json_decode( wp_remote_retrieve_body( $response ), true );

				if (isset($api_response['data']['crypto_address'])){

					$htmlOutput = '<div class="container" style=" padding: 10px;" align="center">';
					$htmlOutput .= '<h2>PiratePay</h2>';
					$htmlOutput .= '<h4>PirateChain (ARRR) Cryptocurrency Payment:</h4>';
					$htmlOutput .= '<img src=' . $api_response['data']['crypto_qr'] . ' loading="eager" alt="ARRR QR Code" width="300" height="300"> ';
					$htmlOutput .= '<p><b>ARRR Market Price:</b><br> ' . $api_response['data']['crypto_market_price'] . '</p>';
					$htmlOutput .= '<p><b>ARRR Order Price:</b><br> ' . $api_response['data']['crypto_price'] . '</p>';
					$htmlOutput .= '<p><b>ARRR Address:</b><br> ' . $api_response['data']['crypto_address'] . '</p>';
					$htmlOutput .= '</div>';
		
					echo $htmlOutput;

				} else {
					// No Crypto Address Found
					echo 'Error: Unable to get ARRR address from PiratePay';
				}

			} else {
				// Status Code 201 Error Handling.
				echo 'Error: Connection to PiratePay failed. Error Code: ' . wp_remote_retrieve_response_code( $response );
			}
			
		}



		/**
		 * Add content to the WC emails.
		 *
		 * @since 1.0.0
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */

		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

			
			if ($this->emailEnabled === 'yes' && ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status('on-hold')) {

				$response = wp_remote_post( $this->apiUrl . '/initiate', array(
					'method'    => 'POST',
					'headers' => array(
						'Authorization'	=> 'Bearer ' . $this->apiToken,
					),
					'body' => array(
	
						'store_currency'   		=> $order->get_currency(),
						'store_order_price'   	=> $order->get_total(),
						'store_order_id'   		=> $order->get_id(),
						'store_buyer_name' 		=> $order->get_billing_first_name(),
						'store_buyer_email'		=> $order->get_billing_email(),
	
					)
				));
	
				$response_code = wp_remote_retrieve_response_code( $response );
				
				if ( $response_code == 200 || $response_code == 201 ) {

					$api_response = json_decode( wp_remote_retrieve_body( $response ), true );
	
					if (isset($api_response['data']['crypto_address'])){
	
						$htmlOutput = '<div class="container" style=" padding: 10px;" align="center">';
						$htmlOutput .= 	$this->emailInstructions . '<br><br>';
						$htmlOutput .= '<h2>PiratePay</h2>';
						$htmlOutput .= '<h4>PirateChain (ARRR) Cryptocurrency Payment:</h4>';
						$htmlOutput .= '<img src=' . $api_response['data']['crypto_qr'] . '  alt="ARRR QR Code" width="300" height="300"> ';
						$htmlOutput .= '<p><b>ARRR Market Price:</b><br> ' . $api_response['data']['crypto_market_price'] . '</p>';
						$htmlOutput .= '<p><b>ARRR Order Price:</b><br> ' . $api_response['data']['crypto_price'] . '</p>';
						$htmlOutput .= '<p><b>ARRR Address:</b><br> ' . $api_response['data']['crypto_address'] . '</p>';
						$htmlOutput .= '</div>';
			
						echo $htmlOutput;
	
					} else {
						// No Crypto Address Found.
						echo 'Error: Unable to get ARRR address from PiratePay';
					}
	
				} else {
					// Status Code 201 Error Handling.
					echo 'Error: Connection to PiratePay failed. Error Code: ' . wp_remote_retrieve_response_code( $response );
				}

			}
			
		}
	
	
		/**
		 * Process the payment and return the result
		 * This will put the order into on-hold status, reduce inventory levels, and empty customer shopping cart.
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
	
			$order = wc_get_order( $order_id );
			
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'on-hold', __( 'Awaiting PiratePay payment', 'piratepay-wc-gateway' ) );
			
			// Reduce stock levels
			wc_reduce_stock_levels($order_id);
			
			// Remove cart
			WC()->cart->empty_cart();
			
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}
	
  } // end \PiratePay_WC_Gateway class
}
