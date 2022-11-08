<?php
/**
 *
 * WC_Wallee_Subscription_Admin_Notices Class
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
 * WC Wallee Subscription Admin Notices class
 */
class WC_Wallee_Subscription_Admin_Notices {

	/**
	 * Migration failed notices.
	 *
	 * @return void
	 */
	public static function migration_failed_notices() {
		require_once WC_WALLEE_SUBSCRIPTION_ABSPATH . 'views/admin-notices/migration-failed.php';
	}

	/**
	 * Plugin deactivated.
	 *
	 * @return void
	 */
	public static function plugin_deactivated() {
		require_once WC_WALLEE_SUBSCRIPTION_ABSPATH . 'views/admin-notices/plugin-deactivated.php';
	}
}
