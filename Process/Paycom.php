<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * Paycom processor
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
 * @category  Payment
 * @package   Payment_Process
 * @author    Joe Stump <joe@joestump.net> 
 * @copyright 1997-2005 The PHP Group
 * @license   http://www.opensource.org/licenses/bsd-license.php BSD License
 * @version   CVS: $Id$
 * @link      http://pear.php.net/package/Payment_Process
 */

require_once('Payment/Process.php');
require_once('Payment/Process/Common.php');
require_once('HTTP/Request.php');

$GLOBALS['_Payment_Process_Paycom'] = array(
    PAYMENT_PROCESS_ACTION_NORMAL   => 'approveclose',
    PAYMENT_PROCESS_ACTION_AUTHONLY => 'approve',
    PAYMENT_PROCESS_ACTION_POSTAUTH => 'close',
    PAYMENT_PROCESS_ACTION_CREDIT => 'credit'
);

class Payment_Process_Paycom extends Payment_Process_Common {
    
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
        'login' => 'co_code',
        'password' => 'pwd',
        'action' => 'transtype',
        'invoiceNumber' => 'approval',
        'customerId' => 'user1',
        'amount' => 'price',
        'name' => 'cardname',
        'zip' => 'zip',
        // Optional
        'address' => 'street',
        'state' => 'state',
        'country' => 'contry',
        'phone' => 'phone',
        'email' => 'email',
        'ip' => 'ipaddr',
    );

    /**
    * $_typeFieldMap
    *
    * @author Joe Stump <joe@joestump.net>
    * @access protected
    */
    var $_typeFieldMap = array(
           'CreditCard' => array(
                    'cardNumber' => 'cardnum',
                    'expDate' => 'cardexp'
           )
    );

    /**
     * Default options for this processor.
     *
     * @see Payment_Process::setOptions()
     * @access private
     */
    var $_defaultOptions = array(
         'authorizeUri' => 'https://wnu.com/secure/trans31.cgi',
         'country' => '840'
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
        $this->_driver = 'Paycom';
    }

    function Payment_Process_Paycom($options = false)
    {
        $this->__construct($options);
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
            return PEAR::raiseError('validate(): '.$result->getMessage());
        }

        // Prepare the data
        $result = $this->_prepare();
        if (PEAR::isError($result)) {
            return PEAR::raiseError('_prepare(): '.$result->getMessage());
        }

        // Don't die partway through
        PEAR::pushErrorHandling(PEAR_ERROR_RETURN);

        $fields = $this->_prepareQueryString();
        $request = & new HTTP_Request($this->_options['authorizeUri']);
        $request->setMethod(HTTP_REQUEST_METHOD_POST);
        $request->addHeader('User-Agent','PEAR Payment_Process_Paycom 0.1');
        foreach ($fields as $var => $val) {
            $request->addPostData($var,$val);
        }
 
        $result = $request->sendRequest();
        if (PEAR::isError($result)) {
            PEAR::popErrorHandling();
            return PEAR::raiseError('Request: '.$result->getMessage());
        } 


        $this->_responseBody = trim($request->getResponseBody());
        $this->_processed = true;

        // Restore error handling
        PEAR::popErrorHandling();

        $response = &Payment_Process_Result::factory($this->_driver,
                                                     $this->_responseBody,
                                                     &$this);
        if (!PEAR::isError($response)) {
            $response->parse();
        }

        return $response;
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
            $return[$key] = $val;
            $sets[] = $key.'='.urlencode($val);
        }

        $this->_options['authorizeUri'] .= '?'.implode('&',$sets);

        return $return;
    }

    // {{{ _handleExpDate()
    /**
    * _handleExpDate
    *
    * @author Joe Stump <joe@joestump.net>
    * @access protected
    */
    function _handleExpDate()
    {
        list($month,$year) = explode($this->_data['cardexp']);
        $this->_data['cardexp'] = $month.substr($year,2,2);
    }
    // }}}
}


class Payment_Process_Result_Paycom extends Payment_Process_Result {

