<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at                              |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Joe Stump <joe@joestump.net>                                |
// +----------------------------------------------------------------------+
//
// $Id$


require_once('Validate/CreditCard.php');

/**
* Payment_Process_offline
*
* An offline driver that allows you to do offline validation of credit card
* via the Validate_CreditCard package. This package is intended for those 
* who wish to someday use a payment gateway, but at this time are not currently
* using one.
*
* @author Joe Stump <joe@joestump.net>
* @package Payment_Process
*/
class Payment_Process_offline extends Payment_Process {

  /**
  * $_processed
  *
  * Set to true after the credit card has been processed
  *
  * @author Joe Stump <joe@joestump.net>
  * @var bool $_processed 
  */
  var $_processed = false;

  /**
  * $_response
  *
  * The response after the credit card has been processed
  *
  * @author Joe Stump <joe@joestump.net>
  * @var bool $_response
  */
  var $_response  = false;

  /**
  * Payment_Process_offline
  *
  * Constructor - currently does nothing
  *
  * @author Joe Stump <joe@joestump.net>
  * @return void
  */
  function Payment_Process_offline()
  {

  }

  /**
  * process
  *
  * Processes the given credit card. Returns PEAR_Error when an error has 
  * occurred or it will return a valid Payment_Process_Result on success.
  *
  * @author Joe Stump <joe@joestump.net>
  * @access public
  * @return mixed
  */
  function process() 
  { 
    $card = array();
    $card['number'] = $this->cardNumber;
    $card['month']  = $this->expMonth;
    $card['year']   = $this->expYear;

    $check = false;
    switch($this->type)
    {
      case PROCESS_TYPE_VISA:
        $card['type'] = VALIDATE_CREDITCARD_TYPE_VS;
        break;
      case PROCESS_TYPE_MASTERCARD:
        $card['type'] = VALIDATE_CREDITCARD_TYPE_MC;
        break;
      case PROCESS_TYPE_AMEX:
        $card['type'] = VALIDATE_CREDITCARD_TYPE_AX;
        break;
      case PROCESS_TYPE_DISCOVER:
        $card['type'] = VALIDATE_CREDITCARD_TYPE_DS;
        break;
      case PROCESS_TYPE_CHECK:
        return $check = true; // Nothing to process - it's a check
    }
  
    if (!$check) {
      $this->_result    = Validate_CreditCard::card($card);
      $this->_processed = true;
    }
  
    if ($this->_result) {
      $code = PROCESS_RESULT_APPROVED;
      $message = 'Valid Credit Card';
    } else {
      $code = PROCESS_RESULT_DECLINED;
      
      // Run extra checks to get a better error message
      if(Validate_CreditCard::number($card['number'])) {
        $message = 'Card number is invalid';
      } elseif(Validate_CreditCard::expiryDate($card['month'],$card['year'])) {
        $message = 'Invalid expriation date';
      } elseif(Validate_CreditCard::expiryDate($card['number'],$card['type'])) {
        $message = 'Card number does not match specified type';
      }
    }

    if($code == PROCESS_RESULT_DECLINED) {
      return PEAR::raiseError($message,$code);
    } else {
      return new Payment_Process_Result($message,$code);
    }
  }

  /**
  * getStatus
  *
  * Return status or PEAR_Error when it has not been processed yet.
  *
  * @author Joe Stump <joe@joestump.net>
  * @access public
  */
  function getStatus() 
  {
    if(!$this->processed) { 
      return PEAR::raiseError('The transaction has not been processed yet.', PROCESS_ERROR_INCOMPLETE);
    }

    return $this->_response;
  }
}

?>
