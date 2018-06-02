<?php
/**
 * Text utility
 * @author      PCS Team
 * @copyright   (C) Powerful Community System
 * @package     Powerful Community System
 * @since       2018-05-06
 */

namespace PCS;

/**
 * Text utility class
 *
 * @package PCS
 */
class Text
{
    protected static $singleton;

    /**
     * Get instance
     *
     * @return    Text
     */
    public static function i()
    {
        if (static::$singleton == NULL) {
            static::$singleton = new static;
        }
        return static::$singleton;
    }

    protected function __construct()
    {
    }

    /**
     * Replace n-th occurrence in string
     *
     * @param     string $search Search string
     * @param     string $replace Replace string
     * @param     string $subject Source string
     * @param     string $occurrence Nth occurrence
     * @return    string                Replaced string
     *
     * @see       <a href="https://vijayasankarn.wordpress.com/2017/01/03/string-replace-nth-occurrence-php/"></a>
     */
    public function replaceNth($search, $replace, $subject, $occurrence)
    {
        $search = preg_quote($search);
        return preg_replace("/^((?:(?:.*?$search){" . --$occurrence . "}.*?))$search/", "$1$replace", $subject);
    }
}