<?php

  if(!function_exists('is_a')) {
      function is_a($object,$string) {
          if(stristr(get_class($object),$string) || 
             stristr(get_parent_class($object),$string)) {
              return TRUE;
          } else {
              return FALSE;
          }
      }
  }

  require_once('Payment/Process.php');

  $options = array();
  $options['debug'] = TRUE;

  $process = & Payment_Process::factory('TrustCommerce',$options);
  $process->_debug = true;
  $process->login = 'TestMerchant';
  $process->password = 'password';
  $process->action = PAYMENT_PROCESS_ACTION_NORMAL;
  $process->amount = 999999.99;

  $card = & Payment_Process_Type::factory('CreditCard');
  $card->type = PAYMENT_PROCESS_CC_VISA;
  $card->cardNumber = '4111111111111111';
  $card->expDate = '01/2005';
  if(!$process->setPayment($card)) {
  	die("Unable to set payment\n");
  }
  $result = $process->process();
  if (PEAR::isError($result)) {
      echo "---------------------- ERROR ------------------------\n";
      echo $result->getMessage()."\n";
      echo "---------------------- ERROR ------------------------\n";
  } else {
      echo "---------------------- RESPONSE ------------------------\n";
      echo 'Processor result: ';
      echo $result->getCode()." - ";
      echo $result->getMessage();
      echo "---------------------- RESPONSE ------------------------\n";
  }
?>
