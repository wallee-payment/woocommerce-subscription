<?php
if (!defined('ABSPATH')) {
	exit();
}

/**
 * WC Wallee Subscription Admin Notices class
 */
class WC_Wallee_Subscription_Admin_Notices {

	public static function migration_failed_notices(){
	    require_once WC_WALLEE_SUBSCRIPTION_ABSPATH.'views/admin-notices/migration-failed.php';
	}
	
	public static function plugin_deactivated(){
	    require_once WC_WALLEE_SUBSCRIPTION_ABSPATH.'views/admin-notices/plugin-deactivated.php';
	}
}