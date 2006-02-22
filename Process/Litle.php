<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * Litle processor
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to the New BSD license, That is bundled
 * with this package in the file LICENSE, and is available through
 * the world-wide-web at
 * http://www.opensource.org/licenses/bsd-license.php
 * If you did not receive a copy of the new BSDlicense and are unable
 * to obtain it through the world-wide-web, please send a note to
 * jausions@php.net so we can mail you a copy immediately.
 *
 * @category   Payment
 * @package    Payment_Process_Litle
 * @author     Philippe Jausions <Philippe.Jausions@11abacus.com>
 * @copyright  2006 The PHP Group
 * @license    http://www.opensource.org/licenses/bsd-license.php  New BSD License
 * @version    CVS: $Revision$
 * @link       http://pear.php.net/package/Payment_Process
 * @link       http://www.litle.com/
 */

/**
 * Required includes
 */
require_once 'Payment/Process.php';
require_once 'Payment/Process/Common.php';
require_once 'Net/Curl.php';
require_once 'XML/Parser/Simple.php';

/**
 * Global (private) configuration
 */
$GLOBALS['_Payment_Process_Litle'] = array(
    PAYMENT_PROCESS_ACTION_NORMAL   => 'sale',
    PAYMENT_PROCESS_ACTION_AUTHONLY => 'authorization',
    PAYMENT_PROCESS_ACTION_POSTAUTH => 'capture',
    PAYMENT_PROCESS_ACTION_CREDIT   => 'credit',
    PAYMENT_PROCESS_ACTION_VOID     => 'void',
);

/**
 * Specifications version
 */
define('PAYMENT_PROCESS_LITLE_VERSION', '1.0');


/**
 * Litle processor
 *
 * This is a processor for Litle's merchant payment gateway.
 * (http://www.litle.com/)
 *
 * *** WARNING ***
 * This is BETA code, and has not been fully tested (especially the auth-only,
 * post-auth, credit and void actions.) It is not recommended
 * that you use it in a production environment without further testing.
 *
 * @package Payment_Process_Litle
 * @author Philippe Jausions <Philippe.Jausions@11abacus.com>
 * @version @version@
 */
class Payment_Process_Litle extends Payment_Process_Common
{
    /**
     * Front-end -> back-end field map.
     *
     * This array contains the mapping from front-end fields (defined in
     * the Payment_Process class) to the tag names Litle requires.
     *
     * @see _prepare()
     * @access private
     */
    var $_fieldMap = array(
        // Required
        'login'             => 'user',
        'password'          => 'password',
        'action'            => 'action',
        'invoiceNumber'     => 'orderId',
        //PostAuth
        'transactionId'     => 'litleTxnId',
        // Optional
        'amount'            => 'amount',
        'transactionSource' => 'orderSource',
        'customerId'        => 'customerId',
        'description'       => 'descriptor',
        'address'           => 'addressLine1',
        'city'              => 'city',
        'state'             => 'state',
        'zip'               => 'zip',
        'country'           => 'country',
        'email'             => 'email',
        'phone'             => 'phone',
    );

    /**
     * $_typeFieldMap
     *
     * @access protected
     */
    var $_typeFieldMap = array(

        'CreditCard' => array(
            'name'       => 'name',
            'type'       => 'type',
            'cardNumber' => 'number',
            'cvv'        => 'cardValidationNum',
            'expDate'    => 'expDate'
        ),
    );

    /**
     * Default options for this processor.
     *
     * @see Payment_Process::setOptions()
     * @access private
     */
    var $_defaultOptions = array(
         'host'        => 'cert.litle.com',
         'port'        => '443',
         'result'      => '',
         'reportGroup' => 'Orders',
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
        $this->_driver = 'Litle';

        $this->_makeRequired('login', 'password');
    }

    /**
     * Payment_Process_Litle
     *

     * @access public
     * @param array $options
     * @return void
     */
    function Payment_Process_Litle($options = false)
    {
        $this->__construct($options);
    }

