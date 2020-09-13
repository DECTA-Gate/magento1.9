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
 * @author     Decta
 *
 */
require_once dirname(__FILE__) . "/../lib/decta_api.php";
require_once dirname(__FILE__) . "/../lib/decta_logger_magento.php";

class Decta_Decta_Block_Response extends Mage_Core_Block_Abstract
{

    protected function _toHtml()
    {

        $order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();

        $dectaModel = Mage::getModel('Decta/Decta');

        $decta = new DectaAPI(
            $dectaModel->getConfigData('private_key'),
            $dectaModel->getConfigData('public_key'),
            new DectaLoggerMagento()
        );

        $decta->log_info('Processing callback');
        if ($_GET['result'] == 'failure') {
            $decta->log_info('Failure callback');
            if ($lastQuoteId = Mage::getSingleton('checkout/session')->getLastQuoteId()){
                $quote = Mage::getModel('sales/quote')->load($lastQuoteId);
                $quote->setIsActive(true)->save();
            }

            $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
            if ($order->getId()) {
                $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, "ERROR: Payment refused");
                $order->setStatus("canceled");
                $order->save();
            }

            $url = Mage::getUrl('checkout/cart', array('_secure' => true));
            Mage::app()->getFrontController()->getResponse()->setRedirect($url);
            return;
        }
        
        if ($_GET['result'] == 'success') {
            $payment_id = Mage::getSingleton('core/session')->getDectaPaymentId();
            $decta->log_info('Success callback');


            
            if ($decta->was_payment_successful($order_id, $payment_id)) {
                try {
                    $order = Mage::getModel('sales/order');
                    $order->loadByIncrementId($order_id);
                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, 'Gateway has authorized the payment.');

                    $success_order_status = $dectaModel->getConfigData('after_pay_status');
                    $order->setStatus($success_order_status);
                    
                    $order->sendNewOrderEmail();
                    
                    $order->setEmailSent(true);
                    $order->save();
                    
                    Mage::getSingleton('checkout/session')->unsQuoteId();
                    $url = Mage::getUrl('checkout/onepage/success', array('_secure' => true));
                    Mage::app()->getFrontController()->getResponse()->setRedirect($url);
                    $decta->log_info('Payment verified, redirecting to success');
                } catch (Exception $e) {
                    $decta->log_error('Payment verified, but internal magento error occurred', $e);
                    $url = Mage::getUrl('checkout/onepage/failure', array('_secure' => true));
                    Mage::app()->getFrontController()->getResponse()->setRedirect($url);                   
                }                         
            } else {
                $decta->log_error('Could not verify payment!');
                $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
                if ($order->getId()) {
                    $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true, "ERROR: Payment received, but verification failed.");
                    $order->setStatus("canceled");
                    $order->save();
                }
                $url = Mage::getUrl('checkout/onepage/success', array('_secure' => true));
                Mage::app()->getFrontController()->getResponse()->setRedirect($url);
            }
        }
    }


}