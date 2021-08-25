<?php

/**
 * b_pay Mobile Payments Gateway.
 *
 * b_pay Mobile Payments Gateway.
 *
 * @class       WC_Gateway_b_pay
 * @extends     WC_Payment_Gateway
 * @version     0.1.0
 * @package     WooCommerce/Classes/Payment
 */
// require_once plugin_dir_path( __FILE__ ) . '/includes/b_pay_api.php';
require("b_pay_api.php");
	
class WC_Gateway_b_pay extends WC_Payment_Gateway { 
	// Attribute
	
	/**
	 * 
	 * 
	 * 
	 * 
	 * 
	 * Constructor for gateway.
	 */
	public function __construct() {
		// Setup general properties.
		$this->setup_properties();

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Get settings.
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->dev_key            = $this->get_option( 'dev_key' );
		$this->business_key       = $this->get_option( 'business_key' );
		$this->taux               = $this->get_option( 'taux');
		$this->instructions       = $this->get_option( 'instructions' );
		$this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
		$this->enable_for_virtual = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes';

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		// add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'change_payment_complete_order_status' ), 10, 3 );

		// Customer Emails.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties() {
		$this->id                 = 'b_pay';
		$this->icon               = apply_filters( 'woocommerce_b_pay_icon', plugins_url('../assets/b_pay.jpg', __FILE__ ) );
		$this->method_title       = __( 'b_pay Mobile Payments', 'b_pay-payments-woo' );
		$this->dev_key            = __( 'Ajouter Dev_Key', 'b_pay-payments-woo' );
		$this->business_key       = __( 'Ajouter Business_key', 'b_pay-payments-woo' );
		$this->taux               = __( 'définir le taux d\'échange du dollar', 'b_pay-payments-woo');
		$this->method_description = __( 'Le mode de paiement que vont utiliser vos clients.', 'b_pay-payments-woo' );
		$this->has_fields         = false;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __( 'Activer/Désactiver', 'b_pay-payments-woo' ),
				'label'       => __( 'Activer le mode de paiement b_pay', 'b_pay-payments-woo' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'              => array(
				'title'       => __( 'Titre', 'b_pay-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Le titre du mode de paiement que verra le lient lors de la commande.', 'b_pay-payments-woo' ),
				'default'     => __( 'b_pay', 'b_pay-payments-woo' ),
				'desc_tip'    => true,
			),
			'dev_key'             => array(
				'title'       => __( 'Dev_key', 'b_pay-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Ajouter votre dev_key b_pay', 'b_pay-payments-woo' ),
				'desc_tip'    => true,
			),
			'business_key'    => array(
				'title'       => __( 'Business_key', 'b_pay-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Ajouter votre business_key b_pay', 'b_pay-payments-woo' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( 'Description Business', 'b_pay-payments-woo' ),
				'type'        => 'Textarea',
				'description' => __( 'méthode de paiement que verra le client sur le site.', 'b_pay-payments-woo' ),
				'default'     => __( 'effectuer le paiement via b_pay avant la livraison.', 'b_pay-payments-woo' ),
				'desc_tip'    => true,
			),
			'taux'             => array(
				'title'       => __( 'Taux', 'b_pay-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'définir le taux de dollar', 'b_pay-payments-woo' ),
				'desc_tip'    => true,



			),
		);	
	}

	/**
	 * Check If The Gateway Is Available For Use.
	 *
	 * @return bool
	 */
	public function is_available() {
		$order          = null;
		$needs_shipping = false;

		// Test if shipping is needed first.
		if ( WC()->cart && WC()->cart->needs_shipping() ) {
			$needs_shipping = true;
		} elseif ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = wc_get_order( $order_id );

			// Test if order needs shipping.
			if ( 0 < count( $order->get_items() ) ) {
				foreach ( $order->get_items() as $item ) {
					$_product = $item->get_product;
					if ( $_product && $_product->needs_shipping() ) {
						$needs_shipping = true;
						break;
					}
				}
			}
		}


		$needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );

		// Virtual order, with virtual disabled.
		if ( ! $this->enable_for_virtual && ! $needs_shipping ) {
			return false;
		}

		// Only apply if all packages are being shipped via chosen method, or order is virtual.
		if ( ! empty( $this->enable_for_methods ) && $needs_shipping ) {
			$order_shipping_items            = is_object( $order ) ? $order->get_shipping_methods() : false;
			$chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

			if ( $order_shipping_items ) {
				$canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids( $order_shipping_items );
			} else {
				$canonical_rate_ids = $this->get_canonical_package_rate_ids( $chosen_shipping_methods_session );
			}

			/*if ( ! count( $this->get_matching_rates( $canonical_rate_ids ) ) ) {
				return false;
			}*/
		}

