<?php
/**
 * File description here
 * @author      Mikołaj
 * @copyright   (C) Mikołaj
 * @package     PCS
 * @since       2018-05-11
 */

namespace Text;

use PHPUnit\Framework\TestCase;

class TextTest extends TestCase
{
    public function testCanReplace()
    {
        $this->assertEquals("aabaaaaa", \PCS\Text::i()->replaceNth("a", "b", "aaaaaaaa", 3));
    }
}
