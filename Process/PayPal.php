<?php

/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * PayPal "Direct Payment" processor
 * 
 * NOTE: NOT COMPLETED YET.
 *
 * PHP versions 4 and 5
 *
 * LICENSE:
 * 
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * 
 * 1. Redistributions of source code must retain the above copyright notice, this 
 *    list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation 
 *    and/or other materials provided with the distribution.
 *
 * 3. The name of the authors may not be used to endorse or promote products 
 *    derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHORS ``AS IS'' AND ANY EXPRESS OR IMPLIED 
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF 
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO 
 * EVENT SHALL THE AUTHORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, 
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, 
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR
 * BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER 
 * IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) 
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE 
 * POSSIBILITY OF SUCH DAMAGE.
 * 
 * @category   Payment
 * @package    Payment_Process
 * @author     Philippe Jausions <Philippe.Jausions@11abacus.com>
 * @copyright  2007 The PHP Group
 * @license   http://www.opensource.org/licenses/bsd-license.php BSD License
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/Payment_Process
 * @todo       Complete the implementation
 */

/**
 * Required classes
 */
require_once 'Payment/Process.php';
require_once 'Payment/Process/Common.php';
require_once 'Net/Curl.php';

/**
 * Defines global variables
 */
$GLOBALS['_Payment_Process_PayPal'] = array(
    PAYMENT_PROCESS_ACTION_NORMAL   => 'Sale',
    PAYMENT_PROCESS_ACTION_AUTHONLY => 'Authorization',
    PAYMENT_PROCESS_ACTION_POSTAUTH => 'DoCapture',
    PAYMENT_PROCESS_ACTION_VOID     => 'DoVoid',
    PAYMENT_PROCESS_ACTION_CREDIT   => 'RefundTransaction',
);

/**
 * This is a processor for PayPal's merchant  Direct Payment gateway.
 *
 * @package    Payment_Process
 * @author     Philippe Jausions <Philippe.Jausions@11abacus.com>
 * @version    @version@
 * @link       http://www.paypal.com/
 */
class Payment_Process_PayPal extends Payment_Process_Common
{
    /**
     * Front-end -> back-end field map.
     *
     * This array contains the mapping from front-end fields (defined in
     * the Payment_Process class) to the field names PayPal requires.
     *
     * @see _prepare()
     * @access private
     */
    var $_fieldMap = array(
        // Required
        'login'         => 'USER',
        'password'      => 'PWD',
        'action'        => 'PAYMENTACTION',

        // Optional
        'invoiceNumber' => 'INVNUM',
        'customerId'    => 'CUSTOM',
        'amount'        => 'AMT',
        'description'   => 'DESC',
        'name'          => '',
        'postalCode'    => 'ZIP',
        'zip'           => 'ZIP',
        'company'       => 'STREET2',
        'address'       => 'STREET',
        'city'          => 'CITY',
        'state'         => 'STATE',
        'country'       => 'COUNTRYCODE',
        'phone'         => 'PHONENUM',
        'email'         => 'EMAIL',
        'ip'            => 'IPADDRESS',
    );

    /**
     * $_typeFieldMap
     *
     * @access protected
     */
    var $_typeFieldMap = array(

           'CreditCard' => array(
                'firstName'  => 'FIRSTNAME',
                'lastName'   => 'LASTNAME',
                'cardNumber' => 'ACCT',
                'cvv'        => 'CVV2',
                'type'       => 'CREDITCARDTYPE',
                'expDate'    => 'EXPDATE',
           ),
    );

