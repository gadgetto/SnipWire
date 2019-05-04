<?php namespace ProcessWire;

/**
 * ProcessSnipWire - Snipcart dashboard integration for ProcessWire.
 * (This file is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

class ProcessSnipWire extends Process implements Module {

    /**
     * Returns information for ProcessSnipWire module.
     *
     */
    public static function getModuleInfo() {
        return array(
            'title' => __('SnipWire Dashboard'),
            'summary' => __('Snipcart dashboard integration for ProcessWire.'),
            'version' => 1, 
            'author'  => 'Martin Gartner',
            'icon' => 'shopping-cart', 
            'permission' => 'snipwire-dashboard',
            'permissions' => array(
                'snipwire-dashboard' => __('Use the SnipWire Dashboard'),
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
            'requires' => array(
                'ProcessWire>=3.0.0',
                'SnipWire',
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
        $snipREST = $this->wire('snipREST');
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

        $this->browserTitle($this->_('SnipWireDashboard'));
        $this->headline($this->_('SnipWire Dashboard'));

        $test = $snipREST->testConnection();
        $out = '<pre>' . $test . '</pre>';
        
        /*
        $snipwireConfig = $modules->getConfig('SnipWire');
        $out = '<pre>' . print_r($snipwireConfig['currencies'], true) . '</pre>';
        $out .= '<pre>' . print_r(wireDecodeJSON($snipwireConfig['currencies'][0]), true) . '</pre>';
        $out .= '<pre>' . print_r(wireDecodeJSON($snipwireConfig['currencies'][1]), true) . '</pre>';
        */
        
        

        // Dashboard markup wrapper
        $out = '<div id="snipwire-dashboard">'.$out.'</div>';

        return $out;
    }

    /**
     * Test the connection to the Snipcart REST API.
     * (Called when the URL is this module's page URL + "/test-snipcart-rest-connection/")
     *
     */
    public function ___executeTestSnipcartRestConnection() {
        $input = $this->wire('input');
        $sanitizer = $this->wire('sanitizer');
        $snipREST = $this->wire('snipREST');

        $comeFromUrl = $sanitizer->url($input->get('ret'));

        $this->browserTitle($this->_('SnipWire - Snipcart Connection Test'));
        $this->headline($this->_('SnipWire - Snipcart Connection Test'));

        if (($result = $snipREST->testConnection()) !== true) {                        
            $this->warning($result . ' ' . $this->_('Snipcart REST API connection failed! Please check your secret API keys.'));
        } else {
            $this->message($this->_('Snipcart REST API connection successfull!'));
        }
        if ($comeFromUrl) $this->wire('session')->redirect($comeFromUrl);

        $out = '';
        
        // Custom Wire notices output
        foreach ($this->wire('notices') as $notice) {
            if ($notice instanceof NoticeWarning) {
                $out .= '<p style="color: red;">' . $notice->text . '</p>';
            } elseif ($notice instanceof NoticeMessage) {
                $out .= '<p style="color: green;">' . $notice->text . '</p>';
            }
        }
        return $out;
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

        $snipwireConfig = $modules->getConfig('SnipWire');
        
        // Prevent installation when already installed
        if (isset($snipwireConfig['product_package']) && $snipwireConfig['product_package']) {
            
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
                    // Update SnipWire module config to tell system that product package is installed
                    $snipwireConfig['product_package'] = true;
                    $modules->saveConfig('SnipWire', $snipwireConfig);            
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
        
        $snipwireConfig = $modules->getConfig('SnipWire');
        
        // Prevent uninstallation when already uninstalled
        if (!isset($snipwireConfig['product_package']) || !$snipwireConfig['product_package']) {

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
                        // Update SnipWire module config to tell system that product package is not installed
                        $snipwireConfig['product_package'] = false;
                        $modules->saveConfig('SnipWire', $snipwireConfig);            
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
        parent::___uninstall();
    }

}
