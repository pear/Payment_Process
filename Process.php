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
// |          Ondrej Jombik <nepto@pobox.sk>                              |
// +----------------------------------------------------------------------+
//
// $Id$

require_once 'PEAR.php';
require_once 'Validate.php';

// Error codes
define('PAYMENT_PROCESS_ERROR_NOTIMPLEMENTED', -100);
define('PAYMENT_PROCESS_ERROR_NOFIELD', -101);
define('PAYMENT_PROCESS_ERROR_NOPROCESSOR', -102);
define('PAYMENT_PROCESS_ERROR_INCOMPLETE', -1);
define('PAYMENT_PROCESS_ERROR_INVAILD', -2);

// Transaction types
define('PAYMENT_PROCESS_TYPE_VISA', 100);
define('PAYMENT_PROCESS_TYPE_MASTERCARD', 101);
define('PAYMENT_PROCESS_TYPE_AMEX', 102);
define('PAYMENT_PROCESS_TYPE_DISCOVER', 103);
define('PAYMENT_PROCESS_TYPE_CHECK', 104);

// Transaction actions
// A normal transaction
define('PAYMENT_PROCESS_ACTION_NORMAL', 200);
// Authorize only. No funds are transferred.
define('PAYMENT_PROCESS_ACTION_AUTHONLY', 201);
// Credit funds back from a previously-charged transaction.
define('PAYMENT_PROCESS_ACTION_CREDIT', 202);
// Post-authorize an AUTHONLY transaction.
define('PAYMENT_PROCESS_ACTION_POSTAUTH', 203);

// Transaction sources
define('PAYMENT_PROCESS_SOURCE_POS', 300);
define('PAYMENT_PROCESS_SOURCE_ONLINE', 301);

// Results
define('PAYMENT_PROCESS_RESULT_APPROVED', 400);
define('PAYMENT_PROCESS_RESULT_DECLINED', 401);
define('PAYMENT_PROCESS_RESULT_OTHER', 402);

/**
 * Payment_Process
 *
 * @author Ian Eure <ieure@php.net>
 * @package Payment_Process
 * @category Payment
 * @version @version@
 */
class Payment_Process {

    /**
     * Options.
     *
     * @see setOptions()
     * @access private;
     */
    var $_options = '';

    /**
     * Transaction type.
     *
     * This should be set to one of the PAYMENT_PROCESS_TYPE_* constants.
     */
    var $type = '';

    /**
     * Your login name to use for authentication to the online processor.
     */
    var $login = '';

    /**
     * Your password to use for authentication to the online processor.
     */
    var $password = '';

    /**
     * Processing action.
     *
     * This should be set to one of the PAYMENT_PROCESS_ACTION_* constants.
     */
    var $action = '';

    /**
     * A description of the transaction (used by some processors to send
     * information to the client, normally not a required field).
     */
    var $description = '';

    /**
     * The transaction amount.
     */
    var $amount = '';

    /**
     * An invoice number.
     */
    var $invoiceNumber = '';

    /**
     * Customer identifier
     */
    var $customerId = '';

    /**
     * The customer's name.
     */
    var $name = '';

    /**
     * The customer's address.
     */
    var $address = '';

    /**
     * The customer's city.
     */
    var $city = '';

    /**
     * The customer's state.
     */
    var $state = '';

    /**
     * The customer's ZIP code.
     */
    var $zip = '';

    /**
     * The customer's country.
     */
    var $country = '';

    /**
     * The customer's phone number.
     */
    var $phone = '';

    /**
     * The customer's fax number.
     */
    var $fax = '';

    /**
     * The customer's email address.
     */
    var $email = '';

    /**
     * Credit card number.
     */
    var $cardNumber = '';

    /**
     * Credit card expiration date, in MM/YY format.
     */
    var $expDate = '';

    /**
     * Bank account number for electronic checks or electronic funds transfer.
     */
    var $accountNumber = '';

    /**
     * Bank routing code for EFT.
     */
    var $routingCode = '';

    /**
     * Bank name for EFT.
     */
    var $bankName = '';

    /**
     * CVV2 code.
     */
    var $cvv = '';

