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

require_once 'Payment/Process.php';
require_once 'Payment/Process/Common.php';
require_once 'Net/Curl.php';

$GLOBALS['_Payment_Process_AuthorizeNet'] = array(
    PAYMENT_PROCESS_ACTION_NORMAL   => 'AUTH_CAPTURE',
    PAYMENT_PROCESS_ACTION_AUTHONLY => 'AUTH_ONLY',
    PAYMENT_PROCESS_ACTION_POSTAUTH => 'PRIOR_AUTH_CAPTURE'
);

/**
 * Payment_Process_AuthorizeNet
 *
 * This is a processor for Authorize.net's merchant payment gateway.
 * (http://www.authorize.net/)
 *
 * *** WARNING ***
 * This is BETA code, and has not been fully tested. It is not recommended
 * that you use it in a production envorinment without further testing.
 *
 * @package Payment_Process
 * @author Joe Stump <joe@joestump.net> 
 * @version @version@
 */
class Payment_Process_AuthorizeNet extends Payment_Process_Common {
    /**
     * Front-end -> back-end field map.
     *
     * This array contains the mapping from front-end fields (defined in
     * the Payment_Process class) to the field names DPILink requires.
     *
     * @see _prepare()
     * @access private
     */
    // x_delim_data = TRUE
    // x_relay = FALSE
    // x_email_customer = FALSE
    // x_email_merchant = merchant email
    // x_currency_code = (?)
    // x_method = CC
    // x_first_name & x_last_name
    var $_fieldMap = array(
        // Required
        'login' => 'x_login',
        'password' => 'x_password',
        'action' => 'x_type',
        'invoiceNumber' => 'x_invoice_num',
        'customerId' => 'x_cust_id',
        'amount' => 'x_amount',
        'cardNumber' => 'x_card_num',
        'expDate' => 'x_exp_date',
        'zip' => 'x_zip',
        // Optional
        'company' => 'x_company',
        'address' => 'x_address',
        'city' => 'x_city',
        'state' => 'x_state',
        'country' => 'x_country',
        'phone' => 'x_phone',
        'email' => 'x_email',
        'ip' => 'x_customer_ip',
        'cvv' => 'x_card_code',
        'transactionSource' => 'ECommerce'
    );

    /**
     * Default options for this processor.
     *
     * @see Payment_Process::setOptions()
     * @access private
     */
    var $_defaultOptions = array(
         'authorizeUri' => 'https://secure.authorize.net/gateway/transact.dll',
         'x_delim_data' => 'TRUE',
         'x_relay' => 'FALSE',
         'x_email_customer' => 'FALSE',
         'x_test_request' => 'FALSE',
         'x_currency_code' => 'USD',
         'x_version' => '3.1',
         'x_method' => 'CC'
    );

    /**
     * Has the transaction been processed?
     *
     * @type boolean
     * @access private
     */
    var $_processed = false;

    /**
     * The response body sent back from the gateway.
     *
     * @access private
     */
    var $_responseBody = '';

    /**
     * Constructor.
     *
     * @param  array  $options  Class options to set.
     * @see Payment_Process::setOptions()
     * @return void
     */
    function Payment_Process_AuthorizeNet($options = false)
    {
        $this->setOptions($options);
    }

    /**
     * Process the transaction.
     *
     * @return mixed Payment_Process_Result on success, PEAR_Error on failure
     */
    function &process()
    {
        if($this->_options['debug'] === true) {
            echo "----------- DATA -----------\n";
            print_r($this->_data);
            echo "----------- DATA -----------\n";
        }

        // Sanity check
        $result = $this->validate();
        if(PEAR::isError($result)) {
            return $result;
        }

        // Prepare the data
        $result = $this->_prepare();
        if (PEAR::isError($result)) {
            return $result; 
        }

        // Don't die partway through
        PEAR::pushErrorHandling(PEAR_ERROR_RETURN);

        if($this->_options['debug'] === true) {
            print_r($this->_options);
        }

        $fields = $this->_prepareQueryString();
        $curl = & new Net_Curl($this->_options['authorizeUri']);
        if (PEAR::isError($curl)) {
            PEAR::popErrorHandling();
            return $curl;
        }

        $curl->type = 'PUT';
        $curl->fields = $fields;
        if($this->_options['debug'] === true) {
            echo "------------ CURL FIELDS -------------\n";
            print_r($curl->fields); 
            echo "------------ CURL FIELDS -------------\n";
        }

        $curl->userAgent = 'PEAR Payment_Process_AuthorizeNet 0.1';

        $result = &$curl->execute();
        if (PEAR::isError($result)) {
            PEAR::popErrorHandling();
            return $result;
        } else {
            $curl->close();
        }


        $this->_responseBody = trim($result);
        $this->_processed = true;

        // Restore error handling
        PEAR::popErrorHandling();

        $response = &Payment_Process_Result::factory($this->_driver,$this->_responseBody);
        if(!PEAR::isError($response))
        {
          $response->_request = & $this;
          $response->parse();
        }

        return $response;

//        $result->setResponse($this->_responseBody);
//        $result->setRequest(&$this);
//        $this->_result = &$result;

//        return $result;

        /*
         * HTTP_Request doesn't do SSL until PHP 4.3.0, but it
         * might be useful later...
        $req = &new HTTP_Request($this->_authUri);
        $this->_setPostData();
        $req->sendRequest();
        */
    }

    /**
     * Get (completed) transaction status.
     *
     * @return string Two-digit status returned from gateway.
     */
    function getStatus()
    {
        return false;
    }

