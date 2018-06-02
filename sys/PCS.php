<?php
/**
 * Main PCS class
 * @author      PCS Team
 * @copyright   (C) Powerful Community System
 * @package     Powerful Community System
 * @since       2018-06-01
 */

namespace PCS;

/**
 * General PCS utilities class
 *
 * @package PCS
 */
class PCS
{
    public static function defineConstants($root)
    {
        $constants = array_merge([
            'ROOT_DIR' => $root
        ], include('../constants.php'));

        foreach ($constants as $k => $v) {
            define('PCS\\' . $k, $v);
        }
    }
}