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
require_once 'Payment/Process/Type.php';

class Payment_Process_Common extends Payment_Process {
    /**
    * $avsCheck
    *
    * Used by Payment_Process_Result to determine if the result class should
    * validate the AVS response from the gateway.
    *
    * @author Joe Stump <joe@joestump.net>
    * @access public
    * @var bool $avsCheck
    */
    var $avsCheck = false;

    /**
    * $cvvCheck
    *
    * Used by Payment_Process_Result to determine if the result class should
    * validate the CVV response from the gateway.
    *
    * @author Joe Stump <joe@joestump.net>
    * @access public
    * @var bool $cvvCheck
    */

    /**
    * $_payment
    *
    * @author Joe Stump <joe@joestump.net>
    * @access protected
    * @var mixed $_payment Instance of Payment_Type
    * @see Payment_Process_Common::setPayment()
    */
    var $_payment = null;

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
                if (PEAR::isError($res)) {
                    return $res;
                } elseif (is_bool($res) && $res == false) {
                    return PEAR::raiseError('Validation of field "'.$field.'" failed.', PAYMENT_PROCESS_ERROR_INVAILD);
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
        return (isset($GLOBALS['_Payment_Process_'.$this->_driver][$this->action]));
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
     * Prepare the POST data.
     *
     * This function handles translating the data set in the front-end to the
     * format needed by the back-end. The prepared data is stored in 
     * $this->_data. If a '_handleField' method exists in this class (e.g. 
     * '_handleCardNumber()'), that function is called and /must/ set 
     * $this->_data correctly. If no field-handler function exists, the data 
     * from the front-end is mapped into $_data using $this->_fieldMap.
     *
     * @access private
     * @return array Data to POST
     */
    function _prepare()
    {
        if ($this->_options['debug']) {
            echo '----------- PREPARE A ----------'."\n";
            echo print_r($this->_data);
            echo '----------- PREPARE A ----------'."\n";
        }

        /*
         * FIXME - because this only loops through stuff in the fieldMap, we
         *         can't have handlers for stuff which isn't specified in there.
         *         But the whole point of having a _handler() is that you need
         *         to do something more than simple mapping.
         */
        foreach ($this->_fieldMap as $generic => $specific) {
            //$this->debug("Handling mapping from {$generic} to {$specific}");
            $func = '_handle'.ucfirst($generic);
            if (method_exists($this, $func)) {
                //$this->debug("Calling {$func} to handle {$generic}");
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
                }
            }
        }

        if ($this->_options['debug']) {
            echo '----------- PREPARE ----------'."\n";
            echo print_r($this->_data);
            echo '----------- PREPARE ----------'."\n";
        }
                                                                                
        return true;
    }

    /**
    * Set payment
    *
    * @author Joe Stump <joe@joestump.net>
    * @access public
    * @param mixed $payment Object of Payment_Process_Type
    * @return bool
    */
    function setPayment($payment)
    {
        if (Payment_Process_Type::isValid($payment)) {
            $this->_payment = $payment;
            foreach ($this->_fieldMap as $generic => $specific) {
                if(isset($payment->$generic)) {
                    //$this->debug("Mapping Type::{$generic} to {$specific}");
                    $this->_data[$specific] = $payment->$generic;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Handle action
     *
     * Actions are defined in $GLOBALS['_Payment_Process_DriverName'] and then
     * handled here. We may decide to abstract the defines in the driver.
     *
     * @author Joe Stump <joe@joestump.net>
     * @access private
     * @return void
     */
    function _handleAction()
    {
        $this->_data[$this->_fieldMap['action']] = $GLOBALS['_Payment_Process_'.$this->_driver][$this->action];
    }
    
    /**
     * Print a debug message.
     *
     * This will only print the message if 'debug' is set in the Processor
     * options.
     *
     * @param string $msg Message to print
     * @return void
     * @author Ian Eure <ieure@php.net>
     */
    function debug($msg)
    {
        if ($this->_options['debug']) {
            print $msg."\n";
        }
    }

}

?>
