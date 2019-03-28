<?php
if (!defined('ABSPATH')) {
	exit();
}
/**
 * wallee WooCommerce
 *
 * This WooCommerce plugin enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */
/**
 * This class handles the database setup and migration.
 */
class WC_Wallee_Subscription_Migration {
    
    const CK_DB_VERSION = 'wc_wallee_subscription_db_version';
    
	private static $db_migrations = array(
	);

	/**
	 * Hook in tabs.
	 */
	public static function init(){
		add_action('init', array(
			__CLASS__,
			'check_version' 
		), 5);
		add_action('wpmu_new_blog', array(
			__CLASS__,
			'wpmu_new_blog' 
		));
		add_filter('wpmu_drop_tables', array(
			__CLASS__,
			'wpmu_drop_tables' 
		));
		add_action('in_plugin_update_message-woo-wallee-subscription/woo-wallee-subscription.php', array(
			__CLASS__,
			'in_plugin_update_message' 
		));
	}

	public static function install_db($networkwide){
		global $wpdb;
		if (!is_blog_installed()) {
			return;
		}
		if (!defined('WC_WALLEE_SUBSCRIPTION_INSTALLING')) {
			define('WC_WALLEE_SUBSCRIPTION_INSTALLING', true);
		}		
		self::check_requirements();		
		if (function_exists('is_multisite') && is_multisite()) {
			// check if it is a network activation - if so, run the activation function for each blog id
			if ($networkwide) {
				$old_blog = $wpdb->blogid;
				// Get all blog ids
				$blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
				foreach ($blog_ids as $blog_id) {
					switch_to_blog($blog_id);
					self::migrate_db();
				}
				switch_to_blog($old_blog);
				return;
			}
		}
		self::migrate_db();
	}

	
	/**
	 * Checks if the system requirements are met
	 *
	 * calls wp_die f requirements not met
	 */
	private static function check_requirements() {
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' ) ;
		
		$errors = array();
		
		if (!is_plugin_active('woo-wallee/woocommerce-wallee.php')){
		    $errors[] = sprintf(__("Woocommerce wallee %s+ has to be active.", "woo-wallee-subscription"), WC_WALLEE_SUBSCRIPTION_REQUIRED_WALLEE_VERSION);
		}
		else{
			$base_module_data = get_plugin_data(WP_PLUGIN_DIR .'/woo-wallee/woocommerce-wallee.php', false, false);
			
			if (version_compare ($base_module_data['Version'] , WC_WALLEE_SUBSCRIPTION_REQUIRED_WALLEE_VERSION, '<')){
			    $errors[] = sprintf(__("Woocommerce wallee %s+ is required. (You're running version %s)", "woo-wallee-subscription"), WC_WALLEE_SUBSCRIPTION_REQUIRED_WALLEE_VERSION, $base_module_data['Version']);
			}
		}
		
		if (!is_plugin_active('woocommerce-subscriptions/woocommerce-subscriptions.php')){
		    $errors[] = sprintf(__("Woocommerce Subscriptions %s+ has to be active.", "woo-wallee-subscription"), WC_WALLEE_REQUIRED_WC_SUBSCRIPTION_VERSION);
		}
		else{
		    $woocommerce_subscriptions_data = get_plugin_data(WP_PLUGIN_DIR .'/woocommerce/woocommerce.php', false, false);
		    
		    if (version_compare ($woocommerce_subscriptions_data['Version'] , WC_WALLEE_REQUIRED_WC_SUBSCRIPTION_VERSION, '<')){
		        $errors[] = sprintf(__("Woocommerce Subscriptions %s+ is required. (You're running version %s)", "woo-wallee-subscription"), WC_WALLEE_REQUIRED_WC_SUBSCRIPTION_VERSION, $woocommerce_subscriptions_data['Version']);
		    }
		}
		
		if(!empty($errors)){
			$title = __('Could not activate plugin WooCommerce wallee Subscription', 'woo-wallee-subscription');
			$message = '<h1><strong>'.$title.'</strong></h1><br/>'.
					'<h3>'.__('Please check the following requirements before activating:', 'woo-wallee-subscription').'</h3>'.
					'<ul><li>'.
					implode('</li><li>', $errors).
					'</li></ul>';
			wp_die($message, $title, array('back_link' => true));
			return;
		}
	}
	
