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
        'name' => '',
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
    );

    /**
    */
    var $_typeField = array(

           'CreditCard' => array(

                    'cardNumber' => 'x_card_num',
                    'cvv' => 'x_card_code',
                    'expDate' => 'x_exp_date'

           ),

           'eCheck' => array(

                    'routingCode' => 'x_bank_aba_code',
                    'accountNumber' => 'x_bank_acct_type',
                    'bankName' => 'x_bank_name',
                    'name' => 'x_bank_acct_name'

           )
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
         'x_version' => '3.1'
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

        // Set payment method to eCheck if our payment type is eCheck.
        // Default is Credit Card.
        $data['x_method'] = 'CC';
        if($this->payment->getType() == 'eCheck')
        {
          $data['x_method'] = 'ECHECK';
        }

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
      $parts = explode(' ',$this->_payment->name);
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
          '1' => 'This transaction has been approved.',
          '2' => 'This transaction has been declined.',
          '3' => 'This transaction has been declined.',
          '4' => 'This transaction has been declined.',
          '5' => 'A valid amount is required.',
          '6' => 'The credit card number is invalid.',
          '7' => 'The credit card expiration date is invalid.',
          '8' => 'The credit card has expired.',
          '9' => 'The ABA code is invalid.',
          '10' => 'The account number is invalid.',
          '11' => 'A duplicate transaction has been submitted.',
          '12' => 'An authorization code is required but not present.',
          '13' => 'The merchant Login ID is invalid or the account is inactive.',
          '14' => 'The Referrer or Relay Response URL is invalid.',
          '15' => 'The transaction ID is invalid.',
          '16' => 'The transaction was not found.',
          '17' => 'The merchant does not accept this type of credit card.',
          '18' => 'ACH transactions are not accepted by this merchant.',
          '19' => 'An error occurred during processing. Please try again in 5 minutes.',
          '20' => 'An error occurred during processing. Please try again in 5 minutes.',
          '21' => 'An error occurred during processing. Please try again in 5 minutes.',
          '22' => 'An error occurred during processing. Please try again in 5 minutes.',
          '23' => 'An error occurred during processing. Please try again in 5 minutes.',
          '24' => 'The Nova Bank Number or Terminal ID is incorrect. Call Merchant Service Provider.',
          '25' => 'An error occurred during processing. Please try again in 5 minutes.',
          '26' => 'An error occurred during processing. Please try again in 5 minutes.',
          '27' => 'The transaction resulted in an AVS mismatch. The address provided does not match billing address of cardholder.',
          '28' => 'The merchant does not accept this type of credit card.',
          '29' => 'The PaymentTech identification numbers are incorrect. Call Merchant Service Provider.',
          '30' => 'The configuration with the processor is invalid. Call Merchant Service Provider.',
          '31' => 'The FDC Merchant ID or Terminal ID is incorrect. Call Merchant Service Provider.',
          '32' => 'The merchant password is invalid or not present.',
          '33' => 'Missing required field',
          '34' => 'The VITAL identification numbers are incorrect. Call Merchant Service Provider.',
          '35' => 'An error occurred during processing. Call Merchant Service Provider.',
          '36' => 'The authorization was approved, but settlement failed.',
          '37' => 'The credit card number is invalid.',
          '38' => 'The Global Payment System identification numbers are incorrect. Call Merchant Service Provider.',
          '39' => 'The supplied currency code is either invalid, not supported, not allowed for this merchant or doesn\'t have an exchange rate.',
          '40' => 'This transaction must be encrypted.',
          '41' => 'FraudScreen.net fraud score is higher than threshold set by merchant',
          '42' => 'There is missing or invalid information in a required field.',
          '43' => 'The merchant was incorrectly set up at the processor. Call your Merchant Service Provider.',
          '44' => 'This transaction has been declined. Card Code filter error!',
          '45' => 'This transaction has been declined. Card Code / AVS filter error!',
          '46' => 'Your session has expired or does not exist. You must log in to continue working.',
          '47' => 'The amount requested for settlement may not be greater than the original amount authorized.',
          '48' => 'This processor does not accept partial reversals.',
          '49' => 'A transaction amount greater than $99,999 will not be accepted.',
          '50' => 'This transaction is awaiting settlement and cannot be refunded.',
          '51' => 'The sum of all credits against this transaction is greater than the original transaction amount.',
          '52' => 'The transaction was authorized, but the client could not be notified; the transaction will not be settled.',
          '53' => 'The transaction type was invalid for ACH transactions.',
          '54' => 'The referenced transaction does not meet the criteria for issuing a credit.',
          '55' => 'The sum of credits against the referenced transaction would exceed the original debit amount.',
          '56' => 'This merchant accepts ACH transactions only; no credit card transactions are accepted.',
          '57' => 'An error occurred in processing. Please try again in 5 minutes.',
          '58' => 'An error occurred in processing. Please try again in 5 minutes.',
          '59' => 'An error occurred in processing. Please try again in 5 minutes.',
          '60' => 'An error occurred in processing. Please try again in 5 minutes.',
          '61' => 'An error occurred in processing. Please try again in 5 minutes.',
          '62' => 'An error occurred in processing. Please try again in 5 minutes.',
          '63' => 'An error occurred in processing. Please try again in 5 minutes.',
          '64' => 'The referenced transaction was not approved.',
          '65' => 'This transaction has been declined.',
          '66' => 'The transaction did not meet gateway security guidelines.',
          '67' => 'The given transaction type is not supported for this merchant.',
          '68' => 'The version parameter is invalid.',
          '69' => 'The transaction type is invalid. The value submitted in x_type was invalid.',
          '70' => 'The transaction method is invalid.',
          '71' => 'The bank account type is invalid.',
          '72' => 'The authorization code is invalid.',
          '73' => 'The driver\'s license date of birth is invalid.',
          '74' => 'The duty amount is invalid.',
          '75' => 'The freight amount is invalid.',
          '76' => 'The tax amount is invalid.',
          '77' => 'The SSN or tax ID is invalid.',
          '78' => 'The Card Code (CVV2/CVC2/CID) is invalid.',
          '79' => 'The driver\'s license number is invalid.',
          '80' => 'The driver\'s license state is invalid.',
          '81' => 'The merchant requested an integration method not compatible with the AIM API.',
          '82' => 'The system no longer supports version 2.5; requests cannot be posted to scripts.',
          '83' => 'The requested script is either invalid or no longer supported.',
          '84' => 'This reason code is reserved or not applicable to this API.',
          '85' => 'This reason code is reserved or not applicable to this API.',
          '86' => 'This reason code is reserved or not applicable to this API.',
          '87' => 'This reason code is reserved or not applicable to this API.',
          '88' => 'This reason code is reserved or not applicable to this API.',
          '89' => 'This reason code is reserved or not applicable to this API.',
          '90' => 'This reason code is reserved or not applicable to this API.',
          '91' => 'Version 2.5 is no longer supported.',
          '92' => 'The gateway no longer supports the requested method of integration.',
          '93' => 'A valid country is required.',
          '94' => 'The shipping state or country is invalid.',
          '95' => 'A valid state is required.',
          '96' => 'This country is not authorized for buyers.',
          '97' => 'This transaction cannot be accepted.',
          '98' => 'This transaction cannot be accepted.',
          '99' => 'This transaction cannot be accepted.',
          '100' => 'The eCheck type is invalid.',
          '101' => 'The given name on the account and/or the account type does not match the actual account.',
          '102' => 'This request cannot be accepted.',
          '103' => 'This transaction cannot be accepted.',
          '104' => 'This transaction is currently under review.',
          '105' => 'This transaction is currently under review.',
          '106' => 'This transaction is currently under review.',
          '107' => 'This transaction is currently under review.',
          '108' => 'This transaction is currently under review.',
          '109' => 'This transaction is currently under review.',
          '110' => 'This transaction is currently under review.',
          '111' => 'A valid billing country is required.',
          '112' => 'A valid billing state/provice is required.',
          '116' => 'The authentication indicator is invalid.',
          '117' => 'The cardholder authentication value is invalid.',
          '118' => 'The combination of authentication indicator and cardholder authentication value is invalid.',
          '119' => 'Transactions having cardholder authentication values cannot be marked as recurring.',
          '120' => 'An error occurred during processing. Please try again.',
          '121' => 'An error occurred during processing. Please try again.',
          '122' => 'An error occurred during processing. Please try again.',
          '127' => 'The transaction resulted in an AVS mismatch. The address provided does not match billing address of cardholder.',
          '141' => 'This transaction has been declined.',
          '145' => 'This transaction has been declined.',
          '152' => 'The transaction was authorized, but the client could not be notified; the transaction will not be settled.',
          '165' => 'This transaction has been declined.',
          '170' => 'An error occurred during processing. Please contact the merchant.',
          '171' => 'An error occurred during processing. Please contact the merchant.',
          '172' => 'An error occurred during processing. Please contact the merchant.',
          '173' => 'An error occurred during processing. Please contact the merchant.',
          '174' => 'The transaction type is invalid. Please contact the merchant.',
          '175' => 'The processor does not allow voiding of credits.',
          '180' => 'An error occurred during processing. Please try again.',
          '181' => 'An error occurred during processing. Please try again.',
          '200' => 'This transaction has been declined.',
          '201' => 'This transaction has been declined.',
          '202' => 'This transaction has been declined.',
          '203' => 'This transaction has been declined.',
          '204' => 'This transaction has been declined.',
          '205' => 'This transaction has been declined.',
          '206' => 'This transaction has been declined.',
          '207' => 'This transaction has been declined.',
          '208' => 'This transaction has been declined.',
          '209' => 'This transaction has been declined.',
          '210' => 'This transaction has been declined.',
          '211' => 'This transaction has been declined.',
          '212' => 'This transaction has been declined.',
          '213' => 'This transaction has been declined.',
          '214' => 'This transaction has been declined.',
          '215' => 'This transaction has been declined.',
          '216' => 'This transaction has been declined.',
          '217' => 'This transaction has been declined.',
          '218' => 'This transaction has been declined.',
          '219' => 'This transaction has been declined.',
          '220' => 'This transaction has been declined.',
          '221' => 'This transaction has been declined.',
          '222' => 'This transaction has been declined.',
          '223' => 'This transaction has been declined.',
          '224' => 'This transaction has been declined.'
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
