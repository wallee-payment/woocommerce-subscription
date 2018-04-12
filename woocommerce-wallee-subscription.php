<?php
/**
 * Plugin Name: WooCommerce wallee Subscription
 * Plugin URI: https://wordpress.org/plugins/woo-wallee-subscription
 * Description: Addon to processs WooCommerce subscriptions with wallee
 * Version: 1.0.0
 * License: Apache2
 * License URI: http://www.apache.org/licenses/LICENSE-2.0
 * Author: customweb GmbH
 * Author URI: https://www.customweb.com
 * Requires at least: 4.4
 * Tested up to: 4.9
 * WC requires at least: 3.0.0
 * WC tested up to: 3.3.4
 *
 * Text Domain: woocommerce-wallee-subscription
 * Domain Path: /languages/
 *
 */
if (! defined('ABSPATH')) {
    exit(); // Exit if accessed directly.
}

if (! class_exists('WooCommerce_Wallee_Subscription')) {

    /**
     * Main WooCommerce Wallee Class
     *
     * @class WooCommerce_Wallee_Subscription
     */
    final class WooCommerce_Wallee_Subscription
    {

        /**
         * WooCommerce Wallee version.
         *
         * @var string
         */
        private $version = '1.0.0';

        /**
         * The single instance of the class.
         *
         * @var WooCommerce_Wallee_Subscription
         */
        protected static $_instance = null;

        private $logger = null;

        /**
         * Main WooCommerce Wallee Instance.
         *
         * Ensures only one instance of WooCommerce Wallee is loaded or can be loaded.
         *
         * @return WooCommerce_Wallee_Subscription - Main instance.
         */
        public static function instance()
        {
            if (self::$_instance === null) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        /**
         * WooCommerce_Wallee_Subscription Constructor.
         */
        protected function __construct()
        {
            $this->define_constants();
            $this->includes();
            $this->init_hooks();
        }

        public function get_version()
        {
            return $this->version;
        }

        /**
         * Define constant if not already set.
         *
         * @param string $name
         * @param string|bool $value
         */
        protected function define($name, $value)
        {
            if (! defined($name)) {
                define($name, $value);
            }
        }

        public function log($message, $level = WC_Log_Levels::WARNING)
        {
            if ($this->logger == null) {
                $this->logger = new WC_Logger();
            }
            
            $this->logger->log($level, $message, array(
                'source' => 'woo-wallee-subscription'
            ));
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Woo Wallee Subscription: " . $message);
            }
        }

        /**
         * Get the plugin url.
         *
         * @return string
         */
        public function plugin_url()
        {
            return untrailingslashit(plugins_url('/', __FILE__));
        }

        /**
         * Get the plugin path.
         *
         * @return string
         */
        public function plugin_path()
        {
            return untrailingslashit(plugin_dir_path(__FILE__));
        }

        /**
         * Define WC Wallee Constants.
         */
        protected function define_constants()
        {
            $this->define('WC_WALLEE_SUBSCRIPTION_PLUGIN_FILE', __FILE__);
            $this->define('WC_WALLEE_SUBSCRIPTION_ABSPATH', dirname(__FILE__) . '/');
            $this->define('WC_WALLEE_SUBSCRIPTION_PLUGIN_BASENAME', plugin_basename(__FILE__));
            $this->define('WC_WALLEE_SUBSCRIPTION_VERSION', $this->version);
            $this->define('WC_WALLEE_SUBSCRIPTION_REQUIRED_WALLEE_VERSION', '1.1.0');
            $this->define('WC_WALLEE_REQUIRED_WC_SUBSCRIPTION_VERSION', '2.2');
        }

        /**
         * Include required core files used in admin and on the frontend.
         */
        protected function includes()
        {
            /**
             * Class autoloader.
             */
            require_once (WC_WALLEE_SUBSCRIPTION_ABSPATH . 'includes/class-wc-wallee-subscription-autoloader.php');
            require_once (WC_WALLEE_SUBSCRIPTION_ABSPATH . 'includes/class-wc-wallee-subscription-migration.php');
            
            if (is_admin()) {
                require_once (WC_WALLEE_SUBSCRIPTION_ABSPATH . 'includes/admin/class-wc-wallee-subscription-admin.php');
            }
        }

        protected function init_hooks()
        {
            register_activation_hook(__FILE__, array(
                'WC_Wallee_Subscription_Migration',
                'install_db'
            ));
            add_action('plugins_loaded', array(
                $this,
                'loaded'
            ), 0);
        }

        /**
         * Load Localization files.
         *
         * Note: the first-loaded translation file overrides any following ones if the same translation is present.
         *
         * Locales found in:
         * - WP_LANG_DIR/woocommerce-wallee/woocommerce-wallee-LOCALE.mo
         */
        public function load_plugin_textdomain()
        {
            $locale = apply_filters('plugin_locale', get_locale(), 'woocommerce-wallee-subscription');
            
            load_textdomain('woocommerce-wallee-subscription', WP_LANG_DIR . '/woocommerce-wallee/woocommerce-wallee-subscription' . $locale . '.mo');
            load_plugin_textdomain('woocommerce-wallee-subscription', false, plugin_basename(dirname(__FILE__)) . '/languages');
        }

        /**
         * Init WooCommerce Wallee when plugins are loaded.
         */
        public function loaded()
        {
            // Set up localisation.
            $this->load_plugin_textdomain();
            
            add_filter('wc_wallee_enhance_gateway', array(
                $this,
                'enhance_gateway'
            ));
            add_filter('wc_wallee_modify_sesion_create_transaction', array(
                $this,
                'update_transaction_from_session'
            ));
            add_filter('wc_wallee_modify_session_pending_transaction', array(
                $this,
                'update_transaction_from_session'
            ));
            add_filter('wc_wallee_modify_order_create_transaction', array(
                $this,
                'update_transaction_from_order'
            ), 10, 2);
            add_filter('wc_wallee_modify_order_pending_transaction', array(
                $this,
                'update_transaction_from_order'
            ), 10, 2);
            add_filter('wc_wallee_modify_confirm_transaction', array(
                $this,
                'update_transaction_from_order'
            ),10, 2);
            
            add_action('wc_wallee_authorized', array(
                $this,
                'update_subscription_data'
            ), 10, 2);
            add_action('wc_wallee_fulfill', array(
                $this,
                'fulfill_in_progress'
            ), 10, 2);
            add_filter('woocommerce_valid_order_statuses_for_payment', array(
                $this,
                'add_valid_order_statuses_for_subscription_completion'
            ), 10, 2);
            add_filter('wc_wallee_update_transaction_info', array(
                $this,
                'update_transaction_info'
            ), 10,3);
            add_filter('wc_wallee_is_order_pay_endpoint', array(
                $this, 'is_order_pay_endpoint'
            ), 10, 2);
            add_filter('wc_wallee_confirmed_status', array(
                $this, 'ignore_status_update_for_subscription'
            ), 10, 2);
            add_filter('wc_wallee_authorized_status', array(
                $this, 'ignore_status_update_for_subscription'
            ), 10, 2);
            add_filter('wc_wallee_completed_status', array(
                $this, 'ignore_status_update_for_subscription'
            ), 10, 2);
            add_filter('wc_wallee_decline_status', array(
                $this, 'ignore_status_update_for_subscription'
            ), 10, 2);
            add_filter('wc_wallee_failed_status', array(
                $this, 'ignore_status_update_for_subscription'
            ), 10, 2);
            add_filter('wc_wallee_voided_status', array(
                $this, 'ignore_status_update_for_subscription'
            ), 10, 2);
            add_action('wcs_after_renewal_setup_cart_subscriptions', array(
                $this, 'set_transaction_ids_into_session'
            ), 10, 2);
            add_filter('woocommerce_subscriptions_is_failed_renewal_order', array(
                $this, 'check_failed_renewal_order'
            ), 10, 3);
            
        }

        public function enhance_gateway(WC_Wallee_Gateway $gateway)
        {
            $gateway->supports = array_merge($gateway->supports, array(
                'subscriptions',
                'multiple_subscriptions',
                'subscription_cancellation',
                'subscription_suspension',
                'subscription_reactivation',
                'subscription_amount_changes',
                'subscription_date_changes',
                'subscription_payment_method_change',
                'subscription_payment_method_change_customer',
                'subscription_payment_method_change_admin'
            ));
            $subscription_gateway = new WC_Wallee_Subscription_Gateway($gateway);
            add_action('woocommerce_scheduled_subscription_payment_' . $gateway->id, array(
                $subscription_gateway,
                'process_scheduled_subscription_payment'
            ), 10, 2);
            //Handle Admin Token Setting
            add_filter('woocommerce_subscription_payment_meta', array(
                $subscription_gateway,
                'add_subscription_payment_meta'
            ),10, 2);
            add_action('woocommerce_subscription_validate_payment_meta', array(
                $subscription_gateway,
                'validate_subscription_payment_meta'
            ),10, 2);
            //Handle Pay Failed Renewal
            add_action('woocommerce_subscription_failing_payment_method_updated_' . $gateway->id, array(
                $this,
                'process_subscription_failing_payment_method_updated'
            ), 10, 2);
            
            
            return $gateway;
        }
        
        public function fulfill_in_progress(\Wallee\Sdk\Model\Transaction $transaction, $order){
            if(wcs_order_contains_subscription($order, array( 'parent', 'resubscribe', 'switch', 'renewal'))){
                $GLOBALS['_wc_wallee_subscription_fulfill'] = true;
            }
        }
        
        public function add_valid_order_statuses_for_subscription_completion($statuses, $order = null){
            if(isset($GLOBALS['_wc_wallee_subscription_fulfill'])){
                $statuses[] = 'wallee-waiting';
                $statuses[] = 'wallee-manual';
            }
            return $statuses;
        }

        public function update_transaction_from_session(\Wallee\Sdk\Model\AbstractTransactionPending $transaction)
        {
            if(WC_Subscriptions_Cart::cart_contains_subscription() ||  wcs_cart_contains_failed_renewal_order_payment()){
                $transaction->setTokenizationMode(\Wallee\Sdk\Model\TokenizationnMode::FORCE_CREATION_WITH_ONE_CLICK_PAYMENT);
            }
            return $transaction;
        }

        public function update_transaction_from_order(\Wallee\Sdk\Model\AbstractTransactionPending $transaction, $order)
        {
            if(wcs_order_contains_subscription($order, array( 'parent', 'resubscribe', 'switch', 'renewal'))){
                $transaction->setTokenizationMode(\Wallee\Sdk\Model\TokenizationnMode::FORCE_CREATION_WITH_ONE_CLICK_PAYMENT);
            }
            if(wcs_is_subscription($order->get_id())){
                //It is a method change for a subscription -> zero transaction
                $line_item = new \Wallee\Sdk\Model\LineItemCreate();
                $line_item->setAmountIncludingTax(0);
                $line_item->setQuantity(1);
                $line_item->setName(__('Payment Method Change', 'woocommerce-wallee-subscription'));
                $line_item->setShippingRequired(false);
                $line_item->setSku('paymentmethodchange');
                $line_item->setTaxes(array());
                $line_item->setType(\Wallee\Sdk\Model\LineItemType::PRODUCT);
                $line_item->setUniqueId(WC_Wallee_Unique_Id::get_uuid());
                $transaction->setLineItems(array($line_item));
                
                $transaction->setTokenizationMode(\Wallee\Sdk\Model\TokenizationnMode::FORCE_CREATION_WITH_ONE_CLICK_PAYMENT);
            }
            return $transaction;
        }

        public function update_subscription_data(\Wallee\Sdk\Model\Transaction $transaction, $order)
        {
            if(wcs_order_contains_subscription($order, array('renewal', 'parent', 'resubscribe', 'switch'))){
                $order->add_meta_data('_wallee_subscription_space_id', $transaction->getLinkedSpaceId(),true);
                $order->add_meta_data('_wallee_subscription_token_id', $transaction->getToken()->getId(),true);
                $order->save();
                $subscriptions = wcs_get_subscriptions_for_order($order, array('parent', 'switch'));                
                foreach ($subscriptions as $id => $subscription) {
                    $subscription->add_meta_data('_wallee_subscription_space_id', $transaction->getLinkedSpaceId(), true);
                    $subscription->add_meta_data('_wallee_subscription_token_id', $transaction->getToken()
                        ->getId(),true);
                    $subscription->save();
                }
            }
            if(wcs_is_subscription($order->get_id())){
                $order->add_meta_data('_wallee_subscription_space_id', $transaction->getLinkedSpaceId(),true);
                $order->add_meta_data('_wallee_subscription_token_id', $transaction->getToken()->getId(),true);
                $order->save();
            }
        }

        public function process_subscription_failing_payment_method_updated($subscription, $renewal_order ){
            update_post_meta( $subscription->get_id(), '_wallee_subscription_space_id', $renewal_order->get_meta('_wallee_subscription_space_id',true));
            update_post_meta( $subscription->get_id(), '_wallee_subscription_token_id', $renewal_order->get_meta('_wallee_subscription_token_id',true));
        }
        
        public function update_transaction_info(WC_Wallee_Entity_Transaction_Info $info, \Wallee\Sdk\Model\Transaction $transaction, WC_Order $order){
            if(wcs_is_subscription($order->get_id())){
                if(in_array($transaction->getState(), array(\Wallee\Sdk\Model\TransactionState::FAILED, \Wallee\Sdk\Model\TransactionState::VOIDED, \Wallee\Sdk\Model\TransactionState::FULFILL, \Wallee\Sdk\Model\TransactionState::DECLINE))){
                    $info->set_order_id(null);
                }
            }
            return $info;
        }
        
        public function is_order_pay_endpoint($is_endpoint, $order_id){
            if(wcs_is_subscription($order_id)){
                return true;
            }
            return $is_endpoint;
        }
        
        /**
         * Do not change the status for subscriptions, this is done by modifying the corresponding orders
         * @param string $status
         * @param WC_Order $order
         * @return string
         */
        public function ignore_status_update_for_subscription($status, WC_Order $order){
            if(wcs_is_subscription($order->get_id())){
                $status = $order->get_status();
            }
            return $status;
        }
        
        public function set_transaction_ids_into_session($subscription, WC_Order $order){
            $session_handler = WC()->session;
            $transaction_id = $session_handler->get('wallee_transaction_id', null);
            $existing_transaction = WC_Wallee_Entity_Transaction_Info::load_by_order_id($order->get_id());
            if($existing_transaction->get_id() !== null && $existing_transaction->get_state() == \Wallee\Sdk\Model\TransactionState::PENDING){
                $session_handler->set('wallee_transaction_id', $existing_transaction->get_transaction_id());
                $session_handler->set('wallee_space_id', $existing_transaction->get_space_id());
            }
        }
        
        public function check_failed_renewal_order($is_failed_renewal_order, $order_id, $orders_old_status ){
            $order = WC_Order_Factory::get_order($order_id);
            if($order){
                $gateway = wc_get_payment_gateway_by_order($order);
                if ($gateway instanceof WC_Wallee_Gateway) {
                    if($orders_old_status == 'failed'){
                        update_post_meta($order_id, '_wallee_subscription_failed_renewal', true);
                        return $is_failed_renewal_order;
                    }
                    return get_post_meta($order_id, '_wallee_subscription_failed_renewal', true);
                }
            }
            return $is_failed_renewal_order;
        }
    }
}
WooCommerce_Wallee_Subscription::instance();