    /**
     * Handle transaction source.
     *
     * @access private
     */
    function _handleTransactionSource()
    {
        $specific = $this->_fieldMap['transactionSource'];
        switch ($this->transactionSource) {
            case PAYMENT_PROCESS_SOURCE_ONLINE:
                $this->_data[$specific] = 'ecommerce';
                break;
            case PAYMENT_PROCESS_SOURCE_POS:
                $this->_data[$specific] = 'retail';
                break;
            case PAYMENT_PROCESS_SOURCE_MAIL:
                $this->_data[$specific] = 'mailorder';
                break;
            case PAYMENT_PROCESS_SOURCE_PHONE:
                $this->_data[$specific] = 'telephone';
                break;
        }
    }

    /**
     * Handle amount ($5.00 => 500)
     *
     * @access protected
     */
    function _handleAmount()
    {
        $this->_data[$this->_fieldMap['amount']] = intval($this->amount * 100);
    }

    /**
     * Handle credit card type
     *
     * @return boolean|PEAR_Error TRUE or PEAR_Error on error
     * @access protected
     */
    function _handleType()
    {
        $specific = $this->_typeFieldMap['CreditCard']['type'];
        switch ($this->_payment->type) {
            case PAYMENT_PROCESS_CC_MASTERCARD:
                $this->_data[$specific] = 'MC';
                break;
            case PAYMENT_PROCESS_CC_VISA:
                $this->_data[$specific] = 'VI';
                break;
            case PAYMENT_PROCESS_CC_AMEX:
                $this->_data[$specific] = 'AX';
                break;
            case PAYMENT_PROCESS_CC_DISCOVER:
                $this->_data[$specific] = 'DI';
                break;
            case PAYMENT_PROCESS_CC_DINERS:
                $this->_data[$specific] = 'DC';
                break;
            default:
                return PEAR::raiseError('Unsupported card type');
        }
        return true;
    }

    /**
     * Handle card expiration date.
     *
     * Date needs to be in the MMYY format, but is received as MM/YYYY
     *
     * @access protected
     */
    function _handleExpDate()
    {
        $this->_data[$this->_typeFieldMap['CreditCard']['expDate']]
            = substr($this->_payment->expDate, 0, 2) . substr($this->_payment->expDate, -2);
    }

    /**
     * Validates
     *
     * @return boolean|PEAR_Error TRUE if validation succeeded, PEAR Error if it failed.
     */
    function validate()
    {
        switch ($this->action) {
            case PAYMENT_PROCESS_ACTION_AUTHONLY:
            case PAYMENT_PROCESS_ACTION_NORMAL:
                $this->_makeRequired('transactionSource', 'amount', 'invoiceNumber');
                $this->_makeOptional('transactionId');
                break;
            case PAYMENT_PROCESS_ACTION_POSTAUTH:
                $this->_makeRequired('transactionId', 'amount');
                $this->_makeOptional('transactionSource', 'invoiceNumber');
                break;
            case PAYMENT_PROCESS_ACTION_CREDIT:
                /**
                 * @todo support the 2 versions of credit requests
                 */
                $this->_makeRequired('transactionId', 'amount');
                $this->_makeOptional('transactionSource', 'invoiceNumber');
                break;
            case PAYMENT_PROCESS_ACTION_VOID:
                $this->_makeRequired('transactionId');
                $this->_makeOptional('amount', 'transactionSource', 'invoiceNumber');
                break;
        }

        return parent::validate();
    }

