<?php 

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

add_action( 'plugins_loaded', 'b_pay_payment_init', 11 );
add_filter( 'woocommerce_currencies', 'techiepress_add_fr_currencies' );
add_filter( 'woocommerce_currency_symbol', 'techiepress_add_fr_currencies_symbol', 10, 2 );
add_filter( 'woocommerce_payment_gateways', 'add_to_woo_b_pay_payment_gateway');

function b_pay_payment_init() {
    if( class_exists( 'WC_Payment_Gateway' ) ) {
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-wc-payment-gateway-b_pay.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/b_pay-order-statuses.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/b_pay-checkout-description-fields.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/b_pay_api.php';
	}
}

class WC_test extends WC_Payment_Gateway { 
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
		$this->instructions       = $this->get_option( 'instructions' );
		$this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
		$this->enable_for_virtual = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes';

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
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
			'business_key'           => array(
				'title'       => __( 'Business_key', 'b_pay-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Ajouter votre business_key b_pay', 'b_pay-payments-woo' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( 'Description', 'b_pay-payments-woo' ),
				'type'        => 'textarea',
				'description' => __( 'méthode de paiement que verra le client sur le site.', 'b_pay-payments-woo' ),
				'default'     => __( 'effectuer le paiement via b_pay avant la livraison.', 'b_pay-payments-woo' ),
				'desc_tip'    => true,
			),
			'instructions'       => array(
				'title'       => __( 'Instructions', 'b_pay-payments-woo' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page.', 'b_pay-payments-woo' ),
				'default'     => __( 'b_pay Mobile Payments before delivery.', 'b_pay-payments-woo' ),
				'desc_tip'    => true,
			),
			'enable_for_methods' => array(
				'title'             => __( 'Enable for shipping methods', 'b_pay-payments-woo' ),
				'type'              => 'multiselect',
				'class'             => 'wc-enhanced-select',
				'css'               => 'width: 400px;',
				'default'           => '',
				'description'       => __( 'If b_pay is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'b_pay-payments-woo' ),
				// 'options'           => $this->load_shipping_method_options(),
				'desc_tip'          => true,
				'custom_attributes' => array(
					'data-placeholder' => __( 'Select shipping methods', 'b_pay-payments-woo' ),
				),
			),
			'enable_for_virtual' => array(
				'title'   => __( 'Accept for virtual orders', 'b_pay-payments-woo' ),
				'label'   => __( 'Accept b_pay if the order is virtual', 'b_pay-payments-woo' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			),
		);
	}

    
    public function b_pay_payment_processing ( $order ) { 
        // $this->$order = 4953;
        $total = intval($order->get_total());
        var_dump($total);
        $price = $order->get_price();
        $phone = $_POST ['payment_number'];

        

        echo $order;
        $currency = $order->get_currency();
        // $price = $order->get_price();
        $quantity = $order->get_quantity();
    
        // require​ ​__DIR__​ . ​'/vendor/autoload.php'​;
        // set dev cred and project login
        $dev_key = $this->dev_key;
        $business_key = $this->business_key;
    
        // require("\Composer\vendor\autoload.php");
    
        $pp = new ProcessPayment;
    
        $pp->addDev($dev_key, $business_key);
        
        // adding products
        $pp->addProduct($price, $quantity,'polo','tshirt');
        // $pp​->​addProduct​(​30​, ​1​,​"tshirt"​,​"phone t-shirt"​);
        // adding payment info like currency and tax
        $pp->addP_info($currency,0);
        // your customer number user on B-pay
        $pp->addBill_to($phone);
        // format of return
        $pp->addRun_env("json");
        // and apply the payment
        echo $pp->commit();
    }
}
$product_id = 4953;
$product = wc_get_product( $product_id );
echo $product->get_price_html();


?>