    /**
     * Transaction source.
     *
     * This should be set to one of the PAYMENT_PROCESS_SOURCE_* constants.
     */
    var $transactionSource;

    /**
     * Perform AVS?
     *
     * @type boolean
     */
    var $performAvs = false;
    
    /**
     * Array of fields which are required.
     *
     * @type array
     * @access private
     * @see _makeRequired()
     */
    var $_required = array();
    
    /**
     * Processor-specific data.
     *
     * @access private
     * @type array
     */
    var $_data = array();

    /**
     * Return an instance of a specific processor.
     *
     * @param  string  $type     Name of the processor
     * @param  array   $options  Options for the processor
     * @return mixed Instance of the processor object, or a PEAR_Error object.
     */
    function &factory($type, $options = false)
    {
        $class = "Payment_Process_".$type;
        @include_once "Payment/Process/{$type}.php";
        if (!class_exists($class)) {
            return PEAR::raiseError("\"$type\" processor does not exist", PAYMENT_PROCESS_ERROR_NOPROCESSOR);
        }
        return new $class($options);
    }

    /**
     * Validate data before processing.
     *
     * This function may be overloaded by the processor.
     *
     * @return boolean true if validation succeeded, PEAR_Error if it failed.
     */
    function validate()
    {
        foreach ($this->getFields() as $field) {
            $func = '_validate'.ucfirst($field);
            
            // Don't validate unset optional fields
            if (! $this->isRequired($field) && !strlen($this->field)) {
                continue;
            }
            
            if (method_exists($this, $func)) {
                $res = $this->$func();
                if (PEAR::isError($res) || (is_bool($res) && $res == false)) {
                    if (!$res)
                        $res = new PEAR_Error("Validation of field \"{$field}\" failed.", PAYMENT_PROCESS_ERROR_INVAILD);
                    return $res;
                }
            }
        }
        return true;
    }

    /**
     * Process the transaction.
     *
     * This function should be overloaded by the processor.
     */
    function process()
    {
        return PEAR::raiseError("process() is not implemented in this processor.", PAYMENT_PROCESS_ERROR_NOTIMPLEMENTED);
    }

    /**
     * Get transaction result.
     *
     * This function should be overloaded by the processor.
     */
    function getResult()
    {
        return PEAR::raiseError("getResult() is not implemented in this processor.", PAYMENT_PROCESS_ERROR_NOTIMPLEMENTED);
    }

    /**
     * Set multiple fields at once.
     *
     * This function takes a variable number of arguments, in alternating
     * field/value format, e.g.:
     * $object->setMultiple('cardNumber', '1111111111111111', 'expDate', '05/05');
     *
     * @param  mixed  Variable number of arguments; key, value.
     * @return mixed true on success, PEAR_Error object on failure.
     */
    function setMultiple()
    {
        $args = func_get_args();
        if (count(args) % 2) {
            return PEAR::raiseError("Must supply an even number of arguments.", PAYMENT_PROCESS_ERROR_INVAILD);
        }

        for ($i = 0; $i < count($args); $i += 2) {
            $res = $this->set($args[$i], $args[$i + 1]);
            if (PEAR::isError($res)) {
                return $res;
            }
        }
        return true;
    }

    /**
     * Set many fields.
     *
     * @param  array  $where  Associative array of data to set, in the format
     *                       'field' => 'value',
     * @return void
     */
    function setFrom($where)
    {
        foreach ($this->getFields() as $field) {
            if (isset($where[$field]))
                $this->$field = $where[$field];
        }
    }

    /**
     * Set a value.
     *
     * This will set a value, such as the credit card number. If the requested
     * field is not part of the basic set of supported fields, it is set in
     * $_options.
     *
     * @param  string  $field  The field to set
     * @param  string  $value  The value to set
     * @return void
     */
    function set($field, $value)
    {
        if (!$this->fieldExists($field)) {
            return PEAR::raiseError("Field \"$field\" does not exist.", PAYMENT_PROCESS_ERROR_INVALID);
        }
        $this->$field = $value;
        return true;
    }
    
    /**
     * Mark a field as being required.
     *
     * @param $field Field name
     * @param ...
     * @return boolean always true.
     */
    function _makeRequired()
    {
        foreach (func_get_args() as $field) {
            $this->_required[$field] = true;
        }
        return true;
    }
    
