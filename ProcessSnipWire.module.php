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

    /** @var SnipREST $snipREST Interface class for Snipcart REST API */
    protected $snipREST = null;
    
    /** @var array $snipcartAPIproperties All Snipcart configuration API properties (some are currently not in use here) */
    protected $snipcartAPIproperties = array(
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
        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'SnipREST.php';
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
        $this->snipREST = new SnipREST();
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

        $test = $this->snipREST->testConnection();
        $out = '<pre>' . $test . '</pre>';
        
        /*
        $moduleConfig = $modules->getConfig('ProcessSnipWire');
        $out = '<pre>' . print_r($moduleConfig['currencies'], true) . '</pre>';
        $out .= '<pre>' . print_r(wireDecodeJSON($moduleConfig['currencies'][0]), true) . '</pre>';
        $out .= '<pre>' . print_r(wireDecodeJSON($moduleConfig['currencies'][1]), true) . '</pre>';
        */
        
        

        // Dashboard markup wrapper
        $out = '<div id="snipwire-dashboard">'.$out.'</div>';

        return $out;
    }

    /**
     * Getter for snipcartAPIproperties
     *
     * @return array
     *
     */
    public function getSnipcartAPIproperties() {
        return $this->snipcartAPIproperties;
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
        $input = $this->wire('input');
        $sanitizer = $this->wire('sanitizer');

        $comeFromUrl = $sanitizer->url($input->get('ret'));
        $submitInstall = $input->post('submit_install');

        $this->browserTitle($this->_('SnipWire installer'));
        $this->headline($this->_('Install SnipWire product package'));

        /** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm'); 
        $form->attr('id', 'SnipWireInstallerForm'); 
        $form->attr('action', './?ret=' . urlencode($comeFromUrl)); 
        $form->attr('method', 'post');

        $moduleconfig = $modules->getConfig($this->className());
        
        // Prevent installation when already installed
        if (isset($moduleconfig['product_package']) && $moduleconfig['product_package']) {
            
            $this->warning($this->_('SnipWire product package is already installed!'));
            $this->wire('session')->redirect($comeFromUrl);
            
        // Install
        } else {

            /** @var InputfieldMarkup $f (info install) */
            $f = $modules->get('InputfieldMarkup');
            $f->icon = 'sign-in';
            $f->label = $this->_('Install');
            $f->description = $this->_('Install the SnipWire product package? This will create product templates, files, fields and pages required by Snipcart.');
            $form->add($f);
            
            /** @var InputfieldSubmit $f */
            $f = $modules->get('InputfieldSubmit');
            $f->attr('name', 'submit_install');
            $f->attr('value', $this->_('Install'));
            $f->icon = 'sign-in';
            $form->add($f);

            // Was the form submitted?
            if ($submitInstall) {
                /** @var ExstendedInstaller $installer */
                $installer = $this->wire(new ExtendedInstaller());
                $installResources = $installer->installResources(ExtendedInstaller::installerModeAll);
                if (!$installResources) {                        
                    $this->warning($this->_('Installation of SnipWire product package not completet. Please check the warnings...'));
                } else {
                    // Update module config to tell system that product package is installed
                    $moduleconfig['product_package'] = true;
                    $modules->saveConfig($this->className(), $moduleconfig);            
                    $this->message($this->_('Installation of SnipWire product package completet!'));
                }
                $this->wire('session')->redirect($comeFromUrl);
            }

        }

        $out = $form->render();
        //$out .= '<script>window.onunload = refreshParent; function refreshParent() { window.opener.location.reload(); }</script>';            
        return $out;
    }
    
    /**
     * Uninstall product templates, fields and demo pages required by Snipcart.
     * (Called when the URL is this module's page URL + "/uninstall-product-package/")
     *
     */
    public function ___executeUninstallProductPackage() {
        $modules = $this->wire('modules');
        $input = $this->wire('input');
        $sanitizer = $this->wire('sanitizer');
        
        $comeFromUrl = $sanitizer->url($input->get('ret'));
        $submitUninstall = $input->post('submit_uninstall');

        $this->browserTitle($this->_('SnipWire uninstaller'));
        $this->headline($this->_('Uninstall SnipWire product package'));
        
        /** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm'); 
        $form->attr('id', 'SnipWireUninstallerForm'); 
        $form->attr('action', './?ret=' . urlencode($comeFromUrl)); 
        $form->attr('method', 'post');
        
        $moduleconfig = $modules->getConfig($this->className());
        
        // Prevent uninstallation when already uninstalled
        if (!isset($moduleconfig['product_package']) || !$moduleconfig['product_package']) {

            $this->warning($this->_('SnipWire product package is not installed!'));
            $this->wire('session')->redirect($comeFromUrl);

        // Uninstall
        } else {
            
            /** @var InputfieldCheckbox $f (confirm uninstall) */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'uninstall');
            $f->attr('value', '1');
            $f->icon = 'times-circle';
            $f->label = $this->_('Uninstall');
            $f->label2 = $this->_('Confirm uninstall');
            $f->description = $this->_('Uninstall the SnipWire product package? This will delete product templates, files, fields and pages installed and required by Snipcart. This step can not be undone!');

            $form->add($f);

            /** @var InputfieldSubmit $f */
            $f = $modules->get('InputfieldSubmit');
            $f->attr('name', 'submit_uninstall');
            $f->attr('value', $this->_('Uninstall'));
            $f->icon = 'times-circle';
            $form->add($f);

            // Was the form submitted?
            if ($submitUninstall) {
                $form->processInput($input->post); 
                if ($form->get('uninstall')->value) {
                    /** @var ExstendedInstaller $installer */
                    $installer = $this->wire(new ExtendedInstaller());
                    $uninstallResources = $installer->uninstallResources(ExtendedInstaller::installerModeAll);
                    if (!$uninstallResources) {                        
                        $this->warning($this->_('Uninstallation of SnipWire product package not completet. Please check the warnings...'));
                    } else {
                        // Update module config to tell system that product package is not installed
                        $moduleconfig['product_package'] = false;
                        $modules->saveConfig($this->className(), $moduleconfig);            
                        $this->message($this->_('Uninstallation of SnipWire product package completet!'));
                    }
                    
                    $this->wire('session')->redirect($comeFromUrl);

                } else {
                    $this->warning('You need to confirm uninstallation by activating the checkbox!');
                }
            }
        }
    
        $out = $form->render();
        //$out .= '<script>window.onunload = refreshParent; function refreshParent() { window.opener.location.reload(); }</script>';            
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
        // Remove all caches created by SnipWire
        $this->wire('cache')->delete(SnipREST::settingsCacheName);
        parent::___uninstall();
    }

}
