<?php
/**
 * Created by PhpStorm.
 * User: hiephm
 * Date: 4/23/14
 * Time: 5:30 PM
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$setup = Mage::getResourceModel('customer/setup', 'core_setup');

$setup->addAttribute('customer', 'saved_tokens_json', array(
    'input' => '',
    'type' => 'text',
    'label' => '',
    'visible' => '0',
    'required' => '0',
    'user_defined' => '0',
    'backend' => 'ewayrapid/backend_savedtokens',
));

$installer->endSetup();