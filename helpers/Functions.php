<?php namespace ProcessWire;

/**
 * Helper - functions
 * (This file is part of the SnipWire package)
 *
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

function checkPattern($value, $pattern) {
	$regex = '#' . str_replace('#', '\#', $pattern) . '#';
	return preg_match($regex, $value) ? true : false;
}
