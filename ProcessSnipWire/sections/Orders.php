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
        } elseif ($action == 'refresh_all') {
            $sniprest->resetFullCache();
            $this->message(SnipREST::getMessagesText('full_cache_refreshed'));
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
            'format' => 'Excerpt',
        );
 
        $selector = array_merge($defaultSelector, $filter);

        $response = $sniprest->getOrders(
            '',
            $selector,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );

        $orders = isset($response[SnipRest::resPathOrders][WireHttpExtended::resultKeyContent])
            ? $response[SnipRest::resPathOrders][WireHttpExtended::resultKeyContent]
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

        $out .= $this->_renderActionButtons();

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

        // Determine if request comes from within another page in a modal panel.
        // In this case there will be an input param "ret" (can be GET or POST) which holds the return URL.
        $ret = urldecode($input->ret);

        $token = $input->urlSegment(2); // Get Snipcart order token
        $forceRefresh = false;

        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        } elseif ($action == 'refresh_all') {
            $sniprest->resetFullCache();
            $this->message(SnipREST::getMessagesText('full_cache_refreshed'));
        } elseif ($action == 'resend_invoice') {
            $this->_resendInvoice($token);
        }

        $response = $sniprest->getOrder(
            $token,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );
        $dataKey = SnipRest::resPathOrders . '/' . $token;
        $order = isset($response[$dataKey][WireHttpExtended::resultKeyContent])
            ? $response[$dataKey][WireHttpExtended::resultKeyContent]
            : array();
        unset($response, $dataKey);

        $defaultSelector = array(
            'offset' => 0,
            'limit' => 100, // should be enough (@todo: currently no pagination)
        );
        $response = $sniprest->getOrderNotifications(
            $token,
            $defaultSelector,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );
        $dataKey = SnipREST::cacheNamePrefixOrdersNotifications . '.' . $token;
        $notifications = isset($response[$dataKey][WireHttpExtended::resultKeyContent])
            ? $response[$dataKey][WireHttpExtended::resultKeyContent]
            : array();
        unset($response, $dataKey);

        $out = '';

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Order');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconOrder;
        $f->value = $this->_renderDetailOrder($order, $notifications, $ret);
        $f->collapsed = Inputfield::collapsedNever;

        $out .= $f->render();

        $out .= $this->_renderActionButtons();

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
                $f->addClass('filter-form-select');
                $f->attr('name', 'status'); 
                $f->label = $this->_('Status'); 
                $f->value = $filter['status'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 50;
                $f->required = true;
                $f->addOptions($this->getOrderStatuses());

            $fieldset->add($f);

                /** @var InputfieldSelect $f */
                $f = $modules->get('InputfieldSelect'); 
                $f->addClass('filter-form-select');
                $f->attr('name', 'paymentStatus'); 
                $f->label = $this->_('Payment Status'); 
                $f->value = $filter['paymentStatus'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 50;
                $f->required = true;
                $f->addOptions($this->getPaymentStatuses());

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

        /*
        This is a sample order item when using format="Excerpt":
        (hint from Snipcart/@charls on Slack on 20.11.2019 #undocumented)
        {
            "token": "7043e043-bb47-42c4-b7a5-646944c36e10",
            "invoiceNumber": "SNIP-1062",
            "recoveredFromCampaignId": null,
            "isRecurringOrder": false,
            "completionDate": "2019-11-09T06:41:12Z",
            "placedBy": "John Doe",
            "status": "Processed",
            "paymentStatus": "Paid",
            "paymentMethod": "CreditCard",
            "shippingMethod": "Express Delivery",
            "finalGrandTotal": 219.66,
            "adjustedAmount": 76.66,
            "currency": "eur"
        }
        */
        
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
                $total =
                '<strong class="price-field">' .
                    CurrencyFormat::format($item['finalGrandTotal'], $item['currency']) .
                '</strong>';
                $refundsAmount = $item['finalGrandTotal'] - $item['adjustedAmount'];
                $refunded = $refundsAmount
                    ? '<span class="warning-color-dark">' .
                          CurrencyFormat::format($refundsAmount, $item['currency']) .
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
                    wireDate('relative', $item['completionDate']),
                    $item['placedBy'],
                    $this->getOrderStatus($item['status']),
                    $this->getPaymentStatus($item['paymentStatus']),
                    $this->getPaymentMethod($item['paymentMethod']),
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
     * @param array $item The order item
     * @param array $notifications The order comments and notifications
     * @param string $ret A return URL (optional)
     * @return markup 
     *
     */
    private function _renderDetailOrder($item, $notifications, $ret = '') {
        $modules = $this->wire('modules');
        $sniprest = $this->wire('sniprest');

        if (empty($item)) {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No order selected') .
            '</div>';
            return $out;
        }

        $token = $item['token'];
        $refundsCount = count($item['refunds']);
        $notificationsCount = $notifications['totalItems'];

        $out = '';

        if ($ret) {
            $out .=
            '<div class="ItemDetailBackLink">' . 
                '<a href="' .$ret . '?modal=1"
                    class="pw-panel-links">' .
                        wireIconMarkup('times-circle', 'fa-right-margin') .
                        $this->_('Close order details') .
                '</a>' .
            '</div>';
        }

        $out .=
        '<div class="ItemDetailHeader">' .
            '<h2 class="ItemDetailTitle">' .
                wireIconMarkup(self::iconOrder, 'fa-right-margin') .
                $this->_('Order') . ': ' .
                $item['invoiceNumber'] .
            '</h2>' .
            '<div class="ItemDetailActionButtons">' .
                $this->_getOrderDetailActionButtons($token, $ret) .
            '</div>' .
        '</div>';

        $out .= $this->_processRefundForm($item, $ret);
        $out .= $this->_processOrderStatusForm($item, $ret);
        $out .= $this->_processOrderCommentForm($item, $ret);

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

            $address = $item['user']['billingAddress'];
            $data = array();
            foreach ($this->getCustomerAddressLabels() as $key => $caption) {
                $data[$caption] = !empty($address[$key]) ? $address[$key] : '-';
            }

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Billing Address');
            $f->icon = self::iconAddress;
            $f->value = $this->renderDataSheet($data);
            $f->columnWidth = 50;

        $wrapper->add($f);

            $address = $item['user']['shippingAddress'];
            $data = array();
            foreach ($this->getCustomerAddressLabels() as $key => $caption) {
                $data[$caption] = !empty($address[$key]) ? $address[$key] : '-';
            }

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Shipping Address');
            $f->entityEncodeLabel = false;
            if ($item['user']['shippingAddressSameAsBilling']) {
                $f->label .= ' <span class="snipwire-badge snipwire-badge-info">' . $this->_('same as billing') . '</span>';
            }
            $f->icon = self::iconAddress;
            $f->value = $this->renderDataSheet($data);
            $f->columnWidth = 50;

        $wrapper->add($f);

        $out .= $wrapper->render();

        if (!empty($item['refunds'])) {
            /** @var InputfieldForm $wrapper */
            $wrapper = $modules->get('InputfieldForm');

                $refundsBadge = 
                ' <span class="snipwire-badge snipwire-badge-info">' .
                    sprintf(_n("%d refund", "%d refunds", $refundsCount), $refundsCount) .
                '</span>';

                /** @var InputfieldMarkup $f */
                $f = $modules->get('InputfieldMarkup');
                $f->entityEncodeLabel = false;
                $f->label = $this->_('Refunds');
                $f->label .= $refundsBadge;
                $f->icon = self::iconRefund;
                $f->value = $this->_renderTableRefunds($item['refunds'], $item['currency']);
                $f->collapsed = Inputfield::collapsedYes;
                
            $wrapper->add($f);
    
            $out .= $wrapper->render();
        }

        /** @var InputfieldForm $wrapper */
        $wrapper = $modules->get('InputfieldForm');

            $notificationsBadge = 
            ' <span class="snipwire-badge snipwire-badge-info">' .
                sprintf(_n("%d notification", "%d notifications", $notificationsCount), $notificationsCount) .
            '</span>';

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->entityEncodeLabel = false;
            $f->label = $this->_('Notifications');
            $f->label .= $notificationsBadge;
            $f->icon = self::iconComment;
            $f->value = $this->_renderTableNotifications($notifications);
            $f->collapsed = Inputfield::collapsedYes;
            
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
     * @param string $ret A return URL (optional)
     * @return markup 
     *
     */
    private function _processRefundForm($item, $ret = '') {
        $modules = $this->wire('modules');
        $sanitizer = $this->wire('sanitizer');
        $input = $this->wire('input');
        $session = $this->wire('session');

        $token = $item['token'];
        $currency = $item['currency'];
        $maxAmount = $item['adjustedAmount'];
        $refundingEnabled = ($maxAmount > 0) ? true : false;
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

            if ($ret) {
                /** @var InputfieldHidden $f */
                $f = $modules->get('InputfieldHidden');
                $f->name = 'ret';
                $f->value = urlencode($ret);
    
                $form->add($f);
            }

            $refundBadges = 
            ' <span class="snipwire-badge snipwire-badge-info">';
            if ($refundingEnabled) {
                $refundBadges .=
                $this->_('max.') .
                ' ' . $maxAmountFormatted;
            } else {
                $refundBadges .=
                $this->_('total order refunded');
            }
            $refundBadges .= 
            '</span>';
            if ($item['refundsAmount']) {
                $refundBadges .=
                ' <span class="snipwire-badge snipwire-badge-warning">' .
                    $this->_('already refunded') .
                    ' ' . $refundsAmountFormatted .
                '</span>';
            }
            $fieldset = $modules->get('InputfieldFieldset');
            $fieldset->entityEncodeLabel = false;
            $fieldset->label = $this->_('Refund an amount');
            $fieldset->label .= $refundBadges;
            $fieldset->icon = self::iconRefund;
            $fieldset->collapsed = ($input->refunding_active)
                ? Inputfield::collapsedNo
                : Inputfield::collapsedYes;

        $form->add($fieldset);

        if ($refundingEnabled) {
     
                /** @var InputfieldText $f */
                $f = $modules->get('InputfieldText');
                $f->name = 'amount';
                $f->label = $this->_('Amount');
                $f->label .= ' (' . $currencyLabel . ')';
                $f->detail = $this->_('Decimal with a dot (.) as separator e.g. 19.99');
                $f->required = true;
                $f->pattern = '[-+]?[0-9]*[.]?[0-9]+';
            
            $fieldset->add($f);
    
                /** @var InputfieldTextarea $f */
                $f = $modules->get('InputfieldTextarea');
                $f->name = 'comment';
                $f->label = $this->_('Reason for refund');
                $f->rows = 3;
            
            $fieldset->add($f);

                /** @var InputfieldCheckbox $f */
                $f = $modules->get('InputfieldCheckbox');
                $f->name = 'notifyCustomer'; 
                $f->label = $this->_('Send reason for refund with customer notification');
    
            $fieldset->add($f);

                /** @var InputfieldHidden $f */
        		$f = $modules->get('InputfieldHidden');
        		$f->name = 'refunding_active';
        		$f->value = true;

            $fieldset->add($f);

                /** @var InputfieldSubmit $btn */
                $btn = $modules->get('InputfieldSubmit');
                $btn->id = 'SendRefundButton';
                $btn->name = 'send_refund';
                $btn->value = $this->_('Send refund');
                $btn->small = true;
    
            $fieldset->add($btn);

        } else {

                $disabledMarkup =
                '<div class="RefundFormDisabled">' .
                    $this->_('Maximum refund amount reached') .
                '</div>';
                
                /** @var InputfieldMarkup $f */
                $f = $modules->get('InputfieldMarkup');
                $f->label = $this->_('Refunding disabled');
                $f->value = $disabledMarkup;
                $f->collapsed = Inputfield::collapsedNever;
            
            $fieldset->add($f);
        }

        // Render form without processing if not submitted
        if (!$input->post->refunding_active) return $form->render();

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

        $notifyCustomer = $form->get('notifyCustomer');
        $notifyCustomerValue = !empty($notifyCustomer) ? 1 : 0;
        
        $comment = $form->get('comment');
        $commentValue = $comment->value;

        if ($form->getErrors()) {
            // The form is processed and populated but contains errors
            return $form->render();
        }

        // Sanitize input
        $amountValue = $sanitizer->float($amountValue);
        $amountValueFormatted = CurrencyFormat::format($amountValue, $currency);
        $notifyCustomerValue = $sanitizer->bool($notifyCustomerValue);
        $commentValue = $sanitizer->textarea($commentValue);

        $success = $this->_refund($token, $amountValue, $amountValueFormatted, $notifyCustomerValue, $commentValue);
        if ($success) {
            // Reset cache for this order and redirect to itself to display updated values
            $this->wire('sniprest')->deleteOrderCache($token);
            $redirectUrl = $this->currentUrl . '?modal=1';
            if ($ret) $redirectUrl .= '&ret=' . urlencode($ret);
            $session->redirect($redirectUrl);
        }

        return $form->render();
    }

    /**
     * Render and process the order status form.
     *
     * @param array $item
     * @param string $ret A return URL (optional)
     * @return markup 
     *
     */
    private function _processOrderStatusForm($item, $ret = '') {
        $modules = $this->wire('modules');
        $sanitizer = $this->wire('sanitizer');
        $input = $this->wire('input');
        $session = $this->wire('session');

        $token = $item['token'];
        
        if ($input->post->updating_orderstatus_active) {
            $status = $input->post->status;
            $trackingNumber = $input->post->trackingNumber;
            $trackingUrl = $input->post->trackingUrl;
        } else {
            $status = $item['status'];
            $trackingNumber = $item['trackingNumber'];
            $trackingUrl = $item['trackingUrl'];
        }

		/** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm');
        $form->id = 'OrderStatusForm';
        $form->action = $this->currentUrl;

            if ($ret) {
                /** @var InputfieldHidden $f */
                $f = $modules->get('InputfieldHidden');
                $f->name = 'ret';
                $f->value = urlencode($ret);
    
                $form->add($f);
            }

            $statusBadges = 
            ' <span class="snipwire-badge snipwire-badge-info">' .
                $this->getOrderStatus($item['status']) .
            '</span>';

            $fieldset = $modules->get('InputfieldFieldset');
            $fieldset->entityEncodeLabel = false;
            $fieldset->label = $this->_('Update order status');
            $fieldset->label .= $statusBadges;
            $fieldset->icon = self::iconOrderStatus;
            $fieldset->collapsed = ($input->updating_orderstatus_active)
                ? Inputfield::collapsedNo
                : Inputfield::collapsedYes;

        $form->add($fieldset);

            /** @var InputfieldSelect $f */
            $f = $modules->get('InputfieldSelect');
            $f->name = 'status';
            $f->label = $this->_('Order status');
            $f->value = $status;
            $f->required = true;
            $f->addOptions($this->getOrderStatusesSelectable());

        $fieldset->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->name = 'trackingNumber';
            $f->label = $this->_('Tracking number');
            $f->value = $trackingNumber;
            $f->detail = $this->_('Enter the tracking number associated to the order');

        $fieldset->add($f);

            /** @var InputfieldURL $f */
            $f = $modules->get('InputfieldURL');
            $f->name = 'trackingUrl';
            $f->label = $this->_('Tracking URL');
            $f->value = $trackingUrl;
            $f->detail = $this->_('Enter the URL where the customer will be able to track its order');
            $f->noRelative = true;

        $fieldset->add($f);

            /*
            metadata ...
            */

            /** @var InputfieldHidden $f */
    		$f = $modules->get('InputfieldHidden');
    		$f->name = 'updating_orderstatus_active';
    		$f->value = true;

        $fieldset->add($f);

            /** @var InputfieldSubmit $btn */
            $btn = $modules->get('InputfieldSubmit');
            $btn->id = 'UpdateOrderButton';
            $btn->name = 'send_orderstatus';
            $btn->value = $this->_('Update order');
            $btn->small = true;

        $fieldset->add($btn);

        // Render form without processing if not submitted
        if (!$input->post->updating_orderstatus_active) return $form->render();

        $form->processInput($input->post);

        // Validate input
        $status = $form->get('status');
        $statusValue = $status->value;
        if (!$statusValue) {
            $status->error($this->_('Please choose an order status'));
        }

        $trackingNumber = $form->get('trackingNumber');
        $trackingNumberValue = $trackingNumber->value;

        $trackingUrl = $form->get('trackingUrl');
        $trackingUrlValue = $trackingUrl->value;

        if (empty($trackingNumberValue) && !empty($trackingUrlValue)) {
            $trackingNumber->error($this->_('Tracking number may not be empty if Tracking URL is set'));
        }

        if ($form->getErrors()) {
            // The form is processed and populated but contains errors
            return $form->render();
        }

        // Sanitize input for _updateOrderStatus
        $statusValue = $sanitizer->text($statusValue);
        $trackingNumberValue = $sanitizer->text($trackingNumberValue);
        $trackingUrlValue = $sanitizer->httpUrl($trackingUrlValue);

        $success = $this->_updateOrderStatus($token, $statusValue, $trackingNumberValue, $trackingUrlValue);
        if ($success) {
            // Reset cache for this order and redirect to itself to display updated values
            $this->wire('sniprest')->deleteOrderCache($token);
            $redirectUrl = $this->currentUrl . '?modal=1';
            if ($ret) $redirectUrl .= '&ret=' . urlencode($ret);
            $session->redirect($redirectUrl);
        }

        return $form->render();
    }

    /**
     * Render and process the order comment form.
     *
     * @param array $item
     * @param string $ret A return URL (optional)
     * @return markup 
     *
     */
    private function _processOrderCommentForm($item, $ret = '') {
        $modules = $this->wire('modules');
        $sanitizer = $this->wire('sanitizer');
        $input = $this->wire('input');
        $session = $this->wire('session');

        $token = $item['token'];

		/** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm');
        $form->id = 'OrderCommentForm';
        $form->action = $this->currentUrl;

            if ($ret) {
                /** @var InputfieldHidden $f */
                $f = $modules->get('InputfieldHidden');
                $f->name = 'ret';
                $f->value = urlencode($ret);
    
                $form->add($f);
            }

            $fieldset = $modules->get('InputfieldFieldset');
            $fieldset->label = $this->_('Add a comment');
            $fieldset->icon = self::iconComment;
            $fieldset->collapsed = ($input->sending_ordercomment_active)
                ? Inputfield::collapsedNo
                : Inputfield::collapsedYes;

        $form->add($fieldset);

            /** @var InputfieldTextarea $f */
            $f = $modules->get('InputfieldTextarea');
            $f->name = 'message';
            $f->label = $this->_('Comment');
            $f->rows = 4;

        $fieldset->add($f);

            /** @var InputfieldRadios $f */
            $f = $modules->get('InputfieldRadios');
            $f->name = 'deliveryMethod'; 
            $f->label = $this->_('Send this comment to your customer via email or keep it private?');
            $f->optionColumns = 1;
            $f->addOption('Email', 'Email');
            $f->addOption('None', 'Private');
            $f->value = $input->post->deliveryMethod ? $input->post->deliveryMethod : 'Email';

        $fieldset->add($f);

            /** @var InputfieldHidden $f */
    		$f = $modules->get('InputfieldHidden');
    		$f->name = 'sending_ordercomment_active';
    		$f->value = true;

        $fieldset->add($f);

            /** @var InputfieldSubmit $btn */
            $btn = $modules->get('InputfieldSubmit');
            $btn->id = 'UpdateOrderButton';
            $btn->name = 'send_ordercomment';
            $btn->value = $this->_('Add comment');
            $btn->small = true;

        $fieldset->add($btn);

        // Render form without processing if not submitted
        if (!$input->post->sending_ordercomment_active) return $form->render();

        $form->processInput($input->post);

        // Validate input
        $message = $form->get('message');
        $messageValue = $message->value;
        if (!$messageValue) {
            $message->error($this->_('Please enter a comment text'));
        }

        $deliveryMethod = $form->get('deliveryMethod');
        $deliveryMethodValue = $deliveryMethod->value;

        if ($form->getErrors()) {
            // The form is processed and populated but contains errors
            return $form->render();
        }

        // Sanitize input
        $messageValue = $sanitizer->textarea($messageValue);
        $deliveryMethodValue = $sanitizer->text($deliveryMethodValue);
        $deliveryMethodValue = $sanitizer->option($deliveryMethodValue, array('Email', 'None'));

        $success = $this->_addOrderComment($token, $messageValue, $deliveryMethodValue);
        if ($success) {
            // Reset cache for this order and redirect to itself to display updated values
            $this->wire('sniprest')->deleteOrderCache($token);
            $redirectUrl = $this->currentUrl . '?modal=1';
            if ($ret) $redirectUrl .= '&ret=' . urlencode($ret);
            $session->redirect($redirectUrl);
        }

        return $form->render();
    }

    /**
     * Render order detail action buttons.
     *
     * (Currently uses custom button markup as there is a PW bug which 
     * triggers href targets twice + we need to attach JavaScript events on button click)
     *
     * @param $token The order token
     * @param string $ret A return URL (optional)
     * @return buttons markup 
     *
     */
    private function _getOrderDetailActionButtons($token, $ret = '') {
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

        $resendInvoiceUrl = $this->currentUrl . '?action=resend_invoice&modal=1';
        if ($ret) $resendInvoiceUrl .= '&ret=' . urlencode($ret);
        $out .=
        '<a href="' . $resendInvoiceUrl . '"
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
            'email' => $this->_('Email'),
            'creationDate' => $this->_('Order date'),
            'status' => $this->_('Order status'),
            'shippingMethod' => $this->_('Shipping method'),
            'shippingProvider' => $this->_('Shipping provider'),
            'trackingNumber' => $this->_('Tracking number'),
        );

        $itemData = array();
        
        $itemData['customer'] = $item['billingAddressFirstName'] . ' ' . $item['billingAddressName'];
        $itemData['email'] =
        '<a href="mailto:' . $item['email'] . '"
            class="pw-tooltip"
            title="' . $this->_('Send email to customer') .'">' .
                $item['email'] .
        '</a>';
        $itemData['creationDate'] = wireDate('Y-m-d H:i:s', $item['creationDate']);
        $itemData['status'] = $this->getOrderStatus($item['status']);
        $itemData['shippingMethod'] = $item['shippingMethod'];
        $itemData['shippingProvider'] = $item['shippingProvider'];

        $trackingNumber = $item['trackingNumber'];
        if ($item['trackingUrl'] && $item['trackingNumber']) {
            $trackingNumber =
            '<a href="' . $item['trackingUrl'] . '"
                target="_blank"
                class="pw-tooltip"
                title="' . $this->_('Open tracking URL') .'">' .
                    $trackingNumber .
            '</a>';
        }
        $itemData['trackingNumber'] = $trackingNumber;
        
        $data = array();
        foreach ($infoCaptions as $key => $caption) {
            $data[$caption] = !empty($itemData[$key]) ? $itemData[$key] : '-';
        }

        return $this->renderDataSheet($data);
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

        $itemData = array();
        
        $itemData['paymentMethod'] = $this->getPaymentMethod($item['paymentMethod']);

        $itemData['creditCardLast4Digits'] = !empty($item['creditCardLast4Digits'])
            ? '****&nbsp;****&nbsp;****&nbsp;' . $item['creditCardLast4Digits']
            : '';

        $supportedCurrencies = CurrencyFormat::getSupportedCurrencies();
        $itemData['currency'] = isset($supportedCurrencies[$item['currency']])
            ? $supportedCurrencies[$item['currency']]
            : $item['currency'];

        $itemData['paymentStatus'] = $this->getPaymentStatus($item['paymentStatus']);

        $data = array();
        foreach ($infoCaptions as $key => $caption) {
            $data[$caption] = !empty($itemData[$key]) ? $itemData[$key] : '-';
        }

        return $this->renderDataSheet($data);
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
     * Render the refunds table.
     *
     * @param array $refunds
     * @param string $currency
     * @return markup MarkupAdminDataTable 
     *
     */
    private function _renderTableRefunds($refunds, $currency) {
        $modules = $this->wire('modules');

        /*
        {
            "orderToken": "7043e043-bb47-42c4-b7a5-646944c36e10",
            "amount": 55.00,
            "comment": "Internal comment...",
            "notifyCustomer": false,
            "refundedByPaymentGateway": true,
            "id": "5e2c1b82-2dcc-4e37-a79e-308891ae5d84",
            "creationDate": "2019-11-14T13:23:35.897Z",
            "modificationDate": "2019-11-14T13:23:35.897Z"
        },
        */

        /** @var MarkupAdminDataTable $table */
        $table = $modules->get('MarkupAdminDataTable');
        $table->setEncodeEntities(false);
        $table->id = 'RefundsSummaryTable';
        $table->setSortable(false);
        $table->setResizable(false);
        $table->headerRow(array(
            $this->_('Refunded on'),
            $this->_('Amount'),
            $this->_('Reason for refund'),
            $this->_('Payment gateway'),
        ));
        foreach ($refunds as $refund) {
            $table->row(array(
                wireDate('Y-m-d H:i:s', $refund['creationDate']),
                CurrencyFormat::format($refund['amount'], $currency),
                $refund['comment'],
                $refund['refundedByPaymentGateway']
                    ? $this->_('Refunded')
                    : $this->_('Not refunded')
            ));
        }

        $out = $table->render();            

        return $out;
    }

    /**
     * Render the order notifications table.
     *
     * @param array $notifications The order comments and notifications
     * @return markup MarkupAdminDataTable 
     *
     */
    private function _renderTableNotifications($notifications) {
        $modules = $this->wire('modules');

        /*
        Comment item object can be this:
        {
            "id": "8dc30be8-1cea-4923-adfa-bae3e4542eee",
            "creationDate": "2019-12-21T11:14:13.563Z",
            "type": "OrderStatusChanged",
            "deliveryMethod": "None",
            "message": "Order status changed from 'Disputed' to 'Processed'."
        }
        
        or this:
        {
            "id": "6fd7ebbc-7f3a-4135-8d63-c14f084b47fb",
            "creationDate": "2019-12-20T18:19:31.017Z",
            "type": "Invoice",
            "deliveryMethod": "Email",
            "body": "... (can be HTML) ...",
            "message": "", 
            "subject": "Order SNIP-1071 on bitego",
            "sentOn": "2019-12-22T15:53:34.4740987Z" 
        }
        */

        /** @var MarkupAdminDataTable $table */
        $table = $modules->get('MarkupAdminDataTable');
        $table->setEncodeEntities(false);
        $table->id = 'CommentsSummaryTable';
        $table->setSortable(false);
        $table->setResizable(false);
        $table->headerRow(array(
            $this->_('Created on'),
            $this->_('Notification'),            
        ));
        foreach ($notifications['items'] as $notification) {

            switch ($notification['type']) {
                case 'Comment':
                    $message = $notification['message'];
                    break;

                case 'OrderStatusChanged':
                    // Try to parse the Snipcart message (@todo: find a better method to get required parts)
                    $messageParts = explode("'", $notification['message']);
                    if (!empty($messageParts && isset($messageParts[1]) && isset($messageParts[3]))) {
                        $message = sprintf(
                            $this->_('Order status changed from \'%1$s\' to \'%2$s\'.'),
                            $this->getOrderStatus($messageParts[1]),
                            $this->getOrderStatus($messageParts[3])
                        );
                    } else {
                        $message = $notification['message'];
                    }
                    break;

                case 'OrderShipped':
                    $message = $this->_('Order has been shipped to customer.');
                    break;

                case 'OrderCancelled':
                    $message = $this->_('Order has been cancelled.');
                    break;

                case 'TrackingNumber':
                    $message = $this->_('Tracking number has been set.');
                    break;

                case 'Invoice':
                    $message = $this->_('Customer invoice has been sent.');
                    $message .= '<br><small class="ui-priority-secondary tooltip" title="';
                    $message .= wireDate('Y-m-d H:i:s', $notification['sentOn']);
                    $message .= '">';
                    $message .= sprintf(
                        $this->_('%s via email'),
                        wireDate('relative', $notification['sentOn'])
                    );
                    $message .= '</small>';
                    break;

                case 'Refund':
                    $message = $this->_('An amount has been refunded.');
                    break;

                default:
                    $message = $this->_('-- unknown --');
            }

            $table->row(array(
                wireDate('Y-m-d H:i:s', $notification['creationDate']),
                $message,
            ));
        }

        $out = $table->render(); 

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
            // Reset cache for this order
            $sniprest->deleteOrderCache($token);
            $this->message($this->_('The invoice has been sent to customer.'));
        }
    }

    /**
     * Refund an amount.
     *
     * @param string $token The order token
     * @param float $amount The amount to be refunded #required
     * @param string $amountFormatted The formatted amount #required
     * @param boolean $notifyCustomer Send reason for refund (textfield) with customer notification
     * @param string $comment The reason for the refund (internal note - not for customer)
     * @return boolean
     *
     */
    private function _refund($token, $amount, $amountFormatted, $notifyCustomer, $comment = '') {
        $sniprest = $this->wire('sniprest');

        if (empty($token) || empty($amount)) return;

        $options = array(
            'amount' => $amount,
            'comment' => $comment,
            'notifyCustomer' => $notifyCustomer,
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
     * Update the status of the specified order.
     *
     * @param string $token The order token
     * @param string $status The order status
     * @param string $trackingNumber The tracking number associated to the order
     * @param string $trackingUrl The URL where the customer will be able to track its order
     * @return boolean
     *
     */
    private function _updateOrderStatus($token, $status, $trackingNumber, $trackingUrl) {
        $sniprest = $this->wire('sniprest');

        if (empty($token)) return;

        $options = array(
            'status' => $status,
            'trackingNumber' => $trackingNumber,
            'trackingUrl' => $trackingUrl,
        );

        $updated = false;
        $response = $sniprest->putOrderStatus($token, $options);
        if (
            $response[$token][WireHttpExtended::resultKeyHttpCode] != 200 &&
            $response[$token][WireHttpExtended::resultKeyHttpCode] != 201
        ) {
            $this->error(
                $this->_('The order status could not be updated! The following error occurred: ') .
                $response[$token][WireHttpExtended::resultKeyError]);
        } else {
            $this->message($this->_('The order status has been updated.'));
            $updated = true;
        }
        return $updated;
    }

    /**
     * Add a comment to the specified order.
     *
     * @param string $token The order token
     * @param string $message The comment text
     * @param string $deliveryMethod The delivery method ('Email' or 'None')
     * @return boolean
     *
     */
    private function _addOrderComment($token, $message, $deliveryMethod) {
        $sniprest = $this->wire('sniprest');

        if (empty($token)) return;

        $options = array(
            'type' => 'Comment',
            'message' => $message,
            'deliveryMethod' => $deliveryMethod,
        );

        $added = false;
        $response = $sniprest->postOrderNotification($token, $options);
        if (
            $response[$token][WireHttpExtended::resultKeyHttpCode] != 200 &&
            $response[$token][WireHttpExtended::resultKeyHttpCode] != 201
        ) {
            $this->error(
                $this->_('The order comment could not added! The following error occurred: ') .
                $response[$token][WireHttpExtended::resultKeyError]);
        } else {
            $this->message($this->_('The order comment has been added.'));
            $added = true;
        }
        return $added;
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
