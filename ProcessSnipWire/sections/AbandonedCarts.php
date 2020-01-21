<?php namespace ProcessWire;

/**
 * AbandonedCarts trait - sections file for ProcessSnipWire.module.php.
 * (This file is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

trait AbandonedCarts {
    /**
     * The SnipWire Snipcart Abandoned Carts page.
     *
     * @return page markup
     *
     */
    public function ___executeAbandonedCarts() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sanitizer = $this->wire('sanitizer');
        $session = $this->wire('session');
        $sniprest = $this->wire('sniprest');
        
        $this->browserTitle($this->_('Snipcart Abandoned Carts'));
        $this->headline($this->_('Snipcart Abandoned Carts'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

        $forceRefresh = false;
        $limit = 50; // current limit for abandoned carts list
        $continuationToken = null;

        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        } elseif ($action == 'refresh_all') {
            $sniprest->deleteFullCache();
            $this->message(SnipREST::getMessagesText('full_cache_refreshed'));
        }

        $timeRange = $sanitizer->text($input->timeRange);
        if (!$timeRange) $timeRange = 'LessThanAWeek';
        $minimalValue = $sanitizer->text($input->minimalValue);
        $email = $sanitizer->text($input->email);
        $filter = array(
            'timeRange' => $timeRange,
            'minimalValue' => $minimalValue ? $minimalValue : '',
            'email' => $email ? $email : '',
        );

        // Currently there is no pagination available as Snipcart has no offset param in this case.
        // @todo: create an alternative way to use pagination here
        $defaultSelector = array(
            'limit' => $limit,
            'continuationToken' => $continuationToken,
        );

        $selector = array_merge($defaultSelector, $filter);

        $response = $sniprest->getAbandonedCarts(
            '',
            $selector,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );

        $abandonedCarts = isset($response[SnipRest::resPathCartsAbandoned][WireHttpExtended::resultKeyContent])
            ? $response[SnipRest::resPathCartsAbandoned][WireHttpExtended::resultKeyContent]
            : array();
        
        $items = isset($abandonedCarts['items']) ? $abandonedCarts['items'] : array();

        $out = $this->_buildAbandonedCartsFilter($filter);

        $headline = $this->_('Abandoned Carts') . ': ' . $this->getAbandonedCartsTimeRange($timeRange);

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Abandoned Carts');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconAbandonedCart;
        $f->value = $this->_wrapItemListerHeadline($headline);
        $f->value .= $this->_renderTableAbandonedCarts($items);
        $f->collapsed = Inputfield::collapsedNever;

        $out .= $f->render();

        $out .= $this->_renderActionButtons();

        return $this->_wrapDashboardOutput($out);
    }

    /**
     * The SnipWire Abandoned Cart detail page.
     *
     * @return page markup
     *
     */
    public function ___executeAbandonedCart() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sniprest = $this->wire('sniprest');
        
        $this->browserTitle($this->_('Snipcart Abandoned Cart'));
        $this->headline($this->_('Snipcart Abandoned Cart'));

        $this->breadcrumb($this->snipWireRootUrl, $this->_('SnipWire Dashboard'));
        $this->breadcrumb($this->snipWireRootUrl . 'abandoned-carts/', $this->_('Snipcart Abandoned Carts'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

        $id = $input->urlSegment(2); // Get Snipcart cart id
        $forceRefresh = false;

        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        } elseif ($action == 'refresh_all') {
            $sniprest->deleteFullCache();
            $this->message(SnipREST::getMessagesText('full_cache_refreshed'));
        }

        $response = $sniprest->getAbandonedCart(
            $id,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );
        $dataKey = SnipRest::resPathCartsAbandoned . '/' . $id;
        $cart = isset($response[$dataKey][WireHttpExtended::resultKeyContent])
            ? $response[$dataKey][WireHttpExtended::resultKeyContent]
            : array();

        $out = '';

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Abandoned Cart');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconAbandonedCart;
        $f->value = $this->_renderDetailAbandonedCart($cart);
        $f->collapsed = Inputfield::collapsedNever;

        $out .= $f->render();

        $out .= $this->_renderActionButtons();

        return $this->_wrapDashboardOutput($out);
    }

    /**
     * Build the abandoned carts filter form.
     *
     * @param array $filter The current filter values
     * @return markup InputfieldForm
     *
     */
    private function _buildAbandonedCartsFilter($filter) {
        $modules = $this->wire('modules');
        $config = $this->wire('config');

        $filterSettings = array(
            'form' => '#AbandonedCartsFilterForm',
        );

        // Hand over configuration to JS
        $config->js('filterSettings', $filterSettings);

        /** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm');
        $form->attr('id', 'AbandonedCartsFilterForm');
        $form->method = 'post';
        $form->action = $this->currentUrl;

            /** @var InputfieldFieldset $fsSnipWire */
            $fieldset = $modules->get('InputfieldFieldset');
            $fieldset->label = $this->_('Search for Abandoned Carts');
            $fieldset->icon = 'search';
            if (
                ($filter['timeRange'] && $filter['timeRange'] != 'LessThanAWeek') ||
                $filter['minimalValue']
            ) {
                $fieldset->collapsed = Inputfield::collapsedNo;
            } else {
                $fieldset->collapsed = Inputfield::collapsedYes;
            }

                /** @var InputfieldSelect $f */
                $f = $modules->get('InputfieldSelect');
                $f->addClass('filter-form-select');
                $f->attr('name', 'timeRange');
                $f->label = $this->_('Time range');
                $f->value = $filter['timeRange'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 33;
                $f->required = true;
                $f->addOptions($this->getAbandonedCartsTimeRanges());

            $fieldset->add($f);

                /** @var InputfieldText $f */
                $f = $modules->get('InputfieldText');
                $f->attr('name', 'minimalValue');
                $f->label = $this->_('Minimum value');
                $f->value = $filter['minimalValue'];
                $f->pattern = '[-+]?[0-9]*[.]?[0-9]+';
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 33;

            $fieldset->add($f);

                /** @var InputfieldText $f */
                $f = $modules->get('InputfieldEmail');
                $f->attr('name', 'email');
                $f->label = $this->_('Email');
                $f->value = $filter['email'];
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
     * Render the abandoned carts table.
     *
     * @param array $items
     * @return markup MarkupAdminDataTable | custom html with `no items` display 
     *
     */
    private function _renderTableAbandonedCarts($items) {
        $modules = $this->wire('modules');

        if (!empty($items)) {
            $modules->get('JqueryTableSorter')->use('widgets');

            /** @var MarkupAdminDataTable $table */
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->setID('snipwire-abandoned-carts-table');
            $table->setClass('ItemLister');
            $table->setSortable(false);
            $table->setResizable(true);
            $table->setResponsive(true);
            $table->headerRow(array(
                $this->_('Order Email'),
                $this->_('Cart last modified'),
                $this->_('# Items'),
                $this->_('Items'),
                $this->_('Value'),
            ));

            foreach ($items as $item) {
                $customerEmail =
                '<a href="' . $this->snipWireRootUrl . 'abandoned-cart/' . $item['id'] . '"
                    class="pw-panel pw-panel-links"
                    data-panel-width="85%">' .
                        wireIconMarkup(self::iconAbandonedCart, 'fa-right-margin') . $item['email'] .
                '</a>';
                $total =
                '<strong class="price-field">' .
                    CurrencyFormat::format($item['summary']['total'], $item['currency']) .
                '</strong>';
                $products = array();
                foreach ($item['items'] as $product) {
                    $products[] = $product['name'];
                }
                $productNames = implode(',<br>', $products);
                $table->row(array(
                    $customerEmail,
                    wireDate('Y-m-d H:i:s', $item['modificationDate']),
                    count($item['items']),
                    $productNames,
                    $total,
                ));
            }
            $out = $table->render();
        } else {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No abandoned carts found') .
            '</div>';
        }
        return '<div class="ItemListerTable">' . $out . '</div>';
    }

    /**
     * Render the abandoned cart detail view.
     *
     * @param array $item
     * @param string $ret A return URL (optional)
     * @return markup 
     *
     */
    private function _renderDetailAbandonedCart($item, $ret = '') {
        $modules = $this->wire('modules');
        $sniprest = $this->wire('sniprest');

        if (empty($item)) {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No cart selected') .
            '</div>';
            return $out;
        }

        $id = $item['id'];

        $out =
        '<div class="ItemDetailHeader">' .
            '<h2 class="ItemDetailTitle">' .
                wireIconMarkup(self::iconAbandonedCart, 'fa-right-margin') .
                $this->_('Cart') . ': ' .
                $item['email'] .
            '</h2>' .
        '</div>';

        //$out .= $this->_processCartCommentForm($item, $ret);

        /** @var InputfieldForm $wrapper */
        $wrapper = $modules->get('InputfieldForm');

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Cart Info');
            $f->icon = self::iconInfo;
            $f->value = $this->_renderCartInfo($item);
            $f->columnWidth = 50;
            
        $wrapper->add($f);

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Customer Info');
            $f->icon = self::iconCustomer;
            $f->value = $this->_renderCustomerInfo($item);
            $f->columnWidth = 50;
            
        $wrapper->add($f);

        $out .= $wrapper->render();

        /** @var InputfieldForm $wrapper */
        $wrapper = $modules->get('InputfieldForm');

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Cart Details');
            $f->icon = self::iconAbandonedCart;
            $f->value = $this->_renderTableCartSummary($item);
            
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
     * Render the cart info block.
     *
     * @param array $item
     * @return markup 
     *
     */
    private function _renderCartInfo($item) {
        $infoCaptions = array(
            'status' => $this->_('Cart status'),
            'creationDate' => $this->_('Created on'),
            'modificationDate' => $this->_('Modified on'),
            'shippingMethod' => $this->_('Shipping method'),
            'currency' => $this->_('Currency'),
        );

        $item['creationDate'] = wireDate('Y-m-d H:i:s', $item['creationDate']);
        $item['modificationDate'] = wireDate('Y-m-d H:i:s', $item['modificationDate']);
        $item['shippingMethod'] = $item['shippingInformation']['method'];

        $supportedCurrencies = CurrencyFormat::getSupportedCurrencies();
        $item['currency'] = isset($supportedCurrencies[$item['currency']])
            ? $supportedCurrencies[$item['currency']]
            : $item['currency'];

        $data = array();
        foreach ($infoCaptions as $key => $caption) {
            $data[$caption] = !empty($item[$key]) ? $item[$key] : '-';
        }

        return $this->renderDataSheet($data);
    }

    /**
     * Render the customer info block.
     *
     * @param array $item
     * @return markup 
     *
     */
    private function _renderCustomerInfo($item) {
        $infoCaptions = array(
            'email' => $this->_('Email'),
            'firstName' => $this->_('First Name'),
            'name' => $this->_('Last Name'),
            'country' => $this->_('Country'),
            'lang' => $this->_('Language'),
        );

        $customerInfos = array();

        if (!empty($item['email'])) {
            $customerInfos['email'] =
            '<a href="mailto:' . $item['email'] . '"
                class="pw-tooltip"
                title="' . $this->_('Send email to customer') .'">' .
                    $item['email'] .
            '</a>';
        } else {
            $customerInfos['email'] = '';
        }
        $customerInfos['firstName'] = $item['billingAddress']['firstName'];
        $customerInfos['name'] = $item['billingAddress']['name'];
        $customerInfos['country'] = !empty($item['billingAddress']['country'])
            ? Countries::getCountry($item['billingAddress']['country'])
            : '';
        $customerInfos['lang'] = $item['lang'];

        $data = array();
        foreach ($infoCaptions as $key => $caption) {
            $data[$caption] = !empty($customerInfos[$key]) ? $customerInfos[$key] : '-';
        }

        return $this->renderDataSheet($data);
    }

    /**
     * Render the cart summmary table.
     *
     * @param array $item
     * @return markup MarkupAdminDataTable 
     *
     */
    private function _renderTablecartSummary($item) {
        $modules = $this->wire('modules');

        $products = $item['items'];
        $shipping = $item['shippingInformation'];
        $summary = $item['summary'];
        $currency = $item['currency'];

        /** @var MarkupAdminDataTable $table */
        $table = $modules->get('MarkupAdminDataTable');
        $table->setEncodeEntities(false);
        $table->id = 'CartSummaryTable';
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
        $shippingMethod = $shipping['method'] ? ' (' . $shipping['method'] . ')' : '';
        $table->row(array(
            '',
            $this->_('Shipping') . $shippingMethod,
            '',
            '',
            CurrencyFormat::format($shipping['fees'], $currency),
        ), array(
            'class' => 'row-summary-shipping',
        ));

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

        $out = $table->render();            

        return $out;
    }
}
