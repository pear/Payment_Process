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

define('PAYMENT_PROCESS_CK_SAVINGS',1000);
define('PAYMENT_PROCESS_CK_CHECKING',1001);

class Payment_Process_Type_eCheck extends Payment_Process_Type
{
    var $_type = 'eCheck';
    var $type;
    var $accountNumber;
    var $routingCode;
    var $bankName;

    function Payment_Process_Type_eCheck()
    {

    }

    function _validateAccountNumber()
    {
        return (isset($this->accountNumber));
    }

    function _validateRoutingCode()
    {
        return (isset($this->routingCode));
    }

    function _validateBankName()
    {
        return (isset($this->bankName));
    }
}

?>
