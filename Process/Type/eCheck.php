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

class Payment_Process_Type_eCheck extends Payment_Process_Type
{
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
