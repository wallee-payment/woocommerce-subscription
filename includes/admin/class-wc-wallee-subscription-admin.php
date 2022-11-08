<?php
/**
 *
 * WC_Wallee_Subscription_Admin Class
 *
 * Wallee
 * This plugin will add support for process WooCommerce Subscriptions with wallee
 *
 * @category Class
 * @package  Wallee
 * @author   wallee AG (http://www.wallee.com/)
 * @license  http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * WC_Wallee_Subscription_Admin  class
 */
class WC_Wallee_Subscription_Admin {

	/**
	 * The single instance of the class.
	 *
	 * @var WC_Wallee_Subscription_Admin
	 */
	protected static $_instance = null;

	/**
	 * Main Wallee Plugin Admin Instance.
	 *
	 * Ensures only one instance of WC_Wallee_Subscription_Admin is loaded or can be loaded.
	 *
	 * @return WC_Wallee_Subscription_Admin - Main instance.
	 */
	public static function instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * WC Wallee Admin Constructor.
	 */
	protected function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 */
	private function includes() {
		require_once( WC_WALLEE_SUBSCRIPTION_ABSPATH . 'includes/admin/class-wc-wallee-subscription-admin-notices.php' );
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action(
			'admin_init',
			array(
				$this,
				'handle_modules_active',
			)
		);

	}

	/**
	 * Handle modules active.
	 *
	 * @return void
	 */
	public function handle_modules_active() {
		// Subscription plugin or base plugin not activated.
		if ( ! is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) || ! is_plugin_active( 'woo-wallee/woocommerce-wallee.php' ) ) {
			// Deactivate plugin.
			deactivate_plugins( WC_WALLEE_SUBSCRIPTION_PLUGIN_BASENAME );
			add_action(
				'admin_notices',
				array(
					'WC_Wallee_Subscription_Admin_Notices',
					'plugin_deactivated',
				)
			);
		}

	}

}

WC_Wallee_Subscription_Admin::instance();
