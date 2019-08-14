<?php

require_once "Mage" . DS . "Checkout" . DS . "controllers" . DS . "OnepageController.php";

class Eway_Rapid31_TransparentController extends Mage_Checkout_OnepageController
{
    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote;

    public static $_transparentmodel;

    protected $methodPayment;
    protected $transMethod;
    protected $paypalSavedToken;
    protected $savedToken;
    protected $cardInfo;
    protected $masterPassSavedToken;

    function _getSession()
    {
        $this->methodPayment = Mage::getSingleton('core/session')->getMethod();
        $this->transMethod = Mage::getSingleton('core/session')->getTransparentNotsaved();
        if (!$this->transMethod) {
            $this->transMethod = Mage::getSingleton('core/session')->getTransparentSaved();
        }

        if ($this->methodPayment == Eway_Rapid31_Model_Config::PAYMENT_SAVED_METHOD) {
            $this->savedToken = Mage::getSingleton('core/session')->getSavedToken();
        }
        $this->cardInfo = Mage::getSingleton('core/session')->getCardInfo();
    }

    /**
     * @return false|Eway_Rapid31_Model_Request_Transparent
     */
    protected function transparentModel()
    {
        if (!self::$_transparentmodel) {
            self::$_transparentmodel = Mage::getModel('ewayrapid/request_transparent');
        }
        return self::$_transparentmodel;
    }

    /**
     * @return Eway_Rapid31_Helper_Data
     */
    protected function helperData()
    {
        return Mage::helper('ewayrapid/data');
    }

