<?php
/**
 *
 * WC_Wallee_Subscription_Autoloader Class
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
 * This is the autoloader for wallee Subscription classes.
 */
class WC_Wallee_Subscription_Autoloader {

	/**
	 * Path to the includes directory.
	 *
	 * @var string
	 */
	private $include_path = '';

	/**
	 * The Constructor.
	 */
	public function __construct() {
		spl_autoload_register(
			array(
				$this,
				'autoload',
			)
		);
		$this->include_path = WC_WALLEE_SUBSCRIPTION_ABSPATH . 'includes/';
	}

	/**
	 * Take a class name and turn it into a file name.
	 *
	 * @param  string $class Class.
	 * @return string
	 */
	private function get_file_name_from_class( $class ) {
		return 'class-' . str_replace( '_', '-', $class ) . '.php';
	}

	/**
	 * Include a class file.
	 *
	 * @param  string $path Path.
	 * @return bool successful or not
	 */
	private function load_file( $path ) {
		if ( $path && is_readable( $path ) ) {
			include_once( $path );
			return true;
		}
		return false;
	}

	/**
	 * Auto-load WC Wallee classes on demand to reduce memory consumption.
	 *
	 * @param string $class Class.
	 */
	public function autoload( $class ) {
		$class = strtolower( $class );

		if ( 0 !== strpos( $class, 'wc_wallee_subscription' ) ) {
			return;
		}

		$file = $this->get_file_name_from_class( $class );
		$path = '';

		if ( strpos( $class, 'wc_wallee_subscription_service' ) === 0 ) {
			$path = $this->include_path . 'service/';
		} elseif ( strpos( $class, 'wc_wallee_subscription_entity' ) === 0 ) {
			$path = $this->include_path . 'entity/';
		} elseif ( strpos( $class, 'wc_wallee_subscription_provider' ) === 0 ) {
			$path = $this->include_path . 'provider/';
		} elseif ( strpos( $class, 'wc_wallee_subscription_webhook' ) === 0 ) {
			$path = $this->include_path . 'webhook/';
		} elseif ( strpos( $class, 'wc_wallee_subscription_admin' ) === 0 ) {
			$path = $this->include_path . 'admin/';
		}

		if ( empty( $path ) || ! $this->load_file( $path . $file ) ) {
			$this->load_file( $this->include_path . $file );
		}

		$this->load_file( $this->include_path . $file );
	}
}

new WC_Wallee_Subscription_Autoloader();
