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
// | Authors: Joe Stump <joe@joestump.net>                                |
// +----------------------------------------------------------------------+
//
// $Id$ 

require_once 'Payment/Process.php';
require_once 'Payment/Process/Common.php';
require_once 'Net/Curl.php';
require_once 'XML/Parser.php';

$GLOBALS['_Payment_Process_LinkPoint'] = array(
    PAYMENT_PROCESS_ACTION_NORMAL   => 'SALE',
    PAYMENT_PROCESS_ACTION_AUTHONLY => 'PREAUTH',
    PAYMENT_PROCESS_ACTION_POSTAUTH => 'POSTAUTH'
);

/**
 * Payment_Process_LinkPoint
 *
 * This is a processor for LinkPoint's merchant payment gateway.
 * (http://www.linkpoint.net/)
 *
 * *** WARNING ***
 * This is BETA code, and has not been fully tested. It is not recommended
 * that you use it in a production envorinment without further testing.
 *
 * @package Payment_Process
 * @author Joe Stump <joe@joestump.net> 
 * @version @version@
 */
class Payment_Process_LinkPoint extends Payment_Process_Common 
{
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
        'login' => 'configfile',
        'action' => 'ordertype',
        'invoiceNumber' => 'oid',
        'customerId' => 'x_cust_id',
        'amount' => 'chargetotal',
        'name' => '',
        'zip' => 'zip',
        // Optional
        'company' => 'company',
        'address' => 'address1',
        'city' => 'city',
        'state' => 'state',
        'country' => 'country',
        'phone' => 'phone',
        'email' => 'email',
        'ip' => 'ip',
    );

    /**
    * $_typeFieldMap
    *
    * @author Joe Stump <joe@joestump.net>
    * @access protected
    */
    var $_typeFieldMap = array(

           'CreditCard' => array(

                    'cardNumber' => 'cardnumber',
                    'cvv' => 'cvm',
                    'expDate' => 'expDate'

           ),

           'eCheck' => array(

                    'routingCode' => 'routing',
                    'accountNumber' => 'account',
                    'type' => 'type',
                    'bankName' => 'bank',
                    'name' => 'name',
                    'driversLicense' => 'dl',
                    'driversLicenseState' => 'dlstate'

           )
    );

    /**
     * Default options for this processor.
     *
     * @see Payment_Process::setOptions()
     * @access private
     */
    var $_defaultOptions = array(
         'host' => 'secure.linkpt.net',
         'port' => '1129', 
         'result' => 'LIVE'
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
    function Payment_Process_LinkPoint($options = false)
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
        if (!strlen($this->_options['keyfile']) || 
            !file_exists($this->_options['keyfile'])) {
            return PEAR::raiseError('Invalid key file');
        }

        if ($this->_options['debug'] === true) {
            echo "----------- DATA -----------\n";
            print_r($this->_data);
            echo "----------- DATA -----------\n";
        }

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

        // Don't die partway through
        PEAR::pushErrorHandling(PEAR_ERROR_RETURN);

        if ($this->_options['debug'] === true) {
            print_r($this->_options);
        }

        $xml = $this->_prepareQueryString();
        if (PEAR::isError($xml)) {
            return $xml;
        }

        $url = 'https://'.$this->_options['host'].':'.$this->_options['port'].
               '/LSGSXML';

        $curl = & new Net_Curl($url);
        if (PEAR::isError($curl)) {
            PEAR::popErrorHandling();
            return $curl;
        }

        $curl->type = 'PUT';
        $curl->fields = $xml;
        $curl->sslCert = $this->_options['keyfile'];
        $curl->userAgent = 'PEAR Payment_Process_LinkPoint 0.1';
        if($this->_options['debug'] === true) {
            echo "------------ CURL FIELDS -------------\n";
            print_r($curl->fields); 
            echo "------------ CURL FIELDS -------------\n";
        }


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

        $response = &Payment_Process_Result::factory($this->_driver,
                                                     $this->_responseBody);

        if(!PEAR::isError($response))
        {
          $response->_request = & $this;
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

        $xml  = '<!-- Payment_Process order -->'."\n";
        $xml .= '<order>'."\n";
        $xml .= '<merchantinfo>'."\n";
        $xml .= '  <configfile>'.$data['configfile'].'</configfile>'."\n";
        $xml .= '  <keyfile>'.$data['keyfile'].'</keyfile>'."\n";
        $xml .= '  <host>'.$data['authorizeUri'].'</host>'."\n";
        $xml .= '  <appname>PEAR Payment_Process</appname>'."\n";
        $xml .= '</merchantinfo>'."\n";
        $xml .= '<orderoptions>'."\n";
        $xml .= '  <ordertype>'.$data['ordertype'].'</ordertype>'."\n";
        $xml .= '  <result>'.$data['result'].'</result>'."\n";
        $xml .= '</orderoptions>'."\n";
        $xml .= '<payment>'."\n";
        $xml .= '  <subtotal>'.$data['chargetotal'].'</subtotal>'."\n";
        $xml .= '  <tax>0.00</tax>'."\n";
        $xml .= '  <shipping>0.00</shipping>'."\n";
        $xml .= '  <chargetotal>'.$data['chargetotal'].'</chargetotal>'."\n";
        $xml .= '</payment>'."\n";

        // Set payment method to eCheck if our payment type is eCheck.
        // Default is Credit Card.
        $data['x_method'] = 'CC';
        switch ($this->_payment->getType()) 
        {
            case 'eCheck':
                return PEAR::raiseError('eCheck not currently supported',
                                        PAYMENT_PROCESS_ERROR_NOTIMPLEMENTED);

                $xml .= '<telecheck>'."\n";
                $xml .= '  <routing></routing>'."\n";
                $xml .= '  <account></account>'."\n";
                $xml .= '  <checknumber></checknumber>'."\n";
                $xml .= '  <bankname></bankname>'."\n";
                $xml .= '  <bankstate></bankstate>'."\n";
                $xml .= '  <dl></dl>'."\n";
                $xml .= '  <dlstate></dlstate>'."\n";
                $xml .= '  <accounttype>pc|ps|bc|bs</accounttype>'."\n";
                $xml .= '<telecheck>'."\n";
                break;
            case 'CreditCard':
                $xml .= '<creditcard>'."\n";
                $xml .= '  <cardnumber>'.$data['cardnumber'].'</cardnumber>'."\n";
                list($month,$year) = explode('/',$data['expDate']);
                if (strlen($year) == 4) {
                    $year = substr($year,2);
                }

                $xml .= '  <cardexpmonth>'.$month.'</cardexpmonth>'."\n";
                $xml .= '  <cardexpyear>'.$year.'</cardexpyear>'."\n";
                if (strlen($data['cvm'])) {
                    $xml .= '  <cvmvalue>'.$data['cvm'].'</cvmvalue>'."\n";
                    $xml .= '  <cvmindicator>provided</cvmindicator>'."\n";
                }
                $xml .= '</creditcard>'."\n";
        }

        if (strlen($this->_payment->name)) {
            $xml .= '<billing>'."\n";
            $xml .= '  <userid>'.$this->_payment->customerId.'</userid>'."\n";
            $xml .= '  <name>'.$this->_payment->name.'</name>'."\n";
            $xml .= '  <company>'.$this->_payment->company.'</company>'."\n";
            $xml .= '  <address1>'.$this->_payment->address.'</address1>'."\n";
            $xml .= '  <city>'.$this->_payment->city.'</city>'."\n";
            $xml .= '  <state>'.$this->_payment->state.'</state>'."\n";
            $xml .= '  <zip>'.$this->_payment->zip.'</zip>'."\n";
            $xml .= '  <country>'.$this->_payment->country.'</country>'."\n";
            $xml .= '  <phone>'.$this->_payment->phone.'</phone>'."\n";
            $xml .= '  <email>'.$this->_payment->email.'</email>'."\n";
            $xml .= '  <addrnum>'.$this->_payment->address.'</addrnum>'."\n";
            $xml .= '</billing>'."\n";
        }

        $xml .= '</order>'."\n";

        if($this->_options['debug'] === true) {
            echo "--------- PREPARE QS DATA -----------\n";
            print_r($data);
            echo "\n".$xml."\n";
            echo "--------- PREPARE QS DATA -----------\n";
        }

        return $xml;
    }
}

