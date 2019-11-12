<?php namespace ProcessWire;

/**
 * Discounts trait - sections file for ProcessSnipWire.module.php.
 * (This file is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

trait Discounts {
    /**
     * The SnipWire Snipcart Discounts page.
     *
     * @return page markup
     *
     */
    public function ___executeDiscounts() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sanitizer = $this->wire('sanitizer');
        $session = $this->wire('session');
        $sniprest = $this->wire('sniprest');
        
        $this->browserTitle($this->_('Snipcart Discounts'));
        $this->headline($this->_('Snipcart Discounts'));
        
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

        $status = $sanitizer->text($input->status);
        $name = $sanitizer->text($input->name);
        $code = $sanitizer->text($input->code);
        $filter = array(
            'status' => $status ? $status : 'All',
            'name' => $name ? $name : '',
            'code' => $code ? $code : '',
        );

        // Currently there is no pagination available as Snipcart has no limit & offset params in this case.
        // @todo: create an alternative way to use pagination here

        $response = $sniprest->getDiscounts(
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );

        $discounts = isset($response[SnipRest::resourcePathDiscounts][WireHttpExtended::resultKeyContent])
            ? $response[SnipRest::resourcePathDiscounts][WireHttpExtended::resultKeyContent]
            : array();

        // As discounts have no query params for REST, we need to search in the result set instead
        if (!empty($discounts) && ($status == 'Archived' || $status == 'Active')) {
            $archived = ($status == 'Archived') ? true : false;
            $discounts = array_filter($discounts, function ($discounts) use ($archived) {
                return ($discounts['archived'] == $archived);
            });
        }
        if (!empty($discounts) && $name) {
            $discounts = array_filter($discounts, function ($discounts) use ($name) {
                // Compare case insensitive and part of string
                return (stripos($discounts['name'], $name) !== false);
            });
        }
        if (!empty($discounts) && $code) {
            $discounts = array_filter($discounts, function ($discounts) use ($code) {
                // Compare case sensitive + full string
                return ($discounts['code'] == $code);
            });
        }

        $items = $discounts ? $discounts : array();

        $out = $this->_buildDiscountsFilter($filter);

        $headline = $this->_('Discounts');

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Discounts');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconDiscount;
        $f->value = $this->_wrapItemListerHeadline($headline);
        $f->value .= $this->_renderTableDiscounts($items);
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
     * The SnipWire Discount detail page.
     *
     * @return page markup
     *
     */
    public function ___executeDiscount() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sniprest = $this->wire('sniprest');
        
        $id = $input->urlSegment(2); // Get Snipcart discount id
        
        $this->browserTitle($this->_('Snipcart Discount'));
        $this->headline($this->_('Snipcart Discount'));

        $this->breadcrumb($this->snipWireRootUrl, $this->_('SnipWire Dashboard'));
        $this->breadcrumb($this->snipWireRootUrl . 'discounts/', $this->_('Snipcart Discounts'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }
        
        $response = $sniprest->getDiscount($id);
        $discount = isset($response[SnipRest::resourcePathDiscounts . '/' . $id][WireHttpExtended::resultKeyContent])
            ? $response[SnipRest::resourcePathDiscounts . '/' . $id][WireHttpExtended::resultKeyContent]
            : array();

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Discount');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconDiscount;
        $f->value = $this->_renderDetailDiscount($discount);
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
     * Build the discounts filter form.
     *
     * @param array $filter The current filter values
     * @return markup InputfieldForm
     *
     */
    private function _buildDiscountsFilter($filter) {
        $modules = $this->wire('modules');
        $config = $this->wire('config');

        $filterSettings = array(
            'form' => '#DiscountsFilterForm',
        );

        // Hand over configuration to JS
        $config->js('filterSettings', $filterSettings);

        /** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm');
        $form->attr('id', 'DiscountsFilterForm');
        $form->method = 'post';
        $form->action = $this->currentUrl;

            /** @var InputfieldFieldset $fsSnipWire */
            $fieldset = $modules->get('InputfieldFieldset');
            $fieldset->label = $this->_('Search for Discounts');
            $fieldset->icon = 'search';
            if (
                ($filter['status'] && $filter['status'] != 'All') ||
                $filter['name'] ||
                $filter['code']
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
                $f->columnWidth = 33;
                $f->required = true;
                $f->addOptions($this->discountsStatuses);

            $fieldset->add($f);

                /** @var InputfieldText $f */
                $f = $modules->get('InputfieldText');
                $f->attr('name', 'name');
                $f->label = $this->_('Name');
                $f->value = $filter['name'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 34;

            $fieldset->add($f);

                /** @var InputfieldText $f */
                $f = $modules->get('InputfieldText');
                $f->attr('name', 'code');
                $f->label = $this->_('Code');
                $f->value = $filter['code'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 33;

            $fieldset->add($f);

                $buttonsWrapper = $modules->get('InputfieldMarkup');
                $buttonsWrapper->contentClass = 'ItemsFilterButtonWrapper';
                $buttonsWrapper->markupText = $this->_getFilterFormButtons($this->processUrl);

            $fieldset->add($buttonsWrapper);

        $form->add($fieldset);

        return $form->render();
    }

    /**
     * Render the discounts table.
     *
     * @param array $items
     * @return markup MarkupAdminDataTable | custom html with `no items` display 
     *
     */
    private function _renderTableDiscounts($items) {
        $modules = $this->wire('modules');

        if (!empty($items)) {
            $modules->get('JqueryTableSorter')->use('widgets');

            /** @var MarkupAdminDataTable $table */
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->setID('snipwire-discounts-table');
            $table->setClass('ItemLister');
            $table->setSortable(false);
            $table->setResizable(true);
            $table->setResponsive(true);
            $table->headerRow(array(
                $this->_('Name'),
                $this->_('Condition'),
                $this->_('Action'),
                $this->_('Currency'),
                $this->_('Code'),
                $this->_('Usages'),
                $this->_('Expires'),
            ));

            foreach ($items as $item) {
                $panelLink =
                '<a href="' . $this->snipWireRootUrl . 'discount/' . $item['id'] . '"
                    class="pw-panel"
                    data-panel-width="70%">' .
                        wireIconMarkup(self::iconDiscount, 'fa-right-margin') . $item['name'] .
                '</a>';

                $currency = !empty($item['currency']) ? $item['currency'] : '-';

                $condition = $this->discountsTriggers[$item['trigger']];
                if ($item['trigger'] == 'Total') {
                    $condition .= ': <strong>' . CurrencyFormat::format($item['totalToReach'], $currency) . '</strong>';
                }
                
                $action = $this->discountsTypes[$item['type']];
                if (strpos(strtolower($item['type']), 'amount') !== false) {
                    $amount = $item['amount']
                        ? CurrencyFormat::format($item['amount'], $currency)
                        : $this->_('(missing value)');
                    $action .= ': <strong>' . $amount . '</strong>';
                } elseif (strpos(strtolower($item['type']), 'rate') !== false) {
                    $rate = $item['rate']
                        ? $item['rate'] . '%'
                        : $this->_('(missing value)');
                    $action .= ': <strong>' . $rate . '</strong>';
                }

                $supportedCurrencies = CurrencyFormat::getSupportedCurrencies();
                $currencyLabel = isset($supportedCurrencies[$currency])
                    ? $supportedCurrencies[$currency]
                    : $currency;
                
                $code = $item['code'] ? $item['code'] : '-';
                $usages = $item['numberOfUsages'] . ' ' . $this->_('of') . ' ' . $item['maxNumberOfUsages'];

                $table->row(array(
                    $panelLink,
                    $condition,
                    $action,
                    $currencyLabel,
                    $code,
                    $usages,
                    wireDate('Y-m-d', $item['expires']),
                ));
            }
            $out = $table->render();
        } else {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No discounts found') .
            '</div>';
        }
        return '<div class="ItemListerTable">' . $out . '</div>';
    }

    /**
     * Render the discount detail view.
     *
     * @param array $item
     * @return markup 
     *
     */
    private function _renderDetailDiscount($item) {
        $modules = $this->wire('modules');

        if (!empty($item)) {


            $out = '<pre>' . print_r($item, true) . '</pre>';


        } else {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No discount selected') .
            '</div>';
        }

        return $out;
    }
}