		return parent::is_available();
	} 

	/**
	 * Checks to see whether or not the admin settings are being accessed by the current request.
	 *
	 * @return bool
	 */
	private function is_accessing_settings() {
		if ( is_admin() ) {
			// phpcs:disable WordPress.Security.NonceVerification
			if ( ! isset( $_REQUEST['page'] ) || 'wc-settings' !== $_REQUEST['page'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['tab'] ) || 'checkout' !== $_REQUEST['tab'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['section'] ) || 'b_pay' !== $_REQUEST['section'] ) {
				return false;
			}
			// phpcs:enable WordPress.Security.NonceVerification

			return true;
		}

		return false;
	}

	/**
	 * Loads all of the shipping method options for the enable_for_methods field.
	 *
	 * @return array
	 */
	private function load_shipping_method_options() {
		// Since this is expensive, we only want to do it if we're actually on the settings page.
		if ( ! $this->is_accessing_settings() ) {
			return array();
		}

		$data_store = WC_Data_Store::load( 'shipping-zone' );
		$raw_zones  = $data_store->get_zones();

		foreach ( $raw_zones as $raw_zone ) {
			$zones[] = new WC_Shipping_Zone( $raw_zone );
		}
		
		$zones[] = new WC_Shipping_Zone( 0 );

		$options = array();
		foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

			$options[ $method->get_method_title() ] = array();

			// Translators: %1$s shipping method name.
			$options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any &quot;%1$s&quot; method', 'b_pay-payments-woo' ), $method->get_method_title() );

			foreach ( $zones as $zone ) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

					if ( $shipping_method_instance->id !== $method->id ) {
						continue;
					}

					$option_id = $shipping_method_instance->get_rate_id();

					// Translators: %1$s shipping method title, %2$s shipping method id.
					$option_instance_title = sprintf( __( '%1$s (#%2$s)', 'b_pay-payments-woo' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

					// Translators: %1$s zone name, %2$s shipping method instance name.
					$option_title = sprintf( __( '%1$s &ndash; %2$s', 'b_pay-payments-woo' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'b_pay-payments-woo' ), $option_instance_title );

					$options[ $method->get_method_title() ][ $option_id ] = $option_title;
				}
			}
		}

		return $options;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
	 * @return array $canonical_rate_ids    Rate IDs in a canonical format.
	 */
	private function get_canonical_order_shipping_item_rate_ids( $order_shipping_items ) {

		$canonical_rate_ids = array();

		foreach ( $order_shipping_items as $order_shipping_item ) {
			$canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
		}

		return $canonical_rate_ids;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
	 * @return array $canonical_rate_ids  Rate IDs in a canonical format.
	 */
	private function get_canonical_package_rate_ids( $chosen_package_rate_ids ) {

		$shipping_packages  = WC()->shipping()->get_packages();
		$canonical_rate_ids = array();

		if ( ! empty( $chosen_package_rate_ids ) && is_array( $chosen_package_rate_ids ) ) {
			foreach ( $chosen_package_rate_ids as $package_key => $chosen_package_rate_id ) {
				if ( ! empty( $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ] ) ) {
					$chosen_rate          = $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ];
					$canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
				}
			}
		}

		return $canonical_rate_ids;
	}

	/**
	 * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
	 *
	 * @since  3.4.0
	 *
	 * @param array $rate_ids Rate ids to check.
	 * @return boolean
	 */
	private function get_matching_rates( $rate_ids ) {
		// First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
		return array_unique( array_merge( array_intersect( $this->enable_for_methods, $rate_ids ), array_intersect( $this->enable_for_methods, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment ( $order_id ) {
		$order = wc_get_order( $order_id );
		// Getting the items in the order
		$order_items = $order->get_items();
		// Iterating through each item in the order
		foreach ($order_items as $item_id => $item) {
		    // Get the product name
		    $product_name = $item['name'];
			$product_description = $item['description'];
		    // Get the item quantity
		    $item_quantity = wc_get_order_item_meta($item_id, '_qty', true);
		    // Get the item line total
		    $item_total = wc_get_order_item_meta($item_id, '_line_total', true);
			$currencyWebsite = get_woocommerce_currency();
			
		
		    // Displaying this data (to check)
		    // echo 'Product name: '.$product_name.' | Quantity: '.$item_quantity.' | Item total: '. $item_total;
			
		}
	
// $order = wc_get_order(500);

// Iterate though each order item
		// $price = $order->get_price();
		// $quantity = $order->get_;

		if ( $order->get_total() > 0 ) {
			$this->b_pay_payment_processing( $order, $item_quantity, $product_name, $item_total, $product_description, $currencyWebsite);
		}
		else {
			 $order->payment_complete();
		}


		 // WC()->cart->empty_cart();

		// return array(

		//  'result' => 'Success',
		//  'redirect' => $this->get_return_url  ($order),
		// );
	}



	private function b_pay_payment_processing ( $ordertest, $quantitytest, $product_nametest, $pricetotal, $product_description, $currencyWebsite  ) {
	
	 $total = intval($ordertest->get_total());
	 // var_dump($total);
	// $price = $order->get_price();
	 $tauxString = $this->taux;
	 $taux = intval($tauxString);   
	 $phone = $_POST ['payment_number'];
	 $currency = $_POST['paying_devise'];
	 
	 // $currency = $ordertest->get_currency();
	 if($currencyWebsite == 'FC'){

		 if ($currency == 'CDF'){
			$currency = 'cdf';
			$pricetest2 = $pricetotal;
			
		 }
		 else {
			$pricetest2 = ($pricetotal/$taux);
			$currency = 'usd';
			
		 }
	 
		}
		else{

			if ($currency == 'USD'){
				$currency = 'usd';
				$pricetest2 = $pricetotal;
				
			 }
			 else {
				$pricetest2 = ($pricetotal*$taux);
				$currency = 'cdf';
				
				
			 }
			
		}
		

	$price = doubleval($pricetest2);
	$quantitytest1 = ($quantitytest);

	// set dev cred and project login
	 $dev_key = $this->dev_key;
	 $business_key = $this->business_key;

	// require("\Composer\vendor\autoload.php");

	$pp = new ProcessPayment;

	$pp->addDev($dev_key, $business_key);
	
	// adding products
	$pp->addProduct($price, $quantitytest1,$product_nametest,$product_description);
	// $pp​->​addProduct​(​30​, ​1​,​"tshirt"​,​"phone t-shirt"​);
	// adding payment info like currency and tax
	$pp->addP_info($currency,0);
	// your customer number user on B-pay
	$pp->addBill_to($phone);
	// format of return
	$pp->addRun_env("json");
	// and apply the payment
	$url  = $pp->commit();

	$converttoarray = explode(':',$url);
// print_r($josh);

	$code = ($converttoarray[4]);
	$code1 = substr($code, 0, 4); 
	$code3 = (int)$code1;

	

	// $response = wp_remote_post( $url, array( 'timeout' => 60 ) );
	
	
	
	// if ( is_wp_error( $response ) ) {
	// 	$error_message = $response->get_error_message();
	// 	return "Un problème est survenu : $error_message";
		
	// }
	// } else {
	// 	echo 'Response:<pre>';
	// 	//var_dump( wp_remote_retrieve_body( $response ) );
	// 	print_r($response);
	// 	echo '</pre>';
	// } 


	// $a = wp_remote_retrieve_response_code($url);
	// echo($a);
	// $b = wp_remote_retrieve_body($url);
	// echo($b); 

		
	//echo $code3;
	$codec = 8000;
	
	

	switch ($codec) {
		case 5000:
			wc_add_notice( 'L\'authentification du developpeur a échoué', 'error' );
			break;
		case 6000 :
			wc_add_notice( 'L\'authentification du business a échoué', 'error' );
			break;
		case 6001 :
			wc_add_notice('L\'quthentification du developpeur a échoué','error');
			break;
		case 6002 :
			wc_add_notice('La business_key n\’est pas liée à un business E-commerce','error');
			break;
		case 5001 :
			wc_add_notice('Cette erreur survient quand un compte développeur ou business initie un paiement comme un compte client sur un même contexte','error');
			break;
		case 7000 :
			wc_add_notice('Le numéro client qui initie le paiement n\’a pas un compte b-pay','error');
			break;
		case 7001 :
			wc_add_notice('Le paiement a été annulé par le client.','error');
			break;
		case 7002 :
			wc_add_notice('Le token de la session de paiement a expiré','error');
			break;
		case 7002 :
			wc_add_notice('Le token de la session de paiement a expiré','error');
			break;
		case 8000 :

			// $ordertest->reduce_order_stock_leves();
			echo 'Josh';
			
			$ordertest->payment_complete();

			// Remove cart.
				
		 	WC()->cart->empty_cart();
	
			// Return thankyou redirect.
			return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $ordertest ),
			);
			
			
			break;		
			
		}
	 // if ( 5001 === $code3  ) {
		 
		 
		// return 'Something went wrong: ' ;+ intl_get_error_message();
		// wc_add_notice( 'Le paiement a été annulé par l\'utilisateur', 'error' );
		// echo 'Le paiement a été annulé par l\'utilisateur';
			// $error_message = $pp.commit() -> get_error_message();
			// return "Un problème est survenu : $error_message";
	// }

	// if (5001 === wp_remote_retrieve_response_code( $response )){

	// 	$response_body = wp_remote_retrieve_body($response);
	// 	var_dump($response_body['msg']);
		
		// if ('sucess' === $response_body['msg']) {
		// 	$ordertest->payment_complete();
		// };

		
	}

	
	// if ( 200 === wp_remote_retrieve_response_code( $response )) {
			
	// 		$ordertest->payment_complete();

	// 		// Remove cart.
			
	// 		WC()->cart->empty_cart();

	// 		// Return thankyou redirect.
	// 		return array(
	// 			'result'   => 'success',
	// 			'redirect' => $this->get_return_url( $ordertest ),
	// 		);
	// 	}

	// }

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page() {
		if ( $this->instructions ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
		}
	}

	/**
	 * Change payment complete order status to completed for b_pay orders.
	 *
	 * @since  3.1.0
	 * @param  string         $status Current order status.
	 * @param  int            $order_id Order ID.
	 * @param  WC_Order|false $order Order object.
	 * @return string
	 */
	public function change_payment_complete_order_status( $status, $order_id = 0, $order = false ) {
		if ( $order && 'b_pay' === $order->get_payment_method() ) {
			$status = 'waiting for delivering';
		}
		return $status;
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin  Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
		}
	}


}
// API B_pay


?>


