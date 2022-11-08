<?php
/**
 *
 * WC_Wallee_Subscription_Service_ChargeFlow Class
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
	exit(); // Exit if accessed directly.
}
/**
 * This service provides functions to deal with chargeflows
 */
class WC_Wallee_Subscription_Service_ChargeFlow extends WC_Wallee_Service_Abstract {

	/**
	 * The transaction API service.
	 *
	 * @var \Wallee\Sdk\Service\ChargeFlowService
	 */
	private $chargeflow_service;


	/**
	 * Returns the transaction API service.
	 *
	 * @return \Wallee\Sdk\Service\ChargeFlowService
	 */
	protected function get_chargeflow_service() {
		if ( null === $this->chargeflow_service ) {
			$this->chargeflow_service = new \Wallee\Sdk\Service\ChargeFlowService( WC_Wallee_Helper::instance()->get_api_client() );
		}
		return $this->chargeflow_service;
	}

	/**
	 * Apply chargeflow on transaction.
	 *
	 * @param mixed $space_id Space id.
	 * @param mixed $transaction_id Transaction id.
	 * @return mixed
	 */
	public function apply_chargeflow_on_transaction( $space_id, $transaction_id ) {
		return $this->get_chargeflow_service()->applyFlow( $space_id, $transaction_id );
	}
}
