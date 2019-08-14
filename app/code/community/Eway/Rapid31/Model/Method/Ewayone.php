<?php
class Eway_Rapid31_Model_Method_Ewayone extends Eway_Rapid31_Model_Method_Notsaved implements Mage_Payment_Model_Recurring_Profile_MethodInterface
{
    protected $_code  = 'ewayrapid_ewayone';

    protected $_formBlockType = 'ewayrapid/form_direct_ewayone';
    protected $_infoBlockType = 'ewayrapid/info_direct_ewayone';
    protected $_canCapturePartial           = true;
    protected $_billing                     = null;

    public function __construct()
    {
        parent::__construct();
        if (!$this->_isBackendOrder) {
            if (!Mage::helper('ewayrapid')->isBackendOrder()) {
                if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT) {
                    $this->_infoBlockType = 'ewayrapid/info_transparent_ewayone';
                    $this->_formBlockType = 'ewayrapid/form_transparent_ewayone';
                } elseif ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE) {
                    $this->_infoBlockType = 'ewayrapid/info_sharedpage_ewayone';
                    $this->_formBlockType = 'ewayrapid/form_sharedpage_ewayone';
                } elseif ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME) {
                    $this->_infoBlockType = 'ewayrapid/info_sharedpage_ewayone';
                    $this->_formBlockType = 'ewayrapid/form_sharedpage_ewayone';
                }
            }
        }

        if ($this->_isBackendOrder) {
            if (Mage::helper('ewayrapid')->isBackendOrder()) {
                if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE) {
//                    $this->_infoBlockType = 'ewayrapid/info_sharedpage_ewayone';
                    $this->_formBlockType = 'ewayrapid/form_sharedpage_ewayone';
                } elseif ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME) {
//                    $this->_infoBlockType = 'ewayrapid/info_sharedpage_ewayone';
                    $this->_formBlockType = 'ewayrapid/form_sharedpage_ewayone';
                }
            }
        }
    }

     /**
     * Use the grandparent isAvailable
     *
     * @param Mage_Sales_Model_Quote|null $quote
     * @return boolean
     */
    public function isAvailable($quote = null) {
        return Mage_Payment_Model_Method_Abstract::isAvailable($quote);
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
        if($data->getSavedToken() == Eway_Rapid31_Model_Config::TOKEN_NEW) {
            Mage::helper('ewayrapid')->clearSessionSharedpage();
            Mage::getSingleton('core/session')->unsetData('visa_checkout_call_id');
            if (($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
                    || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME)
                && !$this->_isBackendOrder && $data->getSaveCard()
            ) {
                Mage::getSingleton('core/session')->setData('newToken', 1);
            }
            if($data->getSaveCard()){
                $info->setIsNewToken(true);
            }

            if($this->_isBackendOrder
                && ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
                    || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME)
                && $data->getSaveCard()
            ){
                Mage::getSingleton('core/session')->setData('newToken', 1);
            }


        } else {
            if ( ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME)
                && !$this->_isBackendOrder
            ) {
                Mage::getSingleton('core/session')->setData('editToken', $data->getSavedToken());
            }

            if($this->_isBackendOrder
                && ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
                    || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME)
            ){
                Mage::getSingleton('core/session')->setData('editToken', $data->getSavedToken());
            }

            $info->setSavedToken($data->getSavedToken());
            // Update token
            if($data->getCcOwner() && $data->getSaveCard()) {
                $info->setIsUpdateToken(true);
            }
        }

        if ($this->_isBackendOrder &&
            ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
                || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME)
        ) {
            if ($data->getMethod()) {
                Mage::getSingleton('core/session')->setData('ewayMethod', $data->getMethod());
            }
        }

        if (!$this->_isBackendOrder &&
            ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
                || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME)
        ) {
            if ($data->getMethod()) {
                Mage::getSingleton('core/session')->setData('ewayMethod', $data->getMethod());
            }
        } elseif (!$this->_isBackendOrder && $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT) {
            if($data->getVisaCheckoutCallId()){
                Mage::getSingleton('core/session')->setData('visa_checkout_call_id', $data->getVisaCheckoutCallId());
            }
            $info->setTransparentNotsaved($data->getTransparentNotsaved());
            $info->setTransparentSaved($data->getTransparentSaved());

            //Option choice
            if ($data->getMethod() == 'ewayrapid_ewayone' && !$data->getTransparentSaved()) {
                Mage::throwException(Mage::helper('payment')->__('Please select an option payment for eWay saved'));
            } elseif ($data->getMethod() == 'ewayrapid_notsaved' && !$data->getTransparentNotsaved()) {
                Mage::throwException(Mage::helper('payment')->__('Please select an option payment for eWay not saved'));
            }

            //New Token
            if ($data->getMethod() == 'ewayrapid_ewayone'
                && $data->getTransparentSaved() == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD
                && $data->getSavedToken() == Eway_Rapid31_Model_Config::TOKEN_NEW
                && Mage::helper('ewayrapid/customer')->checkTokenListByType(Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD)
                && $data->getSaveCard()
            ) {
                Mage::throwException(Mage::helper('payment')->__('You could only save one PayPal account, please select PayPal account existed to payent.'));
            }

            if ($data->getTransparentNotsaved())
                Mage::getSingleton('core/session')->setTransparentNotsaved($data->getTransparentNotsaved());

            if ($data->getTransparentSaved())
                Mage::getSingleton('core/session')->setTransparentSaved($data->getTransparentSaved());

            if ($data->getMethod())
                Mage::getSingleton('core/session')->setMethod($data->getMethod());

            // Add Save Card to session
            Mage::getSingleton('core/session')->setSaveCard($data->getSaveCard());

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

        Mage::helper('ewayrapid')->serializeInfoInstance($info);

        return $this;
    }

    /**
     * Validate payment method information object
     *
     * @param   Mage_Payment_Model_Info $info
     * @return  Mage_Payment_Model_Abstract
     */
    public function validate()
    {
        $info = $this->getInfoInstance();
        if($info->getIsNewToken()) {
            parent::validate();
        } else {
            // TODO: Check if this token is still Active using GET /Customer endpoint.
        }

        return $this;
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
        if (!$this->_isPreauthCapture($payment) && (
                $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
                || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME)) {
            $transID = Mage::getSingleton('core/session')->getData('ewayTransactionID');
            $payment->setTransactionId($transID);
            $payment->setIsTransactionClosed(0);
            Mage::getSingleton('core/session')->unsetData('ewayTransactionID');
            return $this;
        } elseif (!$this->_isBackendOrder && $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT ) {
            //$payment->setTransactionId(Mage::getSingleton('core/session')->getTransactionId());
            Mage::getModel('ewayrapid/request_transparent')->setTransaction($payment);
            return $this;
        }

        /* @var Mage_Sales_Model_Order_Payment $payment */
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('payment')->__('Invalid amount for capture.'));
        }
        $info = $this->getInfoInstance();
        Mage::helper('ewayrapid')->unserializeInfoInstace($info);

        if(!$info->getIsNewToken() && !$info->getIsUpdateToken()){
            // Not new/update token
            if($info->getSavedToken() && is_numeric($info->getSavedToken())){
                // Saved token is numeric
                $request = Mage::getModel('ewayrapid/request_token');
            }else{
                $request = Mage::getModel('ewayrapid/request_direct');
            }
        }else{
            // New/update token
            $request = Mage::getModel('ewayrapid/request_token');
        }

        $amount = round($amount * 100);
        if($this->_isPreauthCapture($payment)) {
            $previousCapture = $payment->lookupTransaction(false, Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
            if($previousCapture) {
                $customer = Mage::getModel('customer/customer')->load($payment->getOrder()->getCustomerId());
                Mage::helper('ewayrapid/customer')->setCurrentCustomer($customer);

                /* @var Mage_Sales_Model_Order_Payment_Transaction $previousCapture */
                $request->doTransaction($payment, $amount);
                $payment->setParentTransactionId($previousCapture->getParentTxnId());
            } else {
                $request->doCapturePayment($payment, $amount);
            }
        } else {
            if (!$payment->getIsRecurring()) {
                if($request instanceof Eway_Rapid31_Model_Request_Token){
                    $this->_shouldCreateOrUpdateToken($payment, $request);
                }

            }
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
        if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME) {
            $transID = Mage::getSingleton('core/session')->getData('ewayTransactionID');
            $payment->setTransactionId($transID);
            $payment->setIsTransactionClosed(0);
            Mage::getSingleton('core/session')->unsetData('ewayTransactionID');
            return $this;
        } elseif (!$this->_isBackendOrder && $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT) {
            //$payment->setTransactionId(Mage::getSingleton('core/session')->getTransactionId());
            Mage::getModel('ewayrapid/request_transparent')->setTransaction($payment);
            return $this;
        }

        /* @var Mage_Sales_Model_Order_Payment $payment */
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('payment')->__('Invalid amount for authorize.'));
        }
        $info = $this->getInfoInstance();
        Mage::helper('ewayrapid')->unserializeInfoInstace($info);

        if(!$info->getIsNewToken() && !$info->getIsUpdateToken()){
            // Not new/update token
            if($info->getSavedToken() && is_numeric($info->getSavedToken())){
                // Saved token is numeric
                $request = Mage::getModel('ewayrapid/request_token');
            }else{
                $request = Mage::getModel('ewayrapid/request_direct');
            }
        }else{
            // New/update token
            $request = Mage::getModel('ewayrapid/request_token');
        }

        /** @todo there's an error in case recurring profile */
        if (!$payment->getIsRecurring()) {
            if($request instanceof Eway_Rapid31_Model_Request_Token){
                $this->_shouldCreateOrUpdateToken($payment, $request);
            }
        }

        $amount = round($amount * 100);
        $request->doAuthorisation($payment, $amount);

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param Eway_Rapid31_Model_Request_Token $request
     */
    public function _shouldCreateOrUpdateToken(Mage_Sales_Model_Order_Payment $payment, Eway_Rapid31_Model_Request_Token $request)
    {
        $order = $payment->getOrder();
        $billing = ($this->_getBilling() == null) ? $order->getBillingAddress() : $this->_getBilling();
        $info = $this->getInfoInstance();

        Mage::helper('ewayrapid')->unserializeInfoInstace($info);
        if ($info->getIsNewToken()) {
            $request->createNewToken($billing, $info);
            $info->setSavedToken(Mage::helper('ewayrapid/customer')->getLastTokenId());
            Mage::helper('ewayrapid')->serializeInfoInstance($info);
        } elseif ($info->getIsUpdateToken()) {
            $request->updateToken($billing, $info);
        }
    }

    public function _setBilling(Mage_Sales_Model_Quote_Address $billing)
    {
        $this->_billing = $billing;
    }

    public function _getBilling()
    {
        return $this->_billing;
    }

    /**
     * Validate RP data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function validateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {

    }

    /**
     * Submit RP to the gateway
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param Mage_Payment_Model_Info $paymentInfo
     */
    public function submitRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile,
                                           Mage_Payment_Model_Info $paymentInfo
    ) {
        $profile->setReferenceId(strtoupper(uniqid()));
        $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE);
    }

    /**
     * Fetch RP details
     *
     * @param string $referenceId
     * @param Varien_Object $result
     */
    public function getRecurringProfileDetails($referenceId, Varien_Object $result)
    {

    }

    /**
     * Whether can get recurring profile details
     */
    public function canGetRecurringProfileDetails()
    {
        return true;
    }

    /**
     * Update RP data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {

    }

    /**
     * Manage status
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfileStatus(Mage_Payment_Model_Recurring_Profile $profile)
    {

    }
}