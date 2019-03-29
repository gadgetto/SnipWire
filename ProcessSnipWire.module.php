<?php namespace ProcessWire;

/**
 * ProcessSnipWire - Full Snipcart shopping cart integration for ProcessWire CMF.
 * (This module is the master for all other SnipWire modules and files)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2018 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class ProcessSnipWire extends Process implements Module, ConfigurableModule {

    /**
     * Returns information for ProcessSnipWire module.
     *
     */
    public static function getModuleInfo() {
        return array(
            'title' => __('SnipWire'),
            'summary' => __('Full Snipcart shopping cart integration for ProcessWire CMF.'),
            'version' => 1, 
            'author'  => 'Martin Gartner',
            'href' => 'http://modules.processwire.com/processsnipwire', 
            'icon' => 'shopping-cart', 
            'permissions' => array(
                'snipwire-dashboard' => __('Use the ProcessSnipWire Dashboard'),
            ), 
            'page' => array(
                'name' => 'snipwire',
                'title' => 'SnipWire',
                'parent' => 'setup',
            ),
            /*
            'nav' => array(
                array(
                    'url' => 'path/', 
                    'label' => 'Label', 
                    'icon' => 'icon', 
                ),
            ),
            */
            'installs' => array(
                'MarkupSnipWire',
            ),
            'requires' => array(
                'PHP>=5.6.0', 
                'ProcessWire>=3.0.118',
            ),
        );
    }

    /** @var boolean $debug Whether or not module debug mode is active */
    protected $debug = false;

    /** @var array $snipcartAPIkeys All Snipcart configuration API settings keys (some are currently not in use here) */
    protected $snipcartAPIkeys = array(
        'credit_cards',
        'allowed_shipping_methods',
        'excluded_shipping_methods',
        'show_cart_automatically',
        'shipping_same_as_billing',
        'allowed_countries',
        'allowed_provinces',
        'provinces_for_country',
        'show_continue_shopping',
        'split_firstname_and_lastname',
    );

    /**
     * Initalize module config variables (properties)
     *
     */
    public function __construct() {
        parent::__construct();
        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'ExtendedInstaller.php';
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
        parent::init(); 
    }    
    
    /**
     * The GroupMailer dashboard page.
     *
     * @access public
     * @return page markup
     *
     */
    public function ___execute() {
        $modules = $this->wire('modules');
        $pages = $this->wire('pages');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $ajax = $config->ajax;
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

        $this->browserTitle($this->_('SnipWireDashboard'));
        $this->headline($this->_('SnipWire Dashboard'));


        
        $out = 'The SnipWire Dashboard';

        // Dashboard markup wrapper
        $out = '<div id="snipwire-dashboard">'.$out.'</div>';

        return $out;
    }

    /**
     * The subscribers management page for GroupMailer.
     *
     */
    /*
    public function ___executeSubscribers() {
        $this->headline($this->_('GroupMailer Subscribers'));
        $this->browserTitle($this->_('GroupMailer Subscribers'));

        $out = '<p><a href="../">Back</a></p>';
        return $out; 
    }
    */

    /**
     * Getter for snipcartAPIkeys property
     *
     * @return array
     *
     */
    public function getSnipcartAPIkeys() {
        return $this->snipcartAPIkeys;
    }




    /**
     * Install product templates, fields and some demo pages required by Snipcart.
     * (Called when the URL is this module's page URL + "/install-product-package/")
     *
     * This extra installation step is needed to prevent unintended deletion of SnipCart products when module is uninstalled!
     *
     */
    public function ___executeInstallProductPackage() {
        $modules = $this->wire('modules');

        $this->browserTitle($this->_('SnipWire installer'));
        $this->headline($this->_('Install SnipWire product package'));

        $out = '';

        $moduleconfig = $modules->getConfig($this->className());
        
        // Prevent installation when already installed
        if (isset($moduleconfig['product_package']) && $moduleconfig['product_package']) {
            $out .= '<p class="ui-state-error-text">';
            $out .= $this->_('SnipWire product package is already installed!');
            $out .= '</p>';
            
        // Install
        } else {
            
            /** @var ExstendedInstaller $installer */
            $installer = $this->wire(new ExtendedInstaller());            
            
            if (!$installer->installResources(ExtendedInstaller::installerModeAll)) {
                $out .= '<p class="description">';
                $out .= $this->_('Installation of SnipWire product package not completet successfully! Please check for warnings and errors.');
                $out .= '</p>';
            } else {
                $out .= '<p class="description">';
                $out .= $this->_('Installation of SnipWire product package completet successfully! You can now close this window/tab.');
                $out .= '</p>';
                
                // Set module config to tell system that product package is already installed
                $moduleconfig['product_package'] = true;
                $modules->saveConfig($this->className(), $moduleconfig);            
            }
    
            $out .= '<p class="ui-state-highlight">';
            $out .= $this->_('You can now close this window/tab.');
            $out .= '</p>';
            $out .= '<script>window.onunload = refreshParent; function refreshParent() { window.opener.location.reload(); }</script>';
        }

        return $out;
    }
    
    /**
     * Uninstall product templates, fields and demo pages required by Snipcart.
     * (Called when the URL is this module's page URL + "/uninstall-product-package/")
     *
     */
    public function ___executeUninstallProductPackage() {
        $modules = $this->wire('modules');

        $this->browserTitle($this->_('SnipWire uninstaller'));
        $this->headline($this->_('Uninstall SnipWire product package'));

        $out = '';
        
        $moduleconfig = $modules->getConfig($this->className());
        
        // Prevent uninstallation when already uninstalled
        if (!isset($moduleconfig['product_package']) || !$moduleconfig['product_package']) {
            $out .= '<p class="ui-state-error-text">';
            $out .= $this->_('SnipWire product package is not installed!');
            $out .= '</p>';
            
        // Uninstall
        } else {
            
            /** @var ExstendedInstaller $installer */
            $installer = $this->wire(new ExtendedInstaller());            
            
            if (!$installer->uninstallResources(ExtendedInstaller::installerModeAll)) {
                $out .= '<p class="description">';
                $out .= $this->_('Uninstallation of SnipWire product package not completet successfully! Please check the warnings and errors.');
                $out .= '</p>';
            } else {
                $out .= '<p class="description">';
                $out .= $this->_('Uninstallation of SnipWire product package completet successfully! You can now close this window/tab.');
                $out .= '</p>';
                
                // Update module config to tell system that product package is not installed
                $moduleconfig['product_package'] = false;
                $modules->saveConfig($this->className(), $moduleconfig);            
            }
            $out .= '<p class="ui-state-highlight">';
            $out .= $this->_('You can now close this window/tab.');
            $out .= '</p>';
            $out .= '<script>window.onunload = refreshParent; function refreshParent() { window.opener.location.reload(); }</script>';            
        }

        return $out;
    }
    
    /**
     * Called on module install
     *
     */
    public function ___install() {
        parent::___install();
    }

    /**
     * Called on module uninstall
     *
     */
    public function ___uninstall() {
        parent::___uninstall();
    }

}
