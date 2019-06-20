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
            'nav' => array(
                array(
                    'url' => 'orders/', 
                    'label' => __('Orders'), 
                    'icon' => 'list-alt', 
                ),
                array(
                    'url' => 'customers/', 
                    'label' => __('Customers'), 
                    'icon' => 'user', 
                ),
                array(
                    'url' => 'products/', 
                    'label' => __('Products'), 
                    'icon' => 'cube', 
                ),
            ),
            'requires' => array(
                'ProcessWire>=3.0.0',
                'SnipWire',
            ),
        );
    }

    const assetsIncludeDaterangePicker = 1;
    const assetsIncludeApexCharts = 2;
    const assetsIncludeAll = 3;

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
     * Initialize the module.
     * (Called before any execute functions)
     * 
     */
    public function init() {
        parent::init();
    }    

    /**
     * The SnipWire dashboard page.
     *
     * @return page markup
     *
     */
    public function ___execute() {
        $modules = $this->wire('modules');
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
        
        $this->_includeAssets(self::assetsIncludeAll);

        //if ($this->_getInputAction() == 'refresh') bd('refresh');
        $startDate = $this->_getInputStartDate();
        $startDateSelector = $startDate ? $startDate . ' 00:00:00' : '';
        
        $endDate = $this->_getInputEndDate();
        $endDateSelector = $endDate ? $endDate . ' 23:59:59' : '';
                
        $out = $this->_buildDateRangeFilter($startDate, $endDate);

        $packages = $sniprest->getDashboardData($startDateSelector, $endDateSelector);
        $dashboard = $this->_extractDataPackages($packages);

        if (!$dashboard) {
            $out .=
            '<div class="dashboard-empty">' .
                $this->_('Dashboard data could not be fetched') .
            '</div>';
            return $this->_wrapDashboardOutput($out);
        }

        $out .= $this->_renderPerformanceBoxes($dashboard[SnipRest::resourcePathDataPerformance]);
        $out .= $this->_renderChart($dashboard[SnipRest::resourcePathDataOrdersSales], $dashboard[SnipRest::resourcePathDataOrdersCount]);

        /** @var InputfieldForm $form */
        $wrapper = $modules->get('InputfieldForm'); 

            /** @var InputfieldFieldset $fsTop */
            $fsTop = $modules->get('InputfieldFieldset');
            $fsTop->icon = 'bar-chart';
            $fsTop->label = $this->_('Top Store Actions');
            $fsTop->wrapClass = 'bottomSpace';
    
                /** @var InputfieldMarkup $f */
                $f = $modules->get('InputfieldMarkup');
                $f->label = $this->_('Top Customers');
                $f->value = $this->_renderTableCustomers($dashboard[SnipRest::resourcePathCustomers]);
                $f->columnWidth = 50;
                $f->collapsed = Inputfield::collapsedNever;
                
            $fsTop->add($f);
            
                /** @var InputfieldMarkup $f */
                $f = $modules->get('InputfieldMarkup');
                $f->label = $this->_('Top Products');
                $f->value = $this->_renderTableProducts($dashboard[SnipRest::resourcePathProducts]);
                $f->columnWidth = 50;
                $f->collapsed = Inputfield::collapsedNever;
                
            $fsTop->add($f);
            
        $wrapper->add($fsTop);

        $out .= $wrapper->render();

        /** @var InputfieldForm $form */
        $wrapper = $modules->get('InputfieldForm'); 
            
            /** @var InputfieldFieldset $fsOrders */
            $fsOrders = $modules->get('InputfieldFieldset');
            $fsOrders->icon = 'list-alt';
            $fsOrders->label = $this->_('Recent Orders');
            $fsOrders->wrapClass = 'bottomSpace';

                /** @var InputfieldMarkup $f */
                $f = $modules->get('InputfieldMarkup');
                $f->value = $this->_renderTableOrders($dashboard[SnipRest::resourcePathOrders]);
                $f->columnWidth = 100;
                $f->skipLabel = Inputfield::skipLabelHeader;
                $f->collapsed = Inputfield::collapsedNever;
                
            $fsOrders->add($f);
        
        $wrapper->add($fsOrders);

        $out .= $wrapper->render();

        /** @var InputfieldWrapper $wrapper */
        $wrapper = new InputfieldWrapper();

            /** @var InputfieldButton $btn */
            $btn = $modules->get('InputfieldButton');
            $btn->id = 'refresh-data';
            $btn->href = './?action=refresh';
            $btn->value = $this->_('Refresh');
            $btn->icon = 'refresh';
            $btn->showInHeader();

        $wrapper->add($btn);

        $out .= $wrapper->render();

        return $this->_wrapDashboardOutput($out);
    }

    /**
     * The SnipWire Snipcart Orders page.
     *
     * @return page markup
     *
     */
    public function ___executeOrders() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sniprest = $this->wire('sniprest');
        
        $this->browserTitle($this->_('Snipcart Orders'));
        $this->headline($this->_('Snipcart Orders'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

    }

    /**
     * The SnipWire Snipcart Customers page.
     *
     * @return page markup
     *
     */
    public function ___executeCustomers() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sniprest = $this->wire('sniprest');
        
        $this->browserTitle($this->_('Snipcart Customers'));
        $this->headline($this->_('Snipcart Customers'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

    }

    /**
     * The SnipWire Snipcart Products page.
     *
     * @return page markup
     *
     */
    public function ___executeProducts() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sniprest = $this->wire('sniprest');
        
        $this->browserTitle($this->_('Snipcart Products'));
        $this->headline($this->_('Snipcart Products'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

    }

    /**
     * Redirect to SniWire module settings.
     *
     * @return page Markup
     *
     */
    public function ___executeSettings() {
        $redirectTo = $this->wire('modules')->getModuleEditUrl('SnipWire');
        $this->wire('session')->redirect($redirectTo);
        
        // Should never be rendered ... (just to be sure)
        
        /** @var InputfieldButton $btn */
        $btn = $this->wire('modules')->get('InputfieldButton');
        $btn->href = $redirectTo;
        $btn->value = $this->_('Settings');
        $btn->icon = 'gear';
        
        $out = $btn->render();
        return $out;
    }

    /**
     * Extract data packages from Snipcart API results and create new sanitized array ready for rendering.
     *
     * @param array $packages The raw data array returned by Snipcart API
     * @return mixed The sanitized array ready for rendering or false
     *
     */
    private function _extractDataPackages($packages) {
        if (empty($packages) || !is_array($packages)) return false;
        
        $dashboard = array();
        foreach ($packages as $key => $package) {
            if (strpos($key, SnipRest::resourcePathDataPerformance)) {
                
                $dashboard[SnipRest::resourcePathDataPerformance] = isset($package[CurlMulti::resultKeyContent])
                    ? $package[CurlMulti::resultKeyContent]
                    : false;
                
            } elseif (strpos($key, SnipRest::resourcePathDataOrdersSales)) {
                
                $dashboard[SnipRest::resourcePathDataOrdersSales] = isset($package[CurlMulti::resultKeyContent])
                    ? $package[CurlMulti::resultKeyContent]
                    : false;
                
            } elseif (strpos($key, SnipRest::resourcePathDataOrdersCount)) {
                
                $dashboard[SnipRest::resourcePathDataOrdersCount] = isset($package[CurlMulti::resultKeyContent])
                    ? $package[CurlMulti::resultKeyContent]
                    : false;
                
            } elseif (strpos($key, SnipRest::resourcePathCustomers)) {
                
                $dashboard[SnipRest::resourcePathCustomers] = isset($package[CurlMulti::resultKeyContent]['items'])
                    ? $package[CurlMulti::resultKeyContent]['items']
                    : false;
                
            } elseif (strpos($key, SnipRest::resourcePathProducts)) {
                
                $dashboard[SnipRest::resourcePathProducts] = isset($package[CurlMulti::resultKeyContent]['items'])
                    ? $package[CurlMulti::resultKeyContent]['items']
                    : false;
                
            } elseif (strpos($key, SnipRest::resourcePathOrders)) {
                
                $dashboard[SnipRest::resourcePathOrders] = isset($package[CurlMulti::resultKeyContent]['items'])
                    ? $package[CurlMulti::resultKeyContent]['items']
                    : false;
            }
        }
        unset($packages, $key, $package); // free space
        
        return $dashboard;
    }

    /**
     * SnipWire dashboard output wrapper.
     *
     * @return markup
     *
     */
    private function _wrapDashboardOutput($out) {
        return '<div id="SnipwireDashboard">' . $out . '</div>';
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
            $fsFilters->wrapClass = 'bottomSpace';

                // Period date range picker with hidden form fields
                $markup =
                '<input type="hidden" id="period-from" name="periodFrom" value="' . $start . '">' .
                '<input type="hidden" id="period-to" name="periodTo" value="' . $end . '">' .
                '<div id="PeriodPickerContainer">' .
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
     * @param array $results
     * @return markup Custom HTML
     *
     */
    private function _renderPerformanceBoxes($results) {
        
        if (!empty($results) && is_array($results)) {
            
            $values = array(
                'orders' => $results['ordersCount'],
                'sales' => CurrencyFormat::format($results['ordersSales'], 'usd'), // @todo: handle currency(s)!
                'average' => CurrencyFormat::format($results['averageOrdersValue'], 'usd'), // @todo: handle currency(s)!
                'customers' => array(
                    'new' => $results['customers']['newCustomers'],
                    'returning' => $results['customers']['returningCustomers'],
                )
            );
            
        } else {
            
            $this->error($this->_('Values for store performance boxes could not be fetched'));
            $errorIcon = wireIconMarkup('exclamation-triangle');
            $values = array(
                'orders' => $errorIcon,
                'sales' => $errorIcon, 
                'average' => $errorIcon,
                'customers' => array(
                    'new' => $errorIcon,
                    'returning' => $errorIcon,
                )
            );
            
        }

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
        return '<div id="PerformanceBoxesContainer" class="bottomSpace">' . $out . '</div>';
    }

    /**
     * Render the chart.
     *
     * @param array $salesData
     * @param array $ordersData
     * @return markup Chart
     *
     */
    private function _renderChart($salesData, $ordersData) {
        $config = $this->wire('config');

        $salesCategories = array();
        $ordersCategories = array();
        $sales = array();
        $orders = array();

        // Split results in categories & data (prepare for ApexCharts)
        if (!empty($salesData['data']) && is_array($salesData['data'])) {
            foreach ($salesData['data'] as $item) {
                $salesCategories[] = $item['name'];
                $sales[] = $item['value'];
            }
        }
        if (!empty($ordersData['data']) && is_array($ordersData['data'])) {
            foreach ($ordersData['data'] as $item) {
                $ordersCategories[] = $item['name'];
                $orders[] = intval($item['value']);
            }
        }

        // Take categories either from sales or from orders (both are same)
        if ($salesCategories) {
            $categories = $salesCategories;
        } elseif ($ordersCategories) {
            $categories = $ordersCategories;
        } else {
            $categories = array();
        }
        
        // Hand over chartData to JS
        $config->js('chartData', array(
            'categories' => $categories,
            'sales' => $sales,
            'orders' => $orders,
            'salesLabel' => $this->_('Sales'),
            'ordersLabel' => $this->_('Orders'),
            'noDataText' => $this->_('No Chart data available'),
        ));

        $out =
        '<div id="PerformanceChart"' .
        ' class="bottomSpace"' .
        ' aria-label="' . $this->_('Snipcart Performance Chart') . '"' .
        ' role="img">' .
        '</div>';
        
        return $out;
    }

    /**
     * Render the customers table.
     *
     * @param array $items
     * @return markup MarkupAdminDataTable | custom html with `no customers` display 
     *
     */
    private function _renderTableCustomers($items) {
        $modules = $this->wire('modules');
                    
        if (!empty($items)) {

            $out = '';
            /** @var MarkupAdminDataTable $table */
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->id = 'snipwire-top-customers-table';
            $table->setSortable(false);
            $table->setResizable(false);
            $table->headerRow(array(
                $this->_('Name'),
                $this->_('Orders'),
                $this->_('Total Spent'),
            ));
            foreach ($items as $item) {
                $table->row(array(
                    $item['billingAddress']['fullName'] => '#',
                    $item['statistics']['ordersCount'],
                    CurrencyFormat::format($item['statistics']['ordersAmount'], 'usd'), // @todo: handle currency!
                ));
            }
            $out .= $table->render();

            /** @var InputfieldButton $btn */
            $btn = $modules->get('InputfieldButton');
            $btn->href = './customers';
            $btn->value = $this->_('All Customers');
            $btn->icon = 'user';
            $btn->setSecondary(true);
            $btn->set('small', true);

            $out .= $btn->render();
            return $out;
            
        } else {
            
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No customers in selected period') .
            '</div>';
            return $out;
        }
    }

    /**
     * Render the products table.
     *
     * @param array $items
     * @return markup MarkupAdminDataTable | custom html with `no products` display 
     *
     */
    private function _renderTableProducts($items) {
        $modules = $this->wire('modules');

        if (!empty($items)) {

            $out = '';
            /** @var MarkupAdminDataTable $table */
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->id = 'snipwire-top-products-table';
            $table->setSortable(false);
            $table->setResizable(false);
            $table->headerRow(array(
                $this->_('Name'),
                $this->_('Price'),
                $this->_('# Sales'),
                $this->_('Sales'),
            ));
            foreach ($items as $item) {
                $table->row(array(
                    $item['name'] => '#',
                    CurrencyFormat::format($item['price'], 'usd'), // @todo: handle currency!
                    $item['statistics']['numberOfSales'],
                    CurrencyFormat::format($item['statistics']['totalSales'], 'usd'), // @todo: handle currency!
                ));
            }
            $out .= $table->render();

            /** @var InputfieldButton $btn */
            $btn = $modules->get('InputfieldButton');
            $btn->href = './products';
            $btn->value = $this->_('All Products');
            $btn->icon = 'cube';
            $btn->setSecondary(true);
            $btn->set('small', true);

            $out .= $btn->render();
            return $out;
            
        } else {
            
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No products in selected period') .
            '</div>';
            return $out;
        }
    }

    /**
     * Render the orders table.
     *
     * @param array $items
     * @return markup MarkupAdminDataTable | custom html with `no orders` display 
     *
     */
    private function _renderTableOrders($items) {
        $modules = $this->wire('modules');

        if (!empty($items)) {
            
            $out = '';
            /** @var MarkupAdminDataTable $table */
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->id = 'snipwire-recent-orders-table';
            $table->setSortable(false);
            $table->setResizable(false);
            $table->headerRow(array(
                $this->_('Invoice #'),
                $this->_('Placed on'),
                $this->_('Placed by'),
                $this->_('Countrty'),
                $this->_('Payment Status'),
                $this->_('Total'),
            ));
            foreach ($items as $item) {
                $table->row(array(
                    $item['invoiceNumber'] => '#',
                    wireDate('relative', $item['creationDate']),
                    $item['user']['billingAddress']['fullName'],
                    $item['billingAddressCountry'],
                    $item['paymentStatus'],
                    CurrencyFormat::format($item['total'], $item['currency']),
                ));
            }
            $out .= $table->render();

            /** @var InputfieldButton $btn */
            $btn = $modules->get('InputfieldButton');
            $btn->href = './orders';
            $btn->value = $this->_('All Orders');
            $btn->icon = 'list-alt';
            $btn->setSecondary(true);
            $btn->set('small', true);

            $out .= $btn->render();
            return $out;
            
        } else {
            
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No orders in selected period') .
            '</div>';
            return $out;
        }
    }

    /**
     * Get the sanitized start date for date range filter from input.
     *
     * @return string Sanitized ISO 8601 or null
     * 
     */
    private function _getInputStartDate() {
        return $this->wire('input')->get->date('periodFrom', 'Y-m-d', array('strict' => true));
    }

    /**
     * Get the sanitized end date for date range filter from input.
     *
     * @return string Sanitized ISO 8601 or null
     * 
     */
    private function _getInputEndDate() {
        return $this->wire('input')->get->date('periodTo', 'Y-m-d', array('strict' => true));
    }

    /**
     * Get the sanitized action URL param from input.
     *
     * @return string Action URL param
     * 
     */
    private function _getInputAction() {
        return $this->wire('input')->get->entities('action');
    }

    /**
     * Include asset files for SnipWire Dashboard.
     *
     * @param integer $mode
     *
     */
    private function _includeAssets($mode = self::assetsIncludeAll) {
        $config = $this->wire('config');

        $info = $this->getModuleInfo();
        $version = (int) isset($info['version']) ? $info['version'] : 0;
        $versionAdd = "?v=$version";

        if ($mode & self::assetsIncludeAll || $mode & self::assetsIncludeDaterangePicker || $mode & self::assetsIncludeApexCharts) {
            // Include moment.js JS assets
            $config->scripts->add($config->urls->SnipWire . 'vendor/moment.js/moment.min.js?v=2.24.0');
        }
        if ($mode & self::assetsIncludeDaterangePicker) {
            // Include daterangepicker CSS/JS assets
            $config->styles->add($config->urls->SnipWire . 'vendor/daterangepicker.js/daterangepicker.css?v=3.0.5');
            $config->styles->add($config->urls->SnipWire . 'assets/styles/PerformanceRangePicker.css' . $versionAdd);
            $config->scripts->add($config->urls->SnipWire . 'vendor/daterangepicker.js/daterangepicker.min.js?v=3.0.5');
            $config->scripts->add($config->urls->SnipWire . 'assets/scripts/PerformanceRangePicker.min.js' . $versionAdd);
        }
        if ($mode & self::assetsIncludeApexCharts) {
            // Include ApexCharts CSS/JS assets
            $config->styles->add($config->urls->SnipWire . 'assets/styles/PerformanceChart.css' . $versionAdd);
            $config->scripts->add($config->urls->SnipWire . 'vendor/apexcharts.js/apexcharts.min.js?v=3.6.9');
            $config->scripts->add($config->urls->SnipWire . 'assets/scripts/PerformanceChart.min.js' . $versionAdd);
        }
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
