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
        $limit = 50;
        $continuationToken = null;

        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
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

        $request = $sniprest->getAbandonedCarts(
            '',
            $selector,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );

        $abandonedCarts = isset($request[SnipRest::resourcePathCartsAbandoned][WireHttpExtended::resultKeyContent])
            ? $request[SnipRest::resourcePathCartsAbandoned][WireHttpExtended::resultKeyContent]
            : array();
        
        $items = isset($abandonedCarts['items']) ? $abandonedCarts['items'] : array();

        $out = $this->_buildAbandonedCartsFilter($filter);

        $timerangeLabel = array_key_exists($timeRange, $this->abandonedCartsTimeRanges)
            ? $this->abandonedCartsTimeRanges[$timeRange]
            : '-';
        $headline = $this->_('Abandoned Carts') . ': ' . $timerangeLabel;

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Abandoned Carts');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconAbandonedCart;
        $f->value = $this->_wrapItemListerHeadline($headline);
        $f->value .= $this->_renderTableAbandonedCarts($items);
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
        
        $id = $input->urlSegment(2); // Get Snipcart cart id
        
        $this->browserTitle($this->_('Snipcart Abandoned Cart'));
        $this->headline($this->_('Snipcart Abandoned Cart'));

        $this->breadcrumb($this->snipWireRootUrl, $this->_('SnipWire Dashboard'));
        $this->breadcrumb($this->snipWireRootUrl . 'abandoned-carts/', $this->_('Snipcart Abandoned Carts'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }
        
        $request = $sniprest->getAbandonedCart($id);
        $cart = isset($request[SnipRest::resourcePathCartsAbandoned . '/' . $id][WireHttpExtended::resultKeyContent])
            ? $request[SnipRest::resourcePathCartsAbandoned . '/' . $id][WireHttpExtended::resultKeyContent]
            : array();

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Abandoned Cart');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconAbandonedCart;
        $f->value = $this->_renderDetailAbandonedCart($cart);
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
                $f->attr('name', 'timeRange');
                $f->label = $this->_('Time range');
                $f->value = $filter['timeRange'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 33;
                $f->required = true;
                $f->addOptions($this->abandonedCartsTimeRanges);

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
                $buttonsWrapper->contentClass = 'ItemsFilterButtonWrapper';
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
                $panelLink =
                '<a href="' . $this->snipWireRootUrl . 'abandoned-cart/' . $item['id'] . '"
                    class="pw-panel"
                    data-panel-width="70%">' .
                        wireIconMarkup(self::iconAbandonedCart, 'fa-right-margin') . $item['email'] .
                '</a>';
                $products = array();
                foreach ($item['items'] as $product) {
                    $products[] = $product['name'];
                }
                $productNames = implode(',<br>', $products);
                $table->row(array(
                    $panelLink,
                    wireDate('Y-m-d H:i:s', $item['modificationDate']),
                    count($item['items']),
                    $productNames,
                    CurrencyFormat::format($item['summary']['total'], $item['currency']),
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
     * @return markup 
     *
     */
    private function _renderDetailAbandonedCart($item) {
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
}