    /**
     * Mark a field as being optional.
     *
     * @param $field Field name
     * @param ...
     * @return boolean always true.
     */
    function _makeOptional()
    {
        foreach (func_get_args() as $field) {
            unset($this->_required[$field]);
        }
        return true;
    }
    
    /**
     * Determine if a field is required.
     *
     * @param string $field Field to check
     * @return boolean true if required, false if optional.
     */
    function isRequired($field)
    {
        if (isset($this->_required[$field])) {
            return $this->_required[$field];
        }
        return false;
    }

    /**
     * Determines if a field exists.
     *
     * @param  string  $field  Field to check
     * @return boolean true if field exists, false otherwise
     */
    function fieldExists($field)
    {
        return @in_array($field, $this->getFields());
    }

    /**
     * Get a list of fields.
     *
     * This function returns an array containing all the possible fields which
     * may be set.
     *
     * @return array Array of valid fields.
     */
    function getFields()
    {
        $vars = array_keys(get_class_vars(get_class($this)));
        foreach ($vars as $idx => $field) {
            if (ereg('^_+', $field)) {
                unset($vars[$idx]);
            }
        }
        return $vars;
    }

    /**
     * Set class options.
     *
     * @param  Array  $options         Options to set
     * @param  Array  $defaultOptions  Default options
     * @return void
     */
    function setOptions($options = false, $defaultOptions = false)
    {
        $defaultOptions = $defaultOptions ? $defaultOptions : $this->_defaultOptions;
        $this->_options = @array_merge($defaultOptions, $options);
    }

    /**
     * Get an option value.
     *
     * @param  string  $option  Option to get
     * @return mixed Option value
     */
    function getOption($option)
    {
        return @$this->_options[$option];
    }

    /**
     * Validate a credit card number.
     *
     * @access private
     * @return boolean true on success, false on failure.
     */
    function _validateCardNumber()
    {
        return Validate::creditCard($this->cardNumber);
    }

    /**
     * Validate an email address.
     *
     * @access private
     * @return boolean true on success, false on failure.
     */
    function _validateEmail()
    {
        if (isset($this->email) && strlen($this->email)) {
            return Validate::email($this->email, false);
        }
        return true;
    }

    /**
     * Validate transaction type.
     *
     * @access private
     * @return boolean true on success, false on failure.
     */
    function _validateType()
    {
        return $this->_isDefinedConst($this->type, 'type');
    }

    /**
     * Validate transaction acion.
     *
     * @access private
     * @return boolean true on success, false on failure.
     */
    function _validateAction()
    {
        return $this->_isDefinedConst($this->action, 'action');
    }

    /**
     * Validate transaction source.
     *
     * @access private
     * @return boolean true on success, false on failure.
     */
    function _validateSource()
    {
        return $this->_isDefinedConst($this->transactionSource, 'source');
    }
    
    /**
     * Validate the charge amount.
     *
     * Charge amount must be 8 characters long, double-precision.
     * Current min/max are rather arbitrarily set to $1.00 and $99999.99,
     * respectively.
     *
     * @return boolean true on success, false otherwise
     */
    function _validateAmount()
    {
        return Validate::number($this->amount, array(
            'decimal' => '.',
            'dec_prec' => 2,
            'min' => 1.00,
            'max' => 99999.99
        ));
    }

    /**
     * Validate the zip code.
     *
     * @return boolean true on success, false otherwise
     */
    function _validateZip()
    {
        return ereg('^[0-9]{5}(-[0-9]{4})?$', $this->zip);
    }

    /**
     * See if a value is a defined constant.
     *
     * This function checks to see if $value is defined in one of
     * PAYMENT_PROCESS_{$class}_*. It's used to verify that e.g. $object->action is one of
     * PAYMENT_PROCESS_ACTION_NORMAL, PAYMENT_PROCESS_ACTION_AUTHONLY etc.
     *
     * @access private
     * @param  mixed  $value  Value to check
     * @param  mixed  $class  Constant class to check
     * @return boolean true if it is defined, false otherwise.
     */
    function _isDefinedConst($value, $class)
    {
        $re = '^PAYMENT_PROCESS_'.strtoupper($class).'_.*';
        $consts = get_defined_constants();
        foreach ($consts as $constant => $constVal) {
            if (ereg($re, $constant)) {
                $valid[] = $constVal;
            }
        }
        return @in_array($value, $valid);
    }

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
                $result = $this->$func();
                if (PEAR::isError($result)) {
                    return $result;
                }
            } else {
                $this->_data[$specific] = $this->$generic;
            }
        }

        if ($this->_options['testTransaction']) {
            $this->_data['testTransaction'] =
$this->_options['testTransaction'];
        }
                                                                                
        return true;
    }
}

