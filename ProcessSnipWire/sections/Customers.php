<?php
namespace SnipWire\ProcessSnipWire\Sections;

/**
 * Customers trait - sections file for ProcessSnipWire.module.php.
 * (This file is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

use SnipWire\Helpers\Countries;
use SnipWire\Helpers\CurrencyFormat;
use SnipWire\Services\SnipREST;
use SnipWire\Services\WireHttpExtended;
use ProcessWire\Inputfield;

trait Customers {
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
        $sanitizer = $this->wire('sanitizer');
        $session = $this->wire('session');
        $sniprest = $this->wire('sniprest');
        
        $this->browserTitle($this->_('Snipcart Customers'));
        $this->headline($this->_('Snipcart Customers'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

        $forceRefresh = false;
        $limit = 20;
        $offset = ($input->pageNum - 1) * $limit;

        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        } elseif ($action == 'refresh_all') {
            $sniprest->deleteFullCache();
            $this->message(SnipREST::getMessagesText('full_cache_refreshed'));
        }

        $status = $sanitizer->text($input->status);
        $email = $sanitizer->text($input->email);
        $name = $sanitizer->text($input->name);
        $filter = [
            'status' => $status ? $status : 'All',
            'email' => $email ? $email : '',
            'name' => $name ? $name : '',
        ];

        $defaultSelector = [
            'offset' => $offset,
            'limit' => $limit,
        ];

        $selector = array_merge($defaultSelector, $filter);

        $response = $sniprest->getCustomers(
            '',
            $selector,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );

        $dataKey = SnipREST::resPathCustomers;
        $customers = isset($response[$dataKey][WireHttpExtended::resultKeyContent])
            ? $response[$dataKey][WireHttpExtended::resultKeyContent]
            : [];
        
        $total = isset($customers['totalItems']) ? $customers['totalItems'] : 0;
        $items = isset($customers['items']) ? $customers['items'] : [];
        $count = count($items);

        // Pagination out of bound
        if (!$count && $input->pageNum > 1) {
            $session->redirect($this->processUrl);
            return '';
        }

        $out = $this->_buildCustomersFilter($filter);

        $pageArray = $this->_prepareItemListerPagination($total, $count, $limit, $offset);
        $headline = $pageArray->getPaginationString([
            'label' => $this->_('Customers'),
            'zeroLabel' => $this->_('No customers found'), // 3.0.127+ only
        ]);

        $pager = $modules->get('MarkupPagerNav');
        $pager->setBaseUrl($this->processUrl);
        $pager->setGetVars($filter);
        $pagination = $pager->render($pageArray);

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Customers');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconCustomer;
        $f->value = $this->_wrapItemListerHeadline($headline);
        $f->value .= $pagination;
        $f->value .= $this->_renderTableCustomers($items);
        $f->value .= $pagination;
        $f->collapsed = Inputfield::collapsedNever;

        $out .= $f->render();

        $out .= $this->_renderActionButtons();

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
        
        $this->browserTitle($this->_('Snipcart Customer'));
        $this->headline($this->_('Snipcart Customer'));

        $this->breadcrumb($this->snipWireRootUrl, $this->_('SnipWire Dashboard'));
        $this->breadcrumb($this->snipWireRootUrl . 'customers/', $this->_('Snipcart Customers'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

        $id = $input->urlSegment(2); // Get Snipcart customer id
        $forceRefresh = false;

        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        } elseif ($action == 'refresh_all') {
            $sniprest->deleteFullCache();
            $this->message(SnipREST::getMessagesText('full_cache_refreshed'));
        }

        $response = $sniprest->getCustomer(
            $id,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );
        $dataKey = SnipREST::resPathCustomers . '/' . $id;
        $customer = isset($response[$dataKey][WireHttpExtended::resultKeyContent])
            ? $response[$dataKey][WireHttpExtended::resultKeyContent]
            : [];

        $out = '';

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Customer');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconCustomer;
        $f->value = $this->_renderDetailCustomer($customer);
        $f->collapsed = Inputfield::collapsedNever;

        $out .= $f->render();

        $out .= $this->_renderActionButtons();

        return $this->_wrapDashboardOutput($out);
    }

    /**
     * Build the customers filter form.
     *
     * @param array $filter The current filter values
     * @return markup InputfieldForm
     *
     */
    private function _buildCustomersFilter($filter) {
        $modules = $this->wire('modules');
        $config = $this->wire('config');

        $filterSettings = [
            'form' => '#CustomersFilterForm',
        ];

        // Hand over configuration to JS
        $config->js('filterSettings', $filterSettings);

        /** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm');
        $form->attr('id', 'CustomersFilterForm');
        $form->method = 'post';
        $form->action = $this->currentUrl;

            /** @var InputfieldFieldset $fsSnipWire */
            $fieldset = $modules->get('InputfieldFieldset');
            $fieldset->label = $this->_('Search for Customers');
            $fieldset->icon = 'search';
            if (
                ($filter['status'] && $filter['status'] != 'All') ||
                $filter['email'] ||
                $filter['name']
            ) {
                $fieldset->collapsed = Inputfield::collapsedNo;
            } else {
                $fieldset->collapsed = Inputfield::collapsedYes;
            }

                /** @var InputfieldSelect $f */
                $f = $modules->get('InputfieldSelect');
                $f->addClass('filter-form-select');
                $f->attr('name', 'status');
                $f->label = $this->_('Status');
                $f->value = $filter['status'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 33;
                $f->required = true;
                $f->addOptions($this->getCustomerStatuses());

            $fieldset->add($f);

                /** @var InputfieldText $f */
                $f = $modules->get('InputfieldEmail');
                $f->attr('name', 'email');
                $f->label = $this->_('Email');
                $f->value = $filter['email'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 33;

            $fieldset->add($f);

                /** @var InputfieldText $f */
                $f = $modules->get('InputfieldText');
                $f->attr('name', 'name');
                $f->label = $this->_('Name');
                $f->value = $filter['name'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 34;

            $fieldset->add($f);

                $buttonsWrapper = $modules->get('InputfieldMarkup');
                $buttonsWrapper->markupText = $this->_getFilterFormButtons($this->processUrl);

            $fieldset->add($buttonsWrapper);

        $form->add($fieldset);

        return $form->render();
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

            /** @var MarkupAdminDataTable $table */
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->setID('CustomersTable');
            $table->setClass('ItemLister');
            $table->setSortable(false);
            $table->setResizable(true);
            $table->setResponsive(true);
            $table->headerRow([
                $this->_('Name'),
                $this->_('Email'),
                $this->_('Created on'),
                $this->_('# Orders'),
                $this->_('# Subscriptions'),
                $this->_('Status'),
            ]);

            foreach ($items as $item) {
                $panelLink =
                '<a href="' . $this->snipWireRootUrl . 'customer/' . $item['id'] . '"
                    class="pw-panel pw-panel-links"
                    data-panel-width="85%">' .
                        \ProcessWire\wireIconMarkup(self::iconCustomer, 'fa-right-margin') . $item['billingAddress']['fullName'] .
                '</a>';
                $creationDate = '<span class="tooltip" title="';
                $creationDate .= \ProcessWire\wireDate('Y-m-d H:i:s', $item['creationDate']);
                $creationDate .= '">';
                $creationDate .= \ProcessWire\wireDate('relative', $item['creationDate']);
                $creationDate .= '</span>';

                $table->row([
                    $panelLink,
                    $item['email'],
                    $creationDate,
                    $item['statistics']['ordersCount'],
                    $item['statistics']['subscriptionsCount'],
                    $this->getCustomerStatus($item['status']),
                ]);
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
        $sanitizer = $this->wire('sanitizer');
        $sniprest = $this->wire('sniprest');

        if (empty($item)) {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No customer selected') .
            '</div>';
            return $out;
        }

        $id = $item['id'];
        $email = $item['email'];
        $ordersCount = $item['statistics']['ordersCount'];
        $subscriptionsCount = $item['statistics']['subscriptionsCount'];

        $out =
        '<div class="ItemDetailHeader">' .
            '<h2 class="ItemDetailTitle">' .
                \ProcessWire\wireIconMarkup(self::iconCustomer, 'fa-right-margin') .
                $this->_('Customer') . ': ' .
                $item['billingAddressFirstName'] . ' ' . $item['billingAddressName'] .
            '</h2>' .
            '<div class="ItemDetailActionButtons">' .
                $this->_getCustomerDetailActionButtons($id, $email) .
            '</div>' .
        '</div>';

        $response = $sniprest->getCustomersOrders($id);
        $dataKey = SnipREST::resPathCustomersOrders;
        $orders = isset($response[$dataKey][WireHttpExtended::resultKeyContent])
            ? $response[$dataKey][WireHttpExtended::resultKeyContent]
            : [];
        unset($response, $dataKey);
        
        $response = $sniprest->getSubscriptionsItems([
            'userDefinedCustomerNameOrEmail' => $email,
        ]);
        $dataKey = SnipREST::resPathSubscriptions;
        $subscriptions = isset($response[$dataKey][WireHttpExtended::resultKeyContent])
            ? $response[$dataKey][WireHttpExtended::resultKeyContent]
            : [];
        unset($response, $dataKey);
        
        /** @var InputfieldForm $wrapper */
        $wrapper = $modules->get('InputfieldForm');

            $address = $item['billingAddress'];
            $data = [];
            foreach ($this->getCustomerAddressLabels() as $key => $caption) {
                $data[$caption] = !empty($address[$key]) ? $address[$key] : '';
                if ($key == 'country' && !empty($address[$key])) $data[$caption] = Countries::getCountry($address[$key]);
                if (empty($data[$caption])) $data[$caption] = '-';
            }

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Billing Address');
            $f->icon = self::iconAddress;
            $f->value = $this->renderDataSheet($data);
            $f->columnWidth = 50;

        $wrapper->add($f);

            $address = $item['shippingAddress'];
            $data = [];
            foreach ($this->getCustomerAddressLabels() as $key => $caption) {
                $data[$caption] = !empty($address[$key]) ? $address[$key] : '';
                if ($key == 'country' && !empty($address[$key])) $data[$caption] = Countries::getCountry($address[$key]);
                if (empty($data[$caption])) $data[$caption] = '-';
            }

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->entityEncodeLabel = false;
            $f->label = $this->_('Shipping Address');
            if ($item['shippingAddressSameAsBilling']) {
                $f->label .= ' <span class="snipwire-badge snipwire-badge-info">' . $this->_('same as billing') . '</span>';
            }
            $f->icon = self::iconAddress;
            $f->value = $this->renderDataSheet($data);
            $f->columnWidth = 50;

        $wrapper->add($f);

        $out .= $wrapper->render();

        /** @var InputfieldForm $wrapper */
        $wrapper = $modules->get('InputfieldForm');

            $ordersBadge = 
            ' <span class="snipwire-badge snipwire-badge-info">' .
                sprintf($this->_n("%d order", "%d orders", $ordersCount), $ordersCount) .
            '</span>';

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->entityEncodeLabel = false;
            $f->label = $this->_('Order History');
            $f->label .= $ordersBadge;
            $f->collapsed = Inputfield::collapsedYes;
            $f->icon = self::iconOrder;
            $f->value = $this->_renderTableCustomerOrders($orders, $id);
            
        $wrapper->add($f);

        $out .= $wrapper->render();
        
        /** @var InputfieldForm $wrapper */
        $wrapper = $modules->get('InputfieldForm');

            $subscriptionsBadge = 
            ' <span class="snipwire-badge snipwire-badge-info">' .
                sprintf($this->_n("%d subscription", "%d subscriptions", $subscriptionsCount), $subscriptionsCount) .
            '</span>';

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->entityEncodeLabel = false;
            $f->label = $this->_('Subscriptions');
            $f->label .= $subscriptionsBadge;
            $f->collapsed = Inputfield::collapsedYes;
            $f->icon = self::iconOrder;
            $f->value = $this->_renderTableCustomerSubscriptions($subscriptions, $id);
            
        $wrapper->add($f);

        $out .= $wrapper->render();

        if ($this->snipwireConfig->snipwire_debug) {

            /** @var InputfieldForm $wrapper */
            $wrapper = $modules->get('InputfieldForm');

                /** @var InputfieldMarkup $f */
                $f = $modules->get('InputfieldMarkup');
                $f->label = $this->_('Debug Infos');
                $f->collapsed = Inputfield::collapsedYes;
                $f->icon = self::iconDebug;
                $f->value = '<pre>' . $sanitizer->entities(print_r($item, true)) . '</pre>';
                
            $wrapper->add($f);

            $out .= $wrapper->render();
        }

        return $out;
    }

    /**
     * Render customer detail action buttons.
     *
     * (Currently uses custom button markup as there is a PW bug which 
     * triggers href targets twice + we need to attach JavaScript events on button click)
     *
     * @param $id The customer id
     * @param $email The customer email
     * @return buttons markup 
     *
     */
    private function _getCustomerDetailActionButtons($id, $email) {
        
        $out =
        '<a href="mailto:' . $email . '"
            class="SendCustomerEmailButton ui-button ui-widget ui-corner-all ui-state-default"
            role="button">' .
                '<span class="ui-button-text ui-button-text-email">' .
                    \ProcessWire\wireIconMarkup('envelope') . ' ' . $email .
                '</span>' .
        '</a>';                    

        return $out;
    }

    /**
     * Render the customer orders table.
     *
     * @param array $items
     * @param array $customerId The customer id
     * @return markup MarkupAdminDataTable | custom html with `no items` display 
     *
     */
    private function _renderTableCustomerOrders($items, $customerId) {
        $modules = $this->wire('modules');

        if (!empty($items)) {
            $modules->get('JqueryTableSorter')->use('widgets');

            /** @var MarkupAdminDataTable $table */
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->setID('OrdersTable');
            $table->setClass('ItemLister');
            $table->setSortable(false);
            $table->setResizable(true);
            $table->setResponsive(true);
            $table->headerRow([
                $this->_('Invoice #'),
                $this->_('Placed on'),
                $this->_('Country'),
                $this->_('Status'),
                $this->_('Payment status'),
                $this->_('Payment method'),
                $this->_('Total'),
                //$this->_('Refunded'),
                '&nbsp;',
            ]);
            foreach ($items as $item) {
                // Need to attach a return URL to be able to stay in modal panel when order detail is opened
                $ret = urlencode($this->snipWireRootUrl . 'customer/' . $customerId);
                $invoiceNumber =
                '<a href="' . $this->snipWireRootUrl . 'order/' . $item['token'] . '?modal=1&ret=' . $ret . '"
                    class="pw-panel-links">' .
                        \ProcessWire\wireIconMarkup(self::iconOrder, 'fa-right-margin') . $item['invoiceNumber'] .
                '</a>';
                $total =
                '<strong>' .
                    CurrencyFormat::format($item['total'], $item['currency']) .
                '</strong>';
                // refunds values are currently missing in payload - so can't be used now
                $refunded = $item['refundsAmount']
                    ? '<span class="warning-color-dark">' .
                          CurrencyFormat::format($item['refundsAmount'], $item['currency']) .
                      '</span>'
                    : '-';
                $downloadUrl = \ProcessWire\wirePopulateStringTags(
                    SnipREST::snipcartInvoiceUrl,
                    ['token' => $item['token']]
                );
                $downloadLink =
                '<a href="' . $downloadUrl . '"
                    target="' . $item['token'] . '"
                    class="DownloadInvoiceButton pw-tooltip"
                    title="' . $this->_('Download invoice') .'">' .
                        \ProcessWire\wireIconMarkup('download') .
                '</a>';
                $creationDate = '<span class="tooltip" title="';
                $creationDate .= \ProcessWire\wireDate('Y-m-d H:i:s', $item['creationDate']);
                $creationDate .= '">';
                $creationDate .= \ProcessWire\wireDate('relative', $item['creationDate']);
                $creationDate .= '</span>';

                $table->row([
                    $invoiceNumber,
                    $creationDate,
                    Countries::getCountry($item['billingAddressCountry']),
                    $this->getOrderStatus($item['status']),
                    $this->getPaymentStatus($item['paymentStatus']),
                    $this->getPaymentMethod($item['paymentMethod']),
                    $total,
                    //$refunded,
                    $downloadLink,
                ]);
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
     * Render the customer subscriptions table.
     *
     * @param array $items
     * @param array $customerId The customer id
     * @return markup MarkupAdminDataTable | custom html with `no items` display 
     *
     */
    private function _renderTableCustomerSubscriptions($items, $customerId) {
        $modules = $this->wire('modules');

        if (!empty($items)) {
            $modules->get('JqueryTableSorter')->use('widgets');

            /** @var MarkupAdminDataTable $table */
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->setID('snipwire-subscriptions-table');
            $table->setClass('ItemLister');
            $table->setSortable(false);
            $table->setResizable(true);
            $table->setResponsive(true);
            $table->headerRow([
                $this->_('Plan'),
                $this->_('Subscription Date'),
                $this->_('Interval'),
                $this->_('Price'),
                $this->_('Total Spent'),
                $this->_('Status'),
            ]);
            foreach ($items as $item) {
                // Need to attach a return URL to be able to stay in modal panel when subscription detail is opened
                $ret = urlencode($this->snipWireRootUrl . 'customer/' . $customerId);
                $planName =
                '<a href="' . $this->snipWireRootUrl . 'subscription/' . $item['id'] . '?modal=1&ret=' . $ret . '"
                    class="pw-panel-links"
                    data-panel-width="85%">' .
                        \ProcessWire\wireIconMarkup(self::iconSubscription, 'fa-right-margin') . $item['name'] .
                '</a>';
                $creationDate = '<span class="tooltip" title="';
                $creationDate .= \ProcessWire\wireDate('Y-m-d H:i:s', $item['creationDate']);
                $creationDate .= '">';
                $creationDate .= \ProcessWire\wireDate('relative', $item['creationDate']);
                $creationDate .= '</span>';
                $interval = $item['schedule']['intervalCount'] . '&nbsp;/&nbsp;' . $item['schedule']['interval'];
                $amount =
                '<strong class="price-field">' .
                    CurrencyFormat::format($item['amount'], $item['currency']) .
                '</strong>';
                $totalSpent =
                '<strong class="price-field">' .
                    CurrencyFormat::format($item['totalSpent'], $item['currency']) .
                '</strong>';
                if ($item['pausedOn']) {
                    $status = '<span class="info-color-dark">' . $this->getSubscriptionStatus('paused') . '</span>';
                } elseif ($item['cancelledOn']) {
                    $status = '<span class="warning-color-dark">' . $this->getSubscriptionStatus('canceled') . '</span>';
                } else {
                    $status = $this->getSubscriptionStatus('active');
                }

                $table->row([
                    $planName,
                    $creationDate,
                    $interval,
                    $amount,
                    $totalSpent,
                    $status,
                ]);
            }
            $out = $table->render();
        } else {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No subscriptions found') .
            '</div>';
        }
        return '<div class="ItemListerTable">' . $out . '</div>';
    }
}
