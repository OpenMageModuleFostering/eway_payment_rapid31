<?php
/**
 *
 */
class Eway_Rapid31_Adminhtml_EwayadminController extends Mage_Adminhtml_Controller_Action {

    protected function _isAllowed() {
        return true;
    }

    public function indexAction() {
    }

    public function massEwayAuthorisedAction() {
        $data = Mage::app()->getRequest()->getPost();
        if(is_array($data) & isset($data['order_ids'])) {
            foreach($data['order_ids'] as $id) {
                $order = Mage::getModel('sales/order')->load($id);
                $order->setData('state', 'processing');
                $order->setData('status', Eway_Rapid31_Model_Config::ORDER_STATUS_AUTHORISED);
                $order->save();

                // Update user fraud status
                $customer_data = Mage::getModel('customer/customer')->load($order->getCustomerId());
                $customer_data->setData('mark_fraud', 0);
                $customer_data->save();

                // Re-order current order
                // ...
            }
        }
        // Redirect form
        $this->_redirectUrl(Mage::helper("adminhtml")->getUrl("adminhtml/sales_order/index"));
    }

    public function massProcessingAction() {
        $data = Mage::app()->getRequest()->getPost();
        if(is_array($data) & isset($data['order_ids'])) {
            foreach($data['order_ids'] as $id) {
                $order = Mage::getModel('sales/order')->load($id);
                $order->setData('state', 'processing');
                $order->setData('status', 'processing');
                $order->save();

                // Update user fraud status
                $customer_data = Mage::getModel('customer/customer')->load($order->getCustomerId());
                $customer_data->setData('mark_fraud', 0);
                $customer_data->save();

                // Re-order current order
                // ...
            }
        }
        // Redirect form
        $this->_redirectUrl(Mage::helper("adminhtml")->getUrl("adminhtml/sales_order/index"));
    }

    public function massVerifyEwayOrderAction() {
        $data = Mage::app()->getRequest()->getPost();
        if(is_array($data) & isset($data['order_ids'])) {

            foreach($data['order_ids'] as $id) {

                $order = Mage::getModel('sales/order')->load($id);

                $result = $this->__getTransaction($order->getEwayTransactionId());

                // Check return data
                $result_decode = json_decode($result);

                $trans = (isset($result_decode->Transactions) ? $result_decode->Transactions : array());
                if(!isset($trans[0])) {
                    continue; // go to next cycle when no element is exist
                }
                $tranId =  $trans[0]->TransactionID;

                if($trans[0]->ResponseMessage == 'A2000') { // Success - Fraud order has been approved
                    // Create new transaction
                    $this->__createNewTransaction($order, $tranId);
                    //  Update order status
                    $this->__updateStatusOrder($order);
                    // Un-mark fraud customer
                    $this->__unMarkFraudUser($order);
                }
            }
        }
        // Redirect form
        $this->_redirectReferer();
    }
    private function __getTransaction($transId) {
        $ewayConfig = Mage::getSingleton('ewayrapid/config');
        $url = $ewayConfig->getRapidAPIUrl('Transaction') . '/' . $transId;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: application/json"));
        curl_setopt($ch, CURLOPT_USERPWD, $ewayConfig->getBasicAuthenticationHeader());
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $ewayConfig->isEnableSSLVerification());

        $result = curl_exec($ch);
        return $result;
    }

    /**
     * Create new transaction with base order
     * @param $data
     */
    private function __createNewTransaction(Mage_Sales_Model_Order $order, $transId) {

        // Load transaction
        $currentTrans = Mage::getModel('sales/order_payment_transaction')
            ->getCollection()
            ->addFieldToFilter('order_id', array('eq' => $order->getEntityId()));
        foreach($currentTrans as $t) { }
        if($t == null) {
            $t = new Mage_Sales_Model_Order_Payment_Transaction();
        }

        $trans = new Mage_Sales_Model_Order_Payment_Transaction();
        // Load payment object
        $payment = Mage::getModel('sales/order_payment')->load($t->getPaymentId());

        $trans->setOrderPaymentObject($payment);
        $trans->setOrder($order);

        $trans->setParentId($t->getTransactionId());
        $trans->setOrderId($order->getEntityId());
        $trans->setPaymentId($t->getPaymentId());
        // Get new TxnId
        $break = true;
        for($i = 0; $i < 100; $i++) {
            $transId += 1;
            $newTrans = Mage::getModel('sales/order_payment_transaction')
                ->getCollection()
                ->addFieldToFilter('txn_id', array('eq' => $transId));
            if(count($newTrans) == 0) {
                $break = false;
                break;
            }
        }
        if($break) {
            return false;
        }
        $trans->setTxnId($transId);

        $trans->setParentTxnId($t->getTxnId());
        $trans->setTxnType($t->getTxnType());
        $trans->setIsClosed($t->getIsClosed());
        $trans->setCreatedAt(date('Y-m-d H:i:s'));

        try {
            $trans->save();
        } catch(Exception $e) {
            // Do something
        }
        return true;

    }

    private function __updateStatusOrder(Mage_Sales_Model_Order $order) {
        $state_config = Mage::getStoreConfig('payment/ewayrapid_general/verify_eway_order');

        $order->setState($state_config);
        $order->setStatus($state_config);
        $order->save();
    }

    private function __unMarkFraudUser(Mage_Sales_Model_Order $order) {
        if ($uid = $order->getCustomerId()) {
            $customer = Mage::getModel('customer/customer')->load($uid);
            $customer->setMarkFraud(0);
            $customer->save();
        }
    }

    public function ewayordersAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

}