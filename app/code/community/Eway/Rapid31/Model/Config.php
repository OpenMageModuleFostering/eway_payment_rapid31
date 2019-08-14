<?php
class Eway_Rapid31_Model_Config
{
    const MODE_SANDBOX                  = 'sandbox';
    const MODE_LIVE                     = 'live';
    const PAYMENT_NOT_SAVED_METHOD      = 'ewayrapid_notsaved';
    const PAYMENT_SAVED_METHOD          = 'ewayrapid_saved';

    const METHOD_PROCESS_PAYMENT        = 'ProcessPayment';
    const METHOD_CREATE_TOKEN           = 'CreateTokenCustomer';
    const METHOD_UPDATE_TOKEN           = 'UpdateTokenCustomer';
    const METHOD_TOKEN_PAYMENT          = 'TokenPayment';
    const METHOD_AUTHORISE              = 'Authorise';

    const TRANSACTION_PURCHASE          = 'Purchase';
    const TRANSACTION_MOTO              = 'MOTO';
    const TRANSACTION_RECURRING         = 'Recurring';

    const CONNECTION_DIRECT             = 'direct';
    const CONNECTION_TRANSPARENT        = 'transparent';
    const CONNECTION_SHARED_PAGE        = 'sharedpage';

    const CREDITCARD_METHOD             = 'creditcard';
    const PAYPAL_STANDARD_METHOD        = 'paypal';
    const PAYPAL_EXPRESS_METHOD         = 'paypal_express';
    const MASTERPASS_METHOD             = 'masterpass';

    const MESSAGE_ERROR_ORDER           = 'Billing Frequency is wrong. It must be numeric and greater than 0. Status of recurring profile is changed to canceled';

    const TRANSPARENT_ACCESSCODE         = 'AccessCodes';
    const TRANSPARENT_ACCESSCODE_RESULT  = 'AccessCode';

    const ENCRYPTION_PREFIX             = 'eCrypted';
    const TOKEN_NEW                     = 'new';

    const ORDER_STATUS_AUTHORISED       = 'eway_authorised';
    const ORDER_STATUS_CAPTURED         = 'eway_captured';

    private $_isSandbox                 = true;
    private $_isDebug                   = false;
    private $_liveUrl                   = '';
    private $_liveApiKey                = '';
    private $_livePassword              = '';
    private $_sandboxUrl                = '';
    private $_sandboxApiKey             = '';
    private $_sandboxPassword           = '';
    private $_isEnableSSLVerification   = false;

    public function __construct()
    {
        $this->_isSandbox = (Mage::getStoreConfig('payment/ewayrapid_general/mode') == self::MODE_SANDBOX);
        $this->_isDebug = (bool) Mage::getStoreConfig('payment/ewayrapid_general/debug');
        $this->_sandboxUrl = Mage::getStoreConfig('payment/ewayrapid_general/sandbox_endpoint');
        $this->_liveUrl = Mage::getStoreConfig('payment/ewayrapid_general/live_endpoint');
        $this->_liveApiKey = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/ewayrapid_general/live_api_key'));
        $this->_livePassword = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/ewayrapid_general/live_api_password'));
        $this->_sandboxApiKey = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/ewayrapid_general/sandbox_api_key'));
        $this->_sandboxPassword = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/ewayrapid_general/sandbox_api_password'));
        $this->_isEnableSSLVerification = Mage::getStoreConfig('payment/ewayrapid_general/ssl_verification');
    }

    public function isSandbox($sandbox = null)
    {
        if($sandbox !== null) {
            $this->_isSandbox = (bool) $sandbox;
        }

        return $this->_isSandbox;
    }

    public function isDebug($debug = null)
    {
        if($debug !== null) {
            $this->_isDebug = (bool) $debug;
        }

        return $this->_isDebug;
    }

    public function getRapidAPIUrl($action = false)
    {
        $url = $this->isSandbox() ? $this->_sandboxUrl : $this->_liveUrl;
        $url = rtrim($url, '/') . '/';
        if($action) {
            $url .= $action;
        }

        return $url;
    }

    public function getBasicAuthenticationHeader()
    {
        return $this->isSandbox() ? $this->_sandboxApiKey . ':' . $this->_sandboxPassword
                                  : $this->_liveApiKey . ':' . $this->_livePassword;
    }

    public function isEnableSSLVerification()
    {
        // Always return true in Live mode regardless Magento config.
        return !$this->isSandbox() || $this->_isEnableSSLVerification;
    }

    public function getEncryptionKey()
    {
        return $this->isSandbox() ? Mage::getStoreConfig('payment/ewayrapid_general/sandbox_encryption_key')
            : Mage::getStoreConfig('payment/ewayrapid_general/live_encryption_key');
    }

    public function isDirectConnection()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/connection_type') == self::CONNECTION_DIRECT;
    }

    public function isTransparentConnection()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/connection_type') == self::CONNECTION_TRANSPARENT;
    }

    public function canEditToken()
    {
        return (bool) Mage::getStoreConfig('payment/ewayrapid_general/can_edit_token');
    }

    public function getSupportedCardTypes()
    {
        return explode(',', Mage::getStoreConfig('payment/ewayrapid_general/cctypes'));
    }
}