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
// +----------------------------------------------------------------------+
//
// $Id$

require_once 'Payment/Process.php';

/**
 * Payment_PAYMENT_PROCESS_Dummy
 *
 * A dummy processor for offline testing. It can be made to return different
 * result codes and messages for testing purposes.
 *
 * @package Payment_Process
 * @author Ian Eure <ieure@websprockets.com>
 * @version 0.1
 */
class Payment_PAYMENT_PROCESS_Dummy extends Payment_Process {
	/**
     * Default options for this class.
     *
     * @access private
     * @type array
     * @see Payment_Process::setOptions()
     */
    var $_defaultOptions = array(
        'returnCode' => PAYMENT_PROCESS_RESULT_APPROVED,
        'returnMessage' => "Dummy payment approved"
    );

    /**
     * Process the (dummy) transaction
     *
     * @return mixed  Payment_PAYMENT_PROCESS_Result instance on success, PEAR_Error otherwise.
     */
    function &process()
    {
        // Sanity check
        if(PEAR::isError($res = $this->validate())) {
            return($res);
        }

        return Payment_PAYMENT_PROCESS_Result::factory($this->_options['returnMessage'], $this->_options['returnCode']);
    }
}
?>