class Payment_Process_Result_LinkPoint extends Payment_Process_Result 
{

    var $_statusCodeMap = array('APPROVED' => PAYMENT_PROCESS_RESULT_APPROVED,
                                'DECLINED' => PAYMENT_PROCESS_RESULT_DECLINED,
                                'FRAUD' => PAYMENT_PROCESS_RESULT_FRAUD);

    /**
     * LinkPoint status codes
     *
     * This array holds many of the common response codes. There are over 200
     * response codes - so check the LinkPoint manual if you get a status
     * code that does not match (see "Response Reason Codes & Response 
     * Reason Text" in the AIM manual).
     *
     * @see getStatusText()
     * @access private
     */
    var $_statusCodeMessages = array(
        'APPROVED' => 'This transaction has been approved.',
        'DECLINED' => 'This transaction has been declined.',
        'FRAUD' => 'This transaction has been determined to be fraud.');

    var $_avsCodeMap = array(
        'YY' => PAYMENT_PROCESS_AVS_MATCH,
        'YN' => PAYMENT_PROCESS_AVS_MISMATCH,
        'YX' => PAYMENT_PROCESS_AVS_ERROR,
        'NY' => PAYMENT_PROCESS_AVS_MISMATCH,
        'XY' => PAYMENT_PROCESS_AVS_MISMATCH,
        'NN' => PAYMENT_PROCESS_AVS_MISMATCH,
        'NX' => PAYMENT_PROCESS_AVS_MISMATCH,
        'XN' => PAYMENT_PROCESS_AVS_MISMATCH,
        'XX' => PAYMENT_PROCESS_AVS_ERROR
    );

