<?php

if (!defined('ABSPATH')) {
	exit();
}

/**
 * This class implements the wallee subscription gateways
 */
class WC_Wallee_Subscription_Gateway {
	
   /**
    * 
    * @var WC_Wallee_Gateway
    */
    private $gateway;
    
    public function __construct(WC_Wallee_Gateway $gateway){
        $this->gateway = $gateway;
    }
    
    public function process_scheduled_subscription_payment($amount_to_charge, WC_Order $order){
        try{
        $token_space_id =  get_post_meta( $order->get_id(), '_wallee_subscription_space_id', true );
        $token_id =  get_post_meta( $order->get_id(), '_wallee_subscription_token_id', true );
        if(empty($token_space_id) || $token_space_id != get_option(WooCommerce_Wallee::CK_SPACE_ID)){
            $order->update_status('failed', __('The token space and the configured space are not equal.','woocommerce-wallee-subscription-subscription'));
            return;
        }
        if(empty($token_id)){
            $order->update_status('failed', __('There is no token associated with this subscription.','woocommerce-wallee-subscription-subscription'));
            return;
        }
        $transaction_service = WC_Wallee_Subscription_Service_Transaction::instance();
        
        $transaction_info = WC_Wallee_Entity_Transaction_Info::load_by_order_id($order->get_id());
        if($transaction_info->get_id() > 0){
            $existing_transaction = $transaction_service->get_transaction($transaction_info->get_space_id(), $transaction_info->get_id());
            if($existing_transaction->getState() != \Wallee\Sdk\Model\TransactionState::PENDING){
                return;
            }
            $transaction_service->update_transaction_by_renewal_order($order, $order_total, $token_id, $existing_transaction);
            $transaction = $transaction_service->process_transaction_without_user_interaction($existing_transaction->getLinkedSpaceId(), $existing_transaction->getId());
        }
        else{
            $create_transaction = $transaction_service->create_transaction_by_renewal_order($order, $amount_to_charge, $token_id);
            $transaction = $transaction_service->process_transaction_without_user_interaction($token_space_id, $create_transaction->getId());
        }
        $order->add_meta_data('_wallee_linked_ids', array('sapce_id' =>  $transaction->getLinkedSpaceId(), 'transaction_id' => $transaction->getId()), false);
        $order->delete_meta_data('_wc_wallee_restocked');
        }
        catch(Exception $e){
            $order->update_status('failed', $e->getMessage() ,'woocommerce-wallee-subscription-subscription');
            WooCommerce_Wallee_Subscription::instance()->log($e->getMessage().$e->getTraceAsString());
            return;
        }
    }
    
    public function add_subscription_payment_meta($payment_meta, $subscription){
        $payment_meta[ $this->gateway->id ] = array(
            'post_meta' => array(
                '_wallee_subscription_space_id' => array(
                    'value' => get_post_meta( $subscription->get_id(), '_wallee_subscription_space_id', true ),
                    'label' => 'wallee Space Id'
                ),
                '_wallee_subscription_token_id' => array(
                    'value' => get_post_meta( $subscription->get_id(), '_wallee_subscription_token_id', true ),
                    'label' => 'wallee Token Id'
                ),
            ),
        );
        return $payment_meta;        
    }
    
    public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
        
        if ( $this->gateway->id === $payment_method_id ) {            
            if ( ! isset( $payment_meta['post_meta']['_wallee_subscription_space_id']['value'] ) || empty( $payment_meta['post_meta']['_wallee_subscription_space_id']['value'] ) ) {
                throw new Exception( sprintf(__('The %s value is required.', 'woocommerce-wallee-subscription-subscription'), 'wallee Space Id'));
            }
            elseif ( $payment_meta['post_meta']['_wallee_subscription_space_id']['value'] !=  get_option(WooCommerce_Wallee::CK_SPACE_ID)) {
                throw new Exception( sprintf(__('The %s needs to be in the same space as configured in the main configuration.', 'woocommerce-wallee-subscription-subscription'), '_wallee_subscription_space_id'));
            }
            if ( ! isset( $payment_meta['post_meta']['_wallee_subscription_token_id']['value'] ) || empty( $payment_meta['post_meta']['_wallee_subscription_token_id']['value'] ) ) {
                throw new Exception( sprintf(__('The %s value is required.', 'woocommerce-wallee-subscription-subscription'), 'wallee Token Id'));
            }
        }        
    }
    

}