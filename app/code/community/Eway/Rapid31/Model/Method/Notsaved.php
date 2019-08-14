<?php
class Eway_Rapid31_Model_Method_Notsaved extends Mage_Payment_Model_Method_Abstract
{
    protected $_code  = 'ewayrapid_notsaved';

    protected $_formBlockType = 'ewayrapid/form_direct_notsaved';
    protected $_infoBlockType = 'ewayrapid/info_direct_notsaved';
    protected $_canSaveCc = false;

    /**
     * Payment Method features
     * @var bool
     */
    protected $_isGateway                   = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = false;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canVoid                     = true;
    protected $_canUseInternal              = true;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = true;
    protected $_isInitializeNeeded          = false;
    protected $_canFetchTransactionInfo     = true;
    protected $_canReviewPayment            = false;
    protected $_canCreateBillingAgreement   = true;
    protected $_canManageRecurringProfiles  = true;
    protected $_connectionType;
    protected $_isBackendOrder;

    public static $_extension = false;
    public function __construct()
    {
        parent::__construct();
        $this->_isBackendOrder = Mage::helper('ewayrapid')->isBackendOrder();
        $this->_connectionType = Mage::getStoreConfig('payment/ewayrapid_general/connection_type');
        if (!$this->_isBackendOrder) {
            if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT) {
                $this->_infoBlockType = 'ewayrapid/info_transparent_notsaved';
                $this->_formBlockType = 'ewayrapid/form_transparent_notsaved';
            } elseif ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE) {
                $this->_infoBlockType = 'ewayrapid/info_sharedpage_notsaved';
                $this->_formBlockType = 'ewayrapid/form_sharedpage_notsaved';
            }
        }
    }

    public function getConfigData($field, $storeId = null)
    {
        $data = parent::getConfigData($field, $storeId);
        if($data === null) {
            return $this->_getGeneralConfig($field, $storeId);
        } else {
            switch($field) {
                case 'active':
                    return $data && $this->_isActive($storeId);
                default:
                    return $data;
            }
        }
    }

    protected function _getGeneralConfig($field, $storeId = null)
    {
        if (null === $storeId) {
            $storeId = $this->getStore();
        }
        $path = 'payment/ewayrapid_general/'.$field;
        return Mage::getStoreConfig($path, $storeId);
    }

    protected function _isActive($storeId)
    {
        return $this->_getGeneralConfig('active', $storeId);
    }

    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();

        if (!$this->_isBackendOrder && $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE) {
//            Mage::helper('ewayrapid')->clearSessionSharedpage();
            //Mage::getSingleton('core/session')->setData('sharedpagePaypal', $data->getSharedpageNotsaved());
//            Mage::getSingleton('core/session')->setData('sharedpagePaypal', 'paypal');
            if ($data->getMethod()) {
                Mage::getSingleton('core/session')->setData('ewayMethod', $data->getMethod());
            }
        } elseif (!$this->_isBackendOrder && $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT) {
            $info->setTransparentNotsaved($data->getTransparentNotsaved());

            //Option choice
            if ($data->getMethod() == 'ewayrapid_saved' && !$data->getTransparentSaved()) {
                Mage::throwException(Mage::helper('payment')->__('Please select an option payment for eWay saved'));
            } elseif ($data->getMethod() == 'ewayrapid_notsaved' && !$data->getTransparentNotsaved()) {
                Mage::throwException(Mage::helper('payment')->__('Please select an option payment for eWay not saved'));
            }

            //New Token
            if ($data->getMethod() == 'ewayrapid_saved'
                && $data->getTransparentSaved() == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD
                && $data->getSavedToken() == Eway_Rapid31_Model_Config::TOKEN_NEW
                && Mage::helper('ewayrapid/customer')->checkTokenListByType(Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD)
            ) {
                Mage::throwException(Mage::helper('payment')->__('You could only save one PayPal account, please select PayPal account existed to payent.'));
            }

            if ($data->getTransparentNotsaved())
                Mage::getSingleton('core/session')->setTransparentNotsaved($data->getTransparentNotsaved());

            if ($data->getTransparentSaved())
                Mage::getSingleton('core/session')->setTransparentSaved($data->getTransparentSaved());

            if ($data->getMethod())
                Mage::getSingleton('core/session')->setMethod($data->getMethod());

            if ($data->getSavedToken()) {
                Mage::getSingleton('core/session')->setSavedToken($data->getSavedToken());
                if(is_numeric($data->getSavedToken())) {
                    $token = Mage::helper('ewayrapid/customer')->getTokenById($data->getSavedToken());
                    /* @var Eway_Rapid31_Model_Request_Token $model */
                    $model = Mage::getModel('ewayrapid/request_token');
                    $type = $model->checkCardName($token);
                    Mage::getSingleton('core/session')->setTransparentSaved($type);
                    unset($model);
                    unset($token);
                }
            }

            $infoCard = new Varien_Object();
            Mage::getSingleton('core/session')->setInfoCard(
                $infoCard->setCcType($data->getCcType())
                    ->setOwner($data->getCcOwner())
                    ->setLast4($this->_isClientSideEncrypted($data->getCcNumber()) ? 'encrypted' : substr($data->getCcNumber(), -4))
                    ->setCard($data->getCcNumber())
                    ->setNumber($data->getCcNumber())
                    ->setCid($data->getCcCid())
                    ->setExpMonth($data->getCcExpMonth())
                    ->setExpYear($data->getCcExpYear()
            ));

        } else {
            $info->setCcType($data->getCcType())
                ->setCcOwner($data->getCcOwner())
                ->setCcLast4($this->_isClientSideEncrypted($data->getCcNumber()) ? 'encrypted' : substr($data->getCcNumber(), -4))
                ->setCcNumber($data->getCcNumber())
                ->setCcCid($data->getCcCid())
                ->setCcExpMonth($data->getCcExpMonth())
                ->setCcExpYear($data->getCcExpYear());
        }

        return $this;
    }

    protected function _isClientSideEncrypted($ccNumber)
    {
        return (strlen($ccNumber) > 19 && strpos($ccNumber, Eway_Rapid31_Model_Config::ENCRYPTION_PREFIX) !== false);
    }

    /**
     * Validate payment method information object
     *
     * @param   Mage_Payment_Model_Info $info
     * @return  Mage_Payment_Model_Abstract
     */
    public function validate()
    {
        /*
        * calling parent validate function
        */
        parent::validate();
        if (!$this->_isBackendOrder) {
            if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE) {
                return $this;
            } elseif ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT ) {
                return $this;
            }
        }
        $info = $this->getInfoInstance();
        $errorMsg = false;
        $availableTypes = explode(',',$this->getConfigData('cctypes'));

        $ccNumber = $info->getCcNumber();

        // Cannot do normal validation in case client side encrypted
        if($this->_isClientSideEncrypted($ccNumber)) {
            return true;
        }

        // remove credit card number delimiters such as "-" and space
        $ccNumber = preg_replace('/[\-\s]+/', '', $ccNumber);
        $info->setCcNumber($ccNumber);

        $ccType = '';

        if (in_array($info->getCcType(), $availableTypes)){
            if ($this->validateCcNum($ccNumber)) {
                $ccTypeRegExpList = array(
                    // Visa Electron
                    'VE'  => '/^(4026|4405|4508|4844|4913|4917)[0-9]{12}|417500[0-9]{10}$/',
                    // Maestro
                    'ME'  => '/(^(5[0678])[0-9]{11,18}$)|(^(6[^05])[0-9]{11,18}$)|(^(601)[^1][0-9]{9,16}$)|(^(6011)[0-9]{9,11}$)|(^(6011)[0-9]{13,16}$)|(^(65)[0-9]{11,13}$)|(^(65)[0-9]{15,18}$)|(^(49030)[2-9]([0-9]{10}$|[0-9]{12,13}$))|(^(49033)[5-9]([0-9]{10}$|[0-9]{12,13}$))|(^(49110)[1-2]([0-9]{10}$|[0-9]{12,13}$))|(^(49117)[4-9]([0-9]{10}$|[0-9]{12,13}$))|(^(49118)[0-2]([0-9]{10}$|[0-9]{12,13}$))|(^(4936)([0-9]{12}$|[0-9]{14,15}$))/',
                    // Visa
                    'VI'  => '/^4[0-9]{12}([0-9]{3})?$/',
                    // Master Card
                    'MC'  => '/^5[1-5][0-9]{14}$/',
                    // American Express
                    'AE'  => '/^3[47][0-9]{13}$/',
                    // JCB
                    'JCB' => '/^(3[0-9]{15}|(2131|1800)[0-9]{11})$/',
                    // Diners Club
                    'DC' => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',
                );

                foreach ($ccTypeRegExpList as $ccTypeMatch=>$ccTypeRegExp) {
                    if (preg_match($ccTypeRegExp, $ccNumber)) {
                        $ccType = $ccTypeMatch;
                        break;
                    }
                }

                if ($ccType!=$info->getCcType()) {
                    $errorMsg = Mage::helper('payment')->__('Please enter a valid credit card number.');
                }
            } else {
                $errorMsg = Mage::helper('payment')->__('Invalid Credit Card Number');
            }

        } else {
            $errorMsg = Mage::helper('payment')->__('Credit card type is not allowed for this payment method.');
        }

        //validate credit card verification number
        if ($errorMsg === false && $this->hasVerification()) {
            $verifcationRegEx = $this->getVerificationRegEx();
            $regExp = isset($verifcationRegEx[$info->getCcType()]) ? $verifcationRegEx[$info->getCcType()] : '';
            if (!$info->getCcCid() || !$regExp || !preg_match($regExp ,$info->getCcCid())){
                $errorMsg = Mage::helper('payment')->__('Please enter a valid credit card verification number.');
            }
        }

        if (!$this->_validateExpDate($info->getCcExpYear(), $info->getCcExpMonth())) {
            $errorMsg = Mage::helper('payment')->__('Incorrect credit card expiration date.');
        }

        if($errorMsg){
            Mage::throwException($errorMsg);
        }

        return $this;
    }

    public function hasVerification()
    {
        if(Mage::helper('ewayrapid')->isBackendOrder()) {
            return false;
        }

        $configData = $this->getConfigData('useccv');
        if(is_null($configData)){
            return true;
        }
        return (bool) $configData;
    }

    public function getVerificationRegEx()
    {
        $verificationExpList = array(
            'VI' => '/^[0-9]{3}$/', // Visa
            'VE' => '/^[0-9]{3}$/', // Visa Electron
            'MC' => '/^[0-9]{3}$/', // Master Card
            'ME' => '/^[0-9]{3,4}$/', // Maestro
            'AE' => '/^[0-9]{4}$/', // American Express
            'DC' => '/^[0-9]{3}$/', // Diners Club
            'JCB' => '/^[0-9]{3,4}$/' //JCB
        );
        return $verificationExpList;
    }

    protected function _validateExpDate($expYear, $expMonth)
    {
        $date = Mage::app()->getLocale()->date();
        if (!$expYear || !$expMonth || ($date->compareYear($expYear) == 1)
            || ($date->compareYear($expYear) == 0 && ($date->compareMonth($expMonth) == 1))
        ) {
            return false;
        }
        return true;
    }

    /**
     * Validate credit card number
     *
     * @param   string $cc_number
     * @return  bool
     */
    public function validateCcNum($ccNumber)
    {
        $cardNumber = strrev($ccNumber);
        $numSum = 0;

        for ($i=0; $i<strlen($cardNumber); $i++) {
            $currentNum = substr($cardNumber, $i, 1);

            /**
             * Double every second digit
             */
            if ($i % 2 == 1) {
                $currentNum *= 2;
            }

            /**
             * Add digits of 2-digit numbers together
             */
            if ($currentNum > 9) {
                $firstNum = $currentNum % 10;
                $secondNum = ($currentNum - $firstNum) / 10;
                $currentNum = $firstNum + $secondNum;
            }

            $numSum += $currentNum;
        }

        /**
         * If the total has no remainder it's OK
         */
        return ($numSum % 10 == 0);
    }

    /**
     * Authorize & Capture a payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function capture(Varien_Object $payment, $amount)
    {
        if (!$this->_isBackendOrder) {
            if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE) {
                $transID = Mage::getSingleton('core/session')->getData('ewayTransactionID');
                $payment->setTransactionId($transID);
                $payment->setIsTransactionClosed(0);
                Mage::getSingleton('core/session')->unsetData('ewayTransactionID');
                return $this;
            } elseif ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT ) {
                $payment = Mage::getModel('ewayrapid/request_transparent')->setTransaction($payment);
                return $this;
            }
        }

        /* @var Mage_Sales_Model_Order_Payment $payment */
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for capture.'));
        }

        $amount = round($amount * 100);
        $request = Mage::getModel('ewayrapid/request_direct');
        if($this->_isPreauthCapture($payment)) {
            $request->doCapturePayment($payment, $amount);
        } else {
            $request->doTransaction($payment, $amount);
        }

        return $this;
    }

    /**
     * Authorize a payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        if (!$this->_isBackendOrder) {
            if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE) {
                $transID = Mage::getSingleton('core/session')->getData('ewayTransactionID');
                $payment->setTransactionId($transID);
                $payment->setIsTransactionClosed(0);
                Mage::getSingleton('core/session')->unsetData('ewayTransactionID');
                return $this;
            } elseif ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT ) {
                $payment = Mage::getModel('ewayrapid/request_transparent')->setTransaction($payment);
                return $this;
            }
        }

        /* @var Mage_Sales_Model_Order_Payment $payment */
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for authorize.'));
        }

        if (Mage::getStoreConfig('payment/ewayrapid_general/connection_type') === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT
        ) {
            //Mage::app()->getResponse()->setRedirect(Mage::getUrl('ewayrapid/transparent/'))->sendResponse();
            //exit;
        }

        $amount = round($amount * 100);
        $request = Mage::getModel('ewayrapid/request_direct');
        $request->doAuthorisation($payment, $amount);

        return $this;
    }

    /**
     * Refund a payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function refund(Varien_Object $payment, $amount)
    {
        /* @var Mage_Sales_Model_Order_Payment $payment */
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for refund.'));
        }

        $amount = round($amount * 100);
        $request = Mage::getModel('ewayrapid/request_direct');
        $request->doRefund($payment, $amount);

        return $this;
    }

    /**
     * Cancel a payment
     *
     * @param Varien_Object $payment
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function cancel(Varien_Object $payment)
    {
        /* @var Mage_Sales_Model_Order_Payment $payment */
        $request = Mage::getModel('ewayrapid/request_direct');
        $request->doCancel($payment);

        return $this;
    }

    protected function _isPreauthCapture(Mage_Sales_Model_Order_Payment $payment)
    {
        return (bool) $payment->getLastTransId();
    }

    public function canVoid(Varien_Object $payment)
    {
        return $this->_canVoid && (Mage::app()->getRequest()->getActionName() == 'cancel');
    }

    public function getCheckoutRedirectUrl()
    {
        if (Mage::getStoreConfig('payment/ewayrapid_general/connection_type')
            === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
        ) {
            return Mage::getUrl('ewayrapid/sharedpage/start', array('_secure'=>true));
        }
        elseif (Mage::getStoreConfig('payment/ewayrapid_general/connection_type')
                === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT
            && Mage::getSingleton('core/session')->getCheckoutExtension()
        ) {
            return Mage::getUrl('ewayrapid/transparent/build', array('_secure'=>true));
        }
        /*elseif (Mage::getStoreConfig('payment/ewayrapid_general/connection_type')
                === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT
                && (Mage::getStoreConfig('onestepcheckout/general/active')
                || Mage::getStoreConfig('opc/global/status')
                || Mage::getStoreConfig('firecheckout/general/enabled')
                || Mage::getStoreConfig('gomage_checkout/general/enabled')
                || Mage::getStoreConfig('onestepcheckout/general/rewrite_checkout_links'))
        ) {
            return Mage::getUrl('ewayrapid/transparent/build', array('_secure'=>true));
        }*/
        return null;
    }
}