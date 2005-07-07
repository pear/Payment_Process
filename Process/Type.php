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

define('PAYMENT_PROCESS_CC_VISA', 100);
define('PAYMENT_PROCESS_CC_MASTERCARD', 101);
define('PAYMENT_PROCESS_CC_AMEX', 102);
define('PAYMENT_PROCESS_CC_DISCOVER', 103);

define('PAYMENT_PROCESS_CK_SAVINGS',1000);
define('PAYMENT_PROCESS_CK_CHECKING',1001);

/**
 * Payment_Process_Type
 *
 * @author Joe Stump <joe@joestump.net>
 * @category Payment
 * @package Payment_Process
 * @version @version@
 */
class Payment_Process_Type
{
    // {{{ properties
    var $_type = null;
    var $name;
    var $company;
    var $address;
    var $city;
    var $state;
    var $zip;
    var $country;
    var $phone;
    var $fax;
    var $email;
    // }}}
    // {{{ __construct()
    function __construct()
    {

    }
    // }}}
    // {{{ Payment_Process_Type()
    function Payment_Process_Type()
    {
        $this->__construct();
    }
    // }}}
    // {{{ factory()
    /**
    * factory
    *
    * Creates and returns an instance of a payment type. If an error occurs
    * a PEAR_Error is returned.
    *
    * @author Joe Stump <joe@joestump.net>
    * @param string $type
    * @return mixed
    */
    function &factory($type)
    {
        $class = 'Payment_Process_Type_'.$type;
        $file = 'Payment/Process/Type/'.$type.'.php';
        if (include_once($file)) {
            if (class_exists($class)) {
                return new $class();
            }
        } 

        return PEAR::raiseError('Invalid Payment_Process_Type: '.$type);
    }
    // }}}
    // {{{ isValid()
    /**
    * isValid
    *
    * Validate a payment type object
    *
    * @author Joe Stump <joe@joestump.net>
    * @access public
    * @param mixed $obj Type object to validate
    * @return mixed true on success, PEAR_Error on failure
    */
    function isValid($obj)
    {
        if (is_a($obj,'Payment_Process_Type')) {
            $vars = get_object_vars($obj);
            foreach ($vars as $validate => $value) {
                $method = '_validate'.ucfirst($validate);
                if (method_exists($obj,$method)) {
                    if(!$obj->$method()) {
                        return false;
                    } 
                }
            }

            return true;
        }

        return false;
    }
    // }}}
    // {{{ getType()
    /**
    * getType
    *
    * @author Joe Stump <joe@joestump.net>
    * @access public
    * @return string
    */
    function getType()
    {
      return $this->_type;
    }
    // }}}
    // {{{ _validateEmail()
    /**
     * Validate an email address.
     *
     * @author Ian Eure <ieure@php.net>
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
    // }}}
    // {{{ _validateZip()
    /**
     * Validate the zip code.
     *
     * This only validates U.S. zipcodes; country must be set to 'us' for zip to
     * be validated.
     *
     * @author Ian Eure <ieure@php.net>
     * @access private
     * @return boolean true on success, false otherwise
     */
    function _validateZip()
    {
        if (isset($this->zip) && strtolower($this->country) == 'us') {
            return ereg('^[0-9]{5}(-[0-9]{4})?$', $this->zip);
        }

        return true;
    }
    // }}}
}  

?>