    var $_avsCodeMessages = array(
        'YY' => 'Address matches, zip code matches',
        'YN' => 'Address matches, zip code does not match',
        'YX' => 'Address matches, zip code comparison not available',
        'NY' => 'Address does not match, zip code matches',
        'XY' => 'Address comparison not available, zip code matches',
        'NN' => 'Address comparison does not match, zip code does not match',
        'NX' => 'Address does not match, zip code comparison not available',
        'XN' => 'Address comparison not available, zip code does not match',
        'XX' => 'Address comparison not available, zip code comparison not available'
    );

    var $_cvvCodeMap = array('M' => PAYMENT_PROCESS_CVV_MATCH,
                             'N' => PAYMENT_PROCESS_CVV_MISMATCH,
                             'P' => PAYMENT_PROCESS_CVV_ERROR,
                             'S' => PAYMENT_PROCESS_CVV_ERROR,
                             'U' => PAYMENT_PROCESS_CVV_ERROR,
                             'X' => PAYMENT_PROCESS_CVV_ERROR
    );

    var $_cvvCodeMessages = array(
        'M' => 'Card Code Match',
        'N' => 'Card code does not match',
        'P' => 'Not processed',
        'S' => 'Merchant has indicated that the card code is not present on the card',
        'U' => 'Issuer is not certified and/or has not proivded encryption keys',
        'X' => 'No response from the credit card association was received'
    );

    var $_fieldMap = array('r_approved'  => 'code',
                           'r_error'  => 'message',
                           'r_code'  => 'approvalCode',
                           'r_approved'  => 'messageCode',
                           'r_ordernum'  => 'transactionId'
    );

    function Payment_Process_Response_LinkPoint($rawResponse) 
    {
        $this->Payment_Process_Response($rawResponse);
    }

    function parse()
    {
        $xml = & new XML_Parser_LinkPoint();
        $xml->parseString('<response>'.$this->_rawResponse.'</response>');
        if (is_array($xml->response) && count($xml->response)) {
            $this->avsCode = substr($xml->response['r_avs'],0,2);
            $this->cvvCode = substr($xml->response['r_avs'],2,1);
            $this->customerId = $this->_request->customerId;
            $this->invoiceNumber = $this->_request->invoiceNumber;
            print_r($xml->response);         
            $this->_mapFields($xml->response);

            // switch to DECLINED since a duplicate isn't *really* fraud
            if(eregi('duplicate',$this->message)) {
                $this->messageCode = 'DECLINED';
            } 
        }
    }
}

/**
 * XML_Parser_LinkPoint
 * 
 * XML Parser for the LinkPoint response
 *
 * @author Joe Stump <joe@joestump.net>
 * @package Payment_Process
 */
class XML_Parser_LinkPoint extends XML_Parser
{
    var $response = array();
    var $tag = null;

    function XML_Parser_LinkPoint()
    {
        $this->XML_Parser();
    }

    function startHandler($xp, $elem, &$attribs) 
    {
        $this->tag = $elem;
    }

    function endHandler($xp, $elem)
    {

    }

    function defaultHandler($xp,$data) 
    {
        $this->response[strtolower($this->tag)] = $data;    
    }
}

?>
