<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at                              |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Ian Eure <ieure@debian.org>                                 |
// +----------------------------------------------------------------------+
//
// $Id$

require_once 'Payment/Process.php';
//require_once 'HTTP/Request.php';
require_once 'Net/Curl.php';

// DPILink transaction types
// Request authorization only - no funds are transferred.
define('PAYMENT_PROCESS_ACTION_DPILINK_AUTH', 30);
// Transfer funds from a previous authorization.
define('PAYMENT_PROCESS_ACTION_DPILINK_SETTLE', 40);
// Authorize & transfer funds
define('PAYMENT_PROCESS_ACTION_DPILINK_AUTHSETTLE', 32);
// Debit the indicated amount to a previously-charged card.
define('PAYMENT_PROCESS_ACTION_DPILINK_CREDIT', 20);
// Cancel authorization
define('PAYMENT_PROCESS_ACTION_DPILINK_VOID', 61);

define('PAYMENT_PROCESS_RESULT_DPILINK_APPROVAL', 00);
define('PAYMENT_PROCESS_RESULT_DPILINK_DECLINE', 05);
define('PAYMENT_PROCESS_RESULT_DPILINK_INVALIDAMOUNT', 13);
define('PAYMENT_PROCESS_RESULT_DPILINK_INVALIDCARDNO', 14);
define('PAYMENT_PROCESS_RESULT_DPILINK_REENTER', 19);

/**
 * Payment_Process_Dpilink
 *
 * This is a processor for TransFirst's DPILink merchant payment gateway.
 * (http://www.dpicorp.com/)
 *
 * *** WARNING ***
 * This is *ALPHA* code, and has *never* been tested with their system!
 * DO NOT use this in a production environment!
 *
 * @package Payment_Process
 * @author Ian Eure <ieure@websprockets.com>
 * @version 0.1
 */
class Payment_Process_Dpilink extends Payment_Process {
    /**
     * Front-end -> back-end field map.
     *
     * This array contains the mapping from front-end fields (defined in
     * the Payment_Process class) to the field names DPILink requires.
     *
     * @see _prepare()
     * @access private
     */
    var $_fieldMap = array(
        // Required
        'login' => "DPIAccountNum",
        'password' => "password",
        'action' => "transactionCode",
        'invoiceNumber' => "orderNum",
        'customerId' => "customerNum",
        'amount' => "transactionAmount",
        'cardNumber' => "cardAccountNum",
        'expDate' => "expirationDate",
        'zip' => "cardHolderZip",
        // Optional
        'name' => "cardHolderName",
        'address' => "cardHolderAddress",
        'city' => "cardHolderCity",
        'state' => "cardHolderState",
        'phone' => "cardHolderPhone",
        'email' => "cardHolderEmail",
        'cvv' => "CVV2",
        'transactionSource' => "ECommerce"
    );

    /**
     * Default options for this processor.
     *
     * @see Payment_Process::setOptions()
     * @access private
     */
    var $_defaultOptions = array(
        'authorizeUri' => "https://www.dpisecure.com/dpilink/authpd.asp"
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
    function Payment_Process_Dpilink($options = false)
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
        if(PEAR::isError($res = $this->validate())) {
            return($res);
        }

        if ($this->_options['testType']) {
        	$this->_data['testTransaction'] = $this->_options['testType'];
        }

        // Prepare the data
        $this->_prepare();

        // Don't die partway through
        PEAR::pushErrorHandling(PEAR_ERROR_RETURN);

        $req = &new Net_Curl($this->_options['authorizeUri']);
        if (PEAR::isError($req)) {
            PEAR::popErrorHandling();
            return $req;
        }
        $req->type = 'POST';
        $req->fields = $this->_prepareQueryString();
        $req->userAgent = 'PEAR Payment_Process_Dpilink 0.1';
        $res = &$req->execute();
        $req->close();
        if (PEAR::isError($res)) {
            PEAR::popErrorHandling();
            return $res;
        }
        $this->_responseBody = trim($res);

        $this->_processed = true;

        // Restore error handling
        PEAR::popErrorHandling();

        $status = $this->getStatus();
        $sequence = $this->getSequence();
        $code = PAYMENT_PROCESS_RESULT_APPROVED;
        if ($status != PAYMENT_PROCESS_RESULT_DPILINK_APPROVAL) {
            $code = PAYMENT_PROCESS_RESULT_DECLINED;
        }

        $result = &Payment_Process_Result::factory('Dpilink');
        $result->setResponse($this->_responseBody);
        $this->_result = &$result;

        return $result;

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
        foreach($this->_data as $var => $value) {
            $tmp[] = urlencode($var).'='.urlencode($value);
        }
        return @implode('&', $tmp);
    }