    /**
     * Process the transaction.
     *
     * @return mixed Payment_Process_Result on success, PEAR_Error on failure
     * @access public
     */
    function &process()
    {
        if (!strlen($this->_options['merchantId'])) {
            return PEAR::raiseError('Missing merchant Id option value',
                                        PAYMENT_PROCESS_ERROR_INCOMPLETE);
        }

        if (!strlen($this->_options['reportGroup'])) {
            return PEAR::raiseError('Missing report group option value',
                                        PAYMENT_PROCESS_ERROR_INCOMPLETE);
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

        $xml = $this->_prepareQueryString();
        if (PEAR::isError($xml)) {
            return $xml;
        }

        $url = 'https://'.$this->_options['host'].':'.$this->_options['port'].
               '/vap/communicator/online';

        //echo '<p>HERE::' . basename(__FILE__) . '(' . __LINE__ . ')</p>';
        //echo '<pre>' . nl2br(htmlspecialchars($xml)) . '</pre>';

        $curl =& new Net_Curl($url);
        $result = $curl->create();
        if (PEAR::isError($result)) {
            return $result;
        }

        $curl->type   = 'POST';
        $curl->fields = $xml;

        $curl->userAgent = 'PEAR Payment_Process_Litle 0.1';

        $result = &$curl->execute();
        if (PEAR::isError($result)) {
            return PEAR::raiseError('cURL error: '.$result->getMessage());
        } else {
            $curl->close();
        }

        $this->_responseBody = trim($result);
        $this->_processed = true;

        //echo '<p>HERE::' . basename(__FILE__) . '(' . __LINE__ . ')</p>';
        //echo '<pre>' . nl2br(htmlspecialchars($this->_responseBody)) . '</pre>';

        // Restore error handling
        PEAR::popErrorHandling();

        $response = &Payment_Process_Result::factory($this->_driver,
                                                     $this->_responseBody,
                                                     &$this);
        if (!PEAR::isError($response)) {
            $result = $response->parse();
            if (PEAR::isError($result)) {
                return $result;
            }
        }

        return $response;
    }

    /**
     * XML escapes string
     *
     * @param string $text
     * @return string
     * @param protected
     */
    function _xml_escape($text)
    {
        return htmlspecialchars($text, ENT_NOQUOTES);
    }

    /**
     * Prepare the request (POST)
     *
     * @return string The query string
     * @access private
     */
    function _prepareQueryString()
    {
        $data = array_merge($this->_options, $this->_data);

        $xml  = '<litleOnlineRequest version="' . PAYMENT_PROCESS_LITLE_VERSION . '" '
                . 'xmlns="http://www.litle.com/schema/online" merchantId="'
                . htmlspecialchars($this->_options['merchantId'])
                . '">'."\n"
              . '<authentication>'."\n"
              . '  <user>' . $this->_xml_escape($data['user']) . '</user>'."\n"
              . '  <password>' . $this->_xml_escape($data['password'])
                . '</password>'."\n"
              . '</authentication>'."\n";

        $addBillTo = false;
        $addCard   = false;
        switch ($this->action) {
            case PAYMENT_PROCESS_ACTION_AUTHONLY:
                $tag = 'authorization';
                $addBillTo = true;
                $addCard   = true;
                break;
            case PAYMENT_PROCESS_ACTION_NORMAL:
                $tag = 'sale';
                $addBillTo = true;
                $addCard   = true;
                break;
            case PAYMENT_PROCESS_ACTION_POSTAUTH:
                $tag = 'capture';
                break;
            case PAYMENT_PROCESS_ACTION_CREDIT:
                $tag = 'credit';
                break;
            case PAYMENT_PROCESS_ACTION_VOID:
                $tag = 'void';
                break;
            default:
                return PEAR::raiseError('Unsupported or invalid action',
                                            PAYMENT_PROCESS_ERROR_INVALID);
        }

        // Generate a merchant-side transaction ID (must be unique for all transactions)
        // For portability across all back-end processor, we generate one here.
        // Eventually, the user of the package should be able to overwrite this value.
        // Litle specifications limit the ID to 25 chars.
        $id = gmmktime() . '-' . $data['orderId'] . '-' . md5(mt_rand());
        $id = substr($id, 0, 25);

        $xml .= '<' . $tag . ' id="' . htmlspecialchars($id) . '" reportGroup="'
                . htmlspecialchars($this->_options['reportGroup']) . '"';
        if (strlen(trim($data['customerId']))) {
            $xml .= ' customerId="' . htmlspecialchars($data['customerId']) . '"';
        }
        $xml .= '>'."\n";

        if ($this->isRequired('invoiceNumber')) {
            $xml .= '<orderId>' . $data['orderId'] . '</orderId>'."\n";
        }

        if ($this->isRequired('amount')) {
            $xml .= '<amount>' . $data['amount'] . '</amount>'."\n";
        }

        if ($this->isRequired('transactionId')) {
            $xml .= '<litleTxnId>' . $data['litleTxnId'] . '</litleTxnId>'."\n";
        }

        if ($this->isRequired('transactionSource')) {
            $xml .= '<orderSource>' . $data['orderSource'] . '</orderSource>'."\n";
        }

        if ($addBillTo) {
            $xml .= $this->_prepareBillToAddress();
        }
        if ($addCard) {
            $xml .= $this->_prepareCard($data);
        }

        return $xml . '</' . $tag . '></litleOnlineRequest>'."\n";
    }

    /**
     * Prepare the portion of XML for billing address
     *
     * @return string The XML sub-string
     * @access private
     */
    function _prepareBillToAddress()
    {
        $xml = '';
        if (isset($this->_payment->name)) {
            $xml .= '  <name>'.$this->_xml_escape($this->_payment->name).'</name>'."\n";
        }
        if (isset($this->_payment->address)) {
            $xml .= '  <addressLine1>'.$this->_xml_escape($this->_payment->address)
                     .'</addressLine1>'."\n";
        }
        if (isset($this->_payment->city)) {
            $xml .= '  <city>'.$this->_xml_escape($this->_payment->city).'</city>'
                    ."\n";
        }
        if (isset($this->_payment->state)) {
            $xml .= '  <state>'.$this->_xml_escape($this->_payment->state).'</state>'
                    ."\n";
        }
        if (isset($this->_payment->zip)) {
            $xml .= '  <zip>'.$this->_xml_escape($this->_payment->zip).'</zip>'
                    ."\n";
        }
        if (isset($this->_payment->country)) {
            $xml .= '  <country>'.$this->_payment->country.'</country>'
                    ."\n";
        }
        if (isset($this->_payment->email)) {
            $xml .= '  <email>'.$this->_xml_escape($this->_payment->email).'</email>'
                    ."\n";
        }
        if (isset($this->_payment->phone)) {
            $xml .= '  <phone>'.$this->_xml_escape($this->_payment->phone).'</phone>'
                    ."\n";
        }
        return ($xml) ? ('<billToAddress>'."\n" . $xml . '</billToAddress>'."\n") : '';
    }

    /**
     * Prepare the portion of XML for credit card data
     *
     * @param  array $data
     * @return string The XML sub-string
     * @access private
     */
    function _prepareCard($data)
    {
        $xml = '<card>'."\n"
             . '  <type>'.$data['type'].'</type>'."\n"
             . '  <number>'.$data['number'].'</number>'."\n"
             . '  <expDate>'.$data['expDate'].'</expDate>'."\n";
        if (strlen($data['cardValidationNum'])) {
            $xml .= '  <cardValidationNum>'.$data['cardValidationNum']
                    . '</cardValidationNum>'."\n";
        }
        return $xml . '</card>'."\n";
    }

}

/**
 * Payment_Process_Result_Litle
 *
 * Litle result class
 *
 * @package Payment_Process_Litle
 */
class Payment_Process_Result_Litle extends Payment_Process_Result
{
    /**
     * Maps Litle-specific result codes to package generic overall-result codes
     *
     * @var array
     * @access private
     */
    var $_statusCodeMap = array(
        'DUPLICATE' => PAYMENT_PROCESS_RESULT_DUPLICATE,

        '000' => PAYMENT_PROCESS_RESULT_APPROVED,
        '100' => PAYMENT_PROCESS_RESULT_OTHER,
        '101' => PAYMENT_PROCESS_RESULT_OTHER,
        '102' => PAYMENT_PROCESS_RESULT_OTHER,
        '110' => PAYMENT_PROCESS_RESULT_DECLINED,
        '120' => PAYMENT_PROCESS_RESULT_DECLINED,
        '121' => PAYMENT_PROCESS_RESULT_DECLINED,
        '122' => PAYMENT_PROCESS_RESULT_DECLINED,
        '123' => PAYMENT_PROCESS_RESULT_DECLINED,
        '124' => PAYMENT_PROCESS_RESULT_DECLINED,
        '125' => PAYMENT_PROCESS_RESULT_DECLINED,
        '126' => PAYMENT_PROCESS_RESULT_DECLINED,
        '127' => PAYMENT_PROCESS_RESULT_DECLINED,
        '130' => PAYMENT_PROCESS_RESULT_DECLINED,
        '140' => PAYMENT_PROCESS_RESULT_DECLINED,
        '301' => PAYMENT_PROCESS_RESULT_DECLINED,
        '302' => PAYMENT_PROCESS_RESULT_DECLINED,
        '303' => PAYMENT_PROCESS_RESULT_FRAUD,
        '304' => PAYMENT_PROCESS_RESULT_FRAUD,
        '305' => PAYMENT_PROCESS_RESULT_DECLINED,
        '307' => PAYMENT_PROCESS_RESULT_DECLINED,
        '308' => PAYMENT_PROCESS_RESULT_DECLINED,
        '320' => PAYMENT_PROCESS_RESULT_DECLINED,
        '321' => PAYMENT_PROCESS_RESULT_OTHER,
        '322' => PAYMENT_PROCESS_RESULT_OTHER,
        '323' => PAYMENT_PROCESS_RESULT_OTHER,
        '324' => PAYMENT_PROCESS_RESULT_DECLINED,
        '325' => PAYMENT_PROCESS_RESULT_OTHER,
        '326' => PAYMENT_PROCESS_RESULT_FRAUD,
        '327' => PAYMENT_PROCESS_RESULT_FRAUD,
        '328' => PAYMENT_PROCESS_RESULT_DECLINED,
        '330' => PAYMENT_PROCESS_RESULT_OTHER,
        '340' => PAYMENT_PROCESS_RESULT_OTHER,
        '346' => PAYMENT_PROCESS_RESULT_OTHER,
        '349' => PAYMENT_PROCESS_RESULT_FRAUD,
        '350' => PAYMENT_PROCESS_RESULT_DECLINED,
        '351' => PAYMENT_PROCESS_RESULT_FRAUD,
        '352' => PAYMENT_PROCESS_RESULT_DECLINED,
        '353' => PAYMENT_PROCESS_RESULT_DECLINED,
        '354' => PAYMENT_PROCESS_RESULT_DECLINED,
        '355' => PAYMENT_PROCESS_RESULT_DECLINED,
        '360' => PAYMENT_PROCESS_RESULT_OTHER,
        '361' => PAYMENT_PROCESS_RESULT_DUPLICATE,
        '362' => PAYMENT_PROCESS_RESULT_DUPLICATE,
        '370' => PAYMENT_PROCESS_RESULT_OTHER,
    );

