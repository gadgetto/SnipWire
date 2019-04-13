<?php namespace ProcessWire;

/**
 * SnipWire - Full Snipcart shopping cart integration for ProcessWire CMF.
 * (This module is the master for all other SnipWire modules and files)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2018 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class SnipWire extends WireData implements Module, ConfigurableModule {

    /**
     * Returns information for SnipWire module.
     *
     */
    public static function getModuleInfo() {
        return array(
            'title' => __('SnipWire'),
            'summary' => __('Full Snipcart shopping cart integration for ProcessWire.'),
            'version' => 1, 
            'author'  => 'Martin Gartner',
            'icon' => 'shopping-cart', 
            'singular' => true, 
            'autoload' => true, 
            'installs' => array(
                'ProcessSnipWire',
                'MarkupSnipWire',
            ),
            'requires' => array(
                'PHP>=5.6.0', 
                'ProcessWire>=3.0.118',
            ),
        );
    }

    /**
     * Initalize module config variables (properties)
     *
     */
    public function __construct() {
        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'ExtendedInstaller.php';
        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'SnipREST.php';
        parent::__construct();
    }

    /**
     * Initialize the module
     * 
     * This is an optional initialization function called before any execute functions.
     * ProcessWire calls this when the module is loaded. For 'autoload' modules, this will be called
     * when ProcessWire's API is ready. As a result, this is a good place to attach hooks.
     *
     * @access public
     *
     */
    public function init() {
        /** @var SnipREST $snipREST Custom ProcessWire API variable */
        $this->wire('snipREST', new SnipREST());
    }    
    
    /**
     * Called on module install
     *
     */
    public function ___install() {

    }

    /**
     * Called on module uninstall
     *
     */
    public function ___uninstall() {
        // Remove all caches created by SnipWire
        $this->wire('cache')->delete(SnipREST::settingsCacheName);
    }

}
