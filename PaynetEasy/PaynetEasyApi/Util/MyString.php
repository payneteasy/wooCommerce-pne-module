<?php

namespace PaynetEasy\PaynetEasyApi\Util;

class MyString
{
    /**
     * Convert string from format <this_is_the_string> or
     * <this-is-the-string> to format <ThisIsTheString>
     *
     * @param       string      $string         MyString to coversion
     *
     * @return      string                      Converted string
     */
    static public function camelize($string)
    {
        return implode('', array_map('ucfirst', preg_split('/_|-/', $string)));
    }
}