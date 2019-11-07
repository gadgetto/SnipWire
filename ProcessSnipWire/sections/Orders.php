<?php namespace ProcessWire;

/**
 * Orders trait - sections file for ProcessSnipWire.module.php.
 * (This file is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

trait Orders {
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
        $sanitizer = $this->wire('sanitizer');
        $session = $this->wire('session');
        $sniprest = $this->wire('sniprest');

        $this->browserTitle($this->_('Snipcart Orders'));
        $this->headline($this->_('Snipcart Orders'));

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
        }

        $status = $sanitizer->text($input->status);
        $paymentStatus = $sanitizer->text($input->paymentStatus);
        $invoiceNumber = $sanitizer->text($input->invoiceNumber);
        $placedBy = $sanitizer->text($input->placedBy);
        $filter = array(
            'status' => $status ? $status : 'All',
            'paymentStatus' => $paymentStatus ? $paymentStatus : 'All',
            'invoiceNumber' => $invoiceNumber ? $invoiceNumber : '',
            'placedBy' => $placedBy ? $placedBy : '',
        );

        $defaultSelector = array(
            'offset' => $offset,
            'limit' => $limit,
        );
 
        $selector = array_merge($defaultSelector, $filter);

        $request = $sniprest->getOrders(
            '',
            $selector,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );

        $orders = isset($request[SnipRest::resourcePathOrders][WireHttpExtended::resultKeyContent])
            ? $request[SnipRest::resourcePathOrders][WireHttpExtended::resultKeyContent]
            : array();

        $total = isset($orders['totalItems']) ? $orders['totalItems'] : 0;
        $items = isset($orders['items']) ? $orders['items'] : array();
        $count = count($items);
        
        // Pagination out of bound
        if (!$count && $input->pageNum > 1) {
            $session->redirect($this->processUrl);
            return '';
        }

        $out = $this->_buildOrdersFilter($filter);

        $pageArray = $this->_prepareItemListerPagination($total, $count, $limit, $offset);
        $headline = $pageArray->getPaginationString(array(
            'label' => $this->_('Orders'),
            'zeroLabel' => $this->_('No orders found'), // 3.0.127+ only
        ));

        $pager = $modules->get('MarkupPagerNav');
        $pager->setBaseUrl($this->processUrl);
        $pager->setGetVars($filter);
        $pagination = $pager->render($pageArray);

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Orders');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconOrder;
        $f->value = $this->_wrapItemListerHeadline($headline);
        $f->value .= $pagination;
        $f->value .= $this->_renderTableOrders($items);
        $f->value .= $pagination;
        $f->collapsed = Inputfield::collapsedNever;

        $out .= $f->render();

        /** @var InputfieldButton $btn */
        $btn = $modules->get('InputfieldButton');
        $btn->id = 'refresh-data';
        $btn->href = $this->currentUrl . '?action=refresh';
        $btn->value = $this->_('Refresh');
        $btn->icon = 'refresh';
        $btn->showInHeader();

        $out .= '<div class="ItemListerButtons">' . $btn->render() . '</div>';

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
        $btn->href = $this->currentUrl . '?action=refresh&modal=1';
        $btn->value = $this->_('Refresh');
        $btn->icon = 'refresh';

        $out .= $btn->render();

        return $this->_wrapDashboardOutput($out);
    }

    /**
     * Build the orders filter form.
     *
     * @param array $filter The current filter values
     * @return markup InputfieldForm
     *
     */
    private function _buildOrdersFilter($filter) {
        $modules = $this->wire('modules');
        $config = $this->wire('config');

        $statuses = array(
            'All' =>  $this->_('All Orders'),
            'InProgress' => $this->_('In Progress'),
            'Processed' => $this->_('Processed'),
            'Disputed' => $this->_('Disputed'),
            'Shipped' => $this->_('Shipped'),
            'Delivered' => $this->_('Delivered'),
            'Pending' => $this->_('Pending'),
            'Cancelled' => $this->_('Cancelled'),
        );
        
        $filterSettings = array(
            'form' => '#OrdersFilterForm',
        );

        // Hand over configuration to JS
        $config->js('filterSettings', $filterSettings);

        /** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm'); 
        $form->attr('id', 'OrdersFilterForm');
        $form->method = 'post';
        $form->action = $this->currentUrl;

            /** @var InputfieldFieldset $fsSnipWire */
            $fieldset = $modules->get('InputfieldFieldset');
            $fieldset->label = $this->_('Search for Orders');
            $fieldset->icon = 'search';
            if (
                ($filter['status'] && $filter['status'] != 'All') ||
                ($filter['paymentStatus'] && $filter['paymentStatus'] != 'All') ||
                $filter['invoiceNumber'] ||
                $filter['placedBy']
            ) {
                $fieldset->collapsed = Inputfield::collapsedNo;
            } else {
                $fieldset->collapsed = Inputfield::collapsedYes;
            }

                /** @var InputfieldSelect $f */
                $f = $modules->get('InputfieldSelect'); 
                $f->attr('name', 'status'); 
                $f->label = $this->_('Status'); 
                $f->value = $filter['status'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 50;
                $f->required = true;
                $f->addOptions($statuses);

            $fieldset->add($f);

                /** @var InputfieldSelect $f */
                $f = $modules->get('InputfieldSelect'); 
                $f->attr('name', 'paymentStatus'); 
                $f->label = $this->_('Payment Status'); 
                $f->value = $filter['paymentStatus'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 50;
                $f->required = true;
                $f->addOptions($this->paymentStatuses);

            $fieldset->add($f);

                /** @var InputfieldText $f */
                $f = $modules->get('InputfieldText');
                $f->attr('name', 'invoiceNumber');
                $f->label = $this->_('Invoice Number');
                $f->value = $filter['invoiceNumber'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 50;

            $fieldset->add($f);

                /** @var InputfieldText $f */
                $f = $modules->get('InputfieldText');
                $f->attr('name', 'placedBy');
                $f->label = $this->_('Placed By');
                $f->value = $filter['placedBy'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 50;

            $fieldset->add($f);
            
                $buttonsWrapper = $modules->get('InputfieldMarkup');
                $buttonsWrapper->contentClass = 'ItemsFilterButtonWrapper';
                $buttonsWrapper->markupText = $this->_getFilterFormButtons($this->processUrl);

            $fieldset->add($buttonsWrapper);

        $form->add($fieldset);

        return $form->render(); 
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
                $this->_('Status'),
                $this->_('Payment Status'),
                $this->_('Total'),
            ));
            foreach ($items as $item) {
                $panelLink =
                '<a href="' . $this->snipWireRootUrl . 'order/' . $item['token'] . '"
                    class="pw-panel pw-panel-links"
                    data-panel-width="75%">' .
                        wireIconMarkup(self::iconOrder, 'fa-right-margin') . $item['invoiceNumber'] .
                '</a>';
                $panelLink2 =
                '<a href="' . $this->snipWireRootUrl . 'customer/' . $item['user']['id'] . '"
                    class="pw-panel"
                    data-panel-width="75%">' .
                        $item['user']['billingAddress']['fullName'] .
                '</a>';
                $table->row(array(
                    $panelLink,
                    wireDate('relative', $item['creationDate']),
                    $panelLink2,
                    $item['billingAddressCountry'],
                    $item['status'],
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
}
