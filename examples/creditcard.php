<?php

  require_once('Payment/Process.php');

  $options = array();
  $options['x_test_request'] = 'TRUE';
  $options['x_delim_data'] = 'TRUE';
  $options['avsCheck'] = true;
  $options['cvvCheck'] = true;

  $process = & Payment_Process::factory('AuthorizeNet',$options);
  if (!PEAR::isError($process)) {
      $process->_debug = true;
      $process->login = 'username';
      $process->password = 'password';
      $process->action = PAYMENT_PROCESS_ACTION_AUTHONLY;
      $process->amount = 1.00;

      $card = & Payment_Process_Type::factory('CreditCard');
      if (!PEAR::isError($card)) {
          $card->type = PAYMENT_PROCESS_CC_VISA;
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
                  $validate = $result->validate();
                  if(!PEAR::isError($validate)) {
                      echo "All good\n";
                  } else {
                      echo "ERROR: ".$validate->getMessage()."\n";
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
