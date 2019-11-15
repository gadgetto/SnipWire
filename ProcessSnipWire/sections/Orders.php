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

        $this->_setJSConfigValues();
        
        $out = '';

        $forceRefresh = false;
        $limit = 20;
        $offset = ($input->pageNum - 1) * $limit;

        $token = $sanitizer->text($input->token); // Get Snipcart order token
        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        } elseif ($action == 'download_invoice') {
            $out .= $this->_downloadInvoice($token);
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

        $response = $sniprest->getOrders(
            '',
            $selector,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );

        $orders = isset($response[SnipRest::resourcePathOrders][WireHttpExtended::resultKeyContent])
            ? $response[SnipRest::resourcePathOrders][WireHttpExtended::resultKeyContent]
            : array();

        $total = isset($orders['totalItems']) ? $orders['totalItems'] : 0;
        $items = isset($orders['items']) ? $orders['items'] : array();
        $count = count($items);

        // Pagination out of bound
        if (!$count && $input->pageNum > 1) {
            $session->redirect($this->processUrl);
            return '';
        }

        $out .= $this->_buildOrdersFilter($filter);

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
        $session = $this->wire('session');
        $sniprest = $this->wire('sniprest');
        
        $this->browserTitle($this->_('Snipcart Order'));
        $this->headline($this->_('Snipcart Order'));

        $this->breadcrumb($this->snipWireRootUrl, $this->_('SnipWire Dashboard'));
        $this->breadcrumb($this->snipWireRootUrl . 'orders/', $this->_('Snipcart Orders'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

        $this->_setJSConfigValues();

        $token = $input->urlSegment(2); // Get Snipcart order token
        $forceRefresh = false;

        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        } elseif ($action == 'resend_invoice') {
            $this->_resendInvoice($token);
        }

        $response = $sniprest->getOrder(
            $token,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );
        $order = isset($response[SnipRest::resourcePathOrders . '/' . $token][WireHttpExtended::resultKeyContent])
            ? $response[SnipRest::resourcePathOrders . '/' . $token][WireHttpExtended::resultKeyContent]
            : array();

        $out = '';

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Order');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconOrder;
        $f->value = $this->_renderDetailOrder($order);
        $f->collapsed = Inputfield::collapsedNever;

        $out .= $f->render();

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
                $f->addOptions($this->orderStatuses);

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
            $table->setID('OrdersTable');
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
                $this->_('Payment status'),
                $this->_('Payment method'),
                $this->_('Total'),
                $this->_('Refunded'),
                '&nbsp;',
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
                $total =
                '<strong>' .
                    CurrencyFormat::format($item['total'], $item['currency']) .
                '</strong>';
                $refunded = $item['refundsAmount']
                    ? '<span class="warning-color-dark">' .
                          CurrencyFormat::format($item['refundsAmount'], $item['currency']) .
                      '</span>'
                    : '-';
                $downloadUrl = wirePopulateStringTags(
                    SnipREST::snipcartInvoiceUrl,
                    array('token' => $item['token'])
                );
                $downloadLink =
                '<a href="' . $downloadUrl . '"
                    target="' . $item['token'] . '"
                    class="DownloadInvoiceButton pw-tooltip"
                    title="' . $this->_('Download invoice') .'">' .
                        wireIconMarkup('download') .
                '</a>';

                $table->row(array(
                    $panelLink,
                    wireDate('relative', $item['creationDate']),
                    $panelLink2,
                    $item['billingAddressCountry'],
                    $this->orderStatuses[$item['status']],
                    $this->paymentStatuses[$item['paymentStatus']],
                    $this->paymentMethods[$item['paymentMethod']],
                    $total,
                    $refunded,
                    $downloadLink,
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

        if (empty($item)) {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No order selected') .
            '</div>';
            return $out;
        }

        $out =
        '<div class="ItemDetailHeader">' .
            '<h2 class="ItemDetailTitle">' .
                $this->_('Order') . ': ' .
                $item['invoiceNumber'] .
            '</h2>' .
            '<div class="ItemDetailActionButtons">' .
                $this->_getOrderDetailActionButtons($item['token']) .
            '</div>' .
        '</div>';

        $out .= $this->_processRefundForm($item);

        /** @var InputfieldForm $wrapper */
        $wrapper = $modules->get('InputfieldForm');

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Order Info');
            $f->icon = self::iconInfo;
            $f->value = $this->_renderOrderInfo($item);
            $f->columnWidth = 50;
            
        $wrapper->add($f);

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Payment Info');
            $f->icon = self::iconPayment;
            $f->value = $this->_renderPaymentInfo($item);
            $f->columnWidth = 50;
            
        $wrapper->add($f);

        $out .= $wrapper->render();

        /** @var InputfieldForm $wrapper */
        $wrapper = $modules->get('InputfieldForm');

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Order Details');
            $f->icon = self::iconOrder;
            // We don't use 'taxes' key from 'summary' as it has wrong values for 'includedInPrice'
            // (instead we use the global 'taxes' key)
            $f->value = $this->_renderTableOrderSummary($item);
            
        $wrapper->add($f);

        $out .= $wrapper->render();

        /** @var InputfieldForm $wrapper */
        $wrapper = $modules->get('InputfieldForm');

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Billing Address');
            $f->icon = self::iconAddress;
            $f->value = $this->_renderCustomerAddress($item['user']['billingAddress']);
            $f->columnWidth = 50;

        $wrapper->add($f);

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Shipping Address');
            $f->entityEncodeLabel = false;
            if ($item['user']['shippingAddressSameAsBilling']) {
                $f->label .= ' <span class="snipwire-badge snipwire-badge-info">' . $this->_('same as billing') . '</span>';
            }
            $f->icon = self::iconAddress;
            $f->value = $this->_renderCustomerAddress($item['user']['shippingAddress']);
            $f->columnWidth = 50;

        $wrapper->add($f);

        $out .= $wrapper->render();

        if ($this->snipwireConfig->snipwire_debug) {

            /** @var InputfieldForm $wrapper */
            $wrapper = $modules->get('InputfieldForm');

                /** @var InputfieldMarkup $f */
                $f = $modules->get('InputfieldMarkup');
                $f->label = $this->_('Debug infos');
                $f->collapsed = Inputfield::collapsedYes;
                $f->icon = self::iconDebug;
                $f->value = '<pre>' . print_r($item, true) . '</pre>';
                
            $wrapper->add($f);

            $out .= $wrapper->render();
        }

        return $out;
    }

    /**
     * Render and process the refund form.
     *
     * @param array $item
     * @return markup 
     *
     */
    private function _processRefundForm($item) {
        $modules = $this->wire('modules');
        $sanitizer = $this->wire('sanitizer');
        $input = $this->wire('input');
        $session = $this->wire('session');

        $token = $item['token'];
        $currency = $item['currency'];
        $maxAmount = $item['adjustedAmount'];
        $maxAmountFormatted = CurrencyFormat::format($maxAmount, $currency);
        $refundsAmount = $item['refundsAmount'];
        $refundsAmountFormatted = CurrencyFormat::format($refundsAmount, $currency);

        $supportedCurrencies = CurrencyFormat::getSupportedCurrencies();
        $currencyLabel = isset($supportedCurrencies[$currency])
            ? $supportedCurrencies[$currency]
            : $currency;

		/** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm');
        $form->id = 'RefundForm';
        $form->action = $this->currentUrl;
            $refundBadges = 
            ' <span class="snipwire-badge snipwire-badge-info">' .
                $this->_('max.') .
                ' ' . $maxAmountFormatted .
            '</span>' .
            ' <span class="snipwire-badge snipwire-badge-warning">' .
                $this->_('already refunded') .
                ' ' . $refundsAmountFormatted .
            '</span>';
            $fieldset = $modules->get('InputfieldFieldset');
            $fieldset->entityEncodeLabel = false;
            $fieldset->label = $this->_('Refund an amount');
            $fieldset->label .= $refundBadges;
            $fieldset->icon = 'thumbs-o-up';
            $fieldset->collapsed = ($input->send_refund)
                ? Inputfield::collapsedNo
                : Inputfield::collapsedYes;

        $form->add($fieldset);
 
            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->name = 'amount';
            $f->label = $this->_('Amount');
            $f->label .= ' (' . $currencyLabel . ')';
            $f->description = $this->_('Enter the amount to be refunded.');
            $f->detail = $this->_('Decimal with a dot (.) as separator e.g. 19.99');
            $f->required = true;
            $f->pattern = '[-+]?[0-9]*[.]?[0-9]+';
            $f->columnWidth = 40;
        
        $fieldset->add($f);

            /** @var InputfieldTextarea $f */
            $f = $modules->get('InputfieldTextarea');
            $f->name = 'comment';
            $f->label = $this->_('Internal Note');
            $f->description = $this->_('This note is for your eyes only and won\'t be shown to your customer.');
            $f->rows = 3;
            $f->columnWidth = 60;
        
        $fieldset->add($f);

            /** @var InputfieldButton $btn */
            $btn = $modules->get('InputfieldButton');
            $btn->id = 'SendRefundButton';
            $btn->name = 'send_refund';
            $btn->value = $this->_('Send refund');
            $btn->type = 'submit';
            $btn->small = true;

        $fieldset->add($btn);

            /** @var InputfieldButton $btn */
            $btn = $modules->get('InputfieldButton');
            $btn->name = 'cancel_refund';
            $btn->value = $this->_('Cancel');
            $btn->type = 'button';
            $btn->href = $this->currentUrl . '?modal=1';
            $btn->small = true;
            $btn->secondary = true;

        $fieldset->add($btn);

        // Render form without processing if not submitted
        if (!$input->post->send_refund) return $form->render();

        $form->processInput($input->post);

        // Validate input
        $amount = $form->get('amount');
        $amountValue = $amount->value;
        if (!$amountValue) {
            $amount->error($this->_('Please enter an amount'));
        } elseif ($amountValue && !checkPattern($amountValue, $amount->pattern)) {
            $amount->error($this->_('Please enter a valid number'));
        } elseif ($amountValue > $maxAmount) {
            $amount->error($this->_('Maximum amount is') . ' ' . $maxAmountFormatted);
        }

        $comment = $form->get('comment');
        $commentValue = $comment->value;

        if ($form->getErrors()) {
            // The form is processed and populated but contains errors
            return $form->render();
        }

        // Sanitize input
        $amountValue = $sanitizer->float($amountValue);
        $amountValueFormatted = CurrencyFormat::format($amountValue, $currency);
        $commentValue = $sanitizer->textarea($commentValue);

        $success = $this->_refund($token, $amountValue, $amountValueFormatted, $commentValue);
        if ($success) {
            // Reset cache for this order and redirect to myself to display updated values
            $this->wire('sniprest')->deleteOrderCache($token);
            $session->redirect($this->currentUrl . '?modal=1');
        }

        return $form->render();
    }

    /**
     * Render the action buttons.
     *
     * (Currently uses custom button markup as there is a PW bug which 
     * triggers href targets twice + we need to attach JavaScript events on button click)
     *
     * @param $token The order token
     * @return buttons markup 
     *
     */
    private function _getOrderDetailActionButtons($token) {
        $downloadUrl = wirePopulateStringTags(
            SnipREST::snipcartInvoiceUrl,
            array('token' => $token)
        );

        $out =
        '<a href="' . $downloadUrl . '"
            target="' . $token . '"
            class="DownloadInvoiceButton ui-button ui-widget ui-corner-all ui-state-default ui-priority-secondary"
            role="button">' .
                '<span class="ui-button-text">' .
                    wireIconMarkup('download') . ' ' . $this->_('Download Invoice') .
                '</span>' .
        '</a>';

        $out .=
        '<a href="' . $this->currentUrl . '?action=resend_invoice&modal=1"
            class="ResendInvoiceButton ui-button ui-widget ui-corner-all ui-state-default"
            role="button">' .
                '<span class="ui-button-text">' .
                    wireIconMarkup('share') . ' ' . $this->_('Resend Invoice') .
                '</span>' .
        '</a>';

        return $out;
    }

    /**
     * Render the order info block.
     *
     * @param array $item
     * @return markup 
     *
     */
    private function _renderOrderInfo($item) {
        $infoCaptions = array(
            'customer' => $this->_('Customer'),
            'creationDate' => $this->_('Order date'),
            'status' => $this->_('Order status'),
            'shippingMethod' => $this->_('Shipping method'),
            'shippingProvider' => $this->_('Shipping provider'),
            'trackingNumber' => $this->_('Tracking number'),
        );

        $trackingNumber = $item['trackingNumber'] ? $item['trackingNumber'] : '-';
        if ($item['trackingUrl'] && $trackingNumber != '-') {
            $trackingNumber = '<a href="' . $item['trackingUrl'] . '" target="_blank">' . $trackingNumber . '</a>';
        }

        $out =
        '<table class="ItemDetailTable">' .
            '<tr>' .
                '<th>' . $infoCaptions['customer'] . '</th> ' .
                '<td>' . 
                    $item['billingAddressFirstName'] . ' ' . $item['billingAddressName'] .
                    ' (' . $item['email'] .')' .
                '</td>' .
            '</tr>' .
            '<tr>' .
                '<th>' . $infoCaptions['creationDate'] . '</th> ' .
                '<td>' . wireDate('Y-m-d H:i:s', $item['creationDate']) . '</td>' .
            '</tr>' .
            '<tr>' .
                '<th>' . $infoCaptions['status'] . '</th> ' .
                '<td>' . $this->orderStatuses[$item['status']] . '</td>' .
            '</tr>' .
            '<tr>' .
                '<th>' . $infoCaptions['shippingMethod'] . '</th> ' .
                '<td>' . ($item['shippingMethod'] ? $item['shippingMethod'] : '-') . '</td>' .
            '</tr>' .
            '<tr>' .
                '<th>' . $infoCaptions['shippingProvider'] . '</th> ' .
                '<td>' . ($item['shippingProvider'] ? $item['shippingProvider'] : '-') . '</td>' .
            '</tr>' .
            '<tr>' .
                '<th>' . $infoCaptions['trackingNumber'] . '</th> ' .
                '<td>' . $trackingNumber . '</td>' .
            '</tr>' .
        '</table>';

        return $out;
    }

    /**
     * Render the payment info block.
     *
     * @param array $item
     * @return markup 
     *
     */
    private function _renderPaymentInfo($item) {
        $infoCaptions = array(
            'paymentMethod' => $this->_('Payment method'),
            'cardType' => $this->_('Card type'),
            'cardHolderName' => $this->_('Card holder'),
            'creditCardLast4Digits' => $this->_('Card number'),
            'currency' => $this->_('Currency'),
            'paymentStatus' => $this->_('Payment Status'),
        );

        $paymentMethodLabel = isset($this->paymentMethods[$item['paymentMethod']])
            ? $this->paymentMethods[$item['paymentMethod']]
            : $item['paymentMethod'];

        $creditCardLabel = !empty($item['creditCardLast4Digits'])
            ? '****&nbsp;****&nbsp;****&nbsp;' . $item['creditCardLast4Digits']
            : '-';

        $supportedCurrencies = CurrencyFormat::getSupportedCurrencies();
        $currencyLabel = isset($supportedCurrencies[$item['currency']])
            ? $supportedCurrencies[$item['currency']]
            : $item['currency'];

        $paymentStatusLabel = isset($this->paymentStatuses[$item['paymentStatus']])
            ? $this->paymentStatuses[$item['paymentStatus']]
            : $item['paymentStatus'];

        $out =
        '<table class="ItemDetailTable">' .
            '<tr>' .
                '<th>' . $infoCaptions['paymentMethod'] . '</th> ' .
                '<td>' . $paymentMethodLabel . '</td>' .
            '</tr>' .
            '<tr>' .
                '<th>' . $infoCaptions['cardType'] . '</th> ' .
                '<td>' . ($item['cardType'] ? $item['cardType'] : '-') . '</td>' .
            '</tr>' .
            '<tr>' .
                '<th>' . $infoCaptions['cardHolderName'] . '</th> ' .
                '<td>' . ($item['cardHolderName'] ? $item['cardHolderName'] : '-') . '</td>' .
            '</tr>' .
            '<tr>' .
                '<th>' . $infoCaptions['creditCardLast4Digits'] . '</th> ' .
                '<td>' . $creditCardLabel . '</td>' .
            '</tr>' .
            '<tr>' .
                '<th>' . $infoCaptions['currency'] . '</th> ' .
                '<td>' . $currencyLabel . '</td>' .
            '</tr>' .
            '<tr>' .
                '<th>' . $infoCaptions['paymentStatus'] . '</th> ' .
                '<td>' . $paymentStatusLabel . '</td>' .
            '</tr>' .
        '</table>';

        return $out;
    }

    /**
     * Render the order summmary table.
     *
     * @param array $item
     * @return markup MarkupAdminDataTable 
     *
     */
    private function _renderTableOrderSummary($item) {
        $modules = $this->wire('modules');

        /*
        ...
        "refundsAmount": <-- is the sum of all refunds
        "adjustedAmount": <-- is the amont after refunds, when there aren't any refunds, it's the same as 'grandTotal'.
        "finalGrandTotal": <-- is the total saved once the order is completed. Will be the same as 'grandTotal' which is a "computed" property.
        "subtotal": 
        "baseTotal": <-- should be ignored!
        "totalPriceWithoutDiscountsAndTaxes": 
        "grandTotal": <-- is the "computed" final total
        "total": <-- should be the same as 'grandTotal'
        ...
        "summary": {
            "subtotal": 
            "total": <-- should be the same as 'grandTotal'
            "payableNow": <-- mostly for people purchasing subscriptions with trial periods
            "discountInducedTaxesVariation": <-- is related to VAT taxes for european merchants when discounts affect the taxes
            "adjustedTotal": 
        },
        ...
        */

        $products = $item['items'];
        $taxes = $item['taxes'];
        $refunds = $item['refunds'];
        $summary = $item['summary'];
        $currency = $item['currency'];

        /** @var MarkupAdminDataTable $table */
        $table = $modules->get('MarkupAdminDataTable');
        $table->setEncodeEntities(false);
        $table->id = 'OrderSummaryTable';
        $table->setSortable(false);
        $table->setResizable(false);
        $table->headerRow(array(
            $this->_('SKU'),
            $this->_('Name'),
            $this->_('Quantity'),
            $this->_('Price'),
            $this->_('Total'),
        ));
        foreach ($products as $product) {
            $table->row(array(
                $product['id'],
                $product['name'],
                $product['quantity'],
                CurrencyFormat::format($product['price'], $currency),
                CurrencyFormat::format($product['totalPrice'], $currency),
            ));
        }

        // Subtotal row
        $table->row(array(
            '',
            '<span class="subtotal-label">' . $this->_('Subtotal') . '</span>',
            '',
            '',
            '<span class="subtotal-value">' . CurrencyFormat::format($summary['subtotal'], $currency) . '</span>',
        ), array(
            'class' => 'row-summary-subtotal',
        ));
        
        // Shipping row
        $shippingMethod = $item['shippingMethod'] ? ' (' . $item['shippingMethod'] . ')' : '';
        $table->row(array(
            '',
            $this->_('Shipping') . $shippingMethod,
            '',
            '',
            CurrencyFormat::format($item['shippingFees'], $currency),
        ), array(
            'class' => 'row-summary-shipping',
        ));

        // Taxes rows
        foreach ($taxes as $tax) {
            $included = $tax['includedInPrice'] ? ' (' . $this->_('included') . ')' : '';
            $table->row(array(
                '',
                $tax['taxName'] . $included,
                '',
                '',
                CurrencyFormat::format($tax['amount'], $currency),
            ), array(
                'class' => 'row-summary-tax',
            ));
        }

        // Total row
        $table->row(array(
            '',
            '<span class="total-label">' . $this->_('Total') . '</span>',
            '',
            '',
            '<span class="total-value">' . CurrencyFormat::format($summary['total'], $currency) . '</span>',
        ), array(
            'class' => 'row-summary-total',
        ));

        if ($item['refundsAmount']) {
            // Refunds row
            $table->row(array(
                '',
                '<span class="refunds-label">' . $this->_('Refunded') . '</span>',
                '',
                '',
                '<span class="refunds-value">' . CurrencyFormat::format($item['refundsAmount'], $currency) . '</span>',
            ), array(
                'class' => 'row-summary-refunds',
            ));
    
            // Total after refunds row
            $table->row(array(
                '',
                '<span class="total-adjusted-label">' . $this->_('Total after refunds') . '</span>',
                '',
                '',
                '<span class="total-adjusted-value">' . CurrencyFormat::format($item['adjustedAmount'], $currency) . '</span>',
            ), array(
                'class' => 'row-summary-total-adjusted',
            ));
        }

        $out = $table->render();            

        return $out;
    }

    /**
     * Render a customer address block.
     *
     * @param array $address
     * @return markup 
     *
     */
    private function _renderCustomerAddress($address) {
        /*
            "fullName": "John Doe",
            "firstName": "John",
            "name": "Doe",
            "company": "",
            "address1": "Noname Street 7",
            "address2": "",
            "fullAddress": "Noname Street 7",
            "city": "Nowhere",
            "country": "AT",
            "postalCode": "1000",
            "province": "",
            "phone": "1234567890",
            "vatNumber": null
        */

        $addressCaptions = array(
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

        $out = '<table class="ItemDetailTable">';
        foreach ($addressCaptions as $key => $caption) {
            $out .=
            '<tr>' .
                '<th>' . $caption . '</th> ' .
                '<td>' . (!empty($address[$key]) ? $address[$key] : '-') . '</td>';
            '</tr>';
        }
        $out .= '</table>';

        return $out;
    }

    /**
     * Resend a Snipcart invoice to customer.
     *
     * @param string $token The order token
     * @return void 
     *
     */
    private function _resendInvoice($token) {
        $sniprest = $this->wire('sniprest');

        if (empty($token)) return;

        $options = array(
            'type' => 'Invoice',
            'deliveryMethod' => 'Email',
        );

        $response = $sniprest->postOrderNotification($token, $options);
        if (
            $response[$token][WireHttpExtended::resultKeyHttpCode] != 200 &&
            $response[$token][WireHttpExtended::resultKeyHttpCode] != 201
        ) {
            $this->error(
                $this->_('The invoice could not be sent to customer! The following error occurred: ') .
                $response[$token][WireHttpExtended::resultKeyError]);
        } else {
            $this->message($this->_('The invoice has been sent to customer.'));
        }
    }

    /**
     * Refund an amount.
     *
     * @param string $token The order token
     * @param float $amount The amount to be refunded #required
     * @param string $amountFormatted The formatted amount #required
     * @param string $comment The reason for the refund (internal note - not for customer)
     * @return boolean
     *
     */
    private function _refund($token, $amount, $amountFormatted, $comment = '') {
        $sniprest = $this->wire('sniprest');

        if (empty($token) || empty($amount)) return;

        $options = array(
            'amount' => $amount,
            'comment' => $comment,
        );

        $refunded = false;
        $response = $sniprest->postOrderRefund($token, $options);
        if (
            $response[$token][WireHttpExtended::resultKeyHttpCode] != 200 &&
            $response[$token][WireHttpExtended::resultKeyHttpCode] != 201
        ) {
            $this->error(
                $this->_('The amount could not be refunded! The following error occurred: ') .
                $response[$token][WireHttpExtended::resultKeyError]);
        } else {
            $this->message(sprintf($this->_("An amount of %s has been refunded."), $amountFormatted));
            $refunded = true;
        }
        return $refunded;
    }
    
    /**
     * Set JS config values for order pages.
     *
     * @return void 
     *
     */
    private function _setJSConfigValues() {
        $this->wire('config')->js('orderActionStrings', array(
            'info_download_invoice' => $this->_("To download an invoice, you first need to login to your Snipcart dashboard.\nAlso please be sure your browser allows popups from this site.\n\nStart download now?"),
            'confirm_resend_invoice' => $this->_('Do you want to resend this invoice?'),
            'confirm_send_refund' => $this->_('Do you want to send this refund?'),
        ));
    }
}
