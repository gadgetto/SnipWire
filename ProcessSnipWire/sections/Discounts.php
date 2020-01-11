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

        $this->_setDiscountJSConfigValues();

        $forceRefresh = false;

        $id = $sanitizer->text($input->id); // Get Snipcart discount id
        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        } elseif ($action == 'refresh_all') {
            $sniprest->deleteFullCache();
            $this->message(SnipREST::getMessagesText('full_cache_refreshed'));
        } elseif ($action == 'delete_discount' && !empty($id)) {
            $success = $this->_deleteDiscount($id);
            if ($success) {
                // Reset full discounts cache
                $this->wire('sniprest')->deleteDiscountCache();
            }
            // Redirect to itself to remove url params
            $redirectUrl = $this->currentUrl;
            $session->redirect($redirectUrl);
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

        $discounts = isset($response[SnipRest::resPathDiscounts][WireHttpExtended::resultKeyContent])
            ? $response[SnipRest::resPathDiscounts][WireHttpExtended::resultKeyContent]
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
        $btn->addClass('pw-panel pw-panel-links');
        $btn->attr('data-href', $this->snipWireRootUrl . 'discount-add');
        $btn->attr('data-panel-width', '85%');
        $btn->value = $this->_('Add new discount');
        $btn->icon = 'plus-circle';
        $btn->showInHeader();

        $addDiscountButton = $btn->render();

        $out .= $this->_renderActionButtons(true, $addDiscountButton);

        return $this->_wrapDashboardOutput($out);
    }

    /**
     * The SnipWire Discount edit page.
     *
     * @return page markup
     *
     */
    public function ___executeDiscountEdit() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sniprest = $this->wire('sniprest');

        $this->browserTitle($this->_('Edit Snipcart Discount'));
        $this->headline($this->_('Edit Snipcart Discount'));

        $this->breadcrumb($this->snipWireRootUrl, $this->_('SnipWire Dashboard'));
        $this->breadcrumb($this->snipWireRootUrl . 'discounts/', $this->_('Snipcart Discounts'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

        $this->_setDiscountJSConfigValues();

        // Determine if request comes from within another page in a modal panel.
        // In this case there will be an input param "ret" (can be GET or POST) which holds the return URL.
        $ret = urldecode($input->ret);

        $id = $input->urlSegment(2); // Get Snipcart discount id

        $response = $sniprest->getDiscount(
            $id,
            WireCache::expireNow
        );
        $discount = isset($response[SnipRest::resPathDiscounts . '/' . $id][WireHttpExtended::resultKeyContent])
            ? $response[SnipRest::resPathDiscounts . '/' . $id][WireHttpExtended::resultKeyContent]
            : array();

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Discount');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconDiscount;
        $f->value = $this->_renderEditDiscount($discount, $ret);
        $f->collapsed = Inputfield::collapsedNever;

        $out = $f->render();

        return $this->_wrapDashboardOutput($out);
    }

    /**
     * The SnipWire Discount add page.
     *
     * @return page markup
     *
     */
    public function ___executeDiscountAdd() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sniprest = $this->wire('sniprest');

        $this->browserTitle($this->_('Add Snipcart Discount'));
        $this->headline($this->_('Add Snipcart Discount'));

        $this->breadcrumb($this->snipWireRootUrl, $this->_('SnipWire Dashboard'));
        $this->breadcrumb($this->snipWireRootUrl . 'discounts/', $this->_('Snipcart Discounts'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Discount');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconDiscount;
        $f->value = $this->_renderAddDiscount();
        $f->collapsed = Inputfield::collapsedNever;

        $out = $f->render();

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
                $f->addClass('filter-form-select');
                $f->attr('name', 'status');
                $f->label = $this->_('Status');
                $f->value = $filter['status'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 33;
                $f->required = true;
                $f->addOptions($this->getDiscountsStatuses());

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
                '&nbsp;',
            ));

            foreach ($items as $item) {
                $panelLink =
                '<a href="' . $this->snipWireRootUrl . 'discount-edit/' . $item['id'] . '"
                    class="pw-panel pw-panel-links"
                    data-panel-width="85%">' .
                        wireIconMarkup(self::iconDiscount, 'fa-right-margin') . $item['name'] .
                '</a>';

                $currency = !empty($item['currency']) ? $item['currency'] : '-';

                $condition = $this->getDiscountsTrigger($item['trigger']);
                if ($item['trigger'] == 'Total') {
                    $condition .= ': <strong>' . CurrencyFormat::format($item['totalToReach'], $currency) . '</strong>';
                }
                
                $action = $this->getDiscountsType($item['type']);
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
                $expires = $item['expires']
                    ? wireDate('Y-m-d', $item['expires'])
                    : $this->_('Never');

                if ($item['numberOfUsages'] > 0) {
                    $deleteLink =
                    '<span
                        class="ui-priority-secondary pw-tooltip"
                        title="' . $this->_('Discount has been already used') .'">' .
                            wireIconMarkup('trash') .
                    '</span>';
                } else {
                    $deleteUrl = $this->currentUrl . '?id=' . $item['id'] . '&action=delete_discount';
                    $deleteLink =
                    '<a href="' . $deleteUrl . '"
                        class="DeleteDiscountButton pw-tooltip"
                        title="' . $this->_('Delete discount') .'">' .
                            wireIconMarkup('trash') .
                    '</a>';
                    
                }

                $table->row(array(
                    $panelLink,
                    $condition,
                    $action,
                    $currencyLabel,
                    $code,
                    $usages,
                    $expires,
                    $deleteLink,
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
     * Render the discount edit view.
     *
     * @param array $item
     * @param string $ret A return URL (optional)
     * @return markup 
     *
     */
    private function _renderEditDiscount($item, $ret) {
        $modules = $this->wire('modules');

        if (empty($item)) {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No discount selected') .
            '</div>';
            return $out;
        }

        if ($item['numberOfUsages'] > 0) {
            /** @var InputfieldButton $btn */
            $btn = $modules->get('InputfieldButton');
            $btn->attr('name', 'delete_discount');
            $btn->attr('disabled', 'disabled');
            $btn->attr('title', $this->_('Discount has been already used'));
            $btn->addClass('ui-state-disabled');
            $btn->text = $this->_('Delete discount');
            $btn->icon = 'trash';
        } else {
            /** @var InputfieldButton $btn */
            $btn = $modules->get('InputfieldButton');
            $btn->attr('name', 'delete_discount');
            $btn->addClass('ui-priority-danger');
            $btn->href = $this->snipWireRootUrl . 'discounts/?id=' . $item['id'] . '&action=delete_discount';
            $btn->aclass = 'DeleteDiscountButton';
            $btn->text = $this->_('Delete discount');
            $btn->icon = 'trash';
        }
        $deleteButton = $btn->render();

        $out =
        '<div class="ItemDetailHeader">' .
            '<h2 class="ItemDetailTitle">' .
                wireIconMarkup(self::iconDiscount, 'fa-right-margin') .
                $this->_('Edit Discount') . ': ' .
                $item['name'] .
            '</h2>' .
            '<div class="ItemDetailActionButtons">' .
                $deleteButton .
            '</div>' .
        '</div>';

        $out .= $this->_processDiscountForm($item, $ret);

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
     * Render the discount add view.
     *
     * @return markup 
     *
     */
    private function _renderAddDiscount() {
        $modules = $this->wire('modules');

        $out =
        '<div class="ItemDetailHeader">' .
            '<h2 class="ItemDetailTitle">' .
                wireIconMarkup(self::iconDiscount, 'fa-right-margin') .
                $this->_('Add New Discount') .
            '</h2>' .
        '</div>';

        $out .= $this->_processDiscountForm();

        return $out;
    }

    /**
     * Render and process the discount form.
     * (Edit or Add)
     *
     * @param array $item (optional)
     * @param string $ret A return URL (optional)
     * @return markup 
     *
     */
    private function _processDiscountForm($item = null, $ret = '') {
        $modules = $this->wire('modules');
        $sanitizer = $this->wire('sanitizer');
        $input = $this->wire('input');
        $session = $this->wire('session');

        $mode = ($item) ? 'edit' : 'add';

        $expiresDateFormat = 'Y-m-d';
        $expiresPlaceholder = 'YYYY-MM-DD';
        $expiresMinDate = wireDate($expiresDateFormat);
        
        if (!$input->post->saving_discount_active) {

            if ($mode == 'edit') {
                // Get values from payload
                $discount = array(
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'expires' => $item['expires'],
                    'maxNumberOfUsages' => $item['maxNumberOfUsages'],
                    'currency' => (isset($item['currency']) ? $item['currency'] : ''), // currency field isn't always present
                    'combinable' => $item['combinable'],
                    'type' => $item['type'],
                    'type_disabled' => $item['type'], // needed to simulate a readonly select in edit mode
                    'amount' => $item['amount'],
                    'rate' => $item['rate'],
                    'alternatePrice' => $item['alternatePrice'],
                    'shippingDescription' => $item['shippingDescription'],
                    'shippingCost' => $item['shippingCost'],
                    'shippingGuaranteedDaysToDelivery' => $item['shippingGuaranteedDaysToDelivery'],
                    'productIds' => $item['productIds'],
                    'productIds_2' => $item['productIds'], // helper field
                    'maxDiscountsPerItem' => $item['maxDiscountsPerItem'],
                    'categories' => $item['categories'],
                    'numberOfItemsRequired' => $item['numberOfItemsRequired'],
                    'numberOfFreeItems' => $item['numberOfFreeItems'],
                    'trigger' => $item['trigger'],
                    'trigger_disabled' => $item['trigger'], // needed to simulate a readonly select in edit mode
                    'code' => $item['code'],
                    'itemId' => $item['itemId'],
                    'totalToReach' => $item['totalToReach'],
                    'maxAmountToReach' => $item['maxAmountToReach'],
                    'quantityInterval' => $item['quantityInterval'],
                    'quantityOfAProduct' => $item['quantityOfAProduct'],
                    'maxQuantityOfAProduct' => $item['maxQuantityOfAProduct'],
                    'onlyOnSameProducts' => $item['onlyOnSameProducts'],
                    'quantityOfProductIds' => $item['quantityOfProductIds'],
                );
            } else {
                // Set default values (if action = add)
                $discount = array(
                    'id' => '',
                    'name' => '',
                    'expires' => '',
                    'maxNumberOfUsages' => 1,
                    'currency' => '',
                    'combinable' => '1',
                    'type' => 'FixedAmount',
                    'amount' => '',
                    'rate' => '',
                    'alternatePrice' => '',
                    'shippingDescription' => '',
                    'shippingCost' => '',
                    'shippingGuaranteedDaysToDelivery' => '',
                    'productIds' => '',
                    'productIds_2' => '', // helper field
                    'maxDiscountsPerItem' => '',
                    'categories' => '',
                    'numberOfItemsRequired' => '',
                    'numberOfFreeItems' => '',
                    'trigger' => 'Code',
                    'code' => '',
                    'itemId' => '',
                    'totalToReach' => '',
                    'maxAmountToReach' => '', 
                    'quantityInterval' => '',
                    'quantityOfAProduct' => '',
                    'maxQuantityOfAProduct' => '',
                    'onlyOnSameProducts' => '',
                    'quantityOfProductIds' => '',
                );
            }
        }

		/** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm');
        $form->attr('id', 'DiscountForm');
		$form->attr('action', $this->currentUrl);
        $form->attr('method', 'post');

            if ($ret) {
                /** @var InputfieldHidden $f */
                $f = $modules->get('InputfieldHidden');
                $f->attr('name', 'ret');
                $f->attr('value', urlencode($ret));
    
                $form->add($f);
            }

            /** @var InputfieldHidden $f */
    		$f = $modules->get('InputfieldHidden');
            $f->attr('name', 'saving_discount_active');
            $f->attr('value', true);

        $form->add($f);

            /** @var InputfieldHidden $f */
            $f = $modules->get('InputfieldHidden');
            $f->attr('name', 'id');

        $form->add($f);

            /** @var InputfieldFieldset $fieldset */
            $fieldset = $modules->get('InputfieldFieldset');
            $fieldset->label = $this->_('General Information');
            $fieldset->icon = 'info-circle';

        $form->add($fieldset);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'name');
            $f->label = $this->_('Discount name');
            $f->detail = $this->_('Friendly name for this discount');
            $f->required = true;
            $f->columnWidth = 50;

        $fieldset->add($f);
            
            /** @var InputfieldDatetime $f */
            $f = $modules->get('InputfieldDatetime');
            $f->attr('name', 'expires');
            $f->attr('placeholder', $expiresPlaceholder);
            $f->attr('autocomplete', 'off');
            $f->label = $this->_('Expires');
            $f->detail = $this->_('Leave empty to never expire');
            $f->size = 100;
            $f->datepicker = InputfieldDatetime::datepickerFocus;
            $f->dateInputFormat = $expiresDateFormat;
            $f->columnWidth = 50;

        $fieldset->add($f);

            /** @var InputfieldInteger $f */
            $f = $modules->get('InputfieldInteger');
            $f->attr('name', 'maxNumberOfUsages');
            $f->label = $this->_('Max. number of usages');
            $f->detail = $this->_('Leave empty to enable unlimited usage');
            $f->size = 100;
            $f->min = 1;
            $f->columnWidth = 50;

        $fieldset->add($f);

            /** @var InputfieldSelect $f */
            $f = $modules->get('InputfieldSelect');
            $f->attr('name', 'currency');
            $f->label = $this->_('Currency');
            $f->columnWidth = 50;
            $supportedCurrencies = CurrencyFormat::getSupportedCurrencies();
            foreach ($this->currencies as $currencyOption) {
                $currencyLabel = isset($supportedCurrencies[$currencyOption])
                    ? $supportedCurrencies[$currencyOption]
                    : $currencyOption;
                $f->addOption($currencyOption, $currencyLabel);
            }

        $fieldset->add($f);

            /** @var InputfieldCheckbox $f */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'combinable');
            $f->label = $this->_('Combination with other discounts allowed?');

        $fieldset->add($f);

            /** @var InputfieldFieldset $fieldset */
            $fieldset = $modules->get('InputfieldFieldset');
            $fieldset->label = $this->_('Set Discount Actions');
            $fieldset->icon = 'cogs';
            $fieldset->set('themeOffset', 1);

        $form->add($fieldset);

            /** @var InputfieldSelect $f */
            $f = $modules->get('InputfieldSelect');
            if ($mode == 'edit') {
                // simulate a readonly select in edit mode
                $f->attr('name', 'type_disabled');
                $f->attr('disabled', 'disabled');
            } else {
                $f->attr('name', 'type');
            }
            $f->addClass('InputfieldMaxWidth');
            $f->label = $this->_('Type');
            $f->required = true;
            $f->addOptions($this->getDiscountsTypes());

        $fieldset->add($f);

            if ($mode == 'edit') {
                // needed to simulate a readonly select in edit mode
                /** @var InputfieldHidden $f */
                $f = $modules->get('InputfieldHidden');
                $f->attr('name', 'type');

                $fieldset->add($f);
            }

            $f = $modules->get('InputfieldMarkup');
            $f->value = $this->_('A discount on a subscription will be applied to every recurring payments for this subscription.');
            $f->value .= $this->_('If you want to discount only the initial payment, please use another discount type.');
            $f->showIf = 'type=AmountOnSubscription|RateOnSubscription';

        $fieldset->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'amount');
            $f->attr('pattern', '[-+]?[0-9]*[.]?[0-9]+');
            $f->label = $this->_('Amount');
            $f->detail = $this->_('Decimal with a dot (.) as separator e.g. 19.99');
            $f->required = true;
            $f->showIf = 'type=FixedAmount|FixedAmountOnItems|FixedAmountOnCategory|AmountOnSubscription';
            $f->requiredIf = 'type=FixedAmount|FixedAmountOnItems|FixedAmountOnCategory|AmountOnSubscription';

        $fieldset->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'rate');
            $f->attr('pattern', '[-+]?[0-9]*[.]?[0-9]+');
            $f->label = $this->_('Rate in %');
            $f->detail = $this->_('Decimal with a dot (.) as separator e.g. 2.5');
            $f->required = true;
            $f->showIf = 'type=Rate|RateOnItems|RateOnCategory|RateOnSubscription';
            $f->requiredIf = 'type=Rate|RateOnItems|RateOnCategory|RateOnSubscription';

        $fieldset->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'alternatePrice');
            $f->label = $this->_('Alternate price list');
            $f->detail = $this->_('The name of the alternate price list to use');
            $f->required = true;
            $f->showIf = 'type=AlternatePrice';
            $f->requiredIf = 'type=AlternatePrice';

        $fieldset->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'shippingDescription');
            $f->label = $this->_('Shipping description');
            $f->detail = $this->_('The shipping method name that will be displayed to your customers');
            $f->required = true;
            $f->showIf = 'type=Shipping';
            $f->requiredIf = 'type=Shipping';

        $fieldset->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'shippingCost');
            $f->attr('pattern', '[-+]?[0-9]*[.]?[0-9]+');
            $f->label = $this->_('Shipping amount');
            $f->detail = $this->_('Decimal with a dot (.) as separator e.g. 4.5');
            $f->required = true;
            $f->showIf = 'type=Shipping';
            $f->requiredIf = 'type=Shipping';
            $f->columnWidth = 50;

        $fieldset->add($f);

            /** @var InputfieldInteger $f */
            $f = $modules->get('InputfieldInteger');
            $f->attr('name', 'shippingGuaranteedDaysToDelivery');
            $f->addClass('InputfieldMaxWidth');
            $f->label = $this->_('Guaranteed days to delivery');
            $f->detail = $this->_('The number of days it will take for shipping (can be empty)');
            $f->min = 1;
            $f->showIf = 'type=Shipping';
            $f->columnWidth = 50;

        $fieldset->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'productIds');
            $f->label = $this->_('Product/Subscription IDs (SKUs)');
            $f->detail = $this->_('A comma separated list of unique product/subscription IDs (SKU) from which the amount or rate will be deducted');
            $f->required = true;
            $f->showIf = 'type=FixedAmountOnItems|RateOnItems|AmountOnSubscription|RateOnSubscription';
            $f->requiredIf = 'type=FixedAmountOnItems|RateOnItems|AmountOnSubscription|RateOnSubscription';
            $f->columnWidth = 50;

        $fieldset->add($f);

            // @todo: what to specify here exactly? Ask SnipCart team.

            /** @var InputfieldInteger $f */
            $f = $modules->get('InputfieldInteger');
            $f->attr('name', 'maxDiscountsPerItem');
            $f->addClass('InputfieldMaxWidth');
            $f->label = $this->_('Max. occurrences per item');
            $f->detail = $this->_('Max. number of times a discount can be applied to an individual item (can be empty)');
            $f->min = 1;
            $f->showIf = 'type=FixedAmountOnItems|RateOnItems';
            $f->columnWidth = 50;

        $fieldset->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'categories');
            $f->label = $this->_('Product categories');
            $f->detail = $this->_('A comma separated list of your product categories from which products the amount or rate will be deducted');
            $f->required = true;
            $f->showIf = 'type=FixedAmountOnCategory|RateOnCategory';
            $f->requiredIf = 'type=FixedAmountOnCategory|RateOnCategory';

        $fieldset->add($f);

            /** @var InputfieldInteger $f */
            $f = $modules->get('InputfieldInteger');
            $f->attr('name', 'numberOfItemsRequired');
            $f->addClass('InputfieldMaxWidth');
            $f->label = $this->_('Number of required items');
            $f->min = 1;
            $f->required = true;
            $f->showIf = 'type=GetFreeItems';
            $f->requiredIf = 'type=GetFreeItems';
            $f->columnWidth = 50;

        $fieldset->add($f);

            /** @var InputfieldInteger $f */
            $f = $modules->get('InputfieldInteger');
            $f->attr('name', 'numberOfFreeItems');
            $f->addClass('InputfieldMaxWidth');
            $f->label = $this->_('Number of free items');
            $f->min = 1;
            $f->required = true;
            $f->showIf = 'type=GetFreeItems';
            $f->requiredIf = 'type=GetFreeItems';
            $f->columnWidth = 50;

        $fieldset->add($f);

            /** @var InputfieldFieldset $fieldset */
            $fieldset = $modules->get('InputfieldFieldset');
            $fieldset->label = $this->_('Set Discount Conditions');
            $fieldset->icon = 'scissors';

        $form->add($fieldset);

            /** @var InputfieldSelect $f */
            $f = $modules->get('InputfieldSelect');
            if ($mode == 'edit') {
                // simulate a readonly select in edit mode
                $f->attr('name', 'trigger_disabled');
                $f->attr('disabled', 'disabled');
            } else {
                $f->attr('name', 'trigger');
            }
            $f->addClass('InputfieldMaxWidth');
            $f->label = $this->_('Trigger');
            $f->required = true;
            $f->addOptions($this->getDiscountsTriggers());

        $fieldset->add($f);

            if ($mode == 'edit') {
                // needed to simulate a readonly select in edit mode
                /** @var InputfieldHidden $f */
                $f = $modules->get('InputfieldHidden');
                $f->attr('name', 'trigger');

                $fieldset->add($f);
            }

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'code');
            $f->label = $this->_('Code');
            $f->detail = $this->_('The discount code that will need to be entered by the customer');
            $f->required = true;
            $f->showIf = 'trigger=Code';
            $f->requiredIf = 'trigger=Code';

        $fieldset->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'itemId');
            $f->label = $this->_('Product ID (SKU)');
            $f->required = true;
            $f->showIf = 'trigger=Product';
            $f->requiredIf = 'trigger=Product';

        $fieldset->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'totalToReach');
            $f->attr('pattern', '[-+]?[0-9]*[.]?[0-9]+');
            $f->label = $this->_('Total to reach');
            $f->detail = $this->_('Decimal with a dot (.) as separator e.g. 19.99');
            $f->required = true;
            $f->showIf = 'trigger=Total';
            $f->requiredIf = 'trigger=Total';
            $f->columnWidth = 50;

        $fieldset->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'maxAmountToReach');
            $f->attr('pattern', '[-+]?[0-9]*[.]?[0-9]+');
            $f->label = $this->_('Max. amount to reach');
            $f->detail = $this->_('Decimal with a dot (.) as separator e.g. 199.99');
            $f->showIf = 'trigger=Total';
            $f->columnWidth = 50;

        $fieldset->add($f);

            /** @var InputfieldCheckbox $f */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'quantityInterval');
            $f->label = $this->_('Specify an interval (bulk discount)');
            $f->showIf = 'trigger=QuantityOfAProduct';

        $fieldset->add($f);

            /** @var InputfieldInteger $f */
            $f = $modules->get('InputfieldInteger');
            $f->attr('name', 'quantityOfAProduct');
            $f->addClass('InputfieldMaxWidth');
            $f->label = $this->_('Min. quantity');
            $f->min = 1;
            $f->required = true;
            $f->showIf = 'trigger=QuantityOfAProduct';
            $f->requiredIf = 'trigger=QuantityOfAProduct';

        $fieldset->add($f);

            /** @var InputfieldInteger $f */
            $f = $modules->get('InputfieldInteger');
            $f->attr('name', 'maxQuantityOfAProduct');
            $f->addClass('InputfieldMaxWidth');
            $f->label = $this->_('Max. quantity');
            $f->min = 1;
            $f->showIf = 'trigger=QuantityOfAProduct, quantityInterval=1';
            $f->requiredIf = 'quantityInterval=1';

        $fieldset->add($f);

            /** @var InputfieldCheckbox $f */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'onlyOnSameProducts');
            $f->label = $this->_('Trigger only when the quantity is reached on a product in particular');
            $f->showIf = 'trigger=QuantityOfAProduct';

        $fieldset->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'quantityOfProductIds');
            $f->label = $this->_('Product IDs (SKUs)');
            $f->detail = $this->_('Comma separated list of product IDs (SKUs).');
            $f->detail .= $this->_('Leave blank if you want this discount to be applied on any product');
            $f->required = true;
            $f->showIf = 'trigger=QuantityOfAProduct, onlyOnSameProducts=1';
            $f->requiredIf = 'trigger=QuantityOfAProduct, onlyOnSameProducts=1';

        $fieldset->add($f);

            // This is a "companion" field for "productIds" field. Either this or the other one
            // will be shown, but both will set the value for "productIds" in payload sent to Snipcart.
            
            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'productIds_2');
            $f->label = $this->_('Product IDs (SKUs)');
            $f->detail = $this->_('Enter the specific product ids (SKUs) separated by a comma if there is more than one');
            $f->required = true;
            $f->showIf = 'trigger=Product|CartContainsOnlySpecifiedProducts|CartContainsSomeSpecifiedProducts|CartContainsAtLeastAllSpecifiedProducts,type!=FixedAmountOnItems|RateOnItems|AmountOnSubscription|RateOnSubscription';
            $f->requiredIf = 'trigger=Product|CartContainsOnlySpecifiedProducts|CartContainsSomeSpecifiedProducts|CartContainsAtLeastAllSpecifiedProducts,type!=FixedAmountOnItems|RateOnItems|AmountOnSubscription|RateOnSubscription';

        $fieldset->add($f);

            /** @var InputfieldSubmit $btn */
            $btn = $modules->get('InputfieldSubmit');
            $btn->attr('id', 'SaveDiscountButton');
            $btn->attr('name', 'save_discount');
            $btnLabel = ($mode == 'edit')
                ? $this->_('Update discount')
                : $this->_('Add discount');
            $btn->attr('value', $btnLabel);

        $form->add($btn);

        // Render form without processing if not submitted
        if (!$input->post->saving_discount_active) {
            $form->populateValues($discount);
            return $form->render();
        }
        
        // Manually write values for disabled fields to WireInputData before processing form input
        $input->post->set('type_disabled', $input->post->type);
        $input->post->set('trigger_disabled', $input->post->trigger);
        
        $form->processInput($input->post);

        //
        // Get values and validate input
        //
        
        $id = $form->get('id');
        $idValue = $id->value;

        $name = $form->get('name');
        $nameValue = $name->value;

        $expires = $form->get('expires');
        $expiresValue = $expires->value;
        if ($mode == 'add' && $expiresValue && !$sanitizer->date($expiresValue, $expiresDateFormat, array('min' => $expiresMinDate))) {
            $expires->error(sprintf($this->_('Minimum allowed date is %s (today)'), $expiresMinDate));
        }

        $maxNumberOfUsages = $form->get('maxNumberOfUsages');
        $maxNumberOfUsagesValue = $sanitizer->int($maxNumberOfUsages->value);

        $currency = $form->get('currency');
        $currencyValue = $currency->value;

        $combinable = $form->get('combinable');
        $combinableValue = $combinable->value;

        $type = $form->get('type');
        $typeValue = $sanitizer->option($type->value, $this->getDiscountsTypes(true));
        if (empty($typeValue)) {
            $type->error($this->_('Type needs to be selected'));
        }

        $amount = $form->get('amount');
        $amountValue = $amount->value;
        if ($amountValue && !checkPattern($amountValue, '^[-+]?[0-9]*[.]?[0-9]+$')) {
            $amount->error($this->_('Wrong format! Please use decimal with a dot (.) as separator e.g. 19.99'));
        }

        $rate = $form->get('rate');
        $rateValue = $rate->value;
        if ($rateValue && !checkPattern($rateValue, '^[-+]?[0-9]*[.]?[0-9]+$')) {
            $rate->error($this->_('Wrong format! Please use decimal with a dot (.) as separator e.g. 2.5'));
        }

        $alternatePrice = $form->get('alternatePrice');
        $alternatePriceValue = $alternatePrice->value;

        $shippingDescription = $form->get('shippingDescription');
        $shippingDescriptionValue = $shippingDescription->value;

        $shippingCost = $form->get('shippingCost');
        $shippingCostValue = $shippingCost->value;
        if ($shippingCostValue && !checkPattern($shippingCostValue, '^[-+]?[0-9]*[.]?[0-9]+$')) {
            $shippingCost->error($this->_('Wrong format! Please use decimal with a dot (.) as separator e.g. 4.5'));
        }

        $shippingGuaranteedDaysToDelivery = $form->get('shippingGuaranteedDaysToDelivery');
        $shippingGuaranteedDaysToDeliveryValue = $sanitizer->int($shippingGuaranteedDaysToDelivery->value);

        $productIds = $form->get('productIds');
        $productIdsValue = $productIds->value;
        
        // "Companion" field for productIds
        // (on Snipcart website this is handled by using 2 fields with the same name which is not valid in HTML forms)
        $productIds_2 = $form->get('productIds_2');
        $productIds_2Value = $productIds_2->value;

        // productIds will inherit the value from productIds_2 if necessary
        if ($productIds_2Value) {
            $productIdsValue = $productIds_2Value;
        } else {
            $productIds_2Value = $productIdsValue;
        }

        $maxDiscountsPerItem = $form->get('maxDiscountsPerItem');
        $maxDiscountsPerItemValue = $sanitizer->int($maxDiscountsPerItem->value);

        $categories = $form->get('categories');
        $categoriesValue = $categories->value;

        $numberOfItemsRequired = $form->get('numberOfItemsRequired');
        $numberOfItemsRequiredValue = $sanitizer->int($numberOfItemsRequired->value);
        if ($typeValue == 'GetFreeItems' && empty($numberOfItemsRequiredValue)) {
            $numberOfItemsRequired->error($this->_('Please enter a number of required items'));
        }

        $numberOfFreeItems = $form->get('numberOfFreeItems');
        $numberOfFreeItemsValue = $sanitizer->int($numberOfFreeItems->value);
        if ($typeValue == 'GetFreeItems' && empty($numberOfFreeItemsValue)) {
            $numberOfFreeItems->error($this->_('Please enter a number of free items'));
        }

        $trigger = $form->get('trigger');
        $triggerValue = $sanitizer->option($trigger->value, $this->getDiscountsTriggers(true));
        if (empty($triggerValue)) {
            $trigger->error($this->_('Trigger needs to be selected'));
        }

        $code = $form->get('code');
        $codeValue = $code->value;
        if ($triggerValue == 'Code' && empty($codeValue)) {
            $code->error($this->_('Please enter a discount code'));
        }

        $itemId = $form->get('itemId');
        $itemIdValue = $itemId->value;
        
        $totalToReach = $form->get('totalToReach');
        $totalToReachValue = $totalToReach->value;
        if ($totalToReachValue && !checkPattern($totalToReachValue, '^[-+]?[0-9]*[.]?[0-9]+$')) {
            $totalToReach->error($this->_('Wrong format! Please use decimal with a dot (.) as separator e.g. 19.99'));
        }

        $maxAmountToReach = $form->get('maxAmountToReach');
        $maxAmountToReachValue = $maxAmountToReach->value;
        if ($maxAmountToReachValue && !checkPattern($maxAmountToReachValue, '^[-+]?[0-9]*[.]?[0-9]+$')) {
            $maxAmountToReach->error($this->_('Wrong format! Please use decimal with a dot (.) as separator e.g. 199.99'));
        }

        $quantityInterval = $form->get('quantityInterval');
        $quantityIntervalValue = $quantityInterval->value;

        $quantityOfAProduct = $form->get('quantityOfAProduct');
        $quantityOfAProductValue = $sanitizer->int($quantityOfAProduct->value);
        if ($triggerValue == 'QuantityOfAProduct' && empty($quantityOfAProductValue)) {
            $quantityOfAProduct->error($this->_('Please enter a min. quantity'));
        }

        $maxQuantityOfAProduct = $form->get('maxQuantityOfAProduct');
        $maxQuantityOfAProductValue = $sanitizer->int($maxQuantityOfAProduct->value);
        if ($triggerValue == 'QuantityOfAProduct' && $quantityIntervalValue && empty($maxQuantityOfAProductValue)) {
            $maxQuantityOfAProduct->error($this->_('Please enter a max. quantity'));
        }

        $onlyOnSameProducts = $form->get('onlyOnSameProducts');
        $onlyOnSameProductsValue = $onlyOnSameProducts->value;

        $quantityOfProductIds = $form->get('quantityOfProductIds');
        $quantityOfProductIdsValue = $quantityOfProductIds->value;


        // The form is processed and populated but contains errors
        if ($form->getErrors()) return $form->render();

        //
        // Sanitize and prepare input for saving
        //

        $fieldValues = array(
            'name' => $sanitizer->sanitize($nameValue, 'text, entities'),
            
            'expires' => ($expiresValue ? $sanitizer->date($expiresValue, $expiresDateFormat) . 'T23:00:00Z' : ''),
            
            // already sanitized (integer or empty)
            'maxNumberOfUsages' => ($maxNumberOfUsagesValue ? $maxNumberOfUsagesValue : ''),
            
            'currency' => $sanitizer->sanitize($currencyValue, 'text, entities'),
            
             // checkbox
            'combinable' => ($combinableValue ? true : false),
            
             // already sanitized
            'type' => $typeValue,
            
            // don't santize to float because we always need . as decimal separator
            'amount' => $sanitizer->sanitize($amountValue, 'text, entities'),
            
            // don't santize to float because we always need . as decimal separator
            'rate' => $sanitizer->sanitize($rateValue, 'text, entities'),
            
            'alternatePrice' => $sanitizer->sanitize($alternatePriceValue, 'text, entities'),
            
            'shippingDescription' => $sanitizer->sanitize($shippingDescriptionValue, 'text, entities'),
            
            // don't santize to float because we always need . as decimal separator
            'shippingCost' => $sanitizer->sanitize($shippingCostValue, 'text, entities'),
            
            // already sanitized (integer or empty)
            'shippingGuaranteedDaysToDelivery' => ($shippingGuaranteedDaysToDeliveryValue ? $shippingGuaranteedDaysToDeliveryValue : ''),
            
            'productIds' => $sanitizer->text($productIdsValue),
            
            // already sanitized (integer or empty)
            'maxDiscountsPerItem' => ($maxDiscountsPerItemValue ? $maxDiscountsPerItemValue : ''),
            
            'categories' => $sanitizer->text($categoriesValue),
            
            // already sanitized (integer or empty)
            'numberOfItemsRequired' => ($numberOfItemsRequiredValue ? $numberOfItemsRequiredValue : ''),
            
            // already sanitized (integer or empty)
            'numberOfFreeItems' => ($numberOfFreeItemsValue ? $numberOfFreeItemsValue : ''),
            
            // already sanitized
            'trigger' => $triggerValue,
            
            'code' => $sanitizer->text($codeValue),
            
            'itemId' => $sanitizer->text($itemIdValue),
            
            // don't santize to float because we always need . as decimal separator
            'totalToReach' => $sanitizer->sanitize($totalToReachValue, 'text, entities'),
            
            // don't santize to float because we always need . as decimal separator
            'maxAmountToReach' => $sanitizer->sanitize($maxAmountToReachValue, 'text, entities'),
            
            // checkbox
            'quantityInterval' => ($quantityIntervalValue ? true : false),
            
            // already sanitized
            'quantityOfAProduct' => ($quantityOfAProductValue ? $quantityOfAProductValue : ''),
            
            // already sanitized
            'maxQuantityOfAProduct' => ($maxQuantityOfAProductValue ? $maxQuantityOfAProductValue : ''),
            
            // checkbox
            'onlyOnSameProducts' => ($onlyOnSameProductsValue ? true : false),
            
            'quantityOfProductIds' => $sanitizer->text($quantityOfProductIdsValue),
        );

        if ($mode == 'edit') {
            // Add "id" key
            $fieldValues['id'] = $sanitizer->sanitize($idValue, 'text, entities');
            $success = $this->_updateDiscount($fieldValues['id'], $fieldValues);
        } else {
            $success = $this->_createDiscount($fieldValues);
        }

        if ($success) {
            // Reset full discounts cache and redirect to itself to display updated values
            $this->wire('sniprest')->deleteDiscountCache();
            $redirectUrl = $this->currentUrl . '?modal=1';
            if ($ret) $redirectUrl .= '&ret=' . urlencode($ret);
            $session->redirect($redirectUrl);
        }

        return $form->render();
    }

    /**
     * Triggers an update for a Snipcart discount.
     *
     * @param string $id The discount id
     * @param array $options The discount values as array
     * @return boolean
     *
     */
    private function _updateDiscount($id, $options) {
        $sniprest = $this->wire('sniprest');

        $updated = false;
        $response = $sniprest->putDiscount($id, $options);
        if (
            $response[$id][WireHttpExtended::resultKeyHttpCode] != 200 &&
            $response[$id][WireHttpExtended::resultKeyHttpCode] != 201
        ) {
            $this->error(
                $this->_('The discount could not be updated! The following error occurred: ') .
                $response[$token][WireHttpExtended::resultKeyError]);
        } else {
            $this->message($this->_('The discount has been updated.'));
            $updated = true;
        }
        return $updated;
    }

    /**
     * Triggers the creation of a Snipcart discount.
     *
     * @param array $options The discount values as array
     * @return boolean
     *
     */
    private function _createDiscount($options) {
        $sniprest = $this->wire('sniprest');

        $created = false;
        $response = $sniprest->postDiscount($options);
        if (
            $response[WireHttpExtended::resultKeyHttpCode] != 200 &&
            $response[WireHttpExtended::resultKeyHttpCode] != 201
        ) {
            $this->error(
                $this->_('The discount could not be created! The following error occurred: ') .
                $response[$token][WireHttpExtended::resultKeyError]);
        } else {
            $this->message($this->_('The discount has been created.'));
            $created = true;
        }
        return $created;
    }

    /**
     * Triggers the deletion of a Snipcart discount.
     *
     * @param string $id The discount id
     * @return boolean
     *
     */
    private function _deleteDiscount($id) {
        $sniprest = $this->wire('sniprest');

        $deleted = false;
        $response = $sniprest->deleteDiscount($id);
        if (
            $response[$id][WireHttpExtended::resultKeyHttpCode] != 204 // DELETE http response code
        ) {
            $this->error(
                $this->_('The discount could not be deleted! The following error occurred: ') .
                $response[$id][WireHttpExtended::resultKeyError]);
        } else {
            $this->message($this->_('The discount has been deleted.'));
            $deleted = true;
        }
        return $deleted;
    }

    /**
     * Set JS config values for discount pages.
     *
     * @return void 
     *
     */
    private function _setDiscountJSConfigValues() {
        $this->wire('config')->js('discountActionStrings', array(
            'confirm_delete_discount' => $this->_('Do you want to delete this discount?'),
        ));
    }
}
