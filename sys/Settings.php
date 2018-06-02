<?php
/**
 * Settings utility
 * @author      PCS Team
 * @copyright   (C) Powerful Community System
 * @package     Powerful Community System
 * @since       2018-06-01
 */

namespace PCS;
use Psr\Log\NullLogger;

/**
 * Settings utility class
 *
 * @package PCS
 */
class Settings
{
    protected static $cachedConfig = NULL;

    /**
     * Get data from config.php
     *
     * @return array
     */
    public static function config()
    {
        if (!static::$cachedConfig) {
            $config = @include_once("../config.php");
            static::$cachedConfig = $config ?: [];
        }
        return static::$cachedConfig;
    }
}