    /*
    function _setPostData(&$req)
    {
        foreach($this->_data as $var => $value) {
            $req->addPostData($var, $value);
        }
    }
    */

    /**
     * Prepare the POST data.
     *
     * This function handles translating the data set in the front-end to the
     * format needed by the back-end. The prepared data is stored in $this->_data.
     * If a '_handleField' method exists in this class (e.g. '_handleCardNumber()'),
     * that function is called and /must/ set $this->_data correctly. If no field-
     * handler function exists, the data from the front-end is mapped into $_data
     * using $this->_fieldMap.
     *
     * @access private
     * @return array Data to POST
     */
    function _prepare()
    {
        $this->_data = array();
        foreach ($this->_fieldMap as $generic => $specific) {
            $func = '_handle'.ucfirst($generic);
            if (method_exists($this, $func)) {
                $this->$func();
            } else {
                $this->_data[$specific] = $this->$generic;
            }
        }
    }

    /**
     * Handle transaction source.
     *
     * @access private
     */
    function _handleTransactionSource()
    {
        $specific = $this->_fieldMap['transactionSource'];
        if ($this->transactionSource == PAYMENT_PROCESS_SOURCE_ONLINE) {
            $this->_data[$specific] = 'Y';
        } else {
            $this->_data[$specific] = 'N';
        }
    }

    /**
     * Handle action.
     *
     * @access private
     */
    function _handleAction()
    {
        switch ($this->action) {
            case PAYMENT_PROCESS_ACTION_NORMAL:
                $val = PAYMENT_PROCESS_ACTION_DPILINK_AUTHSETTLE;
                break;

            case PAYMENT_PROCESS_ACTION_AUTHONLY:
                $val = PAYMENT_PROCESS_ACTION_DPILINK_AUTH;
                break;

            case PAYMENT_PROCESS_ACTION_CREDIT:
                $val = PAYMENT_PROCESS_ACTION_DPILINK_CREDIT;
                break;

            case PAYMENT_PROCESS_ACTION_POSTAUTH:
                $val = PAYMENT_PROCESS_ACTION_DPILINK_SETTLE;
                break;
        }
        $this->_data[$this->_fieldMap['action']] = $val;
    }
}

class Payment_Process_Result_Dpilink extends Payment_Process_Result {
    var $_responseBody;

    /**
     * DPILink status codes.
     *
     * This array holds every possible status returned by the DPILink gateway.
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
        '99' => "System timeout"
    );

    function setResponse($resp)
    {
    	$this->_responseBody = $resp;
        $this->_parseResponse();
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

    /**
     * Parse response string.
     *
     * This function parses the response the gateway sends back, which is in
     * pipe-delimited format.
     *
     * @return void
     */
    function _parseResponse()
    {
        list(
        	$this->_format, $this->_acctNo, $this->_transactionCode,
            $this->_sequenceNumber, $this->_mailOrder, $this->_accountNo,
            $this->_expDate, $this->_authAmount, $this->_authDate,
            $this->_authTime, $this->_transactionStatus, $this->_custNo,
            $this->_orderNo, $this->_urn, $this->_authResponse,
            $this->_authSource, $this->_authChar, $this->_transactionId,
            $this->_validationCode, $this->_catCode, $this->_currencyCode,
            $this->_avsResponse, $this->_storeNi, $this->_cvv2
        ) = split('\|', $this->_responseBody);

        $this->message = &$this->_getStatusText($this->_transactionCode);

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
