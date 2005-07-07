<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 3.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at                              |
// | http://www.php.net/license/3_0.txt.                                  |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Ian Eure <ieure@php.net>                                    |
// |          Joe Stump <joe@joestump.net>                                |
// +----------------------------------------------------------------------+
//
// $Id$

/**
* Payment_Process_Type_CreditCard
*
* @author Joe Stump <joe@joestump.net>
* @package Payment_Process
*/
class Payment_Process_Type_CreditCard extends Payment_Process_Type 
{
    var $_type = 'CreditCard';
    var $type;
    var $cardNumber;
    var $cvv;
    var $expDate;

    function __construct()
    {
        parent::__construct();

    }

    function Payment_Process_Type_CreditCard()
    {
        $this->__construct();
    }

    // {{{ _validateCardNumber()
    /**
    * _validateCardNumber
    *
    * Uses Validate_Finance_CreditCard to validate the card number.
    *
    * @author Joe Stump <joe@joestump.net>
    * @return bool
    * @see Payment_Process_Type_CreditCard::_getValidateTypeMap()
    * @see Validate_Finance_CreditCard
    */
    function _validateCardNumber()
    {
        $types = Payment_Process_Type_CreditCard::_getValidateTypeMap();
        $validateType = $types[$this->type];
        return (Validate_Finance_CreditCard::number($this->cardNumber)); 
    }
    // }}}
    // {{{ _validateType()
    /**
    * _validateType
    *
    * Uses Validate_Finance_CreditCard to validate the type.
    *
    * @author Joe Stump <joe@joestump.net>
    * @return bool
    * @see Payment_Process_Type_CreditCard::_getValidateTypeMap()
    * @see Validate_Finance_CreditCard
    */
    function _validateType()
    {
        $types = Payment_Process_Type_CreditCard::_getValidateTypeMap();
        $validateType = $types[$this->type];
        return (Validate_Finance_CreditCard::type($this->cardNumber,$validateType));
    }
    // }}} 
    // {{{ _validateExpDate()
    /**
     * Validate the card's expiration date.
     *
     * @todo Fix YxK issues; an expyear of '99' will come up as valid.
     * @author Joe Stump <joe@joestump.net>
     * @return boolean true on success, false otherwise
     */
    function _validateExpDate()
    {
        list($month, $year) = explode('/', $this->expDate);
        if (!is_numeric($month) || !is_numeric($year)) {
            return false;
        }
        
        $monthOptions = array('min'     => 1,
                              'max'     => 12,
                              'decimal' => false);
                                                                                
        $yearOptions  = array('min'     => date("y"),
                              'decimal' => false);

        if (Validate::number($month, $monthOptions) &&
            Validate::number($year, $yearOptions)) {
            if (($month >= date("m") && $year == date("y")) ||
                ($year > date("y"))) {
                return true;
            }
        }
                                                                                
        return false;
    }
    // }}} 
    // {{{ _getValidateTypeMap()
    /**
    * _getValidateTypeMap
    *
    * Since Validate 0.5.0 the credit card checking code has been moved into
    * Validate_Finance_CreditCard and has its own credit card types. We use
    * this map to convert Payment_Process's type constants into types that
    * Validate_Finance_CreditCard can understand.
    *
    * @author Joe Stump <joe@joestump.net>
    * @return array
    * @static
    * @see Validate_Finance_CreditCard
    */
    function _getValidateTypeMap()
    {
        static $validateMap = array(PAYMENT_PROCESS_CC_VISA => 'Visa',
                                    PAYMENT_PROCESS_CC_MASTERCARD => 'MasterCard',
                                    PAYMENT_PROCESS_CC_AMEX => 'AmericanExpress',
                                    PAYMENT_PROCESS_CC_DISCOVER => 'Discover');

        return $validateMap;
    }
    // }}}
}

?>
