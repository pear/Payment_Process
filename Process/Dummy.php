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
// +----------------------------------------------------------------------+
//
// $Id$

require_once 'Payment/Process/Common.php';

/**
 * Payment_PAYMENT_PROCESS_Dummy
 *
 * A dummy processor for offline testing. It can be made to return different
 * result codes and messages for testing purposes.
 *
 * @package Payment_Process
 * @category Payment
 * @author Ian Eure <ieure@php.net>
 * @version @version@
 */
class Payment_Process_Dummy extends Payment_Process_Common {
	/**
     * Default options for this class.
     *
     * @access private
     * @type array
     * @see Payment_Process::setOptions()
     */
    var $_defaultOptions = array(
    	'randomResult' => true,
        'returnCode' => PAYMENT_PROCESS_RESULT_APPROVED,
        'returnMessage' => "Dummy payment approved"
    );

    var $_returnValues = array(
    	array(
        	'code' => PAYMENT_PROCESS_RESULT_APPROVED,
            'message' => "Approved"
        ),
        array(
        	'code' => PAYMENT_PROCESS_RESULT_DECLINED,
            'message' => "Declined"
        ),
        array(
        	'code' => PAYMENT_PROCESS_RESULT_OTHER,
            'message' => "System error"
        )
    );

    /**
     * Process the (dummy) transaction
     *
     * @return mixed  Payment_Process_Result instance or PEAR_Error
     */
    function &process()
    {
        // Sanity check
        if (PEAR::isError($res = $this->validate())) {
            return($res);
            $n = rand(0, count($this->_returnValues) - 1);
            $code = &$this->_returnValues[$n]['code'];
            $message = &$this->_returnValues[$n]['message'];
        }

        if ($this->_options['randomResult']) {
        	srand(microtime());
        } else {
        	$code = &$this->_options['returnCode'];
            $message = &$this->_options['returnMessage'];
        }

        return Payment_Process_Result::factory(null, $this->_options['returnCode'], $this->_options['returnMessage']);
    }
}
?>
