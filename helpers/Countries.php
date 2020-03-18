<?php
namespace SnipWire\Helpers;

/**
 * Countries - helper class for SnipWire to get worldwide countries list.
 * (This file is part of the SnipWire package)
 *
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

use ProcessWire\WireData;

class Countries extends WireData {
    
    /** @var array $countriesCache An array of worldwide countries (static cached) */
    public static $countriesCache = null;

    /**
     * Set the static countries cache.
     *
     * @return void
     * 
     */
    public static function setStaticCountriesCache() {
        // Cache countries in static property
        self::$countriesCache = self::importCountries();
    }

    /**
     * Import worldwide countries from file.
     * 
     * @return array
     * 
     */
    public static function importCountries() {
        return require __DIR__ . '/CountriesTable.php';
    }

    /**
     * Get the full countries array (as language-code => country-name).
     *
     * @return void
     * 
     */
    public static function getCountries() {
        if (empty(self::$countriesCache)) self::setStaticCountriesCache();
        return self::$countriesCache;
    }

    /**
     * Get a country by it's alpha-2 language code as defined by ISO 3166.
     *
     * @param string $key The alpha-2 language code
     * @return string
     *
     */
	public static function getCountry($key) {
        if (empty(self::$countriesCache)) self::setStaticCountriesCache();
		return isset(self::$countriesCache[$key])
		    ? self::$countriesCache[$key]
		    : $this->_('-- unknown --');
	}
}
