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
        require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'installer/ExtendedInstaller.php';
        require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'helpers/CurrencyFormat.php';
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
        $input = $this->wire('input');
        $sniprest = $this->wire('sniprest');
        
        $this->browserTitle($this->_('SnipWire Dashboard'));
        $this->headline($this->_('SnipWire Dashboard'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }
        
        $this->_includeAssets();

        $startDate = $this->_getInputStartDate();
        $startDateSelector = $startDate ? $startDate . ' 00:00:00' : '';
        
        $endDate = $this->_getInputEndDate();
        $endDateSelector = $endDate ? $endDate . ' 23:59:59' : '';
                
        $out =  $this->_buildDateRangeFilter($startDate, $endDate);
        $out .= $this->_renderStorePerformanceBoxes($startDateSelector, $endDateSelector);
        
        /** @var InputfieldMarkup $f */
        $f = $this->modules->get('InputfieldMarkup');
        $f->label = $this->_('Performance Chart');
        $f->value = $this->_renderChartOrders();
        $f->columnWidth = 100;
        $f->collapsed = Inputfield::collapsedNever;

        $out .= $f->render();

        $fsOrdersCustomers = $modules->get('InputfieldFieldset');
        
            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Recent Orders');
            $f->value = $this->_renderTableOrders($startDateSelector, $endDateSelector);
            $f->columnWidth = 50;
            $f->collapsed = Inputfield::collapsedNever;
            
        $fsOrdersCustomers->add($f);
                
            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Top Customers');
            $f->value = $this->_renderTableCustomers($startDateSelector, $endDateSelector);
            $f->columnWidth = 50;
            $f->collapsed = Inputfield::collapsedNever;
            
        $fsOrdersCustomers->add($f);

        $out .= $fsOrdersCustomers->render();

        // Dashboard markup wrapper
        return '<div id="snipwire-dashboard">' . $out . '</div>';
    }

    /**
     * Build the period date range filter form.
     *
     * @param string $start ISO 8601 date format string
     * @param string $end ISO 8601 date format string
     * @return markup InputfieldForm
     *
     */
    private function _buildDateRangeFilter($start = '', $end = '') {
        $modules = $this->wire('modules');
        $config = $this->wire('config');

        // Define "Last 30 days" if no $startDate and/or $endDate properties provided
        if (!$start || !$end) {
            $start = date('Y-m-d', strtotime('-29 days'));
            $end = date('Y-m-d');
        }

        $pickerSettings = array(
            'form' => '#StorePerformanceFilterForm',
            'element' => '#period-picker',
            'display' => '#period-display',
            'fieldFrom' => '#period-from',
            'fieldTo' => '#period-to',
            'startDate' => $start,
            'endDate' => $end,
        );

        $pickerLocale = array(
            'format' => $this->_('YYYY-MM-DD'), // display format (based on `moment.js`)
            'separator' => '&nbsp;&nbsp;' . wireIconMarkup('arrows-h') .'&nbsp;&nbsp;',
            'applyLabel' => $this->_('Apply'),
            'cancelLabel' => $this->_('Chancel'),
            'fromLabel' => $this->_('From'),
            'toLabel' => $this->_('To'),
            'customRangeLabel' => $this->_('Custom Range'),
            'weekLabel' => $this->_('W'),
            'daysOfWeek' => array(
                $this->_('Su'),
                $this->_('Mo'),
                $this->_('Tu'),
                $this->_('We'),
                $this->_('Th'),
                $this->_('Fr'),
                $this->_('Sa'),
            ),
            'monthNames' => array(
                $this->_('January'),
                $this->_('February'),
                $this->_('March'),
                $this->_('April'),
                $this->_('Mai'),
                $this->_('June'),
                $this->_('July'),
                $this->_('August'),
                $this->_('September'),
                $this->_('October'),
                $this->_('November'),
                $this->_('December'),
            ),
        );

        $pickerRangeLabels = array(
            'today' => $this->_('Today'),
            'yesterday' => $this->_('Yesterday'),
            'last7days' => $this->_('Last 7 Days'),
            'last30days' => $this->_('Last 30 Days'),
            'thismonth' => $this->_('This Month'),
            'lastmonth' => $this->_('Last Month'),
        );
        
        // Hand over daterangepicker configuration to JS
        $config->js('pickerSettings', $pickerSettings);
        $config->js('pickerLocale', $pickerLocale);
        $config->js('pickerRangeLabels', $pickerRangeLabels);
        
        /** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm'); 
        $form->attr('id', 'StorePerformanceFilterForm');
        $form->method = 'get';
        $form->action = './';

            /** @var InputfieldFieldset $fsFilters */
            $fsFilters = $modules->get('InputfieldFieldset');
            $fsFilters->icon = 'line-chart';
            $fsFilters->label = $this->_('Store Performance');

                // Period date range picker with hidden form fields
                $markup =
                '<input type="hidden" id="period-from" name="periodFrom" value="' . $start . '">' .
                '<input type="hidden" id="period-to" name="periodTo" value="' . $end . '">' .
                '<div class="period-picker-container">' .
                    '<div id="period-picker" aria-label="' . $this->_('Store performance date range selector') .'">' .
                        wireIconMarkup('calendar') . ' <span id="period-display"></span> ' . wireIconMarkup('caret-down') .
                    '</div>' .
                '</div>';

                /** @var InputfieldMarkup $f */
                $f = $modules->get('InputfieldMarkup');
                $f->label = $this->_('Period Range Picker');
                $f->skipLabel = Inputfield::skipLabelHeader;
                $f->value = $markup;
                $f->collapsed = Inputfield::collapsedNever;

            $fsFilters->add($f);            

        $form->add($fsFilters);        

        return $form->render(); 
    }

    /**
     * Render the store performance boxes.
     *
     * @param string $start ISO 8601 date format string
     * @param string $end ISO 8601 date format string
     * @return markup Custom HTML
     *
     */
    private function _renderStorePerformanceBoxes($start, $end) {
        $sniprest = $this->wire('sniprest');

        $selector = array(
            'from' => $start ? strtotime($start) : '', // UNIX timestamps required
            'to' => $end ? strtotime($end) : '', // UNIX timestamps required
        );

        $result = $sniprest->getPerformance($selector, 300);
        if ($result === false) {
            $this->error(SnipREST::getMessagesText('connection_failed'));
            return '';
        }
        
        $values = array(
            'orders' => $result['ordersCount'],
            'sales' => CurrencyFormat::format($result['ordersSales'], 'usd'), // @todo: handle currency(s)!
            'average' => CurrencyFormat::format($result['averageOrdersValue'], 'usd'), // @todo: handle currency(s)!
            'customers' => array(
                'new' => $result['customers']['newCustomers'],
                'returning' => $result['customers']['returningCustomers'],
            )
        );

        $boxes = array(
            'orders' => $this->_('Orders'),
            'sales' => $this->_('Sales'),
            'average' => $this->_('Average Order'),
            'customers' => $this->_('Customers'),
        );
        
        $out = '';
        
        foreach ($boxes as $box => $label) {
            $out .= 
            '<div class="snipwire-perf-box">' .
                '<div class="snipwire-perf-box-header">' .
                    $label .
                '</div>' .
                '<div class="snipwire-perf-box-body">';
            
            if ($box == 'customers' && is_array($values[$box])) {
                $out .=
                '<div class="customers-multivalue">' .
                    '<div>' .
                        '<span>' . $values[$box]['new'] . '</span>' .
                        '<small>' . $this->_('New') . '</small>' .
                    '</div>' .
                    '<div>' .
                        '<span>' . $values[$box]['returning'] . '</span>' .
                        '<small>' . $this->_('Returning') . '</small>' .
                    '</div>' .
                '</div>';
            } else {
                $out .=
                '<small>' . $values[$box] . '</small>';
            }
            
            $out .=
                '</div>' .
            '</div>';
        }
        return '<div class="snipwire-perf-boxes-container">' . $out . '</div>';
    }

    /**
     * Render the orders chart for a period.
     *
     * @param array $orders
     * @return markup Chart
     *
     */
    private function _renderChartOrders() {
        $modules = $this->wire('modules');
        $config = $this->wire('config');

        $out =
        '<canvas id="snipwire-chart-recentorders"' .
        ' aria-label="' . $this->_('Snipcart Performance Chart') . '"' .
        ' role="img">' .
            $this->_('The Snipcart Performance Chart can not be rendered. Your browser does not support the canvas element.') .
        '</canvas>';
        
        /*
        // get number of skyscrapers
        $counts = [];
        $counts[] = $this->pages->count('template=skyscraper,height<=50');
        $counts[] = $this->pages->count('template=skyscraper,height>50,height<=150');
        $counts[] = $this->pages->count('template=skyscraper,height>150');
        $counts = implode(',', $counts);
        
        $out = "<canvas id='chart' data-counts='$counts'></canvas>";
        */
        
        return $out;
    }

    /**
     * Render the orders table for a period.
     *
     * @param string $start ISO 8601 date format string
     * @param string $end ISO 8601 date format string
     * @return markup MarkupAdminDataTable | custom html with `no orders` display 
     *
     */
    private function _renderTableOrders($start, $end) {
        $modules = $this->wire('modules');
        $sniprest = $this->wire('sniprest');

        $selector = array(
            'limit' => 10,
            'from' => $start,
            'to' => $end,
        );

        $orders = $sniprest->getOrdersItems($selector, 300);
        
        if ($orders === false) {
            
            $this->error(SnipREST::getMessagesText('connection_failed'));
            return '';
            
        } elseif ($orders) {
            
            /** @var MarkupAdminDataTable $table */
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->id = 'snipwire-orders-recent-table';
            $table->setSortable(false);
            $table->headerRow(array(
                $this->_('Invoice'),
                $this->_('Placed'),
                $this->_('Total'),
            ));
            foreach ($orders as $order) {
                $table->row(array(
                    $order['invoiceNumber'] => '#',
                    wireDate('relative', $order['creationDate']),
                    CurrencyFormat::format($order['total'], $order['currency']),
                ));
            }
            return $table->render();
            
        } else {
            
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No orders in selected period') .
            '</div>';
            return $out;
        }
    }

    /**
     * Render the customers table for a period.
     *
     * @param string $start ISO 8601 date format string
     * @param string $end ISO 8601 date format string
     * @return markup MarkupAdminDataTable | custom html with `no customers` display 
     *
     */
    private function _renderTableCustomers($start, $end) {
        $modules = $this->wire('modules');
        $sniprest = $this->wire('sniprest');

        $selector = array(
            'limit' => 10,
            'from' => $start,
            'to' => $end,
        );

        $customers = $sniprest->getCustomersItems($selector, 300);
        
        if ($customers === false) {
            
            $this->error(SnipREST::getMessagesText('connection_failed'));
            return '';
            
        } elseif ($customers) {
            
            /** @var MarkupAdminDataTable $table */
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->id = 'snipwire-customers-recent-table';
            $table->setSortable(false);
            $table->headerRow(array(
                $this->_('Name'),
                $this->_('Orders'),
                $this->_('Total Spent'),
            ));
            foreach ($customers as $customer) {
                $table->row(array(
                    $customer['billingAddress']['fullName'] => '#',
                    $customer['statistics']['ordersCount'],
                    CurrencyFormat::format($customer['statistics']['ordersAmount'], 'usd'), // @todo: handle currency!
                ));
            }
            return $table->render();
            
        } else {
            
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No customers in selected period') .
            '</div>';
            return $out;
        }
    }

    /**
     * Get the start date for date range filter from input.
     *
     * @return string Sanitized ISO 8601 or null
     * 
     */
    private function _getInputStartDate() {
        return $this->wire('input')->get->date('periodFrom', 'Y-m-d', array('strict' => true));
    }

    /**
     * Get the end date for date range filter from input.
     *
     * @return string Sanitized ISO 8601 or null
     * 
     */
    private function _getInputEndDate() {
        return $this->wire('input')->get->date('periodTo', 'Y-m-d', array('strict' => true));
    }

    /**
     * Include asset files for SnipWire Dashboard.
     *
     */
    private function _includeAssets() {
        $config = $this->wire('config');

        $info = $this->getModuleInfo();
        $version = (int) isset($info['version']) ? $info['version'] : 0;
        $versionAdd = "?v=$version";

        // Include assets
        $config->styles->add($this->config->urls->SnipWire . 'vendor/daterangepicker.js/daterangepicker.css?v=3.0.5');
        $config->styles->add($this->config->urls->SnipWire . 'assets/styles/daterangepicker-custom.css' . $versionAdd);
        $config->styles->add($this->config->urls->SnipWire . 'vendor/chart.js/Chart.min.css?v=2.8.0');
        
        $config->scripts->add($this->config->urls->SnipWire . 'vendor/moment.js/moment.min.js?v=2.24.0');
        $config->scripts->add($this->config->urls->SnipWire . 'vendor/daterangepicker.js/daterangepicker.min.js?v=3.0.5');
        $config->scripts->add($this->config->urls->SnipWire . 'assets/scripts/PerformanceRangePicker.min.js' . $versionAdd);
        $config->scripts->add($this->config->urls->SnipWire . 'vendor/chart.js/Chart.min.js?v=2.8.0');
        $config->scripts->add($this->config->urls->SnipWire . 'assets/scripts/PerformanceChart.min.js' . $versionAdd);
    }

    /**
     * Test the connection to the Snipcart REST API.
     * (Called when the URL is this module's page URL + "/test-snipcart-rest-connection/")
     *
     */
    public function ___executeTestSnipcartRestConnection() {
        $input = $this->wire('input');
        $sanitizer = $this->wire('sanitizer');
        $sniprest = $this->wire('sniprest');

        $comeFromUrl = $sanitizer->url($input->get('ret'));

        $this->browserTitle($this->_('SnipWire - Snipcart Connection Test'));
        $this->headline($this->_('SnipWire - Snipcart Connection Test'));

        if (($result = $sniprest->testConnection()) !== true) {                        
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
