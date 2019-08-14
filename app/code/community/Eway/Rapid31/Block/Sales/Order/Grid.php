<?php
/**
 * Created by PhpStorm.
 * User: Administrator PC
 * Date: 7/21/14
 * Time: 3:47 PM
 */
class Eway_Rapid31_Block_Sales_Order_Grid extends Mage_Adminhtml_Block_Sales_Order_Grid {

    protected function _prepareMassaction()
    {
        parent::_prepareMassaction();

        // Append new mass action option
        $this->getMassactionBlock()->addItem(
            'pending',
            array(
                'label' => $this->__('Verify eWAY Order'),
                'url'   => Mage::helper("adminhtml")->getUrl("ewayadmin/index/massVerifyEwayOrder"), //this should be the url where there will be mass operation
                'confirm'=> $this->__('Are you sure?')
            )
        )/*->addItem(
            'eway_authorised',
                array(
                    'label' => 'eWAY Authorised',
                    'url'   => Mage::helper("adminhtml")->getUrl("ewayadmin/index/massEwayAuthorised"), //this should be the url where there will be mass operation
                    'confirm'=> $this->__('Are you sure?')
                )
            )*/;
    }
}
