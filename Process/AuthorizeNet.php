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
require_once 'Net/Curl.php';

// DPILink transaction types
// Request authorization only - no funds are transferred.
define('PAYMENT_PROCESS_ACTION_AUTHORIZENET_AUTH', 'AUTH_ONLY');
// Transfer funds from a previous authorization.
define('PAYMENT_PROCESS_ACTION_AUTHORIZENET_SETTLE', 'PRIOR_AUTH_CAPTURE');
// Authorize & transfer funds
define('PAYMENT_PROCESS_ACTION_AUTHORIZENET_AUTHSETTLE', 'AUTH_CAPTURE');
// debit the indicated amount to a previously-charged card.
//define('PAYMENT_PROCESS_ACTION_DPILINK_CREDIT', 20);
// Cancel authorization
//define('PAYMENT_PROCESS_ACTION_DPILINK_VOID', 61);

$GLOBALS['_Payment_Process_AuthorizeNet'][PAYMENT_PROCESS_ACTION_NORMAL] = 'AUTH_CAPTURE';
$GLOBALS['_Payment_Process_AuthorizeNet'][PAYMENT_PROCESS_ACTION_AUTHONLY] = 'AUTH_ONLY';
$GLOBALS['_Payment_Process_AuthorizeNet'][PAYMENT_PROCESS_ACTION_POSTAUTH] = 'PRIOR_AUTH_CAPTURE';


//define('PAYMENT_PROCESS_RESULT_DPILINK_APPROVAL', 00);
//define('PAYMENT_PROCESS_RESULT_DPILINK_DECLINE', 05);
//define('PAYMENT_PROCESS_RESULT_DPILINK_INVALIDAMOUNT', 13);
//define('PAYMENT_PROCESS_RESULT_DPILINK_INVALIDCARDNO', 14);
//define('PAYMENT_PROCESS_RESULT_DPILINK_REENTER', 19);

/**
 * Payment_Process_Dpilink
 *
 * This is a processor for TransFirst's DPILink merchant payment gateway.
 * (http://www.dpicorp.com/)
 *
 * *** WARNING ***
 * This is BETA code, and has not been fully tested. It is not recommended
 * that you use it in a production envorinment without further testing.
 *
 * @package Payment_Process
 * @author Ian Eure <ieure@php.net>
 * @version @version@
 */
class Payment_Process_AuthorizeNet extends Payment_Process {
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

        if ($this->_debug) {
            echo '<pre>'; print_r($this->_options); echo '</pre>';
        }

        $fields = $this->_prepareQueryString();
        $curl = & new Net_Curl($this->_options['authorizeUri']);
        if (PEAR::isError($curl)) {
            PEAR::popErrorHandling();
            return $curl;
        }

        $curl->type = 'PUT';
        $curl->fields = $fields;
        if($this->_debug === true) {
            echo '<pre>'; print_r($curl->fields); echo '</pre>';
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

        echo $this->_responseBody."\n";

        $this->_processed = true;

        // Restore error handling
        PEAR::popErrorHandling();

//        $result = &Payment_Process_Result::factory('Dpilink');
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
        if (!$this->_processed) {
            return PEAR::raiseError('The transaction has not been processed yet.', PAYMENT_PROCESS_ERROR_INCOMPLETE);
        }
        return $this->_result->code;
    }

    /**
     * Get transaction sequence.
     *
     * 'Sequence' is what DPILink calls their transaction ID/approval code. This
     * function returns that code from a processed transaction.
     *
     * @return mixed  Sequence ID, or PEAR_Error if the transaction hasn't been
     *                processed.
     */
    function getSequence()
    {
        if (!$this->_processed) {
            return PEAR::raiseError('The transaction has not been processed yet.', PAYMENT_PROCESS_ERROR_INCOMPLETE);
        }
        return $this->_result->_sequenceNumber;
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
        $return = array();
        $sets = array();
        foreach ($data as $key => $val) {
            if (eregi('^x_',$key) && strlen($val)) {
                $return[$key] = $val;
                $sets[] = $key.'='.$val;
            }
        }

        $this->_options['authorizeUri'] .= '?'.implode('&',$sets);

        return $return;

/*
        foreach($this->_data as $var => $value) {
            if (strlen($value))
                $tmp[] = urlencode($var).'='.urlencode($value);
        }
        return @implode('&', $tmp);
*/
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

    /**
     * Handle card expiration date.
     *
     * The gateway wants the date in the format MMYY, with no other chars.
     *
     * @access private
     */
    function _handleExpDate()
    {
    	$specific = $this->_fieldMap['expDate'];
        $this->_data[$specific] = str_replace('/', '', $this->expDate);
    }
}

class Payment_Process_Result_Dpilink extends Payment_Process_Result {
	/**
     * The raw response body from the gateway.
     *
     * @access private
     * @type string
     * @see setResponse()
     */
    var $_responseBody;