    // {{{ properties
    /**
    * $_statusCodeMap
    *
    * @author Joe Stump <joe@joestump.net>
    * @access protected
    */
    var $_statusCodeMap = array('approved' => PAYMENT_PROCESS_RESULT_APPROVED,
                                'declined' => PAYMENT_PROCESS_RESULT_DECLINED,
                                'error' => PAYMENT_PROCESS_RESULT_OTHER,
                                'test' => PAYMENT_PROCESS_RESULT_OTHER);

    /**
    * Paycom status codes
    *
    * @author Joe Stump <joe@joestump.net> 
    * @access protected
    * @see getStatusText()
    */
    var $_statusCodeMessages = array(
          'approved' => 'This transaction has been approved.',
          'declined' => 'This transaction has been declined.',
          'error' => 'This transaction has encountered an error.',
          'test' => 'This transaction is a test.'
    );

    var $_avsCodeMap = array(
        'A' => PAYMENT_PROCESS_AVS_MISMATCH,
        'N' => PAYMENT_PROCESS_AVS_MISMATCH,
        'R' => PAYMENT_PROCESS_AVS_ERROR,
        'S' => PAYMENT_PROCESS_AVS_ERROR,
        'G' => PAYMENT_PROCESS_AVS_ERROR,
        'U' => PAYMENT_PROCESS_AVS_ERROR,
        'W' => PAYMENT_PROCESS_AVS_MISMATCH,
        'X' => PAYMENT_PROCESS_AVS_MATCH,
        'Y' => PAYMENT_PROCESS_AVS_MATCH,
        'Z' => PAYMENT_PROCESS_AVS_MISMATCH
    );

    var $_avsCodeMessages = array(
        'A' => 'Address matches, ZIP does not',
        'N' => 'Address and zip do not match',
        'R' => 'Retry - System unavailable or timeout',
        'S' => 'Retry - System unavailable or timeout',
        'G' => 'Retry - System unavailable or timeout',
        'U' => 'Address information unavailable (usually foreign issuing bank)',
        'W' => '9-digit zip matches, Address (street) does not',
        'X' => 'Address and 9-digit zip match',
        'Y' => 'Address and 5-digit zip match',
        'Z' => '5-digit zip matches, Address (street) does not'
    );

    var $_cvvCodeMap = array('E' => PAYMENT_PROCESS_CVV_ERROR);

    var $_cvvCodeMessages = array(
        'E' => 'Paycom module does not support CVV checks'
    );
    // }}}

    // {{{ Payment_Process_Response_Paycom($rawResponse)
    /**
    * Payment_Process_Response_Paycom
    *
    * @author Joe Stump <joe@joestump.net>
    * @access public
    * @param string $rawResponse
    * @return void
    * @see Payemnt_Process_Paycom::process()
    */
    function Payment_Process_Response_Paycom($rawResponse)
    {
        $this->Payment_Process_Response($rawResponse);
    }
    // }}}

    // {{{ parse()
    /**
    * parse
    *
    * @author Joe Stump <joe@joestump.net>
    * @access public
    * @return void
    * @see Payemnt_Process_Paycom::process()
    */
    function parse()
    {
        $parts = explode('|',trim($this->_rawResponse));
 
        foreach ($parts as $part) {
            list($var,$val) = explode('=',$part);
            $$var = trim($val);
        }

        $response = explode(',',$response);
 
        $this->code = $status;
        $this->messageCode = $status;
        $this->approvalCode = substr($response[0],1,strlen($response[1]));

        if ($this->getCode() == PAYMENT_PROCESS_RESULT_APPROVED) { 
            $this->avsCode = substr($response[0],7,1);
        } else {
            $this->avsCode = 'R'; // Default to error
        }

        if (isset($auth_idx)) {
            $this->transactionId = $auth_idx;
        } elseif (isset($order_idx)) {
            $this->transactionId = $order_idx;
        }

        $this->cvvCode = 'E'; // Not supported
    }
    // }}}
}

?>
