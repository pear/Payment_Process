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
// +----------------------------------------------------------------------+
//
// $Id$

class Payment_Process_Type
{
    var $name;
    var $address;
    var $city;
    var $state;
    var $zip;
    var $country;
    var $phone;
    var $fax;
    var $email;

    function Payment_Process_Type()
    {

    }

    function &factory($type)
    {
        $class = 'Payment_Process_Type_'.$type;
        $file = 'Payment/Process/Type/'.$class.'.php';
        if (@include($file)) {
            if (class_exists($class)) {
                return new $class();
            }
        }

        return PEAR::raiseError('Invalid Payment_Process_Type: '.$type);
    }

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
}  

?>