    /**
     * DPILink status codes.
     *
     * This array holds every possible status returned by the DPILink gateway.
     *
     * See the DPILink documentation for more details on each response.
     *
     * @see getStatusText()
     * @access private
     */
    var $_statusCodes = array(
        '00' => "Approved",
        '01' => "Refer to issuer",
        '02' => "Refer to issuer - Special condition",
        '03' => "Invalid merchant ID",
        '04' => "Pick up card",
        '05' => "Declined",
        '06' => "General error",
        '07' => "Pick up card - Special condition",
        '13' => "Invalid amount",
        '14' => "Invalid card number",
        '15' => "No such issuer",
        '19' => "Re-enter transaction",
        '21' => "Unable to back out transaction",
        '28' => "File is temporarily unavailable",
        '39' => "No credit account",
        '41' => "Pick up card - Lost",
        '43' => "Pick up card - Stolen",
        '51' => "Insufficient funds",
        '54' => "Expired card",
        '57' => "Transaction not permitted - Card",
        '61' => "Amount exceeds withdrawal limit",
        '62' => "Invalid service code, restricted",
        '65' => "Activity limit exceeded",
        '76' => "Unable to locate, no match",
        '77' => "Inconsistent data, rev. or repeat",
        '78' => "No account",
        '80' => "Invalid date",
        '85' => "Card OK",
        '91' => "Issuer or switch is unavailable",
        '93' => "Violation, cannot complete",
        '96' => "System malfunction",
        '98' => "No matching transaction to void",
        '99' => "System timeout",
        'L0' => "General System Error - Contact DPI Account Exec.",
        'L1' => "Invalid or missing account number",
        'L2' => "Invalid or missing password",
        'L3' => "Expiration Date is not formatted correctly",
        'L4' => "Reference number not found",
        'L6' => "Order number is required but missing",
        'L8' => "Network timeout",
        'L14' => "Invalid card number",
        'S5' => "Already settled",
        'S6' => "Not authorized",
        'S7' => "Declined",
        'V6' => "Invalid transaction type",
        'V7' => "Declined",
        'V8' => "Already voided",
        'V9' => "Already posted"
    );

    var $_avsCodes = array(
        'A' => "Address match",
        'E' => "Ineligible",
        'N' => "No match",
        'R' => "Retry",
        'S' => "Service unavailable",
        'U' => "Address information unavailable",
        'W' => "9-digit zip match",
        'X' => "Address and 9-digit zip match",
        'Y' => "Address and 5-digit zip match",
        'Z' => "5-digit zip match"
    );

    var $_aciCodes = array(
    	'A' => "CPS Qualified",
        'E' => "CPS Qualified  -  Card Acceptor Data was submitted in the authorization  request.",
        'M' => "Reserved - The card was not present and no AVS request for International transactions",
        'N' => "Not CPS Qualified",
        'V' => "CPS Qualified ? Included an address verification request in the authorization request."
    );

    var $_authSourceCodes = array(
    	' ' => "Terminal doesn't support",
        '0' => "Exception File",
		'1' => "Stand in Processing, time-out response",
        '2' => "Loss Control System (LCS) response provided",
        '3' => "STIP, response provided, issuer suppress inquiry mode",
        '4' => "STIP, response provided, issuer is down",
        '5' => "Response provided by issuer",
        '9' => "Automated referral service (ARS) stand-in"
    );

    var $_fieldMap = array('0'  => 'code',
                           '1'  => 'subcode',
                           '2'  => 'messageCode',
                           '3'  => 'message',
                           '4'  => 'approvalCode',
                           '5'  => 'avsCode',
                           '6'  => 'transactionId',
                           '7'  => 'invoiceNumber',
                           '39' => 'cvvCode');


