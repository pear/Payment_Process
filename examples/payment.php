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


require_once('Payment/Process.php');

$options = array();
$options['x_test_request'] = 'TRUE';
$options['x_delim_data'] = 'TRUE';
$options['x_password'] = 'aff1070comp';

$process = & Payment_Process::factory('AuthorizeNet',$options);
if (!PEAR::isError($process)) {
    $process->_debug = true;
    $process->login = 'login';
    $process->password = 'password';
    $process->action = PAYMENT_PROCESS_ACTION_AUTHONLY;
    $process->amount = 1.00;

    $card = & Payment_Process_Type::factory('CreditCard');
    if (!PEAR::isError($card)) {
        $card->type = PAYMENT_PROCESS_TYPE_VISA;
        $card->invoiceNumber = 112345145;
        $card->customerId = 1461264151;
        $card->cardNumber = '4111111111111111';
        $card->expDate = '01/2005';
        $card->zip = '48197';
        $card->cvv = '768';

        if (Payment_Process_Type::isValid($card)) {
            if(!$process->setPayment($card)) {
                die("Unable to set payment\n");
            }

            $result = $process->process();
            if (PEAR::isError($result)) {
                echo "\n\n";
                echo $result->getMessage()."\n";
            } else {
                print_r($result);
                echo "\n";
                echo "---------------------- RESPONSE ------------------------\n";
                echo $result->getMessage()."\n";
                echo $result->getCode()."\n";
                if($result->validate(true,false)) {
                    echo "AVS was all good\n";
                } else {
                    echo $result->getAVSMessage()."\n";
                    echo $result->getAVSCode()."\n";
                }
  
                if($result->validate(false,true)) {
                    echo "CVV was all good\n";
                } else {
                    echo $result->getCvvMessage()."\n";
                    echo $result->getCvvCode()."\n";
                }
  
                echo "---------------------- RESPONSE ------------------------\n";
            }
        } else {
            echo 'Something is wrong with your card!'."\n";
        }
    } else {
      echo $card->getMessage()."\n";
    }
} else {
    echo $payment->getMessage()."\n";
}

?>
