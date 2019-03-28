<?php
if (!defined('ABSPATH')) {
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
    protected function get_chargeflow_service(){
        if ($this->chargeflow_service === null) {
            $this->chargeflow_service = new \Wallee\Sdk\Service\ChargeFlowService(WC_Wallee_Helper::instance()->get_api_client());
        }
        return $this->chargeflow_service;
    }
    
    public function apply_chargeflow_on_transaction($space_id, $transaction_id){
        return $this->get_chargeflow_service()->applyFlow($space_id, $transaction_id);
    }
}