<?php
/**
 * Plugin Name: wallee Subscription
 * Plugin URI: https://wordpress.org/plugins/woo-wallee-subscription
 * Description: Addon to process WooCommerce Subscriptions with wallee
 * Version: 1.1.6
 * License: Apache2
 * License URI: http://www.apache.org/licenses/LICENSE-2.0
 * Author: wallee AG
 * Author URI: https://www.wallee.com
 * Requires at least: 4.7
 * Tested up to: 6.7.2
 * WC requires at least: 3.2.0
 * WC tested up to: 9.7.0
 * Requires Plugins: woo-wallee
 *
 * Text Domain: woo-wallee-subscription
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

if ( ! class_exists( 'WooCommerce_Wallee_Subscription' ) ) {

	/**
	 * Main WooCommerce Wallee Class
	 *
	 * @class WooCommerce_Wallee_Subscription
	 */
	final class WooCommerce_Wallee_Subscription {


		/**
		 * WooCommerce Wallee version.
		 *
		 * @var string
		 */
		private $version = '1.1.6';

		/**
		 * The single instance of the class.
		 *
		 * @var WooCommerce_Wallee_Subscription
		 */
		protected static $_instance = null;


		/**
		 * Logger.
		 *
		 * @var mixed
		 */
		private $logger = null;

		/**
		 * Main WooCommerce Wallee Instance.
		 *
		 * Ensures only one instance of WooCommerce Wallee is loaded or can be loaded.
		 *
		 * @return WooCommerce_Wallee_Subscription - Main instance.
		 */
		public static function instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * WooCommerce_Wallee_Subscription Constructor.
		 */
		protected function __construct() {
			$this->define_constants();
			$this->includes();
			$this->init_hooks();
		}

		/**
		 * Get version.
		 *
		 * @return string
		 */
		public function get_version() {
			return $this->version;
		}

		/**
		 * Define constant if not already set.
		 *
		 * @param string      $name Name.
		 * @param string|bool $value Value.
		 */
		protected function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Log.
		 *
		 * @param mixed         $message Message.
		 * @param WC_Log_Levels $level Level.
		 * @return void
		 */
		public function log( $message, $level = WC_Log_Levels::WARNING ) {
			if ( null == $this->logger ) {
				$this->logger = new WC_Logger();
			}

			$this->logger->log(
				$level,
				$message,
				array(
					'source' => 'woo-wallee-subscription',
				)
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Woo Wallee Subscription: ' . $message );
			}
		}

		/**
		 * Get the plugin url.
		 *
		 * @return string
		 */
		public function plugin_url() {
			return untrailingslashit( plugins_url( '/', __FILE__ ) );
		}

		/**
		 * Get the plugin path.
		 *
		 * @return string
		 */
		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( __FILE__ ) );
		}

		/**
		 * Define WC Wallee Constants.
		 */
		protected function define_constants() {
			$this->define( 'WC_WALLEE_SUBSCRIPTION_PLUGIN_FILE', __FILE__ );
			$this->define( 'WC_WALLEE_SUBSCRIPTION_ABSPATH', dirname( __FILE__ ) . '/' );
			$this->define( 'WC_WALLEE_SUBSCRIPTION_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
			$this->define( 'WC_WALLEE_SUBSCRIPTION_VERSION', $this->version );
			$this->define( 'WC_WALLEE_SUBSCRIPTION_REQUIRED_WALLEE_VERSION', '1.2.12' );
			$this->define( 'WC_WALLEE_REQUIRED_WC_SUBSCRIPTION_VERSION', '2.5' );
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 */
		protected function includes() {
			/**
			 * Class autoloader.
			 */
			require_once( WC_WALLEE_SUBSCRIPTION_ABSPATH . 'includes/class-wc-wallee-subscription-autoloader.php' );
			require_once( WC_WALLEE_SUBSCRIPTION_ABSPATH . 'includes/class-wc-wallee-subscription-migration.php' );

			if ( is_admin() ) {
				require_once( WC_WALLEE_SUBSCRIPTION_ABSPATH . 'includes/admin/class-wc-wallee-subscription-admin.php' );
			}
		}

		/**
		 * Init hooks.
		 *
		 * @return void
		 */
		protected function init_hooks() {
			register_activation_hook(
				__FILE__,
				array(
					'WC_Wallee_Subscription_Migration',
					'install_db',
				)
			);
			add_action(
				'plugins_loaded',
				array(
					$this,
					'loaded',
				),
				0
			);
		}

		/**
		 * Load Localization files.
		 *
		 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
		 *
		 * Locales found in:
		 * - WP_LANG_DIR/woo-wallee-subscription/woo-wallee-subscription-LOCALE.mo
		 */
		public function load_plugin_textdomain() {
			$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
			$locale = apply_filters( 'plugin_locale', $locale, 'woo-wallee-subscription' );

			load_textdomain( 'woo-wallee-subscription', WP_LANG_DIR . '/woo-wallee/woo-wallee-subscription' . $locale . '.mo' );
			load_plugin_textdomain( 'woo-wallee-subscription', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Init WooCommerce Wallee when plugins are loaded.
		 */
		public function loaded() {
			// Set up localisation.
			$this->load_plugin_textdomain();

			add_filter(
				'wc_wallee_enhance_gateway',
				array(
					$this,
					'enhance_gateway',
				)
			);
			add_filter(
				'wc_wallee_modify_sesion_create_transaction',
				array(
					$this,
					'update_transaction_from_session',
				)
			);
			add_filter(
				'wc_wallee_modify_session_pending_transaction',
				array(
					$this,
					'update_transaction_from_session',
				)
			);
			add_filter(
				'wc_wallee_modify_order_create_transaction',
				array(
					$this,
					'update_transaction_from_order',
				),
				10,
				2
			);
			add_filter(
				'wc_wallee_modify_order_pending_transaction',
				array(
					$this,
					'update_transaction_from_order',
				),
				10,
				2
			);
			add_filter(
				'wc_wallee_modify_confirm_transaction',
				array(
					$this,
					'update_transaction_from_order',
				),
				10,
				2
			);

			add_filter(
				'wc_wallee_modify_line_item_order',
				array(
					$this,
					'modify_line_item_method_change',
				),
				10,
				2
			);

			add_filter(
				'wc_wallee_modify_total_to_check_order',
				array(
					$this,
					'modify_order_total_method_change',
				),
				12,
				2
			);

			add_action(
				'wc_wallee_authorized',
				array(
					$this,
					'update_subscription_data',
				),
				10,
				2
			);
			add_action(
				'wc_wallee_fulfill',
				array(
					$this,
					'fulfill_in_progress',
				),
				10,
				2
			);
			add_filter(
				'woocommerce_valid_order_statuses_for_payment',
				array(
					$this,
					'add_valid_order_statuses_for_subscription_completion',
				),
				10,
				2
			);
			add_filter(
				'wc_wallee_update_transaction_info',
				array(
					$this,
					'update_transaction_info',
				),
				10,
				3
			);
			add_filter(
				'wc_wallee_confirmed_status',
				array(
					$this,
					'ignore_status_update_for_subscription',
				),
				10,
				2
			);
			add_filter(
				'wc_wallee_authorized_status',
				array(
					$this,
					'ignore_status_update_for_subscription',
				),
				10,
				2
			);
			add_filter(
				'wc_wallee_completed_status',
				array(
					$this,
					'ignore_status_update_for_subscription',
				),
				10,
				2
			);
			add_filter(
				'wc_wallee_decline_status',
				array(
					$this,
					'ignore_status_update_for_subscription',
				),
				10,
				2
			);
			add_filter(
				'wc_wallee_failed_status',
				array(
					$this,
					'ignore_status_update_for_subscription',
				),
				10,
				2
			);
			add_filter(
				'wc_wallee_voided_status',
				array(
					$this,
					'ignore_status_update_for_subscription',
				),
				10,
				2
			);
			add_action(
				'wcs_after_renewal_setup_cart_subscriptions',
				array(
					$this,
					'set_transaction_ids_into_session',
				),
				10,
				2
			);
			add_filter(
				'woocommerce_subscriptions_is_failed_renewal_order',
				array(
					$this,
					'check_failed_renewal_order',
				),
				10,
				3
			);

			// Handle order to cart creation for subscriptions.
			add_filter(
				'wcs_before_renewal_setup_cart_subscriptions',
				array(
					$this,
					'before_renewal_setup_cart',
				),
				10,
				2
			);
			add_filter(
				'wcs_before_parent_order_setup_cart',
				array(
					$this,
					'before_renewal_setup_cart',
				),
				10,
				2
			);
			add_filter(
				'wcs_after_renewal_setup_cart_subscriptions',
				array(
					$this,
					'after_renewal_setup_cart',
				),
				10,
				2
			);
			add_filter(
				'wcs_after_parent_order_setup_cart',
				array(
					$this,
					'after_renewal_setup_cart',
				),
				10,
				2
			);
			add_filter(
				'wc_wallee_is_method_available',
				array(
					$this,
					'method_available_for_cart_renewal',
				),
				10,
				1
			);

			add_filter(
				'wc_wallee_success_url',
				array(
					$this,
					'update_success_url',
				),
				10,
				2
			);
			add_filter(
				'wc_wallee_checkout_failure_url',
				array(
					$this,
					'update_failure_url',
				),
				10,
				2
			);

		}

		/**
		 * Enhance gateway.
		 *
		 * @param WC_Wallee_Gateway $gateway Gateway.
		 * @return WC_Wallee_Gateway
		 */
		public function enhance_gateway( WC_Wallee_Gateway $gateway ) {
			$gateway->supports = array_merge(
				$gateway->supports,
				array(
					'subscriptions',
					'multiple_subscriptions',
					'subscription_cancellation',
					'subscription_suspension',
					'subscription_reactivation',
					'subscription_amount_changes',
					'subscription_date_changes',
					'subscription_payment_method_change',
					'subscription_payment_method_change_customer',
					'subscription_payment_method_change_admin',
					'subscription_payment_method_delayed_change',
				)
			);
			// Create Subscription gateway and register hooks/filters.
			new WC_Wallee_Subscription_Gateway( $gateway );
			return $gateway;
		}

		/**
		 * Fulfill in progress.
		 *
		 * @param \Wallee\Sdk\Model\Transaction $transaction Transaction.
		 * @param mixed                                        $order Order.
		 * @return void
		 */
		public function fulfill_in_progress( \Wallee\Sdk\Model\Transaction $transaction, $order ) {
			if ( wcs_order_contains_subscription( $order, array( 'parent', 'resubscribe', 'switch', 'renewal' ) ) ) {
				$GLOBALS['_wc_wallee_subscription_fulfill'] = true;
			}
		}

		/**
		 * Is transaction method change.
		 *
		 * @param \Wallee\Sdk\Model\Transaction $transaction Transaction.
		 * @return bool
		 */
		private function is_transaction_method_change( \Wallee\Sdk\Model\Transaction $transaction ) {
			$line_items = $transaction->getLineItems();
			if ( count( $line_items ) != 1 ) {
				return false;
			}
			$line_item = current( $line_items );
			/* @var  \Wallee\Sdk\Model\LineItem $line_item */
			if ( $line_item->getSku() == 'paymentmethodchange' ) {
				return true;
			}
			return false;
		}

		/**
		 * Before renewal setup.
		 * Create a checkout instead of using the order to pay. Here wet our method as available and ensure we do not create transaction on such order.
		 *
		 * @param mixed $subscriptions Subscriptions.
		 * @param mixed $order Order.
		 * @return void
		 */
		public function before_renewal_setup_cart( $subscriptions, $order ) {
			$GLOBALS['_wc_wallee_renewal_cart_setup'] = true;
		}

		/**
		 * After renewal setup.
		 *
		 * @param mixed $subscriptions Subscriptions.
		 * @param mixed $order Order.
		 * @return void
		 */
		public function after_renewal_setup_cart( $subscriptions, $order ) {
			$GLOBALS['_wc_wallee_renewal_cart_setup'] = false;
		}


		/**
		 * Method available for cart renewal.
		 *
		 * @param mixed $available Available.
		 * @return bool|mixed
		 */
		public function method_available_for_cart_renewal( $available ) {
			if ( isset( $GLOBALS['_wc_wallee_renewal_cart_setup'] ) && $GLOBALS['_wc_wallee_renewal_cart_setup'] ) {
				return true;
			}
			return $available;
		}

		/**
		 * Update success url.
		 *
		 * @param mixed $url Url.
		 * @param mixed $order Order.
		 * @return mixed
		 */
		public function update_success_url( $url, $order ) {
			if ( wcs_is_subscription( $order ) ) {
				return $order->get_view_order_url();
			}
			return $url;
		}


		/**
		 * Update failure url.
		 *
		 * @param mixed $url Url.
		 * @param mixed $order Order.
		 * @return mixed
		 */
		public function update_failure_url( $url, $order ) {
			if ( wcs_is_subscription( $order ) ) {
				wc_clear_notices();
				$msg = WC()->session->get( 'wallee_failure_message', null );
				if ( ! empty( $msg ) ) {
					WooCommerce_Wallee::instance()->add_notice( (string) $msg, 'error' );
					WC()->session->set( 'wallee_failure_message', null );
				}
				return $order->get_view_order_url();
			}
			return $url;
		}


		/**
		 * Add valid order statuses for subscription completion.
		 *
		 * @param mixed $statuses Statuses.
		 * @param mixed $order Order.
		 * @return mixed
		 */
		public function add_valid_order_statuses_for_subscription_completion( $statuses, $order = null ) {
			if ( isset( $GLOBALS['_wc_wallee_subscription_fulfill'] ) ) {
				$statuses[] = 'wallee-waiting';
				$statuses[] = 'wallee-manual';
			}
			return $statuses;
		}


		/**
		 * Update transaction from session.
		 *
		 * @param \Wallee\Sdk\Model\AbstractTransactionPending $transaction Transaction.
		 * @return \Wallee\Sdk\Model\AbstractTransactionPending
		 */
		public function update_transaction_from_session( \Wallee\Sdk\Model\AbstractTransactionPending $transaction ) {
			if ( WC_Subscriptions_Cart::cart_contains_subscription() || wcs_cart_contains_failed_renewal_order_payment() ) {
				$transaction->setTokenizationMode( \Wallee\Sdk\Model\TokenizationMode::FORCE_CREATION_WITH_ONE_CLICK_PAYMENT );
			}
			return $transaction;
		}

		/**
		 * Modify line item method change.
		 *
		 * @param array $line_items Line items.
		 * @param mixed $order Order.
		 * @return array|\Wallee\Sdk\Model\LineItemCreate[] LineItemCreate.
		 */
		public function modify_line_item_method_change( array $line_items, $order ) {
			if ( WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment ) {
				// It is a method change for a subscription -> zero transaction.
				$line_item = new \Wallee\Sdk\Model\LineItemCreate();
				$line_item->setAmountIncludingTax( 0 );
				$line_item->setQuantity( 1 );
				$line_item->setName( __( 'Payment Method Change', 'woo-wallee-subscription' ) );
				$line_item->setShippingRequired( false );
				$line_item->setSku( 'paymentmethodchange' );
				$line_item->setTaxes( array() );
				$line_item->setType( \Wallee\Sdk\Model\LineItemType::PRODUCT );
				$line_item->setUniqueId( WC_Wallee_Unique_Id::get_uuid() );
				return array( $line_item );
			}
			return $line_items;
		}

		/**
		 * Modify order total method change.
		 *
		 * @param mixed $total Total.
		 * @param mixed $order Order.
		 * @return int|mixed
		 */
		public function modify_order_total_method_change( $total, $order ) {
			if ( WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment && wcs_is_subscription( $order ) ) {
				$total = 0;
			}
			return $total;
		}

		/**
		 * Update transaction from order.
		 *
		 * @param \Wallee\Sdk\Model\AbstractTransactionPending $transaction Transaction.
		 * @param mixed                                                       $order Order.
		 * @return \Wallee\Sdk\Model\AbstractTransactionPending
		 */
		public function update_transaction_from_order( \Wallee\Sdk\Model\AbstractTransactionPending $transaction, $order ) {
			if ( wcs_order_contains_subscription( $order, array( 'parent', 'resubscribe', 'switch', 'renewal' ) ) ) {
				$transaction->setTokenizationMode( \Wallee\Sdk\Model\TokenizationMode::FORCE_CREATION_WITH_ONE_CLICK_PAYMENT );
			}
			if ( WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment ) {
				$transaction->setTokenizationMode( \Wallee\Sdk\Model\TokenizationMode::FORCE_CREATION_WITH_ONE_CLICK_PAYMENT );
			}
			return $transaction;
		}

		/**
		 * Update subscription data.
		 *
		 * @param \Wallee\Sdk\Model\Transaction $transaction Transaction.
		 * @param mixed                                        $order Order.
		 * @return void
		 */
		public function update_subscription_data( \Wallee\Sdk\Model\Transaction $transaction, $order ) {
			if ( wcs_order_contains_subscription( $order, array( 'renewal', 'parent', 'resubscribe', 'switch' ) ) ) {
				$order->add_meta_data( '_wallee_subscription_space_id', $transaction->getLinkedSpaceId(), true );
				$order->add_meta_data( '_wallee_subscription_token_id', $transaction->getToken()->getId(), true );
				$order->save();
				$subscriptions = wcs_get_subscriptions_for_order( $order, array( 'parent', 'switch' ) );
				foreach ( $subscriptions as $subscription ) {
					$subscription->add_meta_data( '_wallee_subscription_space_id', $transaction->getLinkedSpaceId(), true );
					$subscription->add_meta_data( '_wallee_subscription_token_id', $transaction->getToken()->getId(), true );
					$subscription->save();
				}
			}
			if ( wcs_is_subscription( $order->get_id() ) ) {
				$order->add_meta_data( '_wallee_subscription_space_id', $transaction->getLinkedSpaceId(), true );
				$order->add_meta_data( '_wallee_subscription_token_id', $transaction->getToken()->getId(), true );
				$order->save();
			}

			if ( wcs_is_subscription( $order->get_id() ) && $this->is_transaction_method_change( $transaction ) ) {
				$gateway_id = $order->get_meta( '_wallee_gateway_id', true, 'edit' );
				$meta_data = array(
					'post_meta' => array(
						'_wallee_subscription_space_id' => array(
							'value' => $transaction->getLinkedSpaceId(),
							'label' => 'wallee Space Id',
						),
						'_wallee_subscription_token_id' => array(
							'value' => $transaction->getToken()->getId(),
							'label' => 'wallee Token Id',
						),
					),
				);

				WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $order, $gateway_id, $meta_data );
				if ( WC_Subscriptions_Change_Payment_Gateway::will_subscription_update_all_payment_methods( $order ) ) {
					WC_Subscriptions_Change_Payment_Gateway::update_all_payment_methods_from_subscription( $order, $gateway_id );
				}
			}

		}


		/**
		 * Update transaction info.
		 *
		 * @param WC_Wallee_Entity_Transaction_Info $info Info.
		 * @param \Wallee\Sdk\Model\Transaction     $transaction Transaction.
		 * @param WC_Order                                         $order Oder.
		 * @return WC_Wallee_Entity_Transaction_Info
		 */
		public function update_transaction_info( WC_Wallee_Entity_Transaction_Info $info, \Wallee\Sdk\Model\Transaction $transaction, WC_Order $order ) {
			if ( wcs_is_subscription( $order->get_id() ) ) {
				if ( in_array( $transaction->getState(), array( \Wallee\Sdk\Model\TransactionState::FAILED, \Wallee\Sdk\Model\TransactionState::VOIDED, \Wallee\Sdk\Model\TransactionState::FULFILL, \Wallee\Sdk\Model\TransactionState::DECLINE ) ) ) {
					$info->set_order_id( null );
				}
			}
			return $info;
		}


		/**
		 * Ignore status update for subscription.
		 * Do not change the status for subscriptions, this is done by modifying the corresponding orders
		 *
		 * @param mixed    $status Status.
		 * @param WC_Order $order Order.
		 * @return mixed
		 */
		public function ignore_status_update_for_subscription( $status, WC_Order $order ) {
			if ( wcs_is_subscription( $order->get_id() ) ) {
				$status = $order->get_status();
			}
			return $status;
		}

		/**
		 * Set transaction ids into session.
		 *
		 * @param mixed    $subscription Subscription.
		 * @param WC_Order $order Order.
		 * @return void
		 */
		public function set_transaction_ids_into_session( $subscription, WC_Order $order ) {
			$session_handler = WC()->session;
			$existing_transaction = WC_Wallee_Entity_Transaction_Info::load_by_order_id( $order->get_id() );
			if ( $existing_transaction->get_id() !== null && $existing_transaction->get_state() == \Wallee\Sdk\Model\TransactionState::PENDING ) {
				$session_handler->set( 'wallee_transaction_id', $existing_transaction->get_transaction_id() );
				$session_handler->set( 'wallee_space_id', $existing_transaction->get_space_id() );
			}
		}

		/**
		 * Check failed renewal order.
		 *
		 * @param mixed $is_failed_renewal_order Is failed renewal order.
		 * @param mixed $order_id Order id.
		 * @param mixed $orders_old_status Orders old status.
		 * @return mixed
		 */
		public function check_failed_renewal_order( $is_failed_renewal_order, $order_id, $orders_old_status ) {
			$order = WC_Order_Factory::get_order( $order_id );
			if ( $order ) {
				$gateway = wc_get_payment_gateway_by_order( $order );
				if ( $gateway instanceof WC_Wallee_Gateway ) {
					if ( 'failed' == $orders_old_status ) {
						update_post_meta( $order_id, '_wallee_subscription_failed_renewal', true );
						return $is_failed_renewal_order;
					}
					return get_post_meta( $order_id, '_wallee_subscription_failed_renewal', true );
				}
			}
			return $is_failed_renewal_order;
		}
	}

}
WooCommerce_Wallee_Subscription::instance();
