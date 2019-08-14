<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();
try {
    $installer->run("
        ALTER IGNORE TABLE sales_flat_order ADD COLUMN eway_transaction_id char(50) NULL;
        ALTER TABLE sales_flat_quote ADD IGNORE COLUMN transaction_id char(50) NULL;
    ");
} catch (Exception $e) {
}

$setup = Mage::getResourceModel('customer/setup', 'core_setup');

$setup->addAttribute('customer', 'mark_fraud', array(
    'input' => '',
    'type' => 'int',
    'label' => '',
    'visible' => '0',
    'required' => '0',
    'user_defined' => '0',
    'backend' => '',
));

$installer->endSetup();