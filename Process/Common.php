<?php

/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * Holds code shared between all processors
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   Payment
 * @package    Payment_Process
 * @author     Ian Eure <ieure@php.net>
 * @author     Joe Stump <joe@joestump.net>
 * @copyright  1997-2005 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/Payment_Process
 */

require_once('Payment/Process.php');
require_once('Payment/Process/Type.php');

class Payment_Process_Common extends Payment_Process {
    /**
     * Mapping between API fields and processors'
     *
     * @var mixed $_typeFieldMap
     * @access protected
     */
    var $_typeFieldMap = array();

    /**
     * Reference to payment type
     *
     * An internal reference to the Payment_Process_Type that is currently
     * being processed.
     *
     * @var mixed $_payment Instance of Payment_Type
     * @access protected
     * @see Payment_Process_Common::setPayment()
     */
    var $_payment = null;

    /**
     * __construct
     *
     * @author Joe Stump <joe@joestump.net>
     * @access public
     */
    function __construct($options = false)
    {
        $this->setOptions($options);
    }

    function Payemnt_Process_Common($options = false)
    {
        $this->__construct();
    }

    /**
     * Validates data before processing.
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
            if (! $this->isRequired($field) && !strlen($this->$field)) {
                continue;
            }

            if (method_exists($this, $func)) {
                $res = $this->$func();
                if (PEAR::isError($res)) {
                    return $res;
                } elseif (is_bool($res) && $res == false) {
                    return PEAR::raiseError('Validation of field "'.$field.'" failed.', PAYMENT_PROCESS_ERROR_INVALID);
                }
            }
        }

        return true;
    }

    /**
     * Processes the transaction.
     *
     * This function should be overloaded by the processor.
     */
    function process()
    {
        return PEAR::raiseError("process() is not implemented in this processor.", PAYMENT_PROCESS_ERROR_NOTIMPLEMENTED);
    }

    /**
     * Gets transaction result.
     *
     * This function should be overloaded by the processor.
     */
    function getResult()
    {
        return PEAR::raiseError("getResult() is not implemented in this processor.", PAYMENT_PROCESS_ERROR_NOTIMPLEMENTED);
    }

    /**
     * Validates transaction type.
     *
     * @return boolean true on success, false on failure.
     * @access private
     */
    function _validateType()
    {
        return $this->_isDefinedConst($this->type, 'type');
    }

    /**
     * Validates transaction action.
     *
     * @return boolean true on success, false on failure.
     * @access private
     */
    function _validateAction()
    {
        return (isset($GLOBALS['_Payment_Process_'.$this->_driver][$this->action]));
    }

    /**
     * Validates transaction source.
     *
     * @return boolean true on success, false on failure.
     * @access private
     */
    function _validateSource()
    {
        return $this->_isDefinedConst($this->transactionSource, 'source');
    }

    /**
     * Validates the charge amount.
     *
     * Charge amount must be 8 characters long, double-precision.
     * Current min/max are rather arbitrarily set to $0.99 and $99999.99,
     * respectively.
     *
     * @return boolean true on success, false otherwise
     */
    function _validateAmount()
    {
        return Validate::number($this->amount, array(
            'decimal' => '.',
            'dec_prec' => 2,
            'min' => 0.99,
            'max' => 99999.99
        ));
    }

    /**
     * Prepares the POST data.
     *
     * This function handles translating the data set in the front-end to the
     * format needed by the back-end. The prepared data is stored in
     * $this->_data. If a '_handleField' method exists in this class (e.g.
     * '_handleCardNumber()'), that function is called and /must/ set
     * $this->_data correctly. If no field-handler function exists, the data
     * from the front-end is mapped into $_data using $this->_fieldMap.
     *
     * @return array Data to POST
     * @access private
     */
    function _prepare()
    {
        /*
         * FIXME - because this only loops through stuff in the fieldMap, we
         *         can't have handlers for stuff which isn't specified in there.
         *         But the whole point of having a _handler() is that you need
         *         to do something more than simple mapping.
         */
        foreach ($this->_fieldMap as $generic => $specific) {
            $func = '_handle'.ucfirst($generic);
            if (method_exists($this, $func)) {
                $result = $this->$func();
                if (PEAR::isError($result)) {
                    return $result;
                }
            } else {
                // TODO This may screw things up - the problem is that
                // CC information is no longer member variables, so we
                // can't overwrite it. You could always handle this with
                // a _handle funciton. I don't think it will cause problems,
                // but it could.
                if (!isset($this->_data[$specific])) {
                    $this->_data[$specific] = $this->$generic;

                    // Form of payments data overrides those set in the
                    // Payment_Process_Common.
                    if (isset($this->_payment->$generic)) {
                        $this->_data[$specific] = $this->_payment->$generic;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Sets payment
     *
     * Returns false if payment could not be set. This usually means the
     * payment type is not valid  or that the payment type is valid, but did
     * not validate. It could also mean that the payment type is not supported
     * by the given processor.
     *
     * @param mixed $payment Object of Payment_Process_Type
     * @return bool
     * @access public
     * @author Joe Stump <joe@joestump.net>
     */
    function setPayment($payment)
    {
        if (isset($this->_typeFieldMap[$payment->getType()]) &&
            is_array($this->_typeFieldMap[$payment->getType()]) &&
            count($this->_typeFieldMap[$payment->getType()])) {

            if (Payment_Process_Type::isValid($payment)) {

                $this->_payment = $payment;
                // Map over the payment specific fields. Check out
                // $_typeFieldMap for more information.
                $paymentType = $payment->getType();
                foreach ($this->_typeFieldMap[$paymentType] as $key => $val) {
                    if (!isset($this->_data[$val])) {
                        $this->_data[$val] = $this->_payment->$key;
                    }
                }

                return true;
            }
        }

        return false;
    }

    /**
     * processCallback
     *
     * This should be overridden in driver classes. It will be used to process
     * communications from gateways to your application. For instance, the
     * Authorize.net gateway will post information about pending transactions
     * to a URL you specify. This function should handle such requests
     *
     * @return object Payment_Process_Result on success, PEAR_Error on failure
     */
    function &processCallback()
    {
        return PEAR::raiseError('processCallback() not implemented',
                                PAYMENT_PROCESS_ERROR_NOTIMPLEMENTED);
    }

    /**
     * Handles action
     *
     * Actions are defined in $GLOBALS['_Payment_Process_DriverName'] and then
     * handled here. We may decide to abstract the defines in the driver.
     *
     * @access private
     */
    function _handleAction()
    {
        $this->_data[$this->_fieldMap['action']] = $GLOBALS['_Payment_Process_'.$this->_driver][$this->action];
    }
}

?>
