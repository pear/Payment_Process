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
// +----------------------------------------------------------------------+
//
// $Id$

require 'Payment/Process.php';

// Set options. These are processor-specific.
$options = array(
    'randomResult' => true
);

// Get an instance of the processor
$processor = Payment_Process::factory('Dummy', $options);

// The data for our transaction.
$data = array(
    'login' => "foo",
    'password' => "bar",
    'action' => PAYMENT_PROCESS_ACTION_NORMAL,
    'amount' => 15.00
);

// The credit card information
$cc = &Payment_Process_Type::factory('CreditCard');
$cc->type = PAYMENT_PROCESS_CC_VISA;
$cc->cardNumber = "4111111111111111";
$cc->expDate = "99/99";
$cc->cvv = "123";

/* Alternately, you can use setFrom()
$ccData = array(
    'type' => PAYMENT_PROCESS_CC_VISA,
    'cardNumber' => "4111111111111111",
    'expDate' => "99/99",
    'cvv' => 123
);
$cc->setFrom($ccData);
*/

// Process it
$processor->setFrom($data);
if (!$processor->setPayment(&$cc)) {
    PEAR::raiseError("Payment data is invalid.");
    die();
}
$result = $processor->process();

if (PEAR::isError($result)) {
    // process() returns a PEAR_Error if validation failed.
    print "Validation failed: {$result->message}\n";
} else if ($result->isSuccess()) {
    // Transaction approved
    print "Success: ";
} else {
    // Transaction declined
    print "Failure: ";
}
print $result->getMessage()."\n";

?>