	/**
	 * Create tables if new MU blog is created
	 * @param  array $tables
	 * @return string[]
	 */
	public static function wpmu_new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta){
		global $wpdb;
		
		if (is_plugin_active_for_network('woo-wallee-subscription/woo-wallee-subscription.php')) {
			$old_blog = $wpdb->blogid;
			switch_to_blog($blog_id);
			self::migrate_db();
			switch_to_blog($old_blog);
		}
	}

	private static function migrate_db(){
	    $current_version = get_option(self::CK_DB_VERSION, 0);
		foreach (self::$db_migrations as $version => $function_name) {
			if (version_compare($current_version, $version, '<')) {				
				call_user_func(array(
					__CLASS__,
					$function_name 
				));				
				update_option(self::CK_DB_VERSION, $version);
				$current_version = $version;
			}
		}
	}

	/**
	 * Uninstall tables when MU blog is deleted.
	 * @param  array $tables
	 * @return string[]
	 */
	public static function wpmu_drop_tables($tables){
		return $tables;
	}

	/**
	 * Check Wallee DB version and run the migration if required.
	 *
	 * This check is done on all requests and runs if he versions do not match.
	 */
	public static function check_version(){
		try {
			$current_version = get_option(self::CK_DB_VERSION, 0);
			$version_keys = array_keys(self::$db_migrations);
			if (version_compare($current_version, '0', '>') && version_compare($current_version, end($version_keys), '<')) {
				//We migrate the Db for all blogs
				self::install_db(true);
			}
		}
		catch (Exception $e) {
			if (is_admin()) {
				add_action('admin_notices', array(
					'WC_Wallee_Admin_Notices',
					'migration_failed_notices' 
				));
			}
		}
	}

	/**
	 * Show plugin changes. Code adapted from W3 Total Cache.
	 */
	public static function in_plugin_update_message($args){
		$transient_name = 'wallee_subscription_upgrade_notice_' . $args['Version'];
		
		if (false === ($upgrade_notice = get_transient($transient_name))) {
			$response = wp_safe_remote_get('https://plugins.svn.wordpress.org/woo-wallee-subscription/trunk/readme.txt');
			
			if (!is_wp_error($response) && !empty($response['body'])) {
				$upgrade_notice = self::parse_update_notice($response['body'], $args['new_version']);
				set_transient($transient_name, $upgrade_notice, DAY_IN_SECONDS);
			}
		}
		echo wp_kses_post($upgrade_notice);
	}

	/**
	 * Parse update notice from readme file.
	 *
	 * @param  string $content
	 * @param  string $new_version
	 * @return string
	 */
	private static function parse_update_notice($content, $new_version){
		// Output Upgrade Notice.
		$matches = null;
		$regexp = '~==\s*Upgrade Notice\s*==\s*=\s*(.*)\s*=(.*)(=\s*' . preg_quote(WC_WALLEE_VERSION) . '\s*=|$)~Uis';
		$upgrade_notice = '';
		
		if (preg_match($regexp, $content, $matches)) {
			$version = trim($matches[1]);
			$notices = (array) preg_split('~[\r\n]+~', trim($matches[2]));
			
			// Check the latest stable version and ignore trunk.
			if ($version === $new_version && version_compare(WC_WALLEE_VERSION, $version, '<')) {
				$upgrade_notice .= '<div class="plugin_upgrade_notice">';
				foreach ($notices as $line) {
					$upgrade_notice .= wp_kses_post(preg_replace('~\[([^\]]*)\]\(([^\)]*)\)~', '<a href="${2}">${1}</a>', $line));
				}
				$upgrade_notice .= '</div> ';
			}
		}		
		return wp_kses_post($upgrade_notice);
	}

}

WC_Wallee_Subscription_Migration::init();