    public function indexAction()
    {
        try {

        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
    }

    /**
     * Action build link redirect checkout after Click Place Order
     */
    public function buildAction()
    {
        try {
            $this->_getSession();
            $quote = $this->_getQuote();
            /** @var Eway_Rapid31_Model_Request_Sharedpage $sharedpageModel */

            $action = 'AccessCodes';
            if ($this->methodPayment == Eway_Rapid31_Model_Config::PAYMENT_SAVED_METHOD) {
                $methodData = Eway_Rapid31_Model_Config::METHOD_TOKEN_PAYMENT;

                //Authorize Only
                if ($this->helperData()->getPaymentAction() != Eway_Rapid31_Model_Method_Notsaved::ACTION_AUTHORIZE_CAPTURE
                    || $this->transMethod == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD) {
                    if ($this->savedToken == Eway_Rapid31_Model_Config::TOKEN_NEW)
                        $methodData = Eway_Rapid31_Model_Config::METHOD_CREATE_TOKEN;
                    else
                        $methodData = Eway_Rapid31_Model_Config::METHOD_UPDATE_TOKEN;
                }
            } else {
                $methodData = Eway_Rapid31_Model_Config::METHOD_PROCESS_PAYMENT;
                if ($this->helperData()->getPaymentAction() != Eway_Rapid31_Model_Method_Notsaved::ACTION_AUTHORIZE_CAPTURE
                    && $this->transMethod != Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD
                    && $this->transMethod != Eway_Rapid31_Model_Config::MASTERPASS_METHOD
                )
                {
                    $methodData = Eway_Rapid31_Model_Config::METHOD_AUTHORISE;
                }
            }

            $data = $this->transparentModel()->createAccessCode($quote, $methodData, $action);
            if ($data['AccessCode']) {
                //save FormActionURL, AccessCode
                Mage::getSingleton('core/session')->setFormActionUrl($data['FormActionURL']);
                if (isset($data['CompleteCheckoutURL']))
                    Mage::getSingleton('core/session')->setCompleteCheckoutURL($data['CompleteCheckoutURL']);
                if ($this->transMethod == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD || $this->transMethod == Eway_Rapid31_Model_Config::PAYPAL_EXPRESS_METHOD || $this->transMethod == Eway_Rapid31_Model_Config::MASTERPASS_METHOD ) {
                    $urlRedirect = Mage::getUrl('ewayrapid/transparent/redirect', array('_secure'=>true)) . '?AccessCode=' . $data['AccessCode'];
                } else {
                    $urlRedirect = Mage::getUrl('ewayrapid/transparent/paynow', array('_secure'=>true)) . '?AccessCode=' . $data['AccessCode'];
                }
                if (Mage::getStoreConfig('payment/ewayrapid_general/connection_type')
                    === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT
                    && Mage::getSingleton('core/session')->getCheckoutExtension()
                    /*(Mage::getStoreConfig('onestepcheckout/general/active')
                        || Mage::getStoreConfig('opc/global/status')
                        || Mage::getStoreConfig('firecheckout/general/enabled')
                        || Mage::getStoreConfig('gomage_checkout/general/enabled')
                        || Mage::getStoreConfig('onestepcheckout/general/rewrite_checkout_links'))*/
                ) {
                    $this->_redirectUrl($urlRedirect);
                    return;
                }
                else {
                    echo($urlRedirect);
                }
            } else {
                Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('An error occurred while connecting to payment gateway. Please try again later.'));
                $this->transparentModel()->unsetSessionData();
                echo Mage::getUrl('checkout/cart/');
                return;
            }
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('An error occurred while connecting to payment gateway. Please try again later.'));
            $this->transparentModel()->unsetSessionData();
            if (Mage::getStoreConfig('payment/ewayrapid_general/connection_type')
                === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT
                && Mage::getSingleton('core/session')->getCheckoutExtension()
                /*(Mage::getStoreConfig('onestepcheckout/general/active')
                    || Mage::getStoreConfig('opc/global/status')
                    || Mage::getStoreConfig('firecheckout/general/enabled')
                    || Mage::getStoreConfig('gomage_checkout/general/enabled')
                    || Mage::getStoreConfig('onestepcheckout/general/rewrite_checkout_links'))*/
            ) {
                $this->_redirectUrl(Mage::getUrl('checkout/cart/'));
                return;
            }
            else {
                echo Mage::getUrl('checkout/cart/');
            }
            return;
        }
        die;
    }

    /**
     * Action display form customer's detail card: Add new info
     */
    public function paynowAction()
    {
        $this->loadLayout();

        $accessCode = $this->getRequest()->getParam('AccessCode');
        $this->getLayout()->getBlock('transparent.block.paynow')->setAccessCode($accessCode);

        $this->renderLayout();
    }

    /**
     * Action display form customer's detail card: Add new info
     */
    public function redirectAction()
    {
        $this->loadLayout();

        $accessCode = $this->getRequest()->getParam('AccessCode');
        $this->getLayout()->getBlock('transparent.block.checkout')->setAccessCode($accessCode);

        $this->renderLayout();
    }

    /**
     * Action process at returnUrl
     */
    public function callBackAction()
    {
        try {
            $this->_getSession();
            $quote = $this->_getQuote();

            $accessCode = $this->getRequest()->getParam('AccessCode');
            $order_id = $transactionID = $tokenCustomerID = 0;

            if ($this->methodPayment == 'ewayrapid_notsaved') {
                $dataResult = $this->resultProcess($accessCode);
                $transactionID = $dataResult['TransactionID'];
            } else {
                $transaction = $this->transparentModel()->getTransaction($accessCode);
                if($transaction) {
                    $tokenCustomerID = $transaction && isset($transaction[0]['TokenCustomerID']) ? $transaction[0]['TokenCustomerID'] : null;
                    unset($transaction);
                }
                $quote->setTokenCustomerID($tokenCustomerID);

                if($this->transMethod == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD) {
                    /*
                    $dataResult = $this->resultProcess($accessCode);
                    $transactionID = $dataResult['TransactionID'];
                    */
                    $quote = $this->transparentModel()->doTransaction($quote, round($this->_getQuote()->getBaseGrandTotal() * 100));
                    $transactionID = $quote->getTransactionId();
                } else {
                    if ($this->helperData()->getPaymentAction() === Eway_Rapid31_Model_Method_Notsaved::ACTION_AUTHORIZE_CAPTURE) {
                        $dataResult = $this->resultProcess($accessCode);
                        $transactionID = $dataResult['TransactionID'];
                    } else {
                        $quote = $this->transparentModel()->doAuthorisation($quote, round($this->_getQuote()->getBaseGrandTotal() * 100));
                        $transactionID = $quote->getTransactionId();
                        //$quote = $this->transparentModel()->doCapturePayment($quote, round($this->_getQuote()->getBaseGrandTotal() * 100));
                    }
                }
                $quote->setTransactionId($transactionID);

                //Save Token
                $this->saveToken($quote, $tokenCustomerID);
            }

            if ($transactionID) {
                Mage::getSingleton('core/session')->setTransactionId($transactionID);
                //Save order
                $order_id = $this->storeOrder('success', $transactionID);
            }

            //unset all session's transaparent
            $this->transparentModel()->unsetSessionData();

            // Redirect to success page
            if ($order_id) {
                $this->_redirect('checkout/onepage/success');
                return;
            } else {
                Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('Create order error. Please again.'));
                $this->_redirect('checkout/cart/');
                return;
            }
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('Call back error: ' . $e->getMessage()));
            $this->transparentModel()->unsetSessionData();
            if (Mage::getStoreConfig('payment/ewayrapid_general/connection_type')
                === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT
                && Mage::getSingleton('core/session')->getCheckoutExtension()
                /*(Mage::getStoreConfig('onestepcheckout/general/active')
                    || Mage::getStoreConfig('opc/global/status')
                    || Mage::getStoreConfig('firecheckout/general/enabled')
                    || Mage::getStoreConfig('gomage_checkout/general/enabled')
                    || Mage::getStoreConfig('onestepcheckout/general/rewrite_checkout_links'))*/
            ) {
                $this->_redirectUrl(Mage::getUrl('checkout/cart/'));
                return;
            }
            else {
                //echo Mage::getUrl('checkout/cart/');
                $this->_redirectUrl(Mage::getUrl('checkout/cart/'));
            }
            return;
        }
    }

    /**
     * @param $accessCode
     */
    protected  function resultProcess($accessCode) {
        return $this->transparentModel()->getInfoByAccessCode($accessCode);
    }

    /**
     * @param $quote
     * @param $tokenCustomerID
     */
    protected function saveToken($quote, $tokenCustomerID) {
        if ($this->savedToken == Eway_Rapid31_Model_Config::TOKEN_NEW || $this->paypalSavedToken == Eway_Rapid31_Model_Config::TOKEN_NEW || $this->masterPassSavedToken == Eway_Rapid31_Model_Config::TOKEN_NEW) {
            $this->cardInfo['SavedType'] = Eway_Rapid31_Model_Config::CREDITCARD_METHOD;

            if ($this->transMethod == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD) {
                $this->cardInfo['SavedType'] = Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD;
            } elseif ($this->transMethod == Eway_Rapid31_Model_Config::MASTERPASS_METHOD) {
                $this->cardInfo['SavedType'] = Eway_Rapid31_Model_Config::MASTERPASS_METHOD;
            }
            $this->transparentModel()->addToken($quote, $this->cardInfo, $tokenCustomerID);
        } else {
            $this->transparentModel()->updateToken($tokenCustomerID, $this->cardInfo);
        }
        return true;
    }

    protected function authorizeOnly() {

    }
    /**
     * Action Cancel
     */
    public function cancelAction()
    {
        Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('Request eway api error. Please try again.'));
        $this->transparentModel()->unsetSessionData();
        $this->_redirect('checkout/cart');
        return;
    }

    /**
     * @param string $successType
     * @param $transactionID
     * @return string
     */
    private function storeOrder($successType = 'success', $transactionID)
    {
        try {
            //Clear the basket and save the order (including some info about how the payment went)
            $this->getOnepage()->getQuote()->collectTotals();
            $this->getOnepage()->getQuote()->getPayment()->setTransactionId($transactionID);
            $this->getOnepage()->getQuote()->getPayment()->setAdditionalInformation('transactionId', $transactionID);
            $this->getOnepage()->getQuote()->getPayment()->setAdditionalInformation('successType', $successType);
            Mage::getSingleton('core/session')->setData('transparentCheckout', true);
            $orderId = $this->getOnepage()->saveOrder()->getLastOrderId();

            $this->getOnepage()->getQuote()->setIsActive(1);
            try {
                $cartHelper = Mage::helper('checkout/cart');

                //Get all items from cart
                $items = $cartHelper->getCart()->getItems();

                //Loop through all of cart items
                foreach ($items as $item) {
                    $itemId = $item->getItemId();
                    //Remove items, one by one
                    $cartHelper->getCart()->removeItem($itemId)->save();
                }
            } catch (Exception $e) {

            }

            $this->getOnepage()->getQuote()->save();
            Mage::getSingleton('core/session')->unsetData('transparentCheckout');
            Mage::getSingleton('core/session')->unsCheckoutExtension();
            return $orderId;
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
    }

    /**
     * Review shipping
     */
    public function reviewAction()
    {
        try {
            $accessCode = $this->getRequest()->getParam('AccessCode');
            $quote = $this->transparentModel()->updateCustomer($accessCode, $this->_getQuote());

            if (!$quote) {
                $quote = $this->_getQuote();
            }

            $this->loadLayout();
            $blockReview = $this->getLayout()->getBlock('eway.block.review');
            $blockReview->setQuote($quote);
            $blockReview->setAccessCode($accessCode);
            $blockReview->setActionUrl(Mage::getUrl('*/*/saveInfoShipping'));
            $this->renderLayout();
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('Update customer info error: ' . $e->getMessage()));
            $this->transparentModel()->unsetSessionData();
            $this->_redirect('checkout/cart/');
            return;
        }
    }

    /**
     *
     */
    public function saveCardInfoAction()
    {
        try {
            $data = $this->getRequest()->getPost();
            if (isset($data['EWAY_CARDNUMBER'])) {
                $config = Mage::getSingleton('ewayrapid/config');
                $data['EWAY_CARDNUMBER'] = $this->helperData()->encryptSha256($data['EWAY_CARDNUMBER'], $config->getBasicAuthenticationHeader());
            }
            Mage::getSingleton('core/session')->setCardInfo($data);
            echo 1;
        } catch (Exception $e) {
            $this->transparentModel()->unsetSessionData();
            Mage::throwException($e->getMessage());
        }
        die;
    }

    /**
     *
     */
    public function saveInfoShippingAction()
    {
        $shippingMethod = $this->getRequest()->getParam('shipping_method');
        if ($shippingMethod) {
            //Get price
            $quote = $this->_getQuote();
            $cRate = $this->transparentModel()->getShippingByCode($quote, $shippingMethod);

            //Save to quote
            $quote->getShippingAddress()->setShippingMethod($shippingMethod)->save();

            if ($cRate) {
                echo json_encode(array(
                    'form_action' => Mage::getSingleton('core/session')->getFormActionUrl(),
                    'input_post' => '<input type="hidden" name="EWAY_NEWSHIPPINGTOTAL" value="' . round($cRate->getPrice() * 100) . '" />',
                ));
            } else {
                $this->transparentModel()->unsetSessionData();
                Mage::throwException($this->__('Method not found.'));
            }
        } else {
            $this->transparentModel()->unsetSessionData();
            Mage::throwException($this->__('Method not support.'));
        }
        die;
    }

    /**
     * @param $orderId
     * @return null
     */
    private function _loadOrder($orderId)
    {
        try {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            if ($order->getIncrementId() == $orderId) {
                return $order;
            }
            return null;
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
    }

    /**
     * Return checkout quote object
     *
     * @return Mage_Sale_Model_Quote
     */
    private function _getQuote()
    {
        /** @var Mage_Sales_Model_Quote $this->_quote */
        $this->_quote = $this->_getCheckoutSession()->getQuote();
        return $this->_quote;
    }

    /**
     * @return Mage_Core_Model_Abstract
     */
    private function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get one page checkout model
     *
     * @return Mage_Checkout_Model_Type_Onepage
     */
    public function getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }
}