    /**
     * Default options for this processor.
     *
     * @see Payment_Process::setOptions()
     * @access private
     */
    var $_defaultOptions = array(
        'paypalUri' => 'https://api-3t.sandbox.paypal.com/nvp',
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
    function __construct($options = false)
    {
        parent::__construct($options);
        $this->_driver = 'PayPal';
        $this->_makeRequired('login', 'password', 'action');
    }

    function Payment_Process_PayPal($options = false)
    {
        $this->__construct($options);
    }

    /**
     * Processes the transaction.
     *
     * Success here doesn't mean the transaction was approved. It means
     * the transaction was sent and processed without technical difficulties.
     *
     * @return mixed Payment_Process_Result on success, PEAR_Error on failure
     * @access public
     */
    function &process()
    {
        // Sanity check
        $result = $this->validate();
        if (PEAR::isError($result)) {
            return $result;
        }

        // Prepare the data
        $result = $this->_prepare();
        if (PEAR::isError($result)) {
            return $result;
        }

        $fields = $this->_prepareQueryString();
        if (PEAR::isError($fields)) {
            return $fields;
        }
        $fields .= '&VERSION=3.2';

        // Don't die partway through
        PEAR::pushErrorHandling(PEAR_ERROR_RETURN);

        $curl = & new Net_Curl($this->_options['paypalUri']);
        if (PEAR::isError($curl)) {
            PEAR::popErrorHandling();
            return $curl;
        }

        $curl->type = 'post';
        $curl->fields = $fields;
        $curl->userAgent = 'PEAR Payment_Process_PayPal 0.1';

        $result = $curl->execute();
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

        $response = &Payment_Process_Result::factory($this->_driver,
                                                     $this->_responseBody,
                                                     &$this);
        if (!PEAR::isError($response)) {
            $response->parse();

            $r = $response->isLegitimate();
            if (PEAR::isError($r)) {
                return $r;
            } elseif ($r === false) {
                return PEAR::raiseError('Illegitimate response from gateway');
            }
        }
        $response->action = $this->action;

        return $response;
    }

    /**
     * Handles action
     *
     * Actions are defined in $GLOBALS['_Payment_Process_PayPal'] and then
     * handled here.
     *
     * @access protected
     */
    function _handleAction()
    {
        switch ($this->action) {
            case PAYMENT_PROCESS_ACTION_NORMAL:
                $this->_data['PAYMENTACTION'] = 'Sale';
                $this->_data['METHOD'] = 'DoDirectPayment';
                break;
            case PAYMENT_PROCESS_ACTION_AUTHONLY:
                $this->_data['PAYMENTACTION'] = 'Authorization';
                $this->_data['METHOD'] = 'DoDirectPayment';
                break;
            case PAYMENT_PROCESS_ACTION_POSTAUTH:
                $this->_data['METHOD'] = 'DoCapture';
                break;
            case PAYMENT_PROCESS_ACTION_VOID    :
                $this->_data['METHOD'] = 'DoVoid';
                break;
            case PAYMENT_PROCESS_ACTION_CREDIT  :
                $this->_data['METHOD'] = 'RefundTransaction';
                break;
        }
    }

    /**
     * Processes a callback from payment gateway
     *
     * Success here doesn't mean the transaction was approved. It means
     * the callback was received and processed without technical difficulties.
     *
     * @return Payment_Process_Result instance on success, PEAR_Error on failure
     * @todo Implement support for PayPal IPN???
     */
    function &processCallback()
    {
        $result =& parent::processCallback();
        return $result;
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
        $data = array_merge($this->_options, $this->_data);

        $return = array();
        foreach ($data as $k => $v) {
            $return[] = urlencode($k).'='.urlencode($v);
        }

        return implode('&', $return);
    }
}


class Payment_Process_Result_PayPal extends Payment_Process_Result
{

    var $_statusCodeMap = array('1' => PAYMENT_PROCESS_RESULT_APPROVED,
                                '2' => PAYMENT_PROCESS_RESULT_DECLINED,
                                '3' => PAYMENT_PROCESS_RESULT_OTHER,
                                '4' => PAYMENT_PROCESS_RESULT_REVIEW,
                                );
    /**
     * PayPal error codes
     *
     * This array holds many of the common response codes. There are over 200
     * response codes - so check the PayPal manual if you get a status
     * code that does not match (see "Error Reference Message" in the NVAPI
     * Developer Guide).
     *
     * @see getStatusText()
     * @access private
     */
    var $_statusCodeMessages = array();

    var $_avsCodeMap = array(
        '0' => PAYMENT_PROCESS_AVS_MATCH,
        '1' => PAYMENT_PROCESS_AVS_MISMATCH,
        '2' => PAYMENT_PROCESS_AVS_MISMATCH,
        '3' => PAYMENT_PROCESS_AVS_NOAPPLY,
        '4' => PAYMENT_PROCESS_AVS_ERROR,
        'A' => PAYMENT_PROCESS_AVS_MISMATCH,
        'B' => PAYMENT_PROCESS_AVS_MISMATCH,
        'C' => PAYMENT_PROCESS_AVS_MISMATCH,
        'D' => PAYMENT_PROCESS_AVS_MATCH,
        'E' => PAYMENT_PROCESS_AVS_NOAPPLY,
        'F' => PAYMENT_PROCESS_AVS_MATCH,
        'G' => PAYMENT_PROCESS_AVS_NOAPPLY,
        'I' => PAYMENT_PROCESS_AVS_NOAPPLY,
        'N' => PAYMENT_PROCESS_AVS_MISMATCH,
        'P' => PAYMENT_PROCESS_AVS_MISMATCH,
        'R' => PAYMENT_PROCESS_AVS_ERROR,
        'S' => PAYMENT_PROCESS_AVS_ERROR,
        'U' => PAYMENT_PROCESS_AVS_ERROR,
        'W' => PAYMENT_PROCESS_AVS_MISMATCH,
        'X' => PAYMENT_PROCESS_AVS_MATCH,
        'Y' => PAYMENT_PROCESS_AVS_MATCH,
        'Z' => PAYMENT_PROCESS_AVS_MISMATCH,
    );

