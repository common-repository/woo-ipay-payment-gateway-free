<?php

/**
 * Plugin Name: WooCommerce iPay/eLipa Payment Gateway Free
 * Plugin URI: http://ipayafrica.com/
 * Description: iPay/eLipa are payment gateway for WooCommerce allowing you to receive payments via iPay/eLipa.
 * Version: 3.0.3
 * Author: iPay
 * Requires at least: 4.4
 * Tested up to: 5.5
 * WC requires at least: 3.0.0
 * WC tested up to: 4.0
 * Author Email: moseskariuki2119@gmail.com
 */

add_action('plugins_loaded', 'init_ipay_payment_gateway');

function ipay_settings($links) {
	$settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=ipay') . '">Settings</a>';
	$ipay_docs = '<a href="https://dev.ipayafrica.com/">Docs</a>';
	array_push($links, $settings_link);
	array_push($links, $ipay_docs);
	return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'ipay_settings');

function init_ipay_payment_gateway() {

	if (!class_exists('WC_Payment_Gateway')) {
		return;
	}

	class WC_Gateway_Ipay extends WC_Payment_Gateway {

		var $plugin_url;

		public function __construct() {
			$this->plugin_url = WP_PLUGIN_URL . DIRECTORY_SEPARATOR . 'woocommerce-ipay-redirect';
			$this->id = 'ipay';

			$this->has_fields = false;
			$this->method_title = __('iPay/eLipa', 'woocommerce');
			$this->method_description = __('Allow customers to conveniently pay with iPay/eLipa payment gateway.');
			$this->callback_url = $this->ipay_callback();

			$this->init_form_fields();
			$this->init_settings();

			$this->title = $this->get_option('title');
			$this->description = $this->get_option('description');
			$this->instructions = $this->get_option('instructions', $this->description);
			$this->mer = $this->get_option('mer');
			$this->vid = $this->get_option('vid');
			$this->merchant_country = $this->get_option('merchant_country');
			$this->hsh = $this->get_option('hsh');
			$this->live = $this->get_option('live');
			$this->autopay = $this->get_option('autopay');
			$this->checkout_option = $this->get_option('checkout_option');

			add_action('init', array($this, 'callback_handler'));
			add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'callback_handler'));

			if (version_compare(WOOCOMMERCE_VERSION, '3.0.0', '>=')) {
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			} else {
				add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
			}

			add_action('woocommerce_receipt_ipay', array($this, 'receipt_page'));

		}

		public function init_form_fields() {

			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', 'woocommerce'),
					'type' => 'checkbox',
					'label' => __('Enable iPay Payments Gateway', 'woocommerce'),
					'default' => 'yes',
				),
				'title' => array(
					'title' => __('Title', 'woocommerce'),
					'type' => 'text',
					'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
					'default' => __('iPay', 'woocommerce'),
					'desc_tip' => true,
				),
				'merchant_country' => array(
					'title' => __('Merchant Country', 'woocommerce'),
					'type' => 'select',
					'description' => __('The location of eLipa or iPay you assigned up to as a merchant.', 'woocommerce'),
					'default' => __('Select Country', 'woocommerce'),
					'options' => array(
						'ke' => 'Kenya',
						'tz' => 'Tanzania',
						'tg' => 'Togo',
						'ug' => 'Uganda',
					),
					'desc_tip' => true,
				),
				'description' => array(
					'title' => __('Description', 'woocommerce'),
					'type' => 'textarea',
					'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
					'default' => __('Pay with Mobile Money or Card Online.', 'woocommerce'),
					'desc_tip' => true,
				),
				'instructions' => array(
					'title' => __('Instructions', 'woocommerce'),
					'type' => 'textarea',
					'description' => __('Instructions that will be added to the thank you page and emails.', 'woocommerce'),
					'default' => __('Pay with Mobile Money or Card Online.', 'woocommerce'),
					'desc_tip' => true,
				),
				'mer' => array(
					'title' => __('Merchant Name', 'woocommerce'),
					'description' => __('Company name', 'woocommerce'),
					'type' => 'text',
					'default' => __('Company Name', 'woocommerce'),
					'desc_tip' => false,
				),
				'vid' => array(
					'title' => __('Vendor ID', 'woocommerce'),
					'type' => 'text',
					'description' => __('Vendor ID as assigned by iPay. SET IN LOWER CASE.', 'woocommerce'),
					'default' => __('demo', 'woocommerce'),
					'desc_tip' => false,
				),
				'hsh' => array(
					'title' => __('Security Key', 'woocommerce'),
					'type' => 'password',
					'description' => __('Security key assigned by iPay', 'woocommerce'),
					'default' => __('demo', 'woocommerce'),
					'desc_tip' => false,
				),
				'live' => array(
					'title' => __('Live/Demo', 'woocommerce'),
					'type' => 'checkbox',
					'label' => __('Make iPay live', 'woocommerce'),
					'default' => 'no',
				),
				'autopay' => array(
					'title' => __('Autopay', 'woocommerce'),
					'type' => 'checkbox',
					'label' => __('The users will not be redirected to the callback. Not a preferred option', 'woocommerce'),
					'default' => 'no',
				),
				'checkout_option' => array(
					'title' => __('Checkout Option', 'woocommerce'),
					'type' => 'select',
					'description' => __('Choose either an iFrame or a redirect. A redirect is recommended though', 'woocommerce'),
					'default' => __('Select Checkout Option', 'woocommerce'),
					'options' => array(
						'redirect' => 'Redirect',
						'iframe' => 'iFrame',
					),
					'desc_tip' => true,
				),
			);
		}

		public function admin_options() {

			echo '<h3>' . 'iPay Payments Gateway' . '</h3>';

			echo '<p>' . 'Allow customers to conveniently pay with iPay payment gateway.' . '</p>';

			echo '<table class="form-table">';

			$this->generate_settings_html();

			echo '</table>';
		}

		public function receipt_page($order_id) {

			echo $this->redirect_ipay($order_id);

		}

		public function redirect_ipay($order_id) {

			global $woocommerce;

			$order = new WC_Order($order_id);
			// $order = wc_get_order($order_id);

			$live = 1;

			if ($this->live == 'no') {
				$live = 0;
			}

			$autopay = 1;

			if ($this->autopay == 'no') {
				$autopay = 0;
			}

			$mer = $this->mer;
			$tel = $order->get_billing_phone();

			$tel = str_replace("-", "", $tel);
			$tel = str_replace(array(' ', '<', '>', '&', '{', '}', '*', "+", '!', '@', '#', "$", '%', '^', '&'), "", $tel);
			$eml = $order->get_billing_email();
			$live = $live;
			$vid = $this->vid;
			$oid = $order->get_id();
			$inv = $oid;
			$p1 = '';
			$p2 = '';
			$p3 = '';
			$p4 = '';
			$eml = $order->get_billing_email();

			$supported_currencies = ["KES", "TZS", "UGX", "XOF", "USD"];

			$curr = "";

			if (in_array(get_woocommerce_currency(), $supported_currencies)) {
				$curr = get_woocommerce_currency();
			} else {
				echo "Unsupported currency";
				exit();
			}

			$ttl = $order->get_total();

			if (in_array($curr, $supported_currencies) && $curr != "USD") {
				$ttl = ceil($ttl);
			}

			$tel = $tel;
			$crl = '0';
			$cst = '1';
			$callbk = $this->callback_url;
			$cbk = $callbk;
			$hsh = $this->hsh;

			$datastring = $live . $oid . $inv . $ttl . $tel . $eml . $vid . $curr . $p1 . $p2 . $p3 . $p4 . $cbk . $cst . $crl;
			$hash_string = hash_hmac('sha1', $datastring, $hsh);
			$hash = $hash_string;

			$ipayUrl = "";
			switch ($this->merchant_country) {

			case "tz":
				$ipayUrl = "https://payments.elipa.co.tz/v3/tz";
				break;

			case "ke":
				$ipayUrl = "https://payments.ipayafrica.com/v3/ke";
				break;

			case "ug":
				$ipayUrl = "https://payments.elipa.co.ug/v3/ug";
				break;

			case "tg":
				$ipayUrl = "https://payments.elipa.tg/v1/v3/index.php/togo";
				break;

			default:
				echo "Unsupported merchant country";
				exit();
				break;
			}

			$url = $ipayUrl . "?live=" . $live . "&oid=" . $oid . "&inv=" . $inv . "&ttl=" . $ttl . "&tel=" . $tel . "&eml=" . $eml . "&vid=" . $vid . "&curr=" . $curr . "&p1=" . $p1 . "&p2=" . $p2 . "&p3=" . $p3 . "&p4=" . $p4 . "&cbk=" . $cbk . "&cst=" . $cst . "&crl=" . $crl . "&hsh=" . $hash . "&autopay=" . $autopay;

			$this->debug_to_console($url);

			$checkout_option = $this->checkout_option;

			switch ($checkout_option) {
			case 'redirect':
				header("location: $url");
				break;

			case 'iframe':
				echo '<iframe src="' . $url . '" width="100%" height="700" style="border:0" scrolling="yes"></iframe>';
				break;
			}
		}

		function debug_to_console($data) {
			$output = $data;
			if (is_array($output)) {
				$output = implode(',', $output);
			}

			echo "<script>console.log('Debug Objects: " . $output . "' );</script>";
		}

		/**
		 * Returns link to the callback class
		 * Refer to WC-API for more information on using classes as callbacks
		 */
		public function ipay_callback() {

			return WC()->api_request_url('WC_Gateway_Ipay');
		}

		/**
		 * This function gets the callback values posted by iPay to the callback url
		 * It updates order status and order notes
		 */
		public function callback_handler() {

			global $woocommerce;

			$ipn_base = "";

			switch ($this->merchant_country) {
			case 'ke':
				$ipn_base = "https://www.ipayafrica.com/ipn";
				break;

			case 'tz':
				$ipn_base = "https://payments.elipa.co.tz/v3/tz/ipn";
				break;

			case 'ug':
				$ipn_base = "https://payments.elipa.co.ug/v3/ug/ipn";
				break;

			case 'tg':
				$ipn_base = "https://payments.elipa.tg/v1/tg/index.php/ipn/check";
				break;

			default:
				echo "Unknown country of operation";
				exit();
				break;
			}

			$val = $this->vid;
			/*
				            these values below are picked from the incoming URL and assigned to variables that we
				            will use in our security check URL
			*/

			$val1 = sanitize_text_field($_GET['id']);
			$val2 = sanitize_text_field($_GET['ivm']);
			$val3 = sanitize_text_field($_GET['qwh']);
			$val4 = sanitize_text_field($_GET['afd']);
			$val5 = sanitize_text_field($_GET['poi']);
			$val6 = sanitize_text_field($_GET['uyt']);
			$val7 = sanitize_text_field($_GET['ifd']);
			$ipnurl = $ipn_base . "?vendor=" . $val . "&id=" . $val1 . "&ivm=" . $val2 . "&qwh=" . $val3 . "&afd=" . $val4 . "&poi=" . $val5 . "&uyt=" . $val6 . "&ifd=" . $val7;

			$fp = fopen($ipnurl, "rb");
			$status = stream_get_contents($fp, -1, -1);
			if ($this->live == 'no') {
				$status = 'aei7p7yrx4ae34';
			}
			fclose($fp);

			$this->notifications($status, $val1);
		}

		public function notifications($status, $order_id) {

			$checkout_option = $this->checkout_option;

			$order = new WC_Order($order_id);

			//Failed
			if ($status == "fe2707etr5s4wq") {
				$order->update_status('failed', 'The attempted payment FAILED - iPay.<br>', 'woocommerce');
				wp_die("iPay payment failed. Check out the email sent to you from iPay for the reason of failure of order $order_id.");
			}

			// Successful
			else if ($status == "aei7p7yrx4ae34") {
				$order->update_status('completed', 'The order was SUCCESSFULLY processed by iPay.<br>', 'woocommerce');
				$order->reduce_order_stock();
				$success_page = $this->get_return_url($order);

				switch ($checkout_option) {
				case 'redirect':
					wp_redirect($success_page);
					break;

				case 'iframe':
					?>
			            <script type="text/javascript">
			                window.top.location.href = "<?php echo $success_page; ?>";
			            </script>
			        <?php
break;
				}
			}

			// Pending
			else if ($status == "bdi6p2yy76etrs") {
				$order->update_status('pending', 'The transaction is PENDING. Tell customer to try again -iPAY', 'woocommerce');
				wp_die("The iPay payment is pending. Please try again in 5 minutes or contact the the owner of the site for assistance.");
			}

			// Used code
			else if ($status == "cr5i3pgy9867e1") {
				$order->update_status('payment-used', __('The input payment code has already been USED. Please contact customer - iPay.<br>', 'woocommerce'));
				wp_die("The iPay payment has already been used. Contact the owner of the site for further assistance.");
			}

			// Less
			else if ($status == "dtfi4p7yty45wq") {
				$order->update_status('on-hold', __('Amount paid was LESS than the required - iPay.<br>', 'woocommerce'));
				wp_die("The iPay payment received is less than the transaction amount expected. Contact the Merchant for assistance.");
			}

			// More
			else if ($status == "eq3i7p5yt7645e") {
				$order->update_status('overpaid', 'The order was overpaid but SUCCESSFULLY processed by iPay.<br>', 'woocommerce');
				$order->reduce_order_stock();
				wp_redirect($this->get_return_url($order));
			}
			die;
		}

		/**
		 * Process the payment field and redirect to checkout/pay page.
		 *
		 * @param $order_id
		 * @return array
		 */
		public function process_payment($order_id) {

			$order = new WC_Order($order_id);

			return array(
				'result' => 'success',
				'redirect' => add_query_arg('order', $order->id,
					add_query_arg('key', $order->order_key, $order->get_checkout_payment_url(true))),
			);

		}

	}

	function add_ipay_gateway_class($methods) {

		$methods[] = 'WC_Gateway_Ipay';

		return $methods;

	}

	if (!add_filter('woocommerce_payment_gateways', 'add_ipay_gateway_class')) {
		die;
	}
}
