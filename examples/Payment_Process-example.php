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
	'login' => "1234567",
    'password' => "foo",
    'action' => PAYMENT_PROCESS_ACTION_NORMAL,
    'amount' => 15.00,
    'type' => PAYMENT_PROCESS_TYPE_VISA,
    'cardNumber' => "4111111111111111",
    'expDate' => "99/99"
);

// Process it
$processor->setFrom($data);
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