    /**
     * Litle status codes
     *
     * @see getStatusText()
     * @access private
     */
    var $_statusCodeMessages = array(
        '000' => 'Approved',
        '100' => 'Processing network unavailable',
        '101' => 'Issuer unavailable',
        '102' => 'Resubmit transaction',
        '110' => 'Insufficient funds',
        '120' => 'Call issuer',
        '121' => 'Call American Express',
        '122' => 'Call Diners Club',
        '123' => 'Call Discover',
        '124' => 'Call JBS',
        '125' => 'Call Visa/MasterCard',
        '126' => 'Call issuer - Update cardholder data',
        '127' => 'Exceeds approval amount limit',
        '130' => 'Call issuer', // Call (number...) To be replaced by actual message
        '140' => 'Update cardholder data',
        '301' => 'Invalid account number',
        '302' => 'Account number does not match payment type',
        '303' => 'Pick up card',
        '304' => 'Lost/stolen card',
        '305' => 'Expired card',
        '307' => 'Restricted card',
        '308' => 'Restricted card - Chargeback',
        '320' => 'Invalid expiration date',
        '321' => 'Invalid merchant',
        '322' => 'Invalid transaction',
        '323' => 'No such issuer',
        '324' => 'Invalid PIN',
        '325' => 'Transaction not allowed at terminal',
        '326' => 'Exceeds number of PIN entries',
        '327' => 'Cardholder transaction not permitted',
        '328' => 'Cardholder requested that recurring or installment payment be stopped',
        '330' => 'Invalid payment type',
        '340' => 'Invalid amount',
        '346' => 'Invalid billing descriptor prefix',
        '349' => 'Do not honor',
        '350' => 'Generic decline',
        '351' => 'Decline - Request positive ID',
        '352' => 'Decline card code fail',
        '353' => 'Merchant requested decline due to address verification result',
        '354' => '3DS transaction not supported for merchant',
        '355' => 'Failed velocity check',
        '360' => 'No transaction found with specified litleTxnId',
        '361' => 'Transaction found but already referenced by another deposit',
        '362' => 'Transaction not voided - Already settled',
        '370' => 'Internal system error - Call Litle & Co.',
    );