/**
 * Payment_Process_Result
 *
 * The core result class that should be returned from each factories process
 * function. Based loosely on PEAR_Error (getCode/getMessage).
 *
 * This class may be extended with a Processor-specific result class to handle
 * the cases where the default return values don't provide enough information
 * to the caller.
 *
 * @author Joe Stump <joe@joestump.net>
 * @package Payment_Process
 */
class Payment_Process_Result {
    /**
     * The requesting processor.
     *
     * @access private
     * @type Object
     * @see setRequest
     */
     var $_request;

    /**
     * Transaction result code.
     *
     * This should be set to one of the PAYMENT_PROCESS_ACTION_* constants.
     *
     * @type int
     */
    var $code;

    /**
     * Transaction result message.
     *
     * e.g. 'APPROVED,' 'DECLINED,' etc. May vary from processor to processor.
     * @type string
     */
    var $message;

    /**
     * Transaction invoice number.
     *
     * @type mixed
     */
    var $invoiceNumber;

    /**
     * Transaction ID.
     *
     * This should be the unique ID the gateway sends back. Sometimes referred to
     * as an approval number.
     *
     * @type string
     */
    var $transactionID;

    /**
     * Constructor.
     *
     * @param  string  $message  Transaction result message.
     * @param  string  $code     Transaction result code.
     */
    function Payment_Process_Result($code = false, $message = false)
    {
        if ($code) {
            $this->code = $code;
        }
        if ($message) {
            $this->message = $message;
        }
    }

    /**
     * Create a new instance of Payment_Process_Result.
     *
     * This will return either a new instance of Payment_Process_Result, or a
     * PEAR_Error instance. PEAR_Error is returned if $code is an invalid result
     * code, or if it is PAYMENT_PROCESS_RESULT_DECLINED.
     *
     * The back-end processors should return the instance returned by this, to
     * indicate a failure/success condition to their callers.
     *
     * An optional type may be requested, for Processors which have a Result
     * subclass.
     *
     * @param  string  $type  Transaction result type.
     * @return mixed Payment_Process_Result instance, or PEAR_Error
     */
    function &factory($type = false, $code = false, $message = false)
    {
        // We assume that the result class is defined in the processor.
        if ($type) {
            $class = 'Payment_Process_Result_'.$type;
        } else {
            $class = get_class($this);
        }

        if (!class_exists($class)) {
            return PEAR::raiseError("Can't instantiate non-existent class \"$class\"", PAYMENT_PROCESS_ERROR_INVAILD);
        }

        return new $class($code, $message);
    }

    function setRequest(&$req)
    {
        if (!is_a($req, 'Payment_Process')) {
            return PEAR::raiseError("Request must be a Payment_Process instance or subclass.");
        }
        $this->_request = &$req;
        return true;
    }

    /**
     * Get the transaction result code.
     *
     * @return string Transaction result code
     */
    function getCode()
    {
        return $this->code;
    }

    /**
     * Get the transaction result message.
     *
     * @return string Transaction result message
     */
    function getMessage()
    {
        return $this->message;
    }

    /**
     * Get the transaction ID.
     *
     * @return string Transaction ID
     */
    function getTransactionID()
    {
        return $this->transactionID;
    }

    /**
     * Was the transaction successful?
     *
     * @return boolean
     */
    function isSuccess()
    {
        return ! $this->isError();
    }

    /**
     * Was the transaction unsuccessful?
     *
     * @return boolean
     */
    function isError()
    {
        if ($this->code && $this->code != PAYMENT_PROCESS_RESULT_APPROVED)
            return true;
        return false;
    }
}


?>
