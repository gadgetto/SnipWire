<?php
namespace ProcessWire;

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

require_once dirname(__DIR__) . '/helpers/Functions.php';
wire('classLoader')->addNamespace('SnipWire\Helpers', dirname(__DIR__) . '/helpers');
wire('classLoader')->addNamespace('SnipWire\Installer', dirname(__DIR__) . '/installer');
wire('classLoader')->addNamespace('SnipWire\ProcessSnipWire\Sections', __DIR__ . '/sections');

use SnipWire\Installer\ExtendedInstaller;
use SnipWire\Helpers\CurrencyFormat;
use SnipWire\Helpers\Countries;
use SnipWire\ProcessSnipWire\Sections\Dashboard;
use SnipWire\ProcessSnipWire\Sections\Orders;
use SnipWire\ProcessSnipWire\Sections\Subscriptions;
use SnipWire\ProcessSnipWire\Sections\AbandonedCarts;
use SnipWire\ProcessSnipWire\Sections\Customers;
use SnipWire\ProcessSnipWire\Sections\Products;
use SnipWire\ProcessSnipWire\Sections\Discounts;

class ProcessSnipWire extends Process implements Module {

    // Import traits
    use Dashboard, Orders, Subscriptions, AbandonedCarts, Customers, Products, Discounts;

