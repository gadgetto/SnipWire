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

require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'helpers/CurrencyFormat.php';
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'installer/ExtendedInstaller.php';

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
                    'icon' => self::iconOrder, 
                ),
                array(
                    'url' => 'customers/', 
                    'label' => __('Customers'), 
                    'icon' => self::iconCustomer, 
                ),
                array(
                    'url' => 'products/', 
                    'label' => __('Products'), 
                    'icon' => self::iconProduct, 
                ),
                array(
                    'url' => 'settings/', 
                    'label' => __('Settings'), 
                    'icon' => self::iconSettings, 
                ),
            ),
            'requires' => array(
                'ProcessWire>=3.0.123',
                'SnipWire',
            ),
        );
    }

    const assetsIncludeDaterangePicker = 1;
    const assetsIncludeApexCharts = 2;
    const assetsIncludeItemLister = 4;
    const assetsIncludeAll = 7;
    
    const iconCustomer = 'user';
    const iconProduct = 'cube';
    const iconOrder = 'file-text';
    const iconSettings = 'gear';

    /** @var array $snipwireConfig The module config of SnipWire module */
    protected $snipwireConfig = array();

    /** @var array $currencies The activated currencies from SnipWire module config */
    private $currencies = array();

    /**var string $snipWireRootUrl The root URL to ProcessSnipWire page */
    protected $snipWireRootUrl = '';

    /**var string $currentUrl The URL to current (virtual) page */
    protected $currentUrl = '';

    /**
     * Initalize module config variables (properties)
     *
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Initialize the module.
     * (Called before any execute functions)
     * 
     */
    public function init() {
        parent::init();
        
        // Get SnipWire module config.
        // (Holds merged data from DB and default config. 
        // This works because of using the ModuleConfig class)
        $this->snipwireConfig = $this->wire('modules')->get('SnipWire');

        // Get activated $currencies from SnipWire module config
        $this->currencies = $this->snipwireConfig->currencies;

        $this->snipWireRootUrl = rtrim($this->wire('pages')->get('snipwire')->url, '/') . '/';
        $this->currentUrl = rtrim($this->wire('input')->url, '/') . '/';
    }    

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
        
        $this->_includeAssets(self::assetsIncludeDaterangePicker | self::assetsIncludeApexCharts);

        $forceRefresh = false;

        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        } elseif ($action == 'reset') {
            $this->message($this->_('Store performance date range set to default.'));
            $this->_resetDateRange();
        }

        $startDate = $this->_getStartDate();
        $endDate = $this->_getEndDate();
        $currency = $this->_getCurrency();

        $out = $this->_buildFilterSelect($startDate, $endDate, $currency);

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
            $out .=
            '<div class="dashboard-empty">' .
                $this->_('Dashboard data could not be fetched') .
            '</div>';
            return $this->_wrapDashboardOutput($out);
        }

        $out .= $this->_renderPerformanceBoxes(
            $dashboard[SnipRest::resourcePathDataPerformance],
            $currency
        );

        $chart = $this->_renderChart(
            $dashboard[SnipRest::resourcePathDataOrdersSales],
            $dashboard[SnipRest::resourcePathDataOrdersCount],
            $currency
        );

        /** @var InputfieldForm $wrapper */
        $wrapper = $modules->get('InputfieldForm');

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Performance Chart');
            $f->icon = 'bar-chart';
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
            $f->value = $this->_renderTableTopCustomers($dashboard[SnipRest::resourcePathCustomers]);
            $f->columnWidth = 50;
            $f->collapsed = Inputfield::collapsedNever;

        $wrapper->add($f);

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Top Products');
            $f->icon = self::iconProduct;
            $f->value = $this->_renderTableTopProducts($dashboard[SnipRest::resourcePathProducts], $currency);
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
            $f->value = $this->_renderTableRecentOrders($dashboard[SnipRest::resourcePathOrders]);
            $f->columnWidth = 100;
            $f->collapsed = Inputfield::collapsedNever;
            
        $wrapper->add($f);

        $out .= $wrapper->render();

        /** @var InputfieldButton $btn */
        $btn = $modules->get('InputfieldButton');
        $btn->id = 'refresh-data';
        $btn->href = $this->currentUrl . '?action=refresh';
        $btn->value = $this->_('Refresh');
        $btn->icon = 'refresh';
        /*
        $btn->addActionLink(
            $this->currentUrl . '?action=refresh_all', 
            'Refresh Complete Snipcart Cache',
            'refresh'
        );
        */
        $btn->showInHeader();

        $out .= $btn->render();

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
        $session = $this->wire('session');
        $sniprest = $this->wire('sniprest');

        $this->browserTitle($this->_('Snipcart Orders'));
        $this->headline($this->_('Snipcart Orders'));

        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }
        
        $limit = 20;        
        $currentOffset = (int) $session->getFor($this, 'offsetOrders');
        $forceRefresh = false;
              
        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        }
        if ($action == 'next') {
            $offset = is_numeric($currentOffset) ? ($currentOffset + $limit) : 0;
        } elseif ($action == 'prev') {
            $offset = is_numeric($currentOffset) ? ($currentOffset - $limit) : 0;
            if ($offset <= 0)  $offset = 0;
        } else {
            $offset = $currentOffset;
        }
        $session->setFor($this, 'offsetOrders', $offset);

        $selector = array(
            'offset' => $offset,
            'limit' => $limit,
        );

        $request = $sniprest->getOrdersItems(
            $selector,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );
        $orders = isset($request[SnipRest::resourcePathOrders][WireHttpExtended::resultKeyContent])
            ? $request[SnipRest::resourcePathOrders][WireHttpExtended::resultKeyContent]
            : array();
        
        $count = count($orders);
        $url = $this->currentUrl;

        $headline = $this->itemListerHeadline($offset, $count);
        $pagination = $this->itemListerPagination($url, $limit, $offset, $count);

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Orders');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconOrder;
        $f->value = $headline;
        $f->value .= $pagination;
        $f->value .= $this->_renderTableOrders($orders);
        $f->value .= $pagination;
        $f->collapsed = Inputfield::collapsedNever;

        $out = $f->render();

        /** @var InputfieldButton $btn */
        $btn = $modules->get('InputfieldButton');
        $btn->id = 'refresh-data';
        $btn->href = $this->currentUrl . '?action=refresh';
        $btn->value = $this->_('Refresh');
        $btn->icon = 'refresh';
        $btn->showInHeader();

        $out .= '<div class="ItemListerButton">' . $btn->render() . '</div>';

        return $this->_wrapDashboardOutput($out);
    }

    /**
     * The SnipWire Snipcart Order detail page.
     *
     * @return page markup
     *
     */
    public function ___executeOrder() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sniprest = $this->wire('sniprest');
        
        $dashboardUrl = $input->url;
        $token = $input->urlSegment(2); // Get Snipcart order token
        
        $this->browserTitle($this->_('Snipcart Order'));
        $this->headline($this->_('Snipcart Order'));

        $this->breadcrumb($this->snipWireRootUrl, $this->_('SnipWire Dashboard'));
        $this->breadcrumb($this->snipWireRootUrl . 'orders/', $this->_('Snipcart Orders'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

        $forceRefresh = false;
        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        }

        $request = $sniprest->getOrder(
            $token,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );
        $order = isset($request[SnipRest::resourcePathOrders . '/' . $token][WireHttpExtended::resultKeyContent])
            ? $request[SnipRest::resourcePathOrders . '/' . $token][WireHttpExtended::resultKeyContent]
            : array();

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Order');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconOrder;
        $f->value = $this->_renderDetailOrder($order);
        $f->collapsed = Inputfield::collapsedNever;

        $out = $f->render();

        /** @var InputfieldButton $btn */
        $btn = $modules->get('InputfieldButton');
        $btn->id = 'refresh-data';
        $btn->href = $this->currentUrl . '?action=refresh';
        $btn->value = $this->_('Refresh');
        $btn->icon = 'refresh';
        $btn->showInHeader();

        $out .= $btn->render();

        return $this->_wrapDashboardOutput($out);
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
        $session = $this->wire('session');
        $sniprest = $this->wire('sniprest');
        
        $this->browserTitle($this->_('Snipcart Customers'));
        $this->headline($this->_('Snipcart Customers'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

        $limit = 20;        
        $currentOffset = (int) $session->getFor($this, 'offsetCustomers');        
        $forceRefresh = false;

        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        }
        if ($action == 'next') {
            $offset = is_numeric($currentOffset) ? ($currentOffset + $limit) : 0;
        } elseif ($action == 'prev') {
            $offset = is_numeric($currentOffset) ? ($currentOffset - $limit) : 0;
            if ($offset <= 0)  $offset = 0;
        } else {
            $offset = $currentOffset;
        }
        $session->setFor($this, 'offsetCustomers', $offset);

        $selector = array(
            'offset' => $offset,
            'limit' => $limit,
        );

        $request = $sniprest->getCustomersItems($selector);
        $customers = isset($request[SnipRest::resourcePathCustomers][WireHttpExtended::resultKeyContent])
            ? $request[SnipRest::resourcePathCustomers][WireHttpExtended::resultKeyContent]
            : array();
        
        $count = count($customers);
        $url = $this->currentUrl;

        $headline = $this->itemListerHeadline($offset, $count);
        $pagination = $this->itemListerPagination($url, $limit, $offset, $count);

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Customers');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconCustomer;
        $f->value = $headline;
        $f->value .= $pagination;
        $f->value .= $this->_renderTableCustomers($customers);
        $f->value .= $pagination;
        $f->collapsed = Inputfield::collapsedNever;

        $out = $f->render();

        /** @var InputfieldButton $btn */
        $btn = $modules->get('InputfieldButton');
        $btn->id = 'refresh-data';
        $btn->href = $this->currentUrl . '?action=refresh';
        $btn->value = $this->_('Refresh');
        $btn->icon = 'refresh';
        $btn->showInHeader();

        $out .= '<div class="ItemListerButton">' . $btn->render() . '</div>';

        return $this->_wrapDashboardOutput($out);
    }

    /**
     * The SnipWire Snipcart Customer detail page.
     *
     * @return page markup
     *
     */
    public function ___executeCustomer() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sniprest = $this->wire('sniprest');
        
        $dashboardUrl = $input->url;
        $id = $input->urlSegment(2); // Get Snipcart customer id
        
        $this->browserTitle($this->_('Snipcart Customer'));
        $this->headline($this->_('Snipcart Customer'));

        $this->breadcrumb($this->snipWireRootUrl, $this->_('SnipWire Dashboard'));
        $this->breadcrumb($this->snipWireRootUrl . 'customers/', $this->_('Snipcart Customers'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }
        
        $request = $sniprest->getCustomer($id);
        $customer = isset($request[SnipRest::resourcePathCustomers . '/' . $id][WireHttpExtended::resultKeyContent])
            ? $request[SnipRest::resourcePathCustomers . '/' . $id][WireHttpExtended::resultKeyContent]
            : array();

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Customer');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconCustomer;
        $f->value = $this->_renderDetailCustomer($customer);
        $f->collapsed = Inputfield::collapsedNever;

        $out = $f->render();

        /** @var InputfieldButton $btn */
        $btn = $modules->get('InputfieldButton');
        $btn->id = 'refresh-data';
        $btn->href = $this->currentUrl . '?action=refresh';
        $btn->value = $this->_('Refresh');
        $btn->icon = 'refresh';
        $btn->showInHeader();

        $out .= $btn->render();

        return $this->_wrapDashboardOutput($out);
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
        $session = $this->wire('session');
        $sniprest = $this->wire('sniprest');
        
        $this->browserTitle($this->_('Snipcart Products'));
        $this->headline($this->_('Snipcart Products'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

        $currency = $this->_getCurrency();

        $limit = 20;        
        $currentOffset = (int) $session->getFor($this, 'offsetProducts');        
        $forceRefresh = false;

        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        }
        if ($action == 'next') {
            $offset = is_numeric($currentOffset) ? ($currentOffset + $limit) : 0;
        } elseif ($action == 'prev') {
            $offset = is_numeric($currentOffset) ? ($currentOffset - $limit) : 0;
            if ($offset <= 0)  $offset = 0;
        } else {
            $offset = $currentOffset;
        }
        $session->setFor($this, 'offsetProducts', $offset);

        $selector = array(
            'offset' => $offset,
            'limit' => $limit,
        );

        $request = $sniprest->getProductsItems($selector);
        $products = isset($request[SnipRest::resourcePathProducts][WireHttpExtended::resultKeyContent])
            ? $request[SnipRest::resourcePathProducts][WireHttpExtended::resultKeyContent]
            : array();
        
        $count = count($products);
        $url = $this->currentUrl;

        $headline = $this->itemListerHeadline($offset, $count);
        $pagination = $this->itemListerPagination($url, $limit, $offset, $count);

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Products');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconProduct;
        $f->value = $headline;
        $f->value .= $pagination;
        $f->value .= $this->_renderTableProducts($products, $currency);
        $f->value .= $pagination;
        $f->collapsed = Inputfield::collapsedNever;

        $out = $f->render();

        /** @var InputfieldButton $btn */
        $btn = $modules->get('InputfieldButton');
        $btn->id = 'refresh-data';
        $btn->href = $this->currentUrl . '?action=refresh';
        $btn->value = $this->_('Refresh');
        $btn->icon = 'refresh';
        $btn->showInHeader();

        $out .= '<div class="ItemListerButton">' . $btn->render() . '</div>';

        return $this->_wrapDashboardOutput($out);
    }

    /**
     * The SnipWire Snipcart Product detail page.
     *
     * @return page markup
     *
     */
    public function ___executeProduct() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sniprest = $this->wire('sniprest');
        
        $dashboardUrl = $input->url;
        $id = $input->urlSegment(2); // Get Snipcart product id
        
        $this->browserTitle($this->_('Snipcart Product'));
        $this->headline($this->_('Snipcart Product'));

        $this->breadcrumb($this->snipWireRootUrl, $this->_('SnipWire Dashboard'));
        $this->breadcrumb($this->snipWireRootUrl . 'products/', $this->_('Snipcart Products'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }
        
        $request = $sniprest->getProduct($id);
        $product = isset($request[SnipRest::resourcePathProducts . '/' . $id][WireHttpExtended::resultKeyContent])
            ? $request[SnipRest::resourcePathProducts . '/' . $id][WireHttpExtended::resultKeyContent]
            : array();

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Product');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconProduct;
        $f->value = $this->_renderDetailProduct($product);
        $f->collapsed = Inputfield::collapsedNever;

        $out = $f->render();

        /** @var InputfieldButton $btn */
        $btn = $modules->get('InputfieldButton');
        $btn->id = 'refresh-data';
        $btn->href = $this->currentUrl . '?action=refresh';
        $btn->value = $this->_('Refresh');
        $btn->icon = 'refresh';
        $btn->showInHeader();

        $out .= $btn->render();

        return $this->_wrapDashboardOutput($out);
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
     * Renders an item lister headline.
     *
     * @param integer $offset
     * @param integer $count
     * @return headline markup
     *
     */
    protected function itemListerHeadline($offset, $count) {
        $labels = array(
            'to' => $this->_('to'),
        );

        $out = 
        '<h2 class="itemlister-headline">' .
            ($offset + 1) . ' ' . $labels['to'] . ' ' . ($offset + $count) .
        '</h2>';
        
        return $out;
    }

    /**
     * Renders an item lister pagination.
     * (has only Prev/Next buttons)
     *
     * @param string $url
     * @param integer $limit
     * @param integer $offset
     * @param integer $count
     * @return pagination markup
     *
     */
    protected function itemListerPagination($url, $limit, $offset, $count) {
        
        $prevDisabled = ($offset <= 0) ? true : false;
        $nextDisabled = ($count < $limit) ? true : false;

        $labels = array(
            'pagination_links' => $this->_('Pagination buttons'),
            'prev' => $this->_('Prev'),
            'next' => $this->_('Next'),
            'list_prev' => $this->_('Previous entries'),
            'list_next' => $this->_('Next entries'),
            'no_prev' => $this->_('No previous entries available'),
            'no_next' => $this->_('No next entries available'),
        );
        
        $out = 
        '<ul class="itemlister-pagination" role="navigation" aria-label="' . $labels['pagination_links'] . '">';
        
        if ($prevDisabled) {
            $out .=
            '<li aria-label="' . $labels['no_prev'] . '">' .
                '<span>' .
                    wireIconMarkup('angle-left') . ' ' . $labels['prev'] .
                '</span>' .
            '</li>';
        } else {
            $out .=
            '<li aria-label="' . $labels['list_prev'] . '">' .
                '<a href="' . $url . '?action=prev" role="button">' .
                    wireIconMarkup('angle-left') . ' ' . $labels['prev'] .
                '</a>' .
            '</li>';
        }    
        if ($nextDisabled) {
            $out .=
            '<li aria-label="' . $labels['no_next'] . '">' .
                '<span>' .
                    $labels['next'] . ' ' . wireIconMarkup('angle-right') .
                '</span>' .
            '</li>';
        } else {
            $out .=
            '<li aria-label="' . $labels['list_next'] . '">' .
                '<a href="' . $url . '?action=next" role="button">' .
                    $labels['next'] . ' ' . wireIconMarkup('angle-right') .
                '</a>' .
            '</li>';
        }

        $out .= 
        '</ul>';
        
        return $out;
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
            SnipRest::resourcePathDataPerformance => array(),
            SnipRest::resourcePathDataOrdersSales => array(),
            SnipRest::resourcePathDataOrdersCount => array(),
            SnipRest::resourcePathCustomers => array(),
            SnipRest::resourcePathProducts => array(),
            SnipRest::resourcePathOrders => array(),
        );

        $ordersSales = 0.0;
        $ordersCount = 0;
        $averageOrdersValue = 0.0;

        foreach ($packages as $key => $package) {
            
            if (strpos($key, SnipRest::resourcePathDataPerformance) !== false) {

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
                $dashboard[SnipRest::resourcePathDataPerformance] = $package;
                
            } elseif (strpos($key, SnipRest::resourcePathDataOrdersSales) !== false) {
                
                $dashboard[SnipRest::resourcePathDataOrdersSales] = $package;

                // Calc sales sum
                if (isset($dashboard[SnipRest::resourcePathDataOrdersSales][WireHttpExtended::resultKeyContent]['data'])) {
                    $data = $dashboard[SnipRest::resourcePathDataOrdersSales][WireHttpExtended::resultKeyContent]['data'];
                    foreach ($data as $item) {
                        $ordersSales += $item['value'];
                    }
                }

            } elseif (strpos($key, SnipRest::resourcePathDataOrdersCount) !== false) {
                
                $dashboard[SnipRest::resourcePathDataOrdersCount] = $package;
                
                // Calc orders count
                if (isset($dashboard[SnipRest::resourcePathDataOrdersCount][WireHttpExtended::resultKeyContent]['data'])) {
                    $data = $dashboard[SnipRest::resourcePathDataOrdersCount][WireHttpExtended::resultKeyContent]['data'];
                    foreach ($data as $item) {
                        $ordersCount += $item['value'];
                    }
                }
                
            } elseif (strpos($key, SnipRest::resourcePathCustomers) !== false) {
                
                $dashboard[SnipRest::resourcePathCustomers] = isset($package[WireHttpExtended::resultKeyContent]['items'])
                    ? $package[WireHttpExtended::resultKeyContent]['items']
                    : array();
                
            } elseif (strpos($key, SnipRest::resourcePathProducts) !== false) {
                
                $dashboard[SnipRest::resourcePathProducts] = isset($package[WireHttpExtended::resultKeyContent]['items'])
                    ? $package[WireHttpExtended::resultKeyContent]['items']
                    : array();
                
            } elseif (strpos($key, SnipRest::resourcePathOrders) !== false) {
                
                $dashboard[SnipRest::resourcePathOrders] = isset($package[WireHttpExtended::resultKeyContent]['items'])
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
        $dashboard[SnipRest::resourcePathDataPerformance][WireHttpExtended::resultKeyContent] = array_merge(
            $dashboard[SnipRest::resourcePathDataPerformance][WireHttpExtended::resultKeyContent],
            $calculated
        );

        unset($packages, $key, $package); // free space
        
        return $dashboard;
    }

    /**
     * SnipWire dashboard output wrapper with tabbed interface.
     *
     * @return markup
     *
     */
    private function _wrapDashboardOutput($out) {
        $modules = $this->wire('modules');
        $config = $this->wire('config');
        $input = $this->wire('input');

        // Prevent rendering of Tabs in modal/panel
        if ($input->get('modal')) {
            return '<div id="SnipwireDashboard">' . $out . '</div>';
        }

        /** @var JqueryWireTabs $wireTabs */
        $wireTabs = $modules->get('JqueryWireTabs');

        $options = array(
            'id' => 'SnipWireTabs',
            'rememberTabs' => JqueryWireTabs::rememberTabsNever,
        );
        // Hand over configuration to JS
        $config->js('tabsOptions', $options);

        $tabsConfig = array(
            'dashboard' => array(
                'label' => $this->_('Dashboard'),
                'urlsegment' => '', // dashboard is root!
            ),
            'orders' => array(
                'label' => $this->_('Orders'),
                'urlsegment' => 'orders',
            ),
            'customers' => array(
                'label' => $this->_('Customers'),
                'urlsegment' => 'customers',
            ),
            'products' => array(
                'label' => $this->_('Products'),
                'urlsegment' => 'products',
            ),
            'settings' => array(
                'label' => wireIconMarkup('gear'),
                'urlsegment' => 'settings',
                'tooltip' => $this->_('Open SnipWire module settings page'),
            ),
        );

        $tabs = array();
        foreach ($tabsConfig as $id => $cfg) {
            $cls = array();
            $attrs = array();
            $attrs[] = 'id="_' . $id . '"';
            if ($cfg['urlsegment'] == $input->urlSegment(1)) $cls[] = 'on';
            if (!empty($cfg['tooltip'])) {
                $attrs[] = 'title="' . $cfg['tooltip'] . '"';
                $attrs[] = 'uk-tooltip';
                $cls[] = 'tooltip';
            }
            $classes = implode(' ', $cls);
            $classes = $classes ? ' class="' . $classes . '"' : '';
            $attributes = implode(' ', $attrs);
            $attributes = $attributes ? ' ' . $attributes : '';
            $tabs[] = '<a href="' . $this->snipWireRootUrl . $cfg['urlsegment'] . '"' . $classes . $attributes . '>' . $cfg['label'] . '</a>';
        }

        $out =
        $wireTabs->renderTabList($tabs, $options) .
        '<div id="SnipwireDashboard">' . $out . '</div>';
        
        return $out;
    }

    /**
     * Build the filter select form.
     *
     * @param string $start ISO 8601 date format string
     * @param string $end ISO 8601 date format string
     * @param string $currency Currency string
     * @return markup InputfieldForm
     *
     */
    private function _buildFilterSelect($start = '', $end = '', $currency = '') {
        $modules = $this->wire('modules');
        $config = $this->wire('config');

        // Define "Last 30 days" if no $startDate and/or $endDate properties provided
        if (!$start || !$end) {
            $start = date('Y-m-d', strtotime('-29 days'));
            $end = date('Y-m-d');
        }

        $filterSettings = array(
            'form' => '#StorePerformanceFilterForm',
            'pickerElement' => '#period-picker',
            'pickerDisplay' => '#period-display',
            'fieldFrom' => '#period-from',
            'fieldTo' => '#period-to',
            'fieldCurrency' => '#currency-picker',
            'startDate' => $start,
            'endDate' => $end,
            'currency' => $currency,
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

        // Hand over configuration to JS
        $config->js('filterSettings', $filterSettings);
        $config->js('pickerLocale', $pickerLocale);
        $config->js('pickerRangeLabels', $pickerRangeLabels);
        
        /** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm'); 
        $form->attr('id', 'StorePerformanceFilterForm');
        $form->method = 'get';
        $form->action = $this->currentUrl;

            // Period date range picker with hidden form fields
            $markup =
            '<input type="hidden" id="period-from" name="periodFrom" value="' . $start . '">' .
            '<input type="hidden" id="period-to" name="periodTo" value="' . $end . '">' .
            '<div id="PeriodPickerSelect">' .
                '<div id="period-picker" aria-label="' . $this->_('Store performance date range selector') .'">' .
                    wireIconMarkup('calendar') . 
                    '<span id="period-display">' .
                        $this->_('Preparing data...') .
                    '</span>' . 
                    wireIconMarkup('caret-down') .
                '</div>' .
            '</div>';
            
            // Reset button
            $markup .= 
            '<a href="' . $this->currentUrl . '?action=reset"
                id="PeriodPickerReset"
                class="tooltip"
                role="button"
                uk-tooltip
                title="' . $this->_('Reset store performance date range to default') .'">' .
                    wireIconMarkup('rotate-left') .
            '</a>';

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->wrapClass = 'PeriodPickerContainer';
            $f->label = $this->_('Date Range Picker');
            $f->skipLabel = Inputfield::skipLabelHeader;
            $f->value = $markup;
            $f->collapsed = Inputfield::collapsedNever;
            $f->columnWidth = 75;

        $form->add($f);  

            /** @var InputfieldSelect $f */
            $f = $modules->get('InputfieldSelect'); 
            $f->attr('id', 'currency-picker'); 
            $f->attr('name', 'currency'); 
            $f->wrapClass = 'CurrencyPickerContainer';
            $f->label = $this->_('Currency Picker'); 
            $f->skipLabel = Inputfield::skipLabelHeader;
            $f->value = $currency;
            $f->collapsed = Inputfield::collapsedNever;
            $f->columnWidth = 25;
            $f->required = true;

            $supportedCurrencies = CurrencyFormat::getSupportedCurrencies();
            foreach ($this->currencies as $currencyOption) {
                $currencyLabel = isset($supportedCurrencies[$currencyOption])
                    ? $supportedCurrencies[$currencyOption]
                    : $currencyOption;
                $f->addOption($currencyOption, $currencyLabel);
            }

        $form->add($f);            

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
            $this->error($this->_('Values for store performance boxes could not be fetched:') . ' ' . $error);
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
        } else {
            $values = array(
                'orders' => $content['ordersCount'],
                'sales' => CurrencyFormat::format($content['ordersSales'], $currency),
                'average' => CurrencyFormat::format($content['averageOrdersValue'], $currency),
                'customers' => array(
                    'new' => $content['customers']['newCustomers'],
                    'returning' => $content['customers']['returningCustomers'],
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
                $panelLink = '<a href="' . $this->snipWireRootUrl . 'customer/' . $item['id'] . '" class="pw-panel" data-panel-width="70%">' . $item['billingAddress']['fullName'] . '</a>';
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
                $panelLink = '<a href="' . $this->snipWireRootUrl . 'product/' . $item['id'] . '" class="pw-panel" data-panel-width="70%">' . $item['name'] . '</a>';

                $product = $pages->findOne('snipcart_item_id="' . $item['userDefinedId'] . '"');
                if ($product->url && $product->editable()) {
                    $editLink = '<a href="' . $product->editUrl . '" class="pw-panel" data-panel-width="70%">' . wireIconMarkup('pencil-square-o') . '</a>';
                } else {
                    $editLink = wireIconMarkup('pencil-square-o');
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
                $this->_('Country'),
                $this->_('Payment Status'),
                $this->_('Total'),
            ));
            foreach ($items as $item) {
                $panelLink = '<a href="' . $this->snipWireRootUrl . 'order/' . $item['token'] . '" class="pw-panel" data-panel-width="70%">' . $item['invoiceNumber'] . '</a>';
                $panelLink2 = '<a href="' . $this->snipWireRootUrl . 'customer/' . $item['user']['id'] . '" class="pw-panel" data-panel-width="70%">' . $item['user']['billingAddress']['fullName'] . '</a>';
                $table->row(array(
                    $panelLink,
                    wireDate('relative', $item['creationDate']),
                    $panelLink2,
                    $item['billingAddressCountry'],
                    $item['paymentStatus'],
                    CurrencyFormat::format($item['total'], $item['currency']),
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

    /**
     * Render the orders table.
     *
     * @param array $items
     * @return markup MarkupAdminDataTable | custom html with `no items` display 
     *
     */
    private function _renderTableOrders($items) {
        $modules = $this->wire('modules');

        if (!empty($items)) {
            $modules->get('JqueryTableSorter')->use('widgets');
            $modules->get('JqueryMagnific');

            /** @var MarkupAdminDataTable $table */
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->setID('snipwire-orders-table');
            $table->setClass('ItemLister');
            $table->setSortable(false);
            $table->setResizable(true);
            $table->setResponsive(true);
            $table->headerRow(array(
                $this->_('Invoice #'),
                $this->_('Placed on'),
                $this->_('Placed by'),
                $this->_('Country'),
                $this->_('Payment Status'),
                $this->_('Total'),
            ));
            foreach ($items as $item) {
                $panelLink = '<a href="' . $this->snipWireRootUrl . 'order/' . $item['token'] . '" class="pw-panel" data-panel-width="70%">' . wireIconMarkup(self::iconOrder, 'fa-fw') . $item['invoiceNumber'] . '</a>';
                $panelLink2 = '<a href="' . $this->snipWireRootUrl . 'customer/' . $item['user']['id'] . '" class="pw-panel" data-panel-width="70%">' . $item['user']['billingAddress']['fullName'] . '</a>';
                $table->row(array(
                    $panelLink,
                    wireDate('relative', $item['creationDate']),
                    $panelLink2,
                    $item['billingAddressCountry'],
                    $item['paymentStatus'],
                    CurrencyFormat::format($item['total'], $item['currency']),
                ));
            }
            $out = $table->render();
        } else {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No orders found') .
            '</div>';
        }
        return '<div class="ItemListerTable">' . $out . '</div>';
    }

    /**
     * Render the order detail view.
     *
     * @param array $item
     * @return markup 
     *
     */
    private function _renderDetailOrder($item) {
        $modules = $this->wire('modules');

        if (!empty($item)) {


            $out = '<pre>' . print_r($item, true) . '</pre>';


        } else {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No order selected') .
            '</div>';
        }

        return $out;
    }

    /**
     * Render the customers table.
     *
     * @param array $items
     * @return markup MarkupAdminDataTable | custom html with `no items` display 
     *
     */
    private function _renderTableCustomers($items) {
        $modules = $this->wire('modules');

        if (!empty($items)) {
            $modules->get('JqueryTableSorter')->use('widgets');
            $modules->get('JqueryMagnific');

            /** @var MarkupAdminDataTable $table */
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->setID('snipwire-customers-table');
            $table->setClass('ItemLister');
            $table->setSortable(false);
            $table->setResizable(true);
            $table->setResponsive(true);
            $table->headerRow(array(
                $this->_('Name'),
                $this->_('Email'),
                $this->_('Created on'),
                $this->_('# Orders'),
                $this->_('# Subscriptions'),
                $this->_('Status'),
            ));

            foreach ($items as $item) {
                $panelLink = '<a href="' . $this->snipWireRootUrl . 'customer/' . $item['id'] . '" class="pw-panel" data-panel-width="70%">' . wireIconMarkup(self::iconCustomer, 'fa-fw') . $item['billingAddress']['fullName'] . '</a>';
                $table->row(array(
                    $panelLink,
                    $item['email'],
                    wireDate('relative', $item['creationDate']),
                    $item['statistics']['ordersCount'],
                    $item['statistics']['subscriptionsCount'],
                    $item['status'],
                ));
            }
            $out = $table->render();
        } else {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No customers found') .
            '</div>';
        }
        return '<div class="ItemListerTable">' . $out . '</div>';
    }

    /**
     * Render the customer detail view.
     *
     * @param array $item
     * @return markup 
     *
     */
    private function _renderDetailCustomer($item) {
        $modules = $this->wire('modules');

        if (!empty($item)) {


            $out = '<pre>' . print_r($item, true) . '</pre>';


        } else {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No customer selected') .
            '</div>';
        }

        return $out;
    }

    /**
     * Render the products table.
     *
     * @param array $items
     * @param string $currency Currency tag
     * @return markup MarkupAdminDataTable | custom html with `no items` display 
     *
     */
    private function _renderTableProducts($items, $currency) {
        $pages = $this->wire('pages');
        $modules = $this->wire('modules');
        $snipwireConfig = $this->snipwireConfig;

        if (!empty($items)) {
            $modules->get('JqueryTableSorter')->use('widgets');
            $modules->get('JqueryMagnific');

            /** @var MarkupAdminDataTable $table */
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->setID('snipwire-products-table');
            $table->setClass('ItemLister');
            $table->setSortable(false);
            $table->setResizable(true);
            $table->setResponsive(true);
            $table->headerRow(array(
                $this->_('SKU'),
                $this->_('Thumb'),
                $this->_('Name'),
                $this->_('Price'),
                $this->_('# Sales'),
                $this->_('Sales'),
                '&nbsp;',
            ));

            foreach ($items as $item) {
                $panelLink = '<a href="' . $this->snipWireRootUrl . 'product/' . $item['id'] . '" class="pw-panel" data-panel-width="70%">' . wireIconMarkup(self::iconProduct, 'fa-fw') . $item['userDefinedId'] . '</a>';
                $thumb = '<img src="' . $item['image'] . '" style="width: ' . $snipwireConfig['cart_image_width'] . 'px; height: ' . $snipwireConfig['cart_image_height'] . 'px;">';

                $product = $pages->findOne('snipcart_item_id="' . $item['userDefinedId'] . '"');
                if ($product->url && $product->editable()) {
                    $editLink = '<a href="' . $product->editUrl . '" class="pw-panel" data-panel-width="70%">' . wireIconMarkup('pencil-square-o') . '</a>';
                } else {
                    $editLink = wireIconMarkup('pencil-square-o');
                }

                $table->row(array(
                    $panelLink,
                    $thumb,
                    $item['name'],
                    CurrencyFormat::format($item['price'], $currency),
                    $item['statistics']['numberOfSales'],
                    CurrencyFormat::format($item['statistics']['totalSales'], 'usd'), // @todo: handle multi currency!
                    $editLink,
                ));
            }
            $out = $table->render();
        } else {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No products found') .
            '</div>';
        }
        return '<div class="ItemListerTable">' . $out . '</div>';
    }

    /**
     * Render the product detail view.
     *
     * @param array $item
     * @return markup 
     *
     */
    private function _renderDetailProduct($item) {
        $modules = $this->wire('modules');

        if (!empty($item)) {


            $out = '<pre>' . print_r($item, true) . '</pre>';


        } else {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No product selected') .
            '</div>';
        }

        return $out;
    }

    /**
     * Reset the date range session to default.
     *
     */
    private function _resetDateRange() {
        $session = $this->wire('session');
        $session->removeFor($this, 'periodFrom');
        $session->removeFor($this, 'periodTo');
    }

    /**
     * Get the sanitized start date from session or input.
     * (session var is only set if date is selected by datepicker)
     *
     * @return string Sanitized ISO 8601 [default: back 29 days]
     * 
     */
    private function _getStartDate() {
        $session = $this->wire('session');
        $periodFrom = $this->wire('input')->get->date('periodFrom', 'Y-m-d', array('strict' => true));
        if ($periodFrom) {
            $session->setFor($this, 'periodFrom', $periodFrom);
        } else {
            $periodFrom = $session->getFor($this, 'periodFrom');
            if (!$periodFrom) $periodFrom = date('Y-m-d', strtotime('-29 days'));
        }
        return $periodFrom;
    }

    /**
     * Get the sanitized end date from session or input.
     * (session var is only set if date is selected by datepicker)
     *
     * @return string Sanitized ISO 8601 [default: today]
     * 
     */
    private function _getEndDate() {
        $session = $this->wire('session');
        $periodTo = $this->wire('input')->get->date('periodTo', 'Y-m-d', array('strict' => true));
        if ($periodTo) {
            $session->setFor($this, 'periodTo', $periodTo);
        } else {
            $periodTo = $session->getFor($this, 'periodTo');
            if (!$periodTo) $periodTo = date('Y-m-d');
        }
        return $periodTo;
    }

    /**
     * Get the sanitized currency string from session or input.
     * (session var is always set)
     *
     * @return string The currency string (e.g. 'eur') [default: first currency from config]
     * 
     */
    private function _getCurrency() {
        $session = $this->wire('session');

        $currency = $this->wire('input')->get->text('currency');
        $sessionCurrency = $session->getFor($this, 'currency');
        if (!$sessionCurrency) $sessionCurrency = $this->currencies[0];
        
        $curr = $currency ? $currency : $sessionCurrency;
        $session->setFor($this, 'currency', $curr);

        return $curr;
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

        if ($mode & self::assetsIncludeDaterangePicker || $mode & self::assetsIncludeApexCharts) {
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
        $form->attr('action', $this->currentUrl . '?ret=' . urlencode($comeFromUrl)); 
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
        $form->attr('action', $this->currentUrl . '?ret=' . urlencode($comeFromUrl)); 
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