    /**
     * Set the response from the gateway.
     *
     * @param  string  $resp  The raw response from the gateway
     * @return mixed boolean true on success, PEAR_Error on failure
     */
    function setResponse($resp)
    {

        $res = $this->_validateResponse($resp);
        if (!$res || PEAR::isError($res)) {
            if (!$res) {
            	$res = PEAR::raiseError("Unable to validate response body");
            }
            return $res;
        }

        $this->_responseBody = $resp;
        $res = $this->_parseResponse();
    }

    /**
     * Get the textual meaning of a status code.
     *
     * @param  string  $status 2-digit status code
     * @return string  Status message
     */
    function _getStatusText($status)
    {
        return @$this->_statusCodes[$status];
    }

    function getAVSResponse()
    {
    	return @$this->_avsCodes[$this->_avsResponse];
    }

    function getAuthSource()
    {
    	return @$this->_authSourceCodes[$this->_authSource];
    }

    function getAuthCharacteristic()
    {
    	return @$this->_aciCodes[$this->_authChar];
    }

    /**
     * Parse the response body.
     *
     * This is just a wrapper which chooses the correct parser for the reponse
     * version.
     *
     * @see _parseR1Response()
     * @return mixed boolean true on success, PEAR_Error on failure
     */
    function _parseResponse()
    {
    	$version = $this->_responseVersion();
        $func = '_parse'.$version.'Response';
        if (!method_exists($this, $func)) {
        	return PEAR::raiseError("Unable to parse response version $version");
        }

        return $this->$func();
    }

    /**
     * Validate the response body.
     *
     * This is just a wrapper which chooses the correct validator for the reponse
     * version.
     *
     * @see _validateR1Response()
     * @return mixed boolean true on success, PEAR_Error on failure
     */
    function _validateResponse($resp)
    {
    	$version = $this->_responseVersion($resp);
    	$func = '_validate'.$version.'Response';
        if (!method_exists($this, $func)) {
        	return PEAR::raiseError("Unable to validate response version $version");
        }

        return $this->$func($resp);
    }

    /**
     * Get the response format version.
     *
     * @return string Response version
     */
    function _responseVersion($resp = false)
    {
    	$resp = $resp ? $resp : $this->_responseBody;
    	list($version) = split('\|', $resp);

        /* According to the documentation, the first field should containt the
         * response format version. During testing, however, I got a blank field.
         * The docs also say that it's a numeric field, but should contain 'R1.'
         * Hmm.
         * Sometimes the version is also 'R '. Sigh.
         */
        if (!strlen($version) || $version == 'R ')
        	$version = 'R1';

        return $version;
    }

    /**
     * Parse R1 response string.
     *
     * This function parses the response the gateway sends back, which is in
     * pipe-delimited format.
     *
     * @return void
     */
    function _parseR1Response()
    {
        list(
        	$this->_format, $this->_acctNo, $this->_transactionCode,
            $this->_sequenceNumber, $this->_mailOrder, $this->_accountNo,
            $this->_expDate, $this->_authAmount, $this->_authDate,
            $this->_authTime, $this->_transactionStatus, $this->_custNo,
            $this->_orderNo, $this->_urn, $this->_authResponse,
            $this->_authSource, $this->_authChar, $this->_transactionId,
            $this->_validationCode, $this->_catCode, $this->_currencyCode,
            $this->_avsResponse, $this->_storeNum, $this->_cvv2
        ) = split('\|', $this->_responseBody);

        $this->_setPublicFields();
    }

    /**
     * Validate a R1 response.
     *
     * @return boolean
     */
    function _validateR1Response($resp)
    {
    	if (strlen($resp) > 160)
        	return false;

        // FIXME - add more tests

		return true;
    }

    /**
     * Set the publicly visible fields from the private ones.
     *
     * @return void
     */
    function _setPublicFields()
    {
        $this->message = $this->_getStatusText($this->_transactionStatus);

        $invalidAVS = array('A', 'E', 'N', 'R', 'S', 'U');
    	// Make sure AVS was successful, if requested
    	if ($this->_request->performAvs && in_array($this->_avsResponse, $invalidAVS)) {
			// It failed.
            $this->message .= " ".$this->getAvsResponse();
        }

        $this->transactionId = $this->_transactionId;
        switch ($this->_transactionStatus) {
        	case '00':
            	$this->code = PAYMENT_PROCESS_RESULT_APPROVED;
                break;

            case '05':
            	$this->code = PAYMENT_PROCESS_RESULT_DECLINED;
                break;

            default:
            	$this->code = PAYMENT_PROCESS_RESULT_OTHER;
                break;
        }
    }
}

?>
