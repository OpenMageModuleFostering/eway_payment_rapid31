<?php

require_once "Mage" . DS . "Checkout" . DS . "controllers" . DS . "OnepageController.php";

class Eway_Rapid31_SharedpageController extends Mage_Checkout_OnepageController
{
    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = null;

    /**
     * @var Eway_Rapid31_Model_Request_Sharedpage
     */
    protected $_checkout = null;

    protected $_checkoutType = 'ewayrapid/request_sharedpage';

    /**
     * process checkout with eway Responsive Shared Page
     */
    public function startAction()
    {
        // check method available
        $method = $this->_getQuote()->getPayment()->getMethod();
        if ($method !== Eway_Rapid31_Model_Config::PAYMENT_NOT_SAVED_METHOD
            && $method !== Eway_Rapid31_Model_Config::PAYMENT_SAVED_METHOD
        ) {
            Mage::getSingleton('core/session')->addError($this->__('Payment method ' . $method . ' not available'));
            $this->_redirect('checkout/cart');
            return;
        }
        if ($method === Eway_Rapid31_Model_Config::PAYMENT_SAVED_METHOD
            && !Mage::helper('ewayrapid')->isSavedMethodEnabled()
        ) {
            Mage::getSingleton('core/session')->addError($this->__('This feature has been disabled. Please contact site owner.'));
            $this->_redirect('checkout/cart');
            return;
        }

        try {
            $this->_initCheckout();
            $data = $this->_checkout->createAccessCode(Mage::getUrl('*/*/return', array('_secure'=>true)), Mage::getUrl('*/*/cancel', array('_secure'=>true)));
            if ($data->isSuccess()) {
                Mage::getSingleton('core/session')->setData('FormActionURL', $data->getFormActionURL());
                if ($data->getSharedPaymentUrl()) {
                    $this->_redirectUrl($data->getSharedPaymentUrl());
                    return;
                }
            } else {
                Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('An error occurred while connecting to payment gateway. Please try again later. (Error message: ' . $data->getMessage() . ')'));
                $this->_redirect('checkout/cart');
                return;
            }

        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__($e->getMessage()));
            $this->_redirect('checkout/cart');
        }
    }

    public function cancelAction()
    {
        $this->_redirect('checkout/cart');
    }

    /**
     * process payment when eway callback
     */
    public function returnAction()
    {
        try {
            $this->_initCheckout();

            $newToken = $this->getRequest()->getParam('newToken');
            $editToken = $this->getRequest()->getParam('editToken');
            $accessCode = $this->getRequest()->getParam('AccessCode');

            $response = $this->_checkout->getInfoByAccessCode($accessCode);
            if ($newToken || $editToken) {
                if ($response->getTokenCustomerID()) {
                    $response = $this->_checkout->saveTokenById($response, $editToken);
                    $response = $this->_processPayment($response);
                } else {
                    Mage::throwException(Mage::helper('ewayrapid')->__('An error occurred while making the transaction. Please try again. (Error message: %s)',
                        $response->getMessage()));
                }
            }

            $orderId = null;
            if ($response->isSuccess()) {
                $orderId = $this->storeOrder($response);
            } else {
                Mage::throwException(Mage::helper('ewayrapid')->__('Sorry, your payment could not be processed (Message: %s). Please check your details and try again, or try an alternative payment method.',
                    $response->getMessage()));
            }
            if ($orderId) {
                $this->_redirect('checkout/onepage/success');
                return;
            }
        } catch (Exception $e) {
            Mage::getSingleton('checkout/session')->addError($e->getMessage());
            Mage::logException($e);
            $this->_redirect('checkout/cart');
            return;
        }
    }

    /**
     * save order
     *
     * @param Eway_Rapid31_Model_Response $response
     * @param string $successType
     * @return string
     */
    private function storeOrder($response, $successType = 'success')
    {
        try {
            // Clear the basket and save the order (including some info about how the payment went)
            $this->getOnepage()->getQuote()->collectTotals();
            $payment = $this->getOnepage()->getQuote()->getPayment();
            $payment->setAdditionalInformation('successType', $successType);
            Mage::getSingleton('core/session')->setData('ewayTransactionID', $response->getTransactionID());
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
            return $orderId;
        } catch (Exception $e) {
            Mage::throwException(Mage::helper('ewayrapid')->__($e->getMessage()));
        }

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

    /**
     * Return checkout session object
     *
     * @return Mage_Checkout_Model_Session
     */
    private function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Return checkout quote object
     *
     * @return Mage_Sales_Model_Quote
     */
    private function _getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }
        return $this->_quote;
    }

    private function _initCheckout()
    {
        $quote = $this->_getQuote();
        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Forbidden');
            Mage::throwException(Mage::helper('ewayrapid')->__('Unable to initialize Shared page Checkout.'));
        }
        $this->_checkout = Mage::getSingleton($this->_checkoutType, array(
            'quote' => $quote
        ));
    }

    /**
     * review order when checkout with paypal express
     */
    public function reviewAction()
    {
        try {
            $this->_initCheckout();
            $accessCode = $this->getRequest()->getParam('AccessCode');
            $this->_checkout->updateCustomer($accessCode);
            $this->loadLayout();
            $blockReview = $this->getLayout()->getBlock('eway.block.review');
            $blockReview->setQuote($this->_getQuote());
            $blockReview->setAccessCode($accessCode);
            $blockReview->setActionUrl(Mage::getUrl('*/*/saveInfoShipping'));
            $this->renderLayout();
            return;
        } catch (Exception $e) {
            Mage::getSingleton('checkout/session')->addError(
                $this->__('Unable to initialize Express Checkout review. Error message: ' . $e->getMessage())
            );
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * save shipping total amount to quote
     * send new shipping total amount to eway
     *
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    public function saveInfoShippingAction()
    {
        $this->_initCheckout();
        $formActionURL = Mage::getSingleton('core/session')->getData('FormActionURL');
        if ($formActionURL) {
            Mage::getSingleton('core/session')->unsetData('FormActionURL');
        }
        $shippingMethod = $this->getRequest()->getParam('shipping_method');
        if ($shippingMethod) {
            //Save to quote
            $this->_quote->getShippingAddress()->setShippingMethod($shippingMethod)->save();

            //Get price
            $quote = $this->_getQuote();
            $cRate = $this->_checkout->getShippingByCode($shippingMethod);
            if ($cRate) {
                echo json_encode(array(
                    'form_action' => $formActionURL,
                    'input_post' => '<input type="hidden" name="EWAY_NEWSHIPPINGTOTAL" value="' . round($cRate->getPrice() * 100) . '" />'
                ));
            } else {
                Mage::throwException($this->__('Method not found.'));
            }
        } else {
            Mage::throwException($this->__('Method not support.'));
        }
        die;
    }

    /**
     * process Payment: authorize only or authorize & capture
     *
     * @param Eway_Rapid31_Model_Response $response
     * @return Eway_Rapid31_Model_Response
     * @throws Mage_Core_Exception
     */
    protected function _processPayment(Eway_Rapid31_Model_Response $response)
    {
        $this->_initCheckout();

        $cardData = $response->getCustomer();

        if ($cardData['CardNumber'] && $cardData['CardName']) {
            $response = $this->_checkout->doAuthorisation($response);
            if ($response->isSuccess()
                && Mage::helper('ewayrapid')->getPaymentAction() === Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE
            ) {
                $response = $this->_checkout->doCapturePayment($response);
            }
        } else {
            if (Mage::helper('ewayrapid')->getPaymentAction() === Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE) {
                $response = $this->_checkout->doAuthorisation($response);
            } elseif (Mage::helper('ewayrapid')->getPaymentAction() === Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
                $response = $this->_checkout->doTransaction($response);
            }
        }
        if (!$response->isSuccess()) {
            Mage::throwException(Mage::helper('ewayrapid')->__('Sorry, your payment could not be processed (Message: %s). Please check your details and try again, or try an alternative payment method.',
                $response->getMessage()));
        }
        return $response;
    }
}