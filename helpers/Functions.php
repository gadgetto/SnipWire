<?php

namespace SnipWire\Helpers;

/**
 * Helper functions class
 * (This file is part of the SnipWire package)
 *
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2023 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright Ryan Cramer
 * https://processwire.com
 */
class Functions
{
    /**
     * Check a string against a regex pattern.
     *
     * @param string $value The string to be checked
     * @param string $pattern The pattern to be used for check
     * @return boolean
     */
    public static function checkPattern($value, $pattern)
    {
        $regex = '#' . str_replace('#', '\#', $pattern) . '#';
        return preg_match($regex, $value) ? true : false;
    }
}
