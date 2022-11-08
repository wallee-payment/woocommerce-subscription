<?php
/**
 *
 * WC_Wallee_Subscription_Migration Class
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
 * This class handles the database setup and migration.
 */
class WC_Wallee_Subscription_Migration {

	const CK_DB_VERSION = 'wc_wallee_subscription_db_version';

	/**
	 * Database migrations.
	 *
	 * @var array
	 */
	private static $db_migrations = array();

	/**
	 * Hook in tabs.
	 */
	public static function init() {
		add_action(
			'init',
			array(
				__CLASS__,
				'check_version',
			),
			5
		);
		add_action(
			'wpmu_new_blog',
			array(
				__CLASS__,
				'wpmu_new_blog',
			)
		);
		add_filter(
			'wpmu_drop_tables',
			array(
				__CLASS__,
				'wpmu_drop_tables',
			)
		);
	}

	/**
	 * Install database.
	 *
	 * @param mixed $networkwide Networkwide.
	 * @return void
	 */
	public static function install_db( $networkwide ) {
		global $wpdb;
		if ( ! is_blog_installed() ) {
			return;
		}
		if ( ! defined( 'WC_WALLEE_SUBSCRIPTION_INSTALLING' ) ) {
			define( 'WC_WALLEE_SUBSCRIPTION_INSTALLING', true );
		}
		self::check_requirements();
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			// check if it is a network activation - if so, run the activation function for each blog id.
			if ( $networkwide ) {
				$old_blog = $wpdb->blogid;
				// Get all blog ids.
				$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
				foreach ( $blog_ids as $blog_id ) {
					switch_to_blog( $blog_id );
					self::migrate_db();
				}
				switch_to_blog( $old_blog );
				return;
			}
		}
		self::migrate_db();
	}


	/**
	 * Checks requirements, calls wp_die if requirements not met.
	 */
	private static function check_requirements() {
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		$errors = array();

		if ( ! is_plugin_active( 'woo-wallee/woocommerce-wallee.php' ) ) {
				/* translators: %s is replaced with "string" */
			$errors[] = sprintf( __( 'wallee %s+ has to be active.', 'woo-wallee-subscription' ), WC_WALLEE_SUBSCRIPTION_REQUIRED_WALLEE_VERSION );
		} else {
			$base_module_data = get_plugin_data( WP_PLUGIN_DIR . '/woo-wallee/woocommerce-wallee.php', false, false );

			if ( version_compare( $base_module_data['Version'], WC_WALLEE_SUBSCRIPTION_REQUIRED_WALLEE_VERSION, '<' ) ) {
					/* translators: %s is replaced with "string" */
				$errors[] = sprintf( __( "wallee %1\$s+ is required. (You're running version %2\$s)", 'woo-wallee-subscription' ), WC_WALLEE_SUBSCRIPTION_REQUIRED_WALLEE_VERSION, $base_module_data['Version'] );
			}
		}

		if ( ! is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {
				/* translators: %s is replaced with "string" */
			$errors[] = sprintf( __( 'Subscriptions %s+ has to be active.', 'woo-wallee-subscription' ), WC_WALLEE_REQUIRED_WC_SUBSCRIPTION_VERSION );
		} else {
			$woocommerce_subscriptions_data = get_plugin_data( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php', false, false );

			if ( version_compare( $woocommerce_subscriptions_data['Version'], WC_WALLEE_REQUIRED_WC_SUBSCRIPTION_VERSION, '<' ) ) {
				$errors[] = sprintf( __( "Subscriptions %1\$s+ is required. (You're running version %2\$s)", 'woo-wallee-subscription' ), WC_WALLEE_REQUIRED_WC_SUBSCRIPTION_VERSION, $woocommerce_subscriptions_data['Version'] );
			}
		}

		if ( ! empty( $errors ) ) {
			$title = __( 'Could not activate plugin wallee Subscription', 'woo-wallee-subscription' );
			$message = '<h1><strong>' . $title . '</strong></h1><br/>' .
					'<h3>' . __( 'Please check the following requirements before activating:', 'woo-wallee-subscription' ) . '</h3>' .
					'<ul><li>' .
					implode( '</li><li>', $errors ) .
					'</li></ul>';
				wp_die( wp_kses_post( $message ), esc_textarea( $title ), array( 'back_link' => true ) );
			return;
		}
	}


	/**
	 * Create tables if new MU blog is created
	 *
	 * @param mixed $blog_id Blog id.
	 * @param mixed $user_id User id.
	 * @param mixed $domain Domain.
	 * @param mixed $path Path.
	 * @param mixed $site_id Site id.
	 * @param mixed $meta meta.
	 * @return void
	 */
	public static function wpmu_new_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
		global $wpdb;

		if ( is_plugin_active_for_network( 'woo-wallee-subscription/woo-wallee-subscription.php' ) ) {
			$old_blog = $wpdb->blogid;
			switch_to_blog( $blog_id );
			self::migrate_db();
			switch_to_blog( $old_blog );
		}
	}

	/**
	 * Migrate database.
	 *
	 * @return void
	 */
	private static function migrate_db() {
		$current_version = get_option( self::CK_DB_VERSION, 0 );
		foreach ( self::$db_migrations as $version => $function_name ) {
			if ( version_compare( $current_version, $version, '<' ) ) {
				call_user_func(
					array(
						__CLASS__,
						$function_name,
					)
				);
				update_option( self::CK_DB_VERSION, $version );
				$current_version = $version;
			}
		}
	}

	/**
	 * Uninstall tables when MU blog is deleted.
	 *
	 * @param  array $tables Tables.
	 * @return string[]
	 */
	public static function wpmu_drop_tables( $tables ) {
		return $tables;
	}

	/**
	 * Check Wallee DB version and run the migration if required.
	 *
	 * This check is done on all requests and runs if he versions do not match.
	 */
	public static function check_version() {
		try {
			$current_version = get_option( self::CK_DB_VERSION, 0 );
			$version_keys = array_keys( self::$db_migrations );
			if ( version_compare( $current_version, '0', '>' ) && version_compare( $current_version, end( $version_keys ), '<' ) ) {
				// We migrate the Db for all blogs.
				self::install_db( true );
			}
		} catch ( Exception $e ) {
			if ( is_admin() ) {
				add_action(
					'admin_notices',
					array(
						'WC_Wallee_Admin_Notices',
						'migration_failed_notices',
					)
				);
			}
		}
	}
}

WC_Wallee_Subscription_Migration::init();