    var $_avsCodeMessages = array(
        '0' => 'Address and postal code match',
        '1' => 'No match on street address nor postal code',
        '2' => 'Only part of your address information matches',
        '3' => 'Address information unavailable',
        '4' => 'System unavailable or timeout',
        'A' => 'Address matches, postal code does not',
        'B' => 'Address matches, postal code does not',
        'C' => 'No match on street address nor postal code',
        'D' => 'Address and full postal code match',
        'E' => 'Address verification not allowed from Internet/phone',
        'F' => 'Address and full postal code match',
        'G' => 'International Card Issuing Bank',
        'I' => 'International Card Issuing Bank',
        'N' => 'No match on street address nor postal code',
        'P' => 'Postal code matches, street address does not',
        'R' => 'Retry - System unavailable or timeout',
        'S' => 'Service not supported by issuer',
        'U' => 'Address information unavailable',
        'W' => 'Full postal code matches, street address does not',
        'X' => 'Address and full postal code match',
        'Y' => 'Address and postal code match',
        'Z' => 'Postal code matches, street address does not',
    );

    var $_cvvCodeMap = array(
        '0' => PAYMENT_PROCESS_CVV_MATCH,
        '1' => PAYMENT_PROCESS_CVV_MISMATCH,
        '2' => PAYMENT_PROCESS_CVV_NOAPPLY,
        '3' => PAYMENT_PROCESS_CVV_NOAPPLY,
        '4' => PAYMENT_PROCESS_CVV_ERROR,
        'M' => PAYMENT_PROCESS_CVV_MATCH,
        'N' => PAYMENT_PROCESS_CVV_MISMATCH,
        'P' => PAYMENT_PROCESS_CVV_ERROR,
        'S' => PAYMENT_PROCESS_CVV_NOAPPLY,
        'U' => PAYMENT_PROCESS_CVV_ERROR,
        'X' => PAYMENT_PROCESS_CVV_ERROR,
    );

    var $_cvvCodeMessages = array(
        '0' => 'Security code matches',
        '1' => 'Security code does not match',
        '2' => 'Security code verification not supported',
        '3' => 'Card does not have security code',
        '4' => 'Issuer unable to process request',
        'M' => 'Security code matches',
        'N' => 'Security code does not match',
        'P' => 'Security code was not processed',
        'S' => 'Security code verification not supported',
        'U' => 'Issuer unable to process request',
        'X' => 'Security could not be verified',
    );

    var $_fieldMap = array('0'  => 'code',
                           '2'  => 'messageCode',
                           '3'  => 'message',
                           '4'  => 'approvalCode',
                           '5'  => 'avsCode',
                           '6'  => 'transactionId',
                           '7'  => 'invoiceNumber',
                           '8'  => 'description',
                           '9'  => 'amount',
                           '12' => 'customerId',
                           '38' => 'cvvCode',
    );

    function Payment_Process_Response_PayPal($rawResponse)
    {
        $this->Payment_Process_Response($rawResponse);
    }

    /**
     * Parses the data received from the payment gateway
     *
     * @access public
     */
    function parse()
    {
        $responseArray = parse_str($this->_rawResponse);

        // Save some fields in private members
        $map = array_flip($this->_fieldMap);
        $this->_amount = $responseArray[$map['amount']];

        $this->_mapFields($responseArray);

        // Adjust result code/message if needed based on raw code
        switch ($this->messageCode) {
            case 33:
                // Something is missing so we send the raw message back
                $this->_statusCodeMessages[33] = $this->message;
                break;
            case 11:
                // Duplicate transactions
                $this->code = PAYMENT_PROCESS_RESULT_DUPLICATE;
                break;
            case 4:
            case 41:
            case 250:
            case 251:
                // Fraud detected
                $this->code = PAYMENT_PROCESS_RESULT_FRAUD;
                break;
        }
    }

    /**
     * Parses the data received from the payment gateway callback
     *
     * @access public
     * @todo Implement support for PayPal's IPN?
     */
    function parseCallback()
    {
        return parent::parseCallback();
    }

    /**
     * Validates the legitimacy of the response
     *
     * @return mixed TRUE if response is legitimate, FALSE if not, PEAR_Error on error
     * @access public
     * @todo Implement support for PayPal's IPN?
     */
    function isLegitimate()
    {
        return true;
    }
}

?>