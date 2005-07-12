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
        return Validate_Finance_CreditCard::number($this->cardNumber); 
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
        if (!($type = $this->_mapType())) {
            return false;
        }

        return Validate_Finance_CreditCard::type($this->cardNumber, $type);
    }
    // }}} 
    // {{{ _validateCvv()
    /**
     * Validates the card verification value
     *
     * @return bool FALSE is CVV was set and is not valid, TRUE otherwise
     * @access protected
     */
    function _validateCvv()
    {
        if (strlen($this->cvv) == 0) {
            return true;
        }

        if (!($type = $this->_mapType())) {
            return false;
        }

        return Validate_Finance_CreditCard::cvv($this->cvv, $type);
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

        $date = getdate();
                                                                                
        $yearOptions  = array('min'     => $date['year'],
                              'decimal' => false);

        if (Validate::number($month, $monthOptions) &&
            Validate::number($year, $yearOptions)) {
            if (($month >= $date['mon'] && $year == $date['year']) ||
                ($year > $date['year'])) {
                return true;
            }
        }
                                                                                
        return false;
    }
    // }}} 
    // {{{ _mapType()
    /**
     * Maps a PAYMENT_PROCESS_CC_* constant with a with a value suitable
     * to Validate_Finance_CreditCard package
     *
     * @return string card type name
     * @access private
     */
    function _mapType()
    {
        switch ($this->type) {
        case PAYMENT_PROCESS_CC_MASTERCARD:
            return 'MasterCard';
        case PAYMENT_PROCESS_CC_VISA:
            return 'Visa';
        case PAYMENT_PROCESS_CC_AMEX:
            return 'Amex';
        case PAYMENT_PROCESS_CC_DISCOVER:
            return 'Discover';
        case PAYMENT_PROCESS_CC_JCB:
            return 'JCB';
        case PAYMENT_PROCESS_CC_DINERS:
            return 'Diners';
        case PAYMENT_PROCESS_CC_ENROUTE:
            return 'EnRoute';
        case PAYMENT_PROCESS_CC_CARTEBLANCHE:
            return 'CarteBlanche';
        default:
            return false;
        }
    }
    // }}}
}

?>