    /**
     * Prepare the POST query string.
     *
     * @access private
     * @return string The query string
     */
    function _prepareQueryString()
    {

        $data = array_merge($this->_options,$this->_data);
        if($this->_options['debug'] === true) {
            echo "--------- PREPARE QS DATA -----------\n";
            print_r($this->_data);
            print_r($data);
            echo "--------- PREPARE QS DATA -----------\n";
        }
        $return = array();
        $sets = array();
        foreach ($data as $key => $val) {
            if (eregi('^x_',$key) && strlen($val)) {
                $return[$key] = $val;
                $sets[] = $key.'='.urlencode($val);
            }
        }

        $this->_options['authorizeUri'] .= '?'.implode('&',$sets);

        return $return;
    }

    /**
    * _handleName
    *
    * @author Joe Stump <joe@joestump.net>
    * @access private
    */
    function _handleName()
    {
      $parts = explode(' ',$this->name);
      $this->_data['x_first_name'] = array_shift($parts);
      $this->_data['x_last_name'] = implode(' ',$parts); 
    }
}

class Payment_Process_Result_AuthorizeNet extends Payment_Process_Result {

    var $_statusCodeMap = array('1' => PAYMENT_PROCESS_RESULT_APPROVED,
                                '2' => PAYMENT_PROCESS_RESULT_DECLINED,
                                '3' => PAYMENT_PROCESS_RESULT_OTHER);

    /**
     * AuthorizeNet status codes
     *
     * This array holds many of the common response codes. There are over 200
     * response codes - so check the AuthorizeNet manual if you get a status
     * code that does not match (see "Response Reason Codes & Response 
     * Reason Text" in the AIM manual).
     *
     * @see getStatusText()
     * @access private
     */
    var $_statusCodeMessages = array(
        '1'   => 'The credit card was approved',
        '2'   => 'This transaction has been declined',
        '3'   => 'This transaction has been declined',
        '4'   => 'This transaction has been declined',
        '5'   => 'A valid amount is required',
        '6'   => 'The credit card number is invalid',
        '7'   => 'The credit card expiration date is invalid',
        '8'   => 'The credit card has expired',
        '9'   => 'The ABA code is invalid',
        '10'  => 'The account number is invalid',
        '11'  => 'A duplicate transaction has been submitted',
        '12'  => 'An authorization code is required but not present',
        '13'  => 'The merchant Login ID is invalid or the account is inactive',
        '14'  => 'The Referrer or Relay Response URL is invalid',
        '15'  => 'The transaction ID is invalid',
        '16'  => 'The transaction was not found',
        '17'  => 'The merchant does not accept this type of credit card',
        '18'  => 'ACH transactions are not accepted by this merchant',
        '27'  => 'The transaction resulted in an AVS mismatch',
        '33'  => 'Credit card number is required',
        '36'  => 'The authorization was approved, but settlement failed',
        '37'  => 'The credit card number is invalid',
        '49'  => 'Transactions greater than $99,999 will not be accepted',
        '103' => 'This transaction cannot be accepted'
    );

    var $_avsCodeMap = array(
        'A' => PAYMENT_PROCESS_AVS_MISMATCH,
        'B' => PAYMENT_PROCESS_AVS_ERROR,
        'E' => PAYMENT_PROCESS_AVS_ERROR,
        'G' => PAYMENT_PROCESS_AVS_NOAPPLY,
        'N' => PAYMENT_PROCESS_AVS_MISMATCH,
        'P' => PAYMENT_PROCESS_AVS_NOAPPLY,
        'R' => PAYMENT_PROCESS_AVS_ERROR,
        'S' => PAYMENT_PROCESS_AVS_ERROR,
        'U' => PAYMENT_PROCESS_AVS_ERROR,
        'W' => PAYMENT_PROCESS_AVS_MISMATCH,
        'X' => PAYMENT_PROCESS_AVS_MATCH,
        'Y' => PAYMENT_PROCESS_AVS_MATCH,
        'Z' => PAYMENT_PROCESS_AVS_MISMATCH
    );

    var $_avsCodeMessages = array(
        'A' => 'Address matches, ZIP does not',
        'B' => 'Address information not provided',
        'E' => 'AVS Error',
        'G' => 'Non-U.S. Card Issuing Bank',
        'N' => 'No match',
        'P' => 'AVS not applicable',
        'R' => 'Retry - System unavailable or timeout',
        'S' => 'Service not supported by issuer',
        'U' => 'Address information unavailable',
        'W' => '9-digit zip matches, Address (street) does not',
        'X' => 'Address and 9-digit zip match',
        'Y' => 'Address and 5-digit zip match',
        'Z' => '5-digit zip matches, Address (street) does not'
    );

    var $_cvvCodeMap = array('M' => PAYMENT_PROCESS_CVV_MATCH,
                             'N' => PAYMENT_PROCESS_CVV_MISMATCH,
                             'P' => PAYMENT_PROCESS_CVV_ERROR,
                             'S' => PAYMENT_PROCESS_CVV_ERROR,
                             'U' => PAYMENT_PROCESS_CVV_ERROR
    );

    var $_cvvCodeMessages = array(
        'M' => 'CVV codes match',
        'N' => 'CVV codes do not match',
        'P' => 'CVV code was not processed',
        'S' => 'CVV code should have been present',
        'U' => 'Issuer unable to process request',
    );

    var $_fieldMap = array('0'  => 'code',
                           '2'  => 'messageCode',
                           '3'  => 'message',
                           '4'  => 'approvalCode',
                           '5'  => 'avsCode',
                           '6'  => 'transactionId',
                           '7'  => 'invoiceNumber',
                           '12' => 'customerId',
                           '39' => 'cvvCode'
    );

    function Payment_Process_Response_AuthorizeNet($rawResponse) 
    {
        $this->Payment_Process_Response($rawResponse);
    }

    function parse()
    {
      $responseArray = explode(',',$this->_rawResponse);
      $this->_mapFields($responseArray);
    }
}

?>
