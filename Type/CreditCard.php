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
// | Authors: Ian Eure <ieure@php.net>                                    |
// |          Joe Stump <joe@joestump.net>                                |
// +----------------------------------------------------------------------+
//
// $Id$

define('PAYMENT_PROCESS_TYPE_VISA', 100);
define('PAYMENT_PROCESS_TYPE_MASTERCARD', 101);
define('PAYMENT_PROCESS_TYPE_AMEX', 102);
define('PAYMENT_PROCESS_TYPE_DISCOVER', 103);
define('PAYMENT_PROCESS_TYPE_CHECK', 104);

class Payment_Process_Type_CreditCard extends Payment_Process_Type 
{
    var $type
    var $number;
    var $cvv;
    var $expDate;

    function Payment_Process_Type_CreditCard()
    {

    }

    function _validateNumber()
    {
        return (Validate::creditCard($this->cardNumber)); 
    }

    function _validateType()
    {
        switch ($this->type) {
            case PAYMENT_PROCESS_TYPE_MASTERCARD:
                return ereg('^5[1-5][0-9]{14}$',$this->number);
            case PAYMENT_PROCESS_TYPE_VISA:
                return ereg('^4[0-9]{12}([0-9]{3})?$',$this->number);
            case PAYMENT_PROCESS_TYPE_AMEX:
                return ereg('^3[47][0-9]{13}$',$creditCard);
            case PAYMENT_PROCESS_TYPE_DISCOVER:
                return ereg('^6011[0-9]{12}$', $creditCard);
            default:
                return false;
        }
    }

    function _validateExpDate()
    {
        list($month,$year) = explode('/',$this->expDate);
        $monthOptions = array('min'     => 1,
                              'max'     => 12,
                              'decimal' => false);
                                                                                
        $yearOptions  = array('min'     => date("Y"),
                              'decimal' => false);
                                                                                
                                                                                
        if (Validate::number($month,$monthOptions) &&
            Validate::number($year,$yearOptions)) {

            if (($month >= date("m") && $year == date("Y")) ||
                ($year > date("Y"))) {
                return true;
            }

        }
                                                                                
        return false;
    }
}

?>