    /**
     * Maps Litle-specific AVS result codes to package generic AVS-result codes
     *
     * @var array
     * @access private
     */
    var $_avsCodeMap = array(
        '00' => PAYMENT_PROCESS_AVS_MATCH,
        '01' => PAYMENT_PROCESS_AVS_MATCH,
        '02' => PAYMENT_PROCESS_AVS_MATCH,
        '10' => PAYMENT_PROCESS_AVS_MISMATCH,
        '11' => PAYMENT_PROCESS_AVS_MISMATCH,
        '12' => PAYMENT_PROCESS_AVS_MISMATCH,
        '13' => PAYMENT_PROCESS_AVS_MISMATCH,
        '14' => PAYMENT_PROCESS_AVS_MISMATCH,
        '20' => PAYMENT_PROCESS_AVS_MISMATCH,
        '30' => PAYMENT_PROCESS_AVS_ERROR,
        '31' => PAYMENT_PROCESS_AVS_ERROR,
        '32' => PAYMENT_PROCESS_AVS_NOAPPLY,
        '33' => PAYMENT_PROCESS_AVS_ERROR,
        '34' => PAYMENT_PROCESS_AVS_NOAPPLY,
        '40' => PAYMENT_PROCESS_AVS_ERROR,
    );

    var $_avsCodeMessages = array(
        '00' => '5-digit ZIP code and address match',
        '01' => '9-digit ZIP code and address match',
        '02' => 'Postal code and address match',
        '10' => '5-digit ZIP code matches, address does not match',
        '11' => '9-digit ZIP code matches, address does not match',
        '12' => 'ZIP code does not match, address matches',
        '13' => 'Postal code does not match, address matches',
        '14' => 'Postal code matches, address not verified',
        '20' => 'Neither postal code nor address match',
        '30' => 'Address verification service not supported by issuer',
        '31' => 'Address verification service not available',
        '32' => 'Address information unavailable',
        '33' => 'General error',
        '34' => 'Address verification not performed',
        '40' => 'Address failed Litle & Co. edit checks',
    );

