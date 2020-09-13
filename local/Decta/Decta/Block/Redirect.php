<?php
/*
 *
 * @category   Community
 * @package    Decta_Decta
 * @copyright
 * @license    Open Software License (OSL 3.0)
 *
 */

/*
 * Decta payment module
 *
 * @author Decta
 *
 */

require_once getcwd() . "/app/code/local/Decta/Decta/lib/decta_api.php";
require_once getcwd() . "/app/code/local/Decta/Decta/lib/decta_logger_magento.php";

class Decta_Decta_Block_Redirect extends Mage_Core_Block_Abstract
{
    protected function _toHtml()
    {
        $decta = Mage::getModel('Decta/Decta');

        $payment = $decta->createPayment();
        $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());


        if (!$payment) {
            $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Could not load decta payment form');
            $order->setStatus('canceled');
            $order->save();
            
            $url = Mage::getUrl('checkout/onepage/failure', array('_secure' => true));
            Mage::app()->getFrontController()->getResponse()->setRedirect($url);
            return;           
        }

        $state = $decta->getConfigData('order_status');
        $order = $decta->getQuote();
        $order->setStatus($state);
        $order->save();

        Mage::getSingleton('core/session')->setDectaPaymentId($payment['id']);
        Mage::app()->getFrontController()->getResponse()->setRedirect($payment['full_page_checkout']);
    }
}