    /**
     * Returns information for ProcessSnipWire module.
     *
     */
    public static function getModuleInfo() {
        return array(
            'title' => __('SnipWire Dashboard'), // Module Title
            'summary' => __('Snipcart dashboard integration for ProcessWire.'), // Module Summary
            'version' => '0.8.7',
            'author'  => 'Martin Gartner',
            'icon' => 'shopping-cart', 
            'permission' => 'snipwire-dashboard',
            'permissions' => array(
                'snipwire-dashboard' => __('Use the SnipWire Dashboard sections'), // Permission Description
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
                    'url' => 'subscriptions/', 
                    'label' => __('Subscriptions'), 
                    'icon' => self::iconSubscription, 
                ),
                array(
                    'url' => 'abandoned-carts/', 
                    'label' => __('Abandoned Carts'), 
                    'icon' => self::iconAbandonedCart, 
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
                    'url' => 'discounts/', 
                    'label' => __('Discounts'), 
                    'icon' => self::iconDiscount, 
                ),
                array(
                    'url' => 'settings/', 
                    'label' => __('Settings'), 
                    'icon' => self::iconSettings, 
                ),
            ),
            'requires' => array(
                'ProcessWire>=3.0.148',
                'SnipWire',
                'PHP>=7.0.0',
            ),
        );
    }

    const assetsIncludeDateRangePicker = 1;
    const assetsIncludeCurrencyPicker = 2;
    const assetsIncludeApexCharts = 4;
    const assetsIncludeItemLister = 8;
    const assetsIncludeAll = 15;

    const iconDasboard = 'bar-chart';
    const iconOrder = 'file-text';
    const iconSubscription = 'calendar';
    const iconAbandonedCart = 'shopping-cart';
    const iconCustomer = 'user';
    const iconAddress = 'address-card';
    const iconProduct = 'tag';
    const iconDiscount = 'scissors';
    const iconInfo = 'info-circle';
    const iconPayment = 'credit-card-alt';
    const iconRefund = 'thumbs-up';
    const iconComment = 'comment';
    const iconOrderStatus = 'question-circle';
    const iconSettings = 'cog';
    const iconDebug = 'bug';

    /** @var array $snipwireConfig The module config of SnipWire module */
    protected $snipwireConfig = array();

    /** @var array $currencies The activated currencies from SnipWire module config */
    private $currencies = array();

    /**var string $snipWireRootUrl The root URL to ProcessSnipWire page */
    protected $snipWireRootUrl = '';

    /**var string $currentUrl The URL to current (virtual) page + path + url-segments */
    protected $currentUrl = '';

    /**var string $processUrl The URL to current (virtual) page */
    protected $processUrl = '';

    /**var array $orderStatuses The order statuses */
    public $orderStatuses = array();
    
    /**var array $orderStatusesSelectable The order statuses for form selects */
    public $orderStatusesSelectable = array();
    
    /**var array $subscriptionStatuses The subscription statuses */
    public $subscriptionStatuses = array();
    
    /**var array $commentTypes The comment types */
    public $commentTypes = array();

    /**var array $paymentStatuses The payment statuses */
    public $paymentStatuses = array();

    /**var array $abandonedCartsTimeRanges The abandoned carts time ranges */
    public $abandonedCartsTimeRanges = array();

    /**var array $customerAddressLabels The customer address labels (billing and shipping) */
    public $customerAddressLabels = array();

    /**var array $customerStatuses The customer statuses */
    public $customerStatuses = array();

    /**var array $discountsStatuses The discounts statuses */
    public $discountsStatuses = array();

    /**var array $discountsTypes The discounts types */
    public $discountsTypes = array();

    /**var array $discountsTriggers The discounts triggers */
    public $discountsTriggers = array();

    /**
     * Initialize module config variables (properties)
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
        
        $modules = $this->wire('modules');
        
        $modules->get('JqueryWireTabs');
        $modules->get('JqueryUI')->use('panel');
        $modules->get('JqueryUI')->use('modal');
        $modules->get('JqueryUI')->use('vex');
        
        // Get SnipWire module config.
        // (Holds merged data from DB and default config. 
        // This works because of using the ModuleConfig class)
        $this->snipwireConfig = $this->wire('modules')->get('SnipWire');
        
        // Get activated $currencies from SnipWire module config
        $this->currencies = $this->snipwireConfig->currencies;
        
        $this->snipWireRootUrl = rtrim($this->wire('pages')->get('template=admin, name=snipwire, status<' . Page::statusTrash)->url, '/') . '/';
        $this->currentUrl = rtrim($this->wire('input')->url, '/') . '/';
        $this->processUrl = $this->snipWireRootUrl . $this->getProcessPage()->urlSegment . '/';
        
        $this->_noticeTestMode();
        
        $this->orderStatusesSelectable = array(
            'Processed' => $this->_('Processed'),
            'Disputed' => $this->_('Disputed'),
            'Shipped' => $this->_('Shipped'),
            'Delivered' => $this->_('Delivered'),
            'Pending' => $this->_('Pending'),
            'Cancelled' => $this->_('Cancelled'),
            'Dispatched' => $this->_('Dispatched'),
        );
        $this->orderStatuses = array_merge(
            array(
                'All' =>  $this->_('All Orders'),
                'InProgress' => $this->_('In Progress'),
            ),
            $this->orderStatusesSelectable
        );
        // Unlike other API endpoints, subscriptions API only supports status keys in lower case!
        $this->subscriptionStatuses = array(
            'all' =>  $this->_('All Subscriptions'),
            'paid' => $this->_('Paid'),
            'active' => $this->_('Active'),
            'paused' => $this->_('Paused'),
            'canceled' => $this->_('Cancelled'), // Key 'Canceled' with a single "l" is not a typo here!
        );
        $this->commentTypes = array(
            'Comment' => $this->_('Comment'),
            'OrderStatusChanged' => $this->_('Status changed'),
            'OrderShipped' => $this->_('Order shipped'),
            'OrderCancelled' =>  $this->_('Order cancelled'),
            'TrackingNumber' => $this->_('Tracking number'),
            'Invoice' => $this->_('Invoice sent'),
            'Refund' => $this->_('Refunded amount')
        );
        $this->paymentStatuses = array(
            'All' =>  $this->_('All Orders'),
            'Paid' => $this->_('Paid'),
            'PaidDeferred' => $this->_('Paid (deferred)'),
            'Deferred' => $this->_('Not paid (deferred)'),
            'ChargedBack' => $this->_('Charged back'),
            'Refunded' => $this->_('Refunded'),
            'Paidout' => $this->_('Paid out'),
            'Failed' => $this->_('Failed'),
            'Pending' => $this->_('Pending'),
            'Expired' => $this->_('Expired'),
            'Cancelled' => $this->_('Cancelled'),
            'Open' => $this->_('Open'),
            'Authorized' => $this->_('Authorized'),
        );
        $this->paymentMethods = array(
            'All' =>  $this->_('All Methods'),
            'CreditCard' => $this->_('Credit Card'),
            'WillBePaidLater' => $this->_('Deferred Payment'),
        );
        $this->abandonedCartsTimeRanges = array(
            'Anytime' =>  $this->_('Anytime'),
            'LessThan4Hours' => $this->_('Last 4 hours'),
            'LessThanADay' => $this->_('Last 24 hours'),
            'LessThanAWeek' => $this->_('Last 7 days'),
            'LessThanAMonth' => $this->_('Last 30 days'),
        );
        $this->customerAddressLabels = array(
            'firstName' => $this->_('First Name'),
            'name' => $this->_('Last Name'),
            'company' => $this->_('Company'),
            'address1' => $this->_('Address 1'),
            'address2' => $this->_('Address 2'),
            'city' => $this->_('City'),
            'postalCode' => $this->_('Postal Code'),
            'province' => $this->_('Province'),
            'country' => $this->_('Country'),
            'phone' => $this->_('Phone'),
            'vatNumber' => $this->_('VAT Number'),
        );
        $this->customerStatuses = array(
            'All' =>  $this->_('All Customers'),
            'Confirmed' => $this->_('Confirmed'),
            'Unconfirmed' => $this->_('Unconfirmed'),
        );
        $this->discountsStatuses = array(
            'All' =>  $this->_('All Discounts'),
            'Active' => $this->_('Active'),
            'Archived' => $this->_('Archived'),
        );
        $this->discountsTypes = array(
            'FixedAmount' => $this->_('Fixed amount deducted from order total'),
            'Rate' => $this->_('Percentage rebate on order total'),
            'AlternatePrice' => $this->_('Discount price provided by alternate price list'),
            'Shipping' => $this->_('Discount on shipping'),
            'FixedAmountOnItems' => $this->_('Fixed amount deducted on specified products'),
            'RateOnItems' => $this->_('Rate deducted on specified products'),
            'FixedAmountOnCategory' => $this->_('Fixed amount deducted on items of specified categories'),
            'RateOnCategory' => $this->_('Rate deducted on products of specified categories'),
            'GetFreeItems' => $this->_('Free products when customer buys specified quantity of product'),
            'AmountOnSubscription' => $this->_('Fixed amount on subscription'),
            'RateOnSubscription' => $this->_('Rate on subscription'),
        );
        $this->discountsTriggers = array(
            'Code' => $this->_('Enter discount code'),
            'Product' => $this->_('Specific product added'),
            'Total' => $this->_('Order reaches specific amount'),
            'QuantityOfAProduct' => $this->_('Product added a number of times'),
            'CartContainsOnlySpecifiedProducts' => $this->_('Cart only contains specified products'),
            'CartContainsSomeSpecifiedProducts' => $this->_('Cart contains some of specified products'),
            'CartContainsAtLeastAllSpecifiedProducts' => $this->_('Cart contains at least all specified products'),
        );
    }

    /**
     * Get all pre-translated order statuses.
     *
     * @param boolean $includeAllKey Wether to return the array with the "All" key included
     * @return array
     *
     */
    public function getOrderStatuses($includeAllKey = true) {
        if ($includeAllKey) return $this->orderStatuses;
        $orderStatuses = $this->orderStatuses;
        array_shift($orderStatuses);
        return $orderStatuses;
    }

    /**
     * Get all selectable pre-translated order statuses.
     *
     * @return array
     *
     */
    public function getOrderStatusesSelectable() {
        return $this->orderStatusesSelectable;
    }

    /**
     * Get a pre-translated order status by it's key.
     *
     * @param string $key The array key
     * @return string
     *
     */
    public function getOrderStatus($key) {
        return isset($this->orderStatuses[$key])
            ? $this->orderStatuses[$key]
            : $this->_('-- unknown --');
    }
    
    /**
     * Get all pre-translated subscription statuses.
     *
     * @param boolean $includeAllKey Wether to return the array with the "All" key included
     * @return array
     *
     */
    public function getSubscriptionStatuses($includeAllKey = true) {
        if ($includeAllKey) return $this->subscriptionStatuses;
        $subscriptionStatuses = $this->subscriptionStatuses;
        array_shift($subscriptionStatuses);
        return $subscriptionStatuses;
    }
    
    /**
     * Get a pre-translated subscription status by it's key.
     *
     * @param string $key The array key
     * @return string
     *
     */
    public function getSubscriptionStatus($key) {
        $key = strtolower($key);
        return isset($this->subscriptionStatuses[$key])
            ? $this->subscriptionStatuses[$key]
            : $this->_('-- unknown --');
    }
    
    /**
     * Get all pre-translated comment types.
     *
     * @return array
     *
     */
    public function getCommentTypes() {
        return $this->commentTypes;
    }

    /**
     * Get a pre-translated comment type by it's key.
     *
     * @param string $key The array key
     * @return string
     *
     */
    public function getCommentType($key) {
        return isset($this->commentTypes[$key])
            ? $this->commentTypes[$key]
            : $this->_('-- unknown --');
    }

    /**
     * Get all pre-translated payment statuses.
     *
     * @param boolean $includeAllKey Wether to return the array with the "All" key included
     * @return array
     *
     */
    public function getPaymentStatuses($includeAllKey = true) {
        if ($includeAllKey) return $this->paymentStatuses;
        $paymentStatuses = $this->paymentStatuses;
        array_shift($paymentStatuses);
        return $paymentStatuses;
    }

    /**
     * Get a pre-translated payment status by it's key.
     *
     * @param string $key The array key
     * @return string
     *
     */
    public function getPaymentStatus($key) {
        return isset($this->paymentStatuses[$key])
            ? $this->paymentStatuses[$key]
            : $this->_('-- unknown --');
    }

    /**
     * Get all pre-translated payment methods.
     *
     * @param boolean $includeAllKey Wether to return the array with the "All" key included
     * @return array
     *
     */
    public function getPaymentMethods($includeAllKey = true) {
        if ($includeAllKey) return $this->paymentMethods;
        $paymentMethods = $this->paymentMethods;
        array_shift($paymentMethods);
        return $paymentMethods;
    }

    /**
     * Get a pre-translated payment method by it's key.
     *
     * @param string $key The array key
     * @return string
     *
     */
    public function getPaymentMethod($key) {
        return isset($this->paymentMethods[$key])
            ? $this->paymentMethods[$key]
            : $this->_('-- unknown --');
    }

    /**
     * Get all pre-translated abandoned carts time-ranges.
     *
     * @return array
     *
     */
    public function getAbandonedCartsTimeRanges() {
        return $this->abandonedCartsTimeRanges;
    }

    /**
     * Get a pre-translated abandoned carts time-range by it's key.
     *
     * @param string $key The array key
     * @return string
     *
     */
    public function getAbandonedCartsTimeRange($key) {
        return isset($this->abandonedCartsTimeRanges[$key])
            ? $this->abandonedCartsTimeRanges[$key]
            : $this->_('-- unknown --');
    }

    /**
     * Get all pre-translated customer address-labels.
     *
     * @return array
     *
     */
    public function getCustomerAddressLabels() {
        return $this->customerAddressLabels;
    }

    /**
     * Get all pre-translated customer statuses.
     *
     * @param boolean $includeAllKey Wether to return the array with the "All" key included
     * @return array
     *
     */
    public function getCustomerStatuses($includeAllKey = true) {
        if ($includeAllKey) return $this->customerStatuses;
        $customerStatuses = $this->customerStatuses;
        array_shift($customerStatuses);
        return $customerStatuses;
    }

    /**
     * Get a pre-translated customer status by it's key.
     *
     * @param string $key The array key
     * @return string
     *
     */
    public function getCustomerStatus($key) {
        return isset($this->customerStatuses[$key])
            ? $this->customerStatuses[$key]
            : $this->_('-- unknown --');
    }

    /**
     * Get all pre-translated discounts statuses.
     *
     * @param boolean $includeAllKey Wether to return the array with the "All" key included
     * @return array
     *
     */
    public function getDiscountsStatuses($includeAllKey = true) {
        if ($includeAllKey) return $this->discountsStatuses;
        $discountsStatuses = $this->discountsStatuses;
        array_shift($discountsStatuses);
        return $discountsStatuses;
    }

    /**
     * Get a pre-translated discounts status by it's key.
     *
     * @param string $key The array key
     * @return string
     *
     */
    public function getDiscountsStatus($key) {
        return isset($this->discountsStatuses[$key])
            ? $this->discountsStatuses[$key]
            : $this->_('-- unknown --');
    }

    /**
     * Get all pre-translated discounts types.
     *
     * @param boolean $keysonly Whether to return only the array keys
     * @return array
     *
     */
    public function getDiscountsTypes($keysonly = false) {
        if ($keysonly) return array_keys($this->discountsTypes);
        return $this->discountsTypes;
    }

    /**
     * Get a pre-translated discounts type by it's key.
     *
     * @param string $key The array key
     * @return string
     *
     */
    public function getDiscountsType($key) {
        return isset($this->discountsTypes[$key])
            ? $this->discountsTypes[$key]
            : $this->_('-- unknown --');
    }

    /**
     * Get all pre-translated discounts triggers.
     *
     * @param boolean $keysonly Whether to return only the array keys
     * @return array
     *
     */
    public function getDiscountsTriggers($keysonly = false) {
        if ($keysonly) return array_keys($this->discountsTriggers);
        return $this->discountsTriggers;
    }

    /**
     * Get a pre-translated discounts trigger by it's key.
     *
     * @param string $key The array key
     * @return string
     *
     */
    public function getDiscountsTrigger($key) {
        return isset($this->discountsTriggers[$key])
            ? $this->discountsTriggers[$key]
            : $this->_('-- unknown --');
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
            return '<div id="SnipWireDashboardModal">' . $out . '</div>';
        }

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
            'subscriptions' => array(
                'label' => $this->_('Subscriptions'),
                'urlsegment' => 'subscriptions',
            ),
            'abandoned-carts' => array(
                'label' => $this->_('Abandoned Carts'),
                'urlsegment' => 'abandoned-carts',
            ),
            'customers' => array(
                'label' => $this->_('Customers'),
                'urlsegment' => 'customers',
            ),
            'products' => array(
                'label' => $this->_('Products'),
                'urlsegment' => 'products',
            ),
            'discounts' => array(
                'label' => $this->_('Discounts'),
                'urlsegment' => 'discounts',
            ),
            'settings' => array(
                'label' => wireIconMarkup(self::iconSettings),
                'urlsegment' => 'settings',
                'tooltip' => $this->_('SnipWire module settings'),
            ),
        );

        $tabs = array();
        foreach ($tabsConfig as $id => $cfg) {
            $cls = array();
            $attrs = array();
            $attrs[] = 'id="_' . $id . '"';
            if ($cfg['urlsegment'] == $this->getProcessPage()->urlSegment) $cls[] = 'on';
            if (!empty($cfg['tooltip'])) {
                $attrs[] = 'title="' . $cfg['tooltip'] . '"';
                $cls[] = 'pw-tooltip';
            }
            $classes = implode(' ', $cls);
            $classes = $classes ? ' class="' . $classes . '"' : '';
            $attributes = implode(' ', $attrs);
            $attributes = $attributes ? ' ' . $attributes : '';
            $tabs[$cfg['urlsegment']] =
            '<a href="' . $this->snipWireRootUrl . $cfg['urlsegment'] . '"' . $classes . $attributes . '>' .
                $cfg['label'] .
            '</a>';
        }

        $out =
        $this->_renderTabListCustom($tabs, $options) .
        '<div id="SnipWireDashboard">' . $out . '</div>';

        $moduleInfoSnipWire = $modules->getModuleInfoVerbose('SnipWire');
        $out .= 
        '<p class="footer-version-info">' .
            $moduleInfoSnipWire['title'] .
            '<small>' .
                $moduleInfoSnipWire['versionStr'] . ' &copy; ' . date('Y') .
            '</small>' .
        '</p>';

        return $out;
    }

    /**
     * Render a tab list (WireTabs) to prevent "jump of tabs" on page reload in UIKit admin theme
     * (This is a modified version of renderTabList method from JqueryWireTabs class)
     *
     * @todo: should be fixed in WireTabs core module!
     * 
     * @param array $tabs array of (tabID => title)
     * @param array $options to modify behavior
     * @return string WireTabs compatible Markup
     * 
     */
    private function _renderTabListCustom(array $tabs, array $options = array()) {
        $settings = $this->wire('config')->get('JqueryWireTabs');
        $defaults = array(
            'class' => isset($options['class']) ? $options['class'] : $settings['ulClass'],
            'id' => '', 
        );
        $options = array_merge($defaults, $options); 
        $attrs = "class='$options[class]'" . ($options['id'] ? " id='$options[id]'" : "");
        if (!empty($settings['ulAttrs'])) $attrs .= " $settings[ulAttrs]";
        $out = "<ul $attrs>";
        
        foreach ($tabs as $tabID => $title) {
            $tabCls = array();
            $tabAttrs = array();
            if ($tabID == $this->getProcessPage()->urlSegment) {
                $tabCls[] = 'uk-active';
                $tabAttrs[] = 'aria-expanded="true"';
            } else {
                $tabAttrs[] = 'aria-expanded="false"';
            }
            $classes = implode(' ', $tabCls);
            $classes = $classes ? ' class="' . $classes . '"' : '';
            $attributes = implode(' ', $tabAttrs);
            $attributes = $attributes ? ' ' . $attributes : '';
            if (strpos($title, '<a ') !== false) {
                $out .= "<li$attributes$classes>$title</li>";
            } else {
                $out .= "<li$attributes$classes><a href='#$tabID' id='_$tabID'>$title</a></li>";
            }
        }
        
        $out .= "</ul>";
        return $out; 
    }

    /**
     * Render action buttons with wrapper.
     * (used below item listers and detail pages)
     *
     * @param boolean $showRefreshButtons Whether to display refresh cache buttons [default=true]
     * @param string $buttons Pre-rendered additional action buttons markup [default='']
     * @return markup
     *
     */
    private function _renderActionButtons($showRefreshButtons = true, $buttons = '') {
        $modules = $this->wire('modules');
        $input = $this->wire('input');
        
        $additionalButtons = '';
        if ($buttons) {
            $additionalButtons = '<div class="AdditionalButtonsWrapper">' . $buttons . '</div>';
        }

        $refreshButtons = '';
        if ($showRefreshButtons) {
            $modal = ($input->get('modal')) ? '&modal=1' : '';
            $right = $additionalButtons ? ' wrapper-right' : '';

            /** @var InputfieldButton $btn */
            $btn = $modules->get('InputfieldButton');
            $btn->href = $this->currentUrl . '?action=refresh' . $modal;
            $btn->value = $this->_('Refresh');
            $btn->icon = 'refresh';
            $btn->secondary = true;
            $btn->showInHeader();
    
            $refreshButtons = $btn->render();
    
            /** @var InputfieldButton $btn */
            $btn = $modules->get('InputfieldButton');
            $btn->href = $this->currentUrl . '?action=refresh_all' . $modal;
            $btn->value = $this->_('Refresh all');
            $btn->icon = 'refresh';
            $btn->secondary = true;
            $btn->attr('title', $this->_('Refresh the complete Snipcart cache for all sections'));
            $btn->addClass('pw-tooltip');
    
            $refreshButtons .= $btn->render();
            $refreshButtons = '<div class="RefreshButtonsWrapper' . $right . '">' . $refreshButtons . '</div>';
        }

        $out = '<div class="ActionButtonsWrapper">' . $additionalButtons . $refreshButtons . '</div>';
        return $out;
    }

    /**
     * Renders a data sheet (styled like AdminDataTable).
     *
     * @param array $data (label => value)
     * @return markup
     *
     */
    public function renderDataSheet(array $data) {

        $out = '<table class="SnipWireDataSheet">';
        foreach ($data as $label => $value) {
            $out .=
            '<tr>' .
                '<th>' . $label . '</th> ' .
                '<td>' . $value . '</td>' .
            '</tr>';
        }
        $out .= '</table>';

        return $out;
    }
    
    /**
     * Show a notice if SnipWire is set to use Snipcart TEST mode
     *
     */
    private function _noticeTestMode() {
        if ($this->snipwireConfig->snipcart_environment != 0) return; // 0 = TEST mode
        $settingsUrl = $this->snipWireRootUrl . 'settings';
        $this->message(
            'icon-plug ' .
            $this->_('SnipWire currently runs in Snipcart TEST mode, suitable for stores in development and for secure staging') .
            '<div><small class="ui-priority-secondary">' .
            $this->_('To switch to LIVE mode, open SnipWire module settings and change the Snipcart environment') .
            '</small></div>',
            Notice::allowMarkup | Notice::anonymous | Notice::noGroup
        );
    }
    
    /**
     * Renders a wrapper for the item lister headline.
     *
     * @param string $headline
     * @return headline markup
     *
     */
    private function _wrapItemListerHeadline($headline) {
        return '<h2 class="ItemListerHeadline">' . $headline . '</h2>';
    }

    /**
     * Prepares a PageArray with generic placeholder pages to give MarkupPagerNav what it needs.
     *
     * @param integer $total 
     * @param integer $count
     * @param integer $limit
     * @param integer $offset
     * @return PageArray $pageArray A PageArray filled with generic pages
     *
     */
    private function _prepareItemListerPagination($total, $count, $limit, $offset) {
        // Add in generic placeholder pages
        $pageArray = new PageArray();
        $pageArray->setDuplicateChecking(false);
        for ($i = 0; $i < $count; $i++) {
            $pageArray->add(new Page());
        }
        
        // Tell the PageArray some details it needs for pagination
        // (something that PW usually does internally, for pages it loads)
        $pageArray->setTotal($total);
        $pageArray->setLimit($limit);
        $pageArray->setStart($offset);
        
        return $pageArray;
    }

    /**
     * Get markup for custom filter form buttons.
     *
     * @param string $resetUrl Currency string
     * @return markup Button markup for filter forms
     *
     */
    private function _getFilterFormButtons($resetUrl) {
        // Currently need to add button(s) below a form this way as 
        // InputfieldButton has a lot of layout based problems
        // @todo: fix this in Processwire core
        $out = 
        '<small>' .
            '<button class="ui-button ui-widget ui-corner-all ui-state-default" type="submit">' .
                '<span class="ui-button-text">' .
                    '<i class="fa fa-search"></i> ' . $this->_('Search') .
                '</span>' .
            '</button>' .
        '</small>' .
        '<small>' .
            '<button class="ui-button ui-widget ui-corner-all ui-state-default ui-priority-secondary ItemsFilterResetButton" value="' . $resetUrl . '" type="button">' .
                '<span class="ui-button-text">' .
                    '<i class="fa fa-rotate-left"></i> ' . $this->_('Reset') .
                '</span>' .
            '</button>' .
        '</small>';

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
        $input = $this->wire('input');
        $sanitizer = $this->wire('sanitizer');
        return $sanitizer->entities($input->action);
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

        if ($mode & self::assetsIncludeDateRangePicker || $mode & self::assetsIncludeApexCharts) {
            // Include moment.js JS assets
            $config->scripts->add($config->urls->SnipWire . 'vendor/moment.js/moment.min.js?v=2.24.0');
        }
        if ($mode & self::assetsIncludeDateRangePicker) {
            // Include daterangepicker CSS/JS assets
            $config->scripts->add($config->urls->SnipWire . 'assets/scripts/DateRangePicker.min.js' . $versionAdd);
        }
        if ($mode & self::assetsIncludeCurrencyPicker) {
            // Include currency picker JS assets
            $config->scripts->add($config->urls->SnipWire . 'assets/scripts/CurrencyPicker.min.js' . $versionAdd);
        }
        if ($mode & self::assetsIncludeApexCharts) {
            // Include ApexCharts CSS/JS assets
            $config->styles->add($config->urls->SnipWire . 'assets/styles/PerformanceChart.css' . $versionAdd);
            $config->scripts->add($config->urls->SnipWire . 'vendor/apexcharts.js/apexcharts.min.js?v=3.15.6');
            $config->scripts->add($config->urls->SnipWire . 'assets/scripts/PerformanceChart.min.js' . $versionAdd);
        }
    }

    /**
     * Redirect to SniWire module settings.
     *
     * @return page Markup
     *
     */
    public function ___executeSettings() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $session = $this->wire('session');

        $this->browserTitle($this->_('Snipcart Module Settings'));
        $this->headline($this->_('Snipcart Module Settings'));
        
        if (!$user->isSuperuser()) {
            $this->error($this->_('You dont have permisson to change SnipWire module settings - please contact your admin!'));
            return '';
        }

        $redirectTo = $modules->getModuleEditUrl('SnipWire');
        $session->redirect($redirectTo);
        
        // Should never be rendered ... (just to be sure)
        
        /** @var InputfieldButton $btn */
        $btn = $modules->get('InputfieldButton');
        $btn->href = $redirectTo;
        $btn->value = $this->_('Settings');
        $btn->icon = 'cog';
        
        $out = $btn->render();
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
        $sniprest = $this->wire('sniprest');

        $comeFromUrl = $sanitizer->url($input->get('ret'));
        $submitInstall = $input->post('submit_install');

        $this->browserTitle($this->_('Products Package Installer'));
        $this->headline($this->_('SnipWire Products Package Installer'));

        /** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm'); 
        $form->attr('id', 'SnipWireInstallerForm'); 
        $form->attr('action', $this->currentUrl . '?ret=' . urlencode($comeFromUrl)); 
        $form->attr('method', 'post');

        $snipwireConfig = $modules->getConfig('SnipWire');
        
        // Prevent installation when already installed
        if (isset($snipwireConfig['product_package']) && $snipwireConfig['product_package']) {
            
            $this->warning($this->_('SnipWire products package is already installed!'));
            $this->wire('session')->redirect($comeFromUrl);
            
        // Install
        } else {

            $apiReady = true;
            if (($result = $sniprest->testConnection()) !== true) {
                $warningMessage = $this->_('SnipWire products package cannot be installed. Snipcart REST API connection failed! Please check your secret API keys.');
                $this->warning($warningMessage);
                $this->wire('session')->redirect($comeFromUrl);
                // Should not get here - just to be sure!
                $apiReady = false;
            }

            /** @var ExstendedInstaller $installer */
            $installer = $this->wire(new ExtendedInstaller());
            $installer->setResourcesFile('ProductsPackage.php');

            /** @var InputfieldMarkup $f (installation info) */
            $f = $modules->get('InputfieldMarkup');
            $f->icon = 'sign-in';
            $f->label = $this->_('Install Products Package');
            $f->description = $this->_('Do you want to install the SnipWire products package? This will create product templates, files, fields and pages required by Snipcart.');
            $f->collapsed = Inputfield::collapsedNever;
            $form->add($f);
            
            /** @var InputfieldMarkup $f (additional info) */
            $f = $modules->get('InputfieldMarkup');
            $f->icon = 'exclamation-circle';
            $f->label = $this->_('Please Note');
            $f->skipLabel = Inputfield::skipLabelHeader;
            $f->addClass('InputfieldIsWarning');
            $f->value  = '<p>';
            $f->value .=     $this->_('To prevent unintended deletion of your Snipcart products catalogue, the products package resources can\'t be uninstalled automatically.') . ' ';
            $f->value .=     $this->_('If you want to uninstall completely, these resources need to be removed manually!');
            $f->value .= '</p>';
            $f->value .= '<p><strong>';
            $f->value .=     $this->_('Before continuing please be sure to backup your ProcessWire installation and your database!');
            $f->value .= '</strong></p>';
            $f->collapsed = Inputfield::collapsedNever;
            $form->add($f);
            
            /** @var InputfieldMarkup $f (listing resources to be installed) */
            $f = $modules->get('InputfieldMarkup');
            $f->icon = 'list-ul';
            $f->label = $this->_('The following resources will be installed');
            $f->value = '<pre>' . $sanitizer->entities(print_r($installer->getResources(true), true)) . '</pre>';
            $f->collapsed = Inputfield::collapsedYes;
            $form->add($f);
                        
            if ($apiReady) {
                /** @var InputfieldSubmit $f */
                $f = $modules->get('InputfieldSubmit');
                $f->attr('name', 'submit_install');
                $f->attr('value', $this->_('Install'));
                $f->icon = 'sign-in';
                $form->add($f);
            }

            // Was the form submitted?
            if ($submitInstall) {
                $installResources = $installer->installResources(ExtendedInstaller::installerModeAll);
                if (!$installResources) {                        
                    $this->warning($this->_('Installation of SnipWire product package not completet. Please check for errors and warnings...'));
                } else {
                    // Update SnipWire module config to tell system that product package is installed
                    // (need to load config again as it could be changed meanwhile!)
                    $snipwireConfig = $modules->getConfig('SnipWire');
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
     * Install additional SnipWire system resources.
     *
     * @return boolean
     *
     */
    private function _installSystemResources() {
        /** @var ExtendedInstaller $installer */
        $installer = $this->wire(new ExtendedInstaller());
        $installer->setResourcesFile('SystemResources.php');
        return $installer->installResources(ExtendedInstaller::installerModeAll);        
    }

    /**
     * Uninstall additional SnipWire system resources.
     *
     * @return boolean
     *
     */
    private function _uninstallSystemResources() {
        /** @var ExtendedInstaller $installer */
        $installer = $this->wire(new ExtendedInstaller());
        $installer->setResourcesFile('SystemResources.php');
        return $installer->uninstallResources(ExtendedInstaller::installerModeAll);        
    }

    /**
     * Called on module install
     *
     */
    public function ___install() {
        parent::___install();
        if (!$this->_installSystemResources()) {                        
            $out = $this->_('Installation of SnipWire system resources failed.');
            $this->error($out);
        }
    }

    /**
     * Called on module version change
     * 
     * @param int|string $fromVersion Previous version
     * @param int|string $toVersion New version
     * 
     */
    public function ___upgrade($fromVersion, $toVersion) {
        // added since v 0.7.1: custom cart fields support
        // added since v 0.8.0: custom product fields support
        if (version_compare($fromVersion, '0.7.9', '<=')) {
            if (!$this->_installSystemResources()) {                        
                $out = $this->_('Installation of SnipWire system resources failed while upgrading the module.');
                $this->error($out);
            }
        }
        
        // added since v 0.8.6: subscription product fields
        if (version_compare($fromVersion, '0.8.5', '<=')) {
            /** @var ExtendedInstaller $installer */
            $installer = $this->wire(new ExtendedInstaller());
            $installer->setResourcesFile('ProductsPackageUpdate-0.8.6.php');
            if(!$installer->installResources(ExtendedInstaller::installerModeFields)) {
                $message = $this->_('Installation of additional SnipWire product fields failed while upgrading the module.');
                $this->error($message);
            }      
        }
    }

    /**
     * Called on module uninstall
     *
     */
    public function ___uninstall() {
        if (!$this->_uninstallSystemResources()) {                        
            $out = $this->_('Uninstallation of SnipWire system resources failed.');
            $this->error($out);
        }
        parent::___uninstall();
    }
}
