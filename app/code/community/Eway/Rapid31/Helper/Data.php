<?php

/**
 * Created by PhpStorm.
 * User: hiephm
 * Date: 4/23/14
 * Time: 5:30 PM
 */ 
class Eway_Rapid31_Helper_Data extends Mage_Core_Helper_Abstract
{
    private $_ccTypeNames = null;
    private $_isSaveMethodEnabled = null;

    public function isBackendOrder()
    {
        return Mage::app()->getStore()->isAdmin();
    }
    
    public function getExtensionVersion()
    {
        return (string) Mage::getConfig()->getModuleConfig("Eway_Rapid31")->version;
    }
    
    public function serializeInfoInstance(&$info)
    {
        $fieldsToSerialize = array('is_new_token', 'is_update_token', 'saved_token');
        $data = array();
        foreach($fieldsToSerialize as $field) {
            $data[$field] = $info->getData($field);
        }

        $info->setAdditionalData(json_encode($data));
    }

    public function unserializeInfoInstace(&$info)
    {
        $data = json_decode($info->getAdditionalData(), true);
        $info->addData($data);
    }

    public function getCcTypeName($type)
    {
        if (preg_match('/^paypal/', strtolower($type))) {
            return 'PayPal';
        }

        if(is_null($this->_ccTypeNames)) {
            $this->_ccTypeNames = Mage::getSingleton('payment/config')->getCcTypes();
        }
        return (isset($this->_ccTypeNames[$type]) ? $this->_ccTypeNames[$type] : 'Unknown');
    }

    public function isSavedMethodEnabled()
    {
        if(is_null($this->_isSaveMethodEnabled)) {
            $this->_isSaveMethodEnabled = Mage::getSingleton('ewayrapid/method_saved')->getConfigData('active');
        }
        return $this->_isSaveMethodEnabled;
    }

    public function isRecurring()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $items = $quote->getAllItems();
        foreach ($items as $item) {
            if ($item->getIsRecurring()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $data
     * @param $key
     * @return string
     */
    public function encryptSha256($data, $key)
    {
        //To Encrypt:
        return trim(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $data, MCRYPT_MODE_ECB));
    }

    public function decryptSha256($data, $key)
    {
        //To Decrypt:
        return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $data, MCRYPT_MODE_ECB));
    }

    public function getPaymentAction()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/payment_action');
    }

    public function getTransferCartLineItems()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/transfer_cart_items');
    }

    public function getLineItems()
    {
        $lineItems = array();
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if ($quote) {
            // add Shipping item
            if ($quote->getShippingAddress()->getBaseShippingInclTax()) {
                $shippingItem = Mage::getModel('ewayrapid/field_lineItem');
                $shippingItem->setSKU('');
                $shippingItem->setDescription('Shipping');
                $shippingItem->setQuantity(1);
                $shippingItem->setUnitCost(round($quote->getShippingAddress()->getBaseShippingAmount() * 100));
                $shippingItem->setTax(round($quote->getShippingAddress()->getBaseShippingTaxAmount() * 100));
                $shippingItem->setTotal(round($quote->getShippingAddress()->getBaseShippingInclTax() * 100));
                $lineItems[] = $shippingItem;
            }

            // add Line items
            $items = $quote->getAllVisibleItems();
            foreach ($items as $item) {
                /* @var Mage_Sales_Model_Order_Item $item */
                $lineItem = Mage::getModel('ewayrapid/field_lineItem');
                $lineItem->setSKU($item->getSku());
                $lineItem->setDescription(substr($item->getName(), 0, 26));
                $lineItem->setQuantity($item->getQty());
                $lineItem->setUnitCost(round($item->getBasePrice() * 100));
                $lineItem->setTax(round($item->getBaseTaxAmount() * 100));
                $lineItem->setTotal(round($item->getBaseRowTotalInclTax() * 100));
                $lineItems[] = $lineItem;
            }

            // add Discount item
            if ((int)$quote->getShippingAddress()->getBaseDiscountAmount() !== 0) {
                $shippingItem = Mage::getModel('ewayrapid/field_lineItem');
                $shippingItem->setSKU('');
                $shippingItem->setDescription('Discount');
                $shippingItem->setQuantity(1);
                $shippingItem->setUnitCost(round($quote->getShippingAddress()->getBaseDiscountAmount() * 100));
                $shippingItem->setTax(0);
                $shippingItem->setTotal(round($quote->getShippingAddress()->getBaseDiscountAmount() * 100));
                $lineItems[] = $shippingItem;
            }
        }
        return $lineItems;
    }

    public function checkCardName($card)
    {
        /* @var Eway_Rapid31_Model_Request_Token $model */
        $model = Mage::getModel('ewayrapid/request_token');
        return $model->checkCardName($card);
    }

    public function clearSessionSharedpage()
    {
        Mage::getSingleton('core/session')->unsetData('editToken');
        Mage::getSingleton('core/session')->unsetData('newToken');
        Mage::getSingleton('core/session')->unsetData('sharedpagePaypal');
    }
}