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
// |          Joe Stump <joe@joestump.net>                                |
// |          Ondrej Jombik <nepto@pobox.sk>                              |
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
 * @author Ian Eure <ieure@debian.org>
 * @package Payment_Process
 * @version 0.1
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
     * @return boolean true if validation succeeded, false if it failed.
     */
    function validate()
    {
        foreach ($this->getFields() as $field) {
            $func = '_validate'.ucfirst($field);
            if (method_exists($this, $func)) {
                $res = $this->$func();
                if (PEAR::isError($res) || (is_bool($res) && $res == false)) {
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
}

/**
 * Payment_Process_Result
 *
 * The core result class that should be returned from each factories process
 * function. Based loosely on PEAR_Error (getCode/getMessage).
 *
 * @author Joe Stump <joe@joestump.net>
 * @package Payment_Process
 */
class Payment_Process_Result {

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
    function Payment_Process_Result($message = false, $code = false)
    {
    	if ($message)
            $this->message = $message;
        if ($code)
            $this->code = $code;
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
     * @param  string  $type  Transaction result type.
     * @return mixed Payment_Process_Result instance, or PEAR_Error
     */
    function &factory($type)
    {
        // If the factory is returning a Payment_Process_Result instead
        // of an error for DECLINED then we should turn ourselves into an error
        if ($code == PAYMENT_PROCESS_RESULT_DECLINED) {
            return PEAR::raiseError($message, $code);
        }

        // We assume that the result class is defined in the processor.
        $class = 'Payment_Process_Result_'.$type;

        return new $class($message, $code);
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
}


?>
