<?php

class Decta_Decta_Model_Decta extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'Decta';
    protected $_formBlockType = 'Decta/form';

    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('Decta/redirect', array('_secure' => true));
    }

    public function getQuote()
    {
        $orderIncrementId = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        return $order;
    }

    public function createPayment()
    {
        $order_id = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);

        $decta = new DectaAPI(
            $this->getConfigData('private_key'),
            $this->getConfigData('public_key'),
            new DectaLoggerMagento()
        );

        $params = array(
            'number' => (string)$order_id,
            'referrer' => 'Magento v1.x module ' . DECTA_MODULE_VERSION,
            'language' =>  $this->_language('en'),
            'success_redirect' => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'Decta/response?result=success',
            'failure_redirect' => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'Decta/response?result=failure',
            'currency' => $order->getOrderCurrencyCode()
        );

        $this->addUserData($order, $decta, $params);
        $params['products'][] = [
            'price' => round($order->getGrandTotal(), 2),
            'title' => 'default',
            'quantity' => 1
        ];

        $payment = $decta->create_payment($params);
        return $payment;
    }

    protected function addUserData($order, $decta, &$params)
    {
        $user_data = [
            'email' => $order->getCustomerEmail(),
            'first_name' => $order->getCustomerFirstname(),
            'last_name' => $order->getCustomerLastname(),
            'phone' => $order->getShippingAddress()->getTelephone(),
            'send_to_email' => true
        ];
        $findUser = $decta->getUser($user_data['email'], $user_data['phone']);
        if(!$findUser){
            if($decta->createUser($user_data)){
                $findUser = $decta->getUser($user_data['email'],$user_data['phone']);
            }
        }
        $user_data['original_client'] = $findUser['id'];

        $params['client'] = $user_data;
    }

    function _language($lang_id)
    {
        $languages = array('en', 'ru', 'lv', 'lt');

        if (in_array(strtolower($lang_id), $languages)) {
            return $lang_id;
        } else {
            return 'en';
        }
    }
}
