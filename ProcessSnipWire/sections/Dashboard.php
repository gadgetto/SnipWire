<?php
namespace SnipWire\ProcessSnipWire\Sections;

/**
 * Dashboard trait - sections file for ProcessSnipWire.module.php.
 * (This file is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

use SnipWire\Helpers\CurrencyFormat;
use SnipWire\Services\SnipREST;
use SnipWire\Services\WireHttpExtended;
use ProcessWire\Inputfield;
use ProcessWire\InputfieldDatetime;

trait Dashboard {
    /**
     * The SnipWire Dashboard page.
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

        $this->_includeAssets(
            self::assetsIncludeDateRangePicker | 
            self::assetsIncludeCurrencyPicker | 
            self::assetsIncludeApexCharts
        );

        $action = $this->_getInputAction();
        $forceRefresh = false;
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        } elseif ($action == 'refresh_all') {
            $sniprest->deleteFullCache();
            $this->message(SnipREST::getMessagesText('full_cache_refreshed'));
        } elseif ($action == 'reset') {
            $this->message($this->_('Store performance date range set to default.'));
            $this->_resetDateRange();
        }

        $startDate = $this->_getStartDate();
        $endDate = $this->_getEndDate();
        $currency = $this->_getCurrency();

        $packages = $sniprest->getDashboardData(
            "$startDate 00:00:00",
            "$endDate 23:59:59",
            $currency,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );
        $dashboard = $this->_prepareDashboardData($packages);
        unset($packages);

        if (!$dashboard) {
            $out =
            '<div class="dashboard-empty">' .
                $this->_('Dashboard data could not be fetched') .
            '</div>';
            return $this->_wrapDashboardOutput($out);
        }

        $out = $this->_buildDashboardFilter($startDate, $endDate, $currency);

        $out .= $this->_renderPerformanceBoxes(
            $dashboard[SnipRest::resPathDataPerformance],
            $currency
        );

        $chart = $this->_renderChart(
            $dashboard[SnipRest::resPathDataOrdersSales],
            $dashboard[SnipRest::resPathDataOrdersCount],
            $currency
        );

        /** @var InputfieldForm $wrapper */
        $wrapper = $modules->get('InputfieldForm');

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Performance Chart');
            $f->icon = self::iconDasboard;
            $f->value = $chart;
            $f->columnWidth = 100;
            $f->collapsed = Inputfield::collapsedNever;
            
        $wrapper->add($f);

        $out .= $wrapper->render();

        /** @var InputfieldForm $wrapper */
        $wrapper = $modules->get('InputfieldForm');

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Top Customers');
            $f->icon = self::iconCustomer;
            $f->value = $this->_renderTableTopCustomers($dashboard[SnipRest::resPathCustomers]);
            $f->columnWidth = 50;
            $f->collapsed = Inputfield::collapsedNever;

        $wrapper->add($f);

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Top Products');
            $f->icon = self::iconProduct;
            $f->value = $this->_renderTableTopProducts($dashboard[SnipRest::resPathProducts], $currency);
            $f->columnWidth = 50;
            $f->collapsed = Inputfield::collapsedNever;

        $wrapper->add($f);

        $out .= $wrapper->render();

        /** @var InputfieldForm $wrapper */
        $wrapper = $modules->get('InputfieldForm');

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Recent Orders');
            $f->icon = self::iconOrder;
            $f->value = $this->_renderTableRecentOrders($dashboard[SnipRest::resPathOrders]);
            $f->columnWidth = 100;
            $f->collapsed = Inputfield::collapsedNever;
            
        $wrapper->add($f);

        $out .= $wrapper->render();

        $out .= $this->_renderActionButtons();

        return $this->_wrapDashboardOutput($out);
    }

    /**
     * Extract data packages from Snipcart API results and create new 
     * array ready for dashboard rendering.
     *
     * @param array $packages The raw data array returned by Snipcart API
     * @return mixed The prepared array ready for rendering or false
     *
     */
    private function _prepareDashboardData($packages) {
        if (empty($packages) || !is_array($packages)) return false;

        // Initialize dashboard
        $dashboard = array(
            SnipRest::resPathDataPerformance => array(),
            SnipRest::resPathDataOrdersSales => array(),
            SnipRest::resPathDataOrdersCount => array(),
            SnipRest::resPathCustomers => array(),
            SnipRest::resPathProducts => array(),
            SnipRest::resPathOrders => array(),
        );

        $ordersSales = 0.0;
        $ordersCount = 0;
        $averageOrdersValue = 0.0;

        foreach ($packages as $key => $package) {
            
            if (strpos($key, SnipRest::resPathDataPerformance) !== false) {

                // Performance data is NOT currency dependent therefore some values need to be 
                // determined from other currency dependent sources and will be replaced later.
                // (marked with "calc" in sample array)
                
                /*
                Sample performance array:
                [
                    "ordersSales" => calc,
                    "ordersCount" => calc,
                    "averageCustomerValue" => not in use,
                    "taxesCollected" => not in use,
                    "shippingCollected" => not in use,
                    "customers" => [
                        "newCustomers" => 14,
                        "returningCustomers" => 2
                    ],
                    "averageOrdersValue" => calc,
                    "totalRecovered" => not in use
                ]
                */
                $dashboard[SnipRest::resPathDataPerformance] = $package;
                
            } elseif (strpos($key, SnipRest::resPathDataOrdersSales) !== false) {
                
                $dashboard[SnipRest::resPathDataOrdersSales] = $package;

                // Calc sales sum
                if (isset($dashboard[SnipRest::resPathDataOrdersSales][WireHttpExtended::resultKeyContent]['data'])) {
                    $data = $dashboard[SnipRest::resPathDataOrdersSales][WireHttpExtended::resultKeyContent]['data'];
                    foreach ($data as $item) {
                        $ordersSales += $item['value'];
                    }
                }

            } elseif (strpos($key, SnipRest::resPathDataOrdersCount) !== false) {
                
                $dashboard[SnipRest::resPathDataOrdersCount] = $package;
                
                // Calc orders count
                if (isset($dashboard[SnipRest::resPathDataOrdersCount][WireHttpExtended::resultKeyContent]['data'])) {
                    $data = $dashboard[SnipRest::resPathDataOrdersCount][WireHttpExtended::resultKeyContent]['data'];
                    foreach ($data as $item) {
                        $ordersCount += $item['value'];
                    }
                }
                
            } elseif (strpos($key, SnipRest::resPathCustomers) !== false) {
                
                $dashboard[SnipRest::resPathCustomers] = isset($package[WireHttpExtended::resultKeyContent]['items'])
                    ? $package[WireHttpExtended::resultKeyContent]['items']
                    : array();
                
            } elseif (strpos($key, SnipRest::resPathProducts) !== false) {
                
                $dashboard[SnipRest::resPathProducts] = isset($package[WireHttpExtended::resultKeyContent]['items'])
                    ? $package[WireHttpExtended::resultKeyContent]['items']
                    : array();
                
            } elseif (strpos($key, SnipRest::resPathOrders) !== false) {
                
                $dashboard[SnipRest::resPathOrders] = isset($package[WireHttpExtended::resultKeyContent]['items'])
                    ? $package[WireHttpExtended::resultKeyContent]['items']
                    : array();
            }
        }

        // Replace performance data with currency dependent values
        if ($ordersSales && $ordersCount) $averageOrdersValue = $ordersSales / $ordersCount;
        $calculated = array(
            'ordersSales' => $ordersSales,
            'ordersCount' => $ordersCount,
            'averageOrdersValue' => $averageOrdersValue,
        );
        $dashboard[SnipRest::resPathDataPerformance][WireHttpExtended::resultKeyContent] = array_merge(
            $dashboard[SnipRest::resPathDataPerformance][WireHttpExtended::resultKeyContent],
            $calculated
        );

        unset($packages, $key, $package); // free space
        
        return $dashboard;
    }

    /**
     * Build the dashboard filter form.
     *
     * @param string $start ISO 8601 date format string
     * @param string $end ISO 8601 date format string
     * @param string $currency Currency string
     * @return markup InputfieldForm
     *
     */
    private function _buildDashboardFilter($start = '', $end = '', $currency = '') {
        $modules = $this->wire('modules');
        $config = $this->wire('config');
        $input = $this->wire('input');
        
        $periodDateFormat = 'Y-m-d';
        $periodPlaceholder = 'YYYY-MM-DD';

        // Define "Last 30 days" if no $start and/or $end properties provided
        if (!$start || !$end) {
            $start = date('Y-m-d', strtotime('-29 days'));
            $end = date('Y-m-d');
        }

        $pickerSettings = array(
            'form' => '#StorePerformanceFilterForm',
            'resetButton' => '#DateRangeReset',
            'fieldSelect' => '#periodSelect',
            'fieldFrom' => '#periodFrom',
            'fieldTo' => '#periodTo',
            'fieldCurrency' => '#currency-picker',
            'startDate' => $start,
            'endDate' => $end,
            'currency' => $currency,
            'dateFormat' => 'YYYY-MM-DD', // for moment.js
        );

        $perioRangeLabels = array(
            'today' => $this->_('Today'),
            'yesterday' => $this->_('Yesterday'),
            'last7days' => $this->_('Last 7 Days'),
            'last30days' => $this->_('Last 30 Days'),
            'thismonth' => $this->_('This Month'),
            'lastmonth' => $this->_('Last Month'),
            'custom' =>  $this->_('Custom range'),
        );

        $pickerMessages = array(
            'fromAfterTo' => $this->_('Period from can\'t be after Period to'),
            'yearAfterNow' => $this->_('The chosen year lies in the future'),
        );

        $periodRanges = array(
            'today' => array(
                'start' => date('Y-m-d'),
                'end' => date('Y-m-d'),
            ),
            'yesterday' => array(
                'start' => date('Y-m-d', strtotime('-1 day')),
                'end' => date('Y-m-d', strtotime('-1 day')),
            ),
            'last7days' => array(
                'start' => date('Y-m-d', strtotime('-6 day')),
                'end' => date('Y-m-d'),
            ),
            'last30days' => array(
                'start' => date('Y-m-d', strtotime('-29 day')),
                'end' => date('Y-m-d'),
            ),
            'thismonth' => array(
                'start' => date('Y-m-d', strtotime('first day of this month')),
                'end' => date('Y-m-d', strtotime('last day of this month')),
            ),
            'lastmonth' => array(
                'start' => date('Y-m-d', strtotime('first day of last month')),
                'end' => date('Y-m-d', strtotime('last day of last month')),
            ),
            'custom' => array(
                'start' => '',
                'end' => '',
            ),
        );

        // Determine if this is one of the predefined ranges
        $knownRange = 'custom';
        foreach ($periodRanges as $range => $date) {
            if ($date['start'] == $start && $date['end'] == $end) {
                $knownRange = $range;
                break;
            }
        }

        // Hand over configuration to JS
        $config->js('filterSettings', $pickerSettings);
        $config->js('periodRanges', $periodRanges);
        $config->js('pickerMessages', $pickerMessages);

        /** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm'); 
        $form->attr('id', 'StorePerformanceFilterForm');
        $form->method = 'get';
        $form->action = $this->currentUrl;

            $dateRangeDisplay = 
            '<div id="DateRangeDisplay">' .
                '<em>' . $start . '</em>' . \ProcessWire\wireIconMarkup('arrows-h') . '<em>' . $end . '</em>' .
            '</div>';

            // Date range reset button
            $dateRangeReset = 
            '<a href="' . $this->currentUrl . '?action=reset"
                id="DateRangeReset"
                class="ui-button ui-widget ui-corner-all ui-state-default ui-priority-secondary pw-tooltip"
                role="button"
                title="' . $this->_('Reset date range to default') .'">' .
                    '<span class="ui-button-text">' .
                        \ProcessWire\wireIconMarkup('rotate-left') .
                    '</span>' .
            '</a>';

            /** @var InputfieldFieldset $fieldset */
            $fieldset = $modules->get('InputfieldFieldset');
            $fieldset->label = '<span class="hidable-inputfield-label">';
            $fieldset->label .= $this->_('Store performance');
            $fieldset->label .= '</span>';
            $fieldset->label .= $dateRangeDisplay;
            $fieldset->label .= $dateRangeReset;
            $fieldset->icon = 'calendar';
            $fieldset->entityEncodeLabel = false;

        $form->add($fieldset);

            /** @var InputfieldSelect $f */
            $f = $modules->get('InputfieldSelect');
            $f->attr('id+name', 'periodSelect');
            $f->attr('value', $knownRange);
            $f->addClass('InputfieldMaxWidth');
            $f->label = $this->_('Predefined period');
            $f->required = true;
            $f->columnWidth = 40;
            $f->collapsed = Inputfield::collapsedNever;
            $f->addOptions($perioRangeLabels);

        $fieldset->add($f);

            /** @var InputfieldDatetime $f */
            $f = $modules->get('InputfieldDatetime');
            $f->attr('id+name', 'periodFrom');
            $f->attr('value', $start);
            $f->attr('placeholder', $periodPlaceholder);
            $f->attr('autocomplete', 'off');
            $f->addClass('InputfieldMaxWidth');
            $f->label = $this->_('Period from');
            $f->datepicker = InputfieldDatetime::datepickerFocus;
            $f->dateInputFormat = $periodDateFormat;
            $f->yearRange = '-15:+0';
            $f->collapsed = Inputfield::collapsedNever;
            $f->columnWidth = 20;

        $fieldset->add($f);

            /** @var InputfieldDatetime $f */
            $f = $modules->get('InputfieldDatetime');
            $f->attr('id+name', 'periodTo');
            $f->attr('value', $end);
            $f->attr('placeholder', $periodPlaceholder);
            $f->attr('autocomplete', 'off');
            $f->addClass('InputfieldMaxWidth');
            $f->label = $this->_('Period to');
            $f->datepicker = InputfieldDatetime::datepickerFocus;
            $f->dateInputFormat = $periodDateFormat;
            $f->yearRange = '-15:+0';
            $f->collapsed = Inputfield::collapsedNever;
            $f->columnWidth = 20;

        $fieldset->add($f);

            /** @var InputfieldSelect $f */
            $f = $modules->get('InputfieldSelect'); 
            $f->attr('id', 'currency-picker'); 
            $f->attr('name', 'currency'); 
            $f->wrapClass = 'CurrencyPickerContainer';
            $f->label = $this->_('Currency'); 
            $f->value = $currency;
            $f->collapsed = Inputfield::collapsedNever;
            $f->columnWidth = 20;
            $f->required = true;

            $supportedCurrencies = CurrencyFormat::getSupportedCurrencies();
            foreach ($this->currencies as $currencyOption) {
                $currencyLabel = isset($supportedCurrencies[$currencyOption])
                    ? $supportedCurrencies[$currencyOption]
                    : $currencyOption;
                $f->addOption($currencyOption, $currencyLabel);
            }

        $fieldset->add($f);            

        return $form->render(); 
    }

    /**
     * Render the store performance boxes.
     *
     * @param array $results
     * @param string $currency Currency string
     * @return markup Custom HTML
     *
     */
    private function _renderPerformanceBoxes($results, $currency) {
        
        $content = $results[WireHttpExtended::resultKeyContent];
        $httpCode = $results[WireHttpExtended::resultKeyHttpCode];
        $error = $results[WireHttpExtended::resultKeyError];

        if ($error) {
            $errorMessage = $this->_('Values for store performance boxes could not be fetched:');
            $this->error($errorMessage . ' ' . $error);
            $errorIcon =
            '<span
                class="pw-tooltip"
                title="' . $errorMessage .'">' .
                    \ProcessWire\wireIconMarkup('exclamation-triangle') .
            '</span>';

            $values = array(
                'orders' => $errorIcon,
                'sales' => $errorIcon, 
                'average' => $errorIcon,
                'customers' => array(
                    'new' => $errorIcon,
                    'returning' => $errorIcon,
                )
            );
        } else {
            $errorMessage = $this->_('Missing value in Snipcart data');
            $errorIcon =
            '<span
                class="pw-tooltip"
                title="' . $errorMessage .'">' .
                    \ProcessWire\wireIconMarkup('exclamation-triangle') .
            '</span>';

            $values = array(
                'orders' => isset($content['ordersCount'])
                    ? $content['ordersCount']
                    : $errorIcon,
                'sales' => isset($content['ordersSales'])
                    ? CurrencyFormat::format($content['ordersSales'], $currency)
                    : $errorIcon,
                'average' => isset($content['averageOrdersValue'])
                    ? CurrencyFormat::format($content['averageOrdersValue'], $currency)
                    : $errorIcon,
                'customers' => array(
                    'new' => isset($content['customers']['newCustomers'])
                        ? $content['customers']['newCustomers']
                        : $errorIcon,
                    'returning' => isset($content['customers']['returningCustomers'])
                        ? $content['customers']['returningCustomers']
                        : $errorIcon,
                )
            );
        }

        $boxes = array(
            'sales' => $this->_('Sales'),
            'orders' => $this->_('Orders'),
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
                '<span>' . $values[$box] . '</span>';
            }
            
            $out .=
                '</div>' .
            '</div>';
        }
        return '<div id="PerformanceBoxesContainer">' . $out . '</div>';
    }

    /**
     * Render the chart.
     *
     * @param array $salesData
     * @param array $ordersData
     * @param string $currency Currency string
     * @return markup Chart
     *
     */
    private function _renderChart($salesData, $ordersData, $currency) {
        $config = $this->wire('config');

        $salesDataContent = $salesData[WireHttpExtended::resultKeyContent];
        $salesDataHttpCode = $salesData[WireHttpExtended::resultKeyHttpCode];
        $salesDataError = $salesData[WireHttpExtended::resultKeyError];
        $salesCategories = array();
        $sales = array();

        if ($salesDataError) {
            $this->error($this->_('Values for sales chart could not be fetched:') . ' ' . $salesDataError);
        } else {
            // Split results in categories & data (prepare for ApexCharts)
            if (!empty($salesDataContent['data']) && is_array($salesDataContent['data'])) {
                foreach ($salesDataContent['data'] as $item) {
                    $salesCategories[] = $item['name'];
                    $sales[] = $item['value'];
                }
            }
        }

        $ordersDataContent = $ordersData[WireHttpExtended::resultKeyContent];
        $ordersDataHttpCode = $ordersData[WireHttpExtended::resultKeyHttpCode];
        $ordersDataError = $ordersData[WireHttpExtended::resultKeyError];
        $ordersCategories = array();
        $orders = array();
 
        if ($ordersDataError) {
            $this->error($this->_('Values for orders chart could not be fetched:') . ' ' . $ordersDataError);
        } else {
            // Split results in categories & data (prepare for ApexCharts)
            if (!empty($ordersDataContent['data']) && is_array($ordersDataContent['data'])) {
                foreach ($ordersDataContent['data'] as $item) {
                    $ordersCategories[] = $item['name'];
                    $orders[] = $item['value'];
                }
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
        ' aria-label="' . $this->_('Snipcart Performance Chart') . '"' .
        ' role="img">' .
        '</div>';
        
        return $out;
    }

    /**
     * Render the top customers table.
     *
     * @param array $items
     * @return markup MarkupAdminDataTable | custom html with `no items` display 
     *
     */
    private function _renderTableTopCustomers($items) {
        $modules = $this->wire('modules');
                    
        if (!empty($items)) {
            /** @var MarkupAdminDataTable $table */
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->id = 'snipwire-top-customers-table';
            $table->setSortable(false);
            $table->setResizable(false);
            $table->headerRow(array(
                $this->_('Name'),
                $this->_('# Orders'),
                $this->_('Total Spent'),
            ));
            foreach ($items as $item) {
                $panelLink =
                '<a href="' . $this->snipWireRootUrl . 'customer/' . $item['id'] . '"
                    class="pw-panel pw-panel-links"
                    data-panel-width="85%">' .
                        $item['billingAddress']['fullName'] .
                '</a>';
                $table->row(array(
                    $panelLink,
                    $item['statistics']['ordersCount'],
                    CurrencyFormat::format($item['statistics']['ordersAmount'], 'usd'), // @todo: handle currency!
                ));
            }
            $out = $table->render();
        } else {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No customers in selected period') .
            '</div>';
        }

        /** @var InputfieldButton $btn */
        $btn = $modules->get('InputfieldButton');
        $btn->href = $this->snipWireRootUrl . 'customers';
        $btn->value = $this->_('All Customers');
        $btn->icon = 'user';
        $btn->secondary = true;
        $btn->small = true;

        $out .= $btn->render();
        return $out;
    }

    /**
     * Render the top products table.
     *
     * @param array $items
     * @param string $currency Currency tag
     * @return markup MarkupAdminDataTable | custom html with `no items` display 
     *
     */
    private function _renderTableTopProducts($items, $currency) {
        $pages = $this->wire('pages');
        $modules = $this->wire('modules');

        if (!empty($items)) {
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
                '&nbsp;',
            ));
            foreach ($items as $item) {
                $panelLink =
                '<a href="' . $this->snipWireRootUrl . 'product/' . $item['id'] . '"
                    class="pw-panel pw-panel-links"
                    data-panel-width="85%">' .
                        $item['name'] .
                '</a>';

                $product = $pages->findOne('snipcart_item_id="' . $item['userDefinedId'] . '"');
                if ($product->url) {
                    if ($product->editable()) {
                        $editLink =
                        '<a href="' . $product->editUrl . '"
                            class="pw-tooltip pw-modal pw-modal-large"
                            title="' . $this->_('Edit product page') .'">' .
                                \ProcessWire\wireIconMarkup('pencil-square-o') .
                        '</a>';
                    } else {
                        $editLink =
                        '<span
                            class="pw-tooltip"
                            title="' . $this->_('Product not editable') .'">' .
                                \ProcessWire\wireIconMarkup('pencil-square-o') .
                        '</span>';
                    }
                } else {
                    // If for some reason the Snipcart "userDefinedId" no longer matches the ID of the ProcessWire field "snipcart_item_id"
                    $editLink =
                    '<span
                        class="pw-tooltip"
                        title="' . $this->_('No matching ProcessWire page found.') .'">' . 
                            \ProcessWire\wireIconMarkup('exclamation-triangle') .
                    '</span>';
                }

                $table->row(array(
                    $panelLink,
                    CurrencyFormat::format($item['price'], $currency),
                    $item['statistics']['numberOfSales'],
                    CurrencyFormat::format($item['statistics']['totalSales'], $currency), // @todo: handle multi currency calculation!
                    $editLink,
                ));
            }
            $out = $table->render();
        } else {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No products in selected period') .
            '</div>';
        }

        /** @var InputfieldButton $btn */
        $btn = $modules->get('InputfieldButton');
        $btn->href = $this->snipWireRootUrl . 'products';
        $btn->value = $this->_('All Products');
        $btn->icon = self::iconProduct;
        $btn->secondary = true;
        $btn->small = true;

        $out .= $btn->render();
        return $out;
    }

    /**
     * Render the recent orders table.
     *
     * @param array $items
     * @return markup MarkupAdminDataTable | custom html with `no items` display 
     *
     */
    private function _renderTableRecentOrders($items) {
        $modules = $this->wire('modules');

        if (!empty($items)) {
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
                $this->_('Status'),
                $this->_('Payment status'),
                $this->_('Payment method'),
                $this->_('Total'),
            ));
            foreach ($items as $item) {
                $panelLink =
                '<a href="' . $this->snipWireRootUrl . 'order/' . $item['token'] . '"
                    class="pw-panel pw-panel-links"
                    data-panel-width="85%">' .
                        $item['invoiceNumber'] .
                '</a>';
                $total =
                '<strong class="price-field">' .
                    CurrencyFormat::format($item['finalGrandTotal'], $item['currency']) .
                '</strong>';
                $completionDate = '<span class="tooltip" title="';
                $completionDate .= \ProcessWire\wireDate('Y-m-d H:i:s', $item['completionDate']);
                $completionDate .= '">';
                $completionDate .= \ProcessWire\wireDate('relative', $item['completionDate']);
                $completionDate .= '</span>';

                $table->row(array(
                    $panelLink,
                    $completionDate,
                    $item['placedBy'],
                    $this->getOrderStatus($item['status']),
                    $this->getPaymentStatus($item['paymentStatus']),
                    $this->getPaymentMethod($item['paymentMethod']),
                    $total,
                ));
            }
            $out = $table->render();            
        } else {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No orders in selected period') .
            '</div>';
        }

        /** @var InputfieldButton $btn */
        $btn = $modules->get('InputfieldButton');
        $btn->href = $this->snipWireRootUrl . 'orders/';
        $btn->value = $this->_('All Orders');
        $btn->icon = 'file-text-o';
        $btn->secondary = true;
        $btn->small = true;

        $out .= $btn->render();
        return $out;
    }
}