    /**
     * Maps Litle-specific CVV result codes to package generic CVV-result codes
     *
     * @var array
     * @access private
     */
    var $_cvvCodeMap = array('M' => PAYMENT_PROCESS_CVV_MATCH,
                             'N' => PAYMENT_PROCESS_CVV_MISMATCH,
                             'P' => PAYMENT_PROCESS_CVV_NOAPPLY,
                             'S' => PAYMENT_PROCESS_CVV_ERROR,
                             'U' => PAYMENT_PROCESS_CVV_NOAPPLY,
                             ''  => PAYMENT_PROCESS_CVV_ERROR
    );

    var $_cvvCodeMessages = array(
        'M' => 'Card code matches',
        'N' => 'Card code does not match',
        'P' => 'Not processed',
        'S' => 'Card code should be on the card but the merchant has indicated it is not present',
        'U' => 'Issuer is not certified for card code processing',
        ''  => 'Check was not done for an unspecified reason'
    );

    var $_fieldMap = array('response'             => 'code',
                           'message'              => 'message',
                           'authCode'             => 'approvalCode',
                           'litleTxnId'           => 'transactionId',
                           'avsResult'            => 'avsCode',
                           'cardValidationResult' => 'cvvCode'
    );

    /**
     * Parses response
     *
     * @return boolean|PEAR_Error TRUE on successfull parsing, PEAR_Error on failure
     * @access public
     */
    function parse()
    {
        $xmlParser =& new Payment_Processor_Litle_XML_Parser();
        $xmlParser->folding = false;
        $result = $xmlParser->parseString($this->_rawResponse, true);

        //$result = $xmlParser->parse();
        $response = $xmlParser->response;
        $xmlParser->free();
        if (PEAR::isError($result)) {
            return $result;
        }

        // Check the gateway sent back an understandable response
        // and accepted the request
        if (!is_array($response)
            || !array_key_exists('litleOnlineResponse', $response)
            || !is_array($response['litleOnlineResponse'])
            || !array_key_exists('response', $response['litleOnlineResponse'])) {
            return PEAR::raiseError('Malformed response from gateway');

        } elseif ($response['litleOnlineResponse']['response'] == '1') {
            return PEAR::raiseError($response['litleOnlineResponse']['message']);

        } elseif ($response['litleOnlineResponse']['response'] != '0') {
            return PEAR::raiseError('Malformed response from gateway');
        }

        $this->customerId = $this->_request->customerId;
        $this->invoiceNumber = $this->_request->invoiceNumber;
        $this->_mapFields($response);

        $this->messageCode = $this->code;

        // Adjust result code/message if needed based on raw code
        switch ($this->messageCode) {
            case 130:
                // Get the message with the full phone number
                $this->_statusCodeMessages[$this->messageCode] = $response['message'];
                break;
        }

        // Catch "duplicate" flag
        switch ($this->_request->action) {
            case PAYMENT_PROCESS_ACTION_AUTHONLY:
                $tag = 'authorization';
                break;
            case PAYMENT_PROCESS_ACTION_NORMAL:
                $tag = 'sale';
                break;
            case PAYMENT_PROCESS_ACTION_POSTAUTH:
                $tag = 'capture';
                break;
            case PAYMENT_PROCESS_ACTION_CREDIT:
                $tag = 'credit';
                break;
            case PAYMENT_PROCESS_ACTION_VOID:
                $tag = 'void';
                break;
            default:
                return PEAR::raiseError('Unsupported or invalid action',
                                            PAYMENT_PROCESS_ERROR_INVALID);
        }
        if (isset($response[$tag . 'Response']['duplicate'])) {
            $this->code = 'DUPLICATE';
        }

        return true;
    }
}

/**
 * Payment_Processor_Litle_XML_Parser
 *
 * XML Parser for the Litle response
 *
 * @package Payment_Process_Litle
 */
class Payment_Processor_Litle_XML_Parser extends XML_Parser_Simple
{
    /**
     * Holds raw response as an array
     *
     * @var array $response
     * @access public
     */
    var $response = array();

    /**
     * Payment_Processor_Litle_XML_Parser
     *
     * @access public
     * @see XML_Parser_Simple
     */
    function Payment_Processor_Litle_XML_Parser()
    {
        $this->XML_Parser_Simple();
    }

    /**
     * Handles the tag once completed
     *
     * @param string $name tag name
     * @param array  $attribs attributes of the tag
     * @param string $data CDATA
     * @access public
     */
    function handleElement($name, $attribs, $data)
    {
        if (is_array($attribs) && count($attribs)) {
            $this->response[$name] = $attribs;

        } elseif (strlen(trim($data))) {
            $this->response[$name] = $data;
        }
    }
}

?>