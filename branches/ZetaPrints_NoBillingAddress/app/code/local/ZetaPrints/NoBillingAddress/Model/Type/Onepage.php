<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category    ZetaPrints
 * @package     ZetaPrints_NoBillingAddress
 * @copyright   Copyright (c) 2011 ZetaPrints Ltd. http://www.zetaprints.com/
 * @author      Anatoly A. Kazantsev <anatoly.kazantsev@gmail.com>
 * @attribution Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * One page checkout processing model
 */
class ZetaPrints_NoBillingAddress_Model_Type_Onepage
  extends Mage_Checkout_Model_Type_Onepage {

  /**
   * Save billing address information to quote
   * This method is called by One Page Checkout JS (AJAX) while saving the billing information.
   *
   * @param   array $data
   * @param   int $customerAddressId
   * @return  Mage_Checkout_Model_Type_Onepage
   */
  public function saveBilling ($data, $customerAddressId) {
    if (empty($data))
      return array('error' => -1,
                   'message' => $this->_helper->__('Invalid data.'));

    $address = $this->getQuote()->getBillingAddress();

    if (!empty($customerAddressId)) {
      $customerAddress = Mage::getModel('customer/address')
                           ->load($customerAddressId);

      if ($customerAddress->getId()) {
        if ($customerAddress->getCustomerId()
                                          != $this->getQuote()->getCustomerId())
          return array('error' => 1,
                       'message' => $this->_helper
                                      ->__('Customer Address is not valid.') );

        $address->importCustomerAddress($customerAddress);
      }
    } else {
      // process billing address and validate form
      /* @var $addressForm Mage_Customer_Model_Form */
      $addressForm = Mage::getModel('customer/form');

      $addressForm->setFormCode('customer_address_edit')
        ->setEntity($address)
        ->setEntityType('customer_address')
        ->setIsAjaxRequest(Mage::app()->getRequest()->isAjax());

      // emulate request object
      $addressData    = $addressForm->extractData($addressForm->prepareRequest($data));

      $addressErrors  = $addressForm->validateData($addressData);

      //Disable checking validation results
      //if ($addressErrors !== true) {
      //  return array('error' => 1, 'message' => $addressErrors);
      //}

      $addressForm->compactData($addressData);

      if (!empty($data['save_in_address_book']))
        $address->setSaveInAddressBook(1);
    }

    // validate billing address
    $address->validate();

    //Disable checking validation results
    //if (($validateRes = $address->validate()) !== true)
    //  return array('error' => 1, 'message' => $validateRes);

    $address->implodeStreetAddress();

    if (true !== ($result = $this->_validateCustomerData($data)))
      return $result;

    if (!$this->getQuote()->getCustomerId()
        && self::METHOD_REGISTER == $this->getQuote()->getCheckoutMethod())
      if ($this->_customerEmailExists($address->getEmail(),
                                            Mage::app()->getWebsite()->getId()))
        return array('error' => 1,
                     'message' => $this->_customerEmailExistsMessage);


    if (!$this->getQuote()->isVirtual()) {
      /**
       * Billing address using otions
       */
      $usingCase = isset($data['use_for_shipping'])
                     ? (int)$data['use_for_shipping'] : 0;

      switch($usingCase) {
        case 0:
          $shipping = $this->getQuote()->getShippingAddress();
          $shipping->setSameAsBilling(0);
          break;
        case 1:
          $billing = clone $address;
          $billing->unsAddressId()->unsAddressType();
          $shipping = $this->getQuote()->getShippingAddress();
          $shippingMethod = $shipping->getShippingMethod();
          $shipping->addData($billing->getData())
            ->setSameAsBilling(1)
            ->setShippingMethod($shippingMethod)
            ->setCollectShippingRates(true);
          $this->getCheckout()->setStepData('shipping', 'complete', true);
          break;
      }
    }

    $this->getQuote()->collectTotals();
    $this->getQuote()->save();

    $this->getCheckout()
      ->setStepData('billing', 'allow', true)
      ->setStepData('billing', 'complete', true)
      ->setStepData('shipping', 'allow', true);

    return array();
  }
}