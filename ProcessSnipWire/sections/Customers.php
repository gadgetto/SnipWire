<?php namespace ProcessWire;

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
        }

        $status = $sanitizer->text($input->status);
        $email = $sanitizer->text($input->email);
        $name = $sanitizer->text($input->name);
        $filter = array(
            'status' => $status ? $status : 'All',
            'email' => $email ? $email : '',
            'name' => $name ? $name : '',
        );

        $defaultSelector = array(
            'offset' => $offset,
            'limit' => $limit,
        );

        $selector = array_merge($defaultSelector, $filter);

        $response = $sniprest->getCustomers(
            '',
            $selector,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );

        $customers = isset($response[SnipRest::resourcePathCustomers][WireHttpExtended::resultKeyContent])
            ? $response[SnipRest::resourcePathCustomers][WireHttpExtended::resultKeyContent]
            : array();
        
        $total = isset($customers['totalItems']) ? $customers['totalItems'] : 0;
        $items = isset($customers['items']) ? $customers['items'] : array();
        $count = count($items);

        // Pagination out of bound
        if (!$count && $input->pageNum > 1) {
            $session->redirect($this->processUrl);
            return '';
        }

        $out = $this->_buildCustomersFilter($filter);

        $pageArray = $this->_prepareItemListerPagination($total, $count, $limit, $offset);
        $headline = $pageArray->getPaginationString(array(
            'label' => $this->_('Customers'),
            'zeroLabel' => $this->_('No customers found'), // 3.0.127+ only
        ));

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
        
        $id = $input->urlSegment(2); // Get Snipcart customer id
        
        $this->browserTitle($this->_('Snipcart Customer'));
        $this->headline($this->_('Snipcart Customer'));

        $this->breadcrumb($this->snipWireRootUrl, $this->_('SnipWire Dashboard'));
        $this->breadcrumb($this->snipWireRootUrl . 'customers/', $this->_('Snipcart Customers'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }
        
        $response = $sniprest->getCustomer($id);
        $customer = isset($response[SnipRest::resourcePathCustomers . '/' . $id][WireHttpExtended::resultKeyContent])
            ? $response[SnipRest::resourcePathCustomers . '/' . $id][WireHttpExtended::resultKeyContent]
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
     * Build the customers filter form.
     *
     * @param array $filter The current filter values
     * @return markup InputfieldForm
     *
     */
    private function _buildCustomersFilter($filter) {
        $modules = $this->wire('modules');
        $config = $this->wire('config');

        $statuses = array(
            'All' =>  $this->_('All Customers'),
            'Confirmed' => $this->_('Confirmed'),
            'Unconfirmed' => $this->_('Unconfirmed'),
        );

        $filterSettings = array(
            'form' => '#CustomersFilterForm',
        );

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
                $f->attr('name', 'status');
                $f->label = $this->_('Status');
                $f->value = $filter['status'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 33;
                $f->required = true;
                $f->addOptions($statuses);

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
                $buttonsWrapper->contentClass = 'ItemsFilterButtonWrapper';
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
                $panelLink =
                '<a href="' . $this->snipWireRootUrl . 'customer/' . $item['id'] . '"
                    class="pw-panel"
                    data-panel-width="70%">' .
                        wireIconMarkup(self::iconCustomer, 'fa-right-margin') . $item['billingAddress']['fullName'] .
                '</a>';
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
}
