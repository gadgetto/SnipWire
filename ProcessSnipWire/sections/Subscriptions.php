<?php namespace ProcessWire;

/**
 * Subscriptions trait - sections file for ProcessSnipWire.module.php.
 * (This file is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

trait Subscriptions {
    /**
     * The SnipWire Snipcart Subscriptions page.
     *
     * @return page markup
     *
     */
    public function ___executeSubscriptions() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sanitizer = $this->wire('sanitizer');
        $session = $this->wire('session');
        $sniprest = $this->wire('sniprest');

        $this->browserTitle($this->_('Snipcart Subscriptions'));
        $this->headline($this->_('Snipcart Subscriptions'));

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
        $userDefinedPlanName = $sanitizer->text($input->userDefinedPlanName);
        $userDefinedCustomerNameOrEmail = $sanitizer->text($input->userDefinedCustomerNameOrEmail);
        $filter = array(
            'status' => $status ? $status : 'All',
            'userDefinedPlanName' => $userDefinedPlanName ? $userDefinedPlanName : '',
            'userDefinedCustomerNameOrEmail' => $userDefinedCustomerNameOrEmail ? $userDefinedCustomerNameOrEmail : '',
        );

        $defaultSelector = array(
            'offset' => $offset,
            'limit' => $limit,
        );
 
        $selector = array_merge($defaultSelector, $filter);

        $response = $sniprest->getSubscriptions(
            '',
            $selector,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );

        $subscriptions = isset($response[SnipRest::resPathSubscriptions][WireHttpExtended::resultKeyContent])
            ? $response[SnipRest::resPathSubscriptions][WireHttpExtended::resultKeyContent]
            : array();

        $total = isset($subscriptions['totalItems']) ? $subscriptions['totalItems'] : 0;
        $items = isset($subscriptions['items']) ? $subscriptions['items'] : array();
        $count = count($items);
        
        // Pagination out of bound
        if (!$count && $input->pageNum > 1) {
            $session->redirect($this->processUrl);
            return '';
        }

        $out = $this->_buildSubscriptionsFilter($filter);

        $pageArray = $this->_prepareItemListerPagination($total, $count, $limit, $offset);
        $headline = $pageArray->getPaginationString(array(
            'label' => $this->_('Subscriptions'),
            'zeroLabel' => $this->_('No subscriptions found'), // 3.0.127+ only
        ));

        $pager = $modules->get('MarkupPagerNav');
        $pager->setBaseUrl($this->processUrl);
        $pager->setGetVars($filter);
        $pagination = $pager->render($pageArray);

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Subscriptions');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconOrder;
        $f->value = $this->_wrapItemListerHeadline($headline);
        $f->value .= $pagination;
        $f->value .= $this->_renderTableSubscriptions($items);
        $f->value .= $pagination;
        $f->collapsed = Inputfield::collapsedNever;

        $out .= $f->render();

        $out .= $this->_renderActionButtons();

        return $this->_wrapDashboardOutput($out);
    }

    /**
     * The SnipWire Snipcart Subscription detail page.
     *
     * @return page markup
     *
     */
    public function ___executeSubscription() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sniprest = $this->wire('sniprest');
        
        $id = $input->urlSegment(2); // Get Snipcart subscription ID
        
        $this->browserTitle($this->_('Snipcart Subscription'));
        $this->headline($this->_('Snipcart Subscription'));

        $this->breadcrumb($this->snipWireRootUrl, $this->_('SnipWire Dashboard'));
        $this->breadcrumb($this->snipWireRootUrl . 'subscriptions/', $this->_('Snipcart Subscriptions'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

        $forceRefresh = false;
        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        } elseif ($action == 'refresh_all') {
            $sniprest->deleteFullCache();
            $this->message(SnipREST::getMessagesText('full_cache_refreshed'));
        }

        $response = $sniprest->getSubscription(
            $id,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );
        $subscription = isset($response[SnipRest::resPathSubscriptions . '/' . $id][WireHttpExtended::resultKeyContent])
            ? $response[SnipRest::resPathSubscriptions . '/' . $id][WireHttpExtended::resultKeyContent]
            : array();

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Subscription');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconOrder;
        $f->value = $this->_renderDetailSubscription($subscription);
        $f->collapsed = Inputfield::collapsedNever;

        $out = $f->render();

        $out .= $this->_renderActionButtons();

        return $this->_wrapDashboardOutput($out);
    }

    /**
     * Build the subscriptions filter form.
     *
     * @param array $filter The current filter values
     * @return markup InputfieldForm
     *
     */
    private function _buildSubscriptionsFilter($filter) {
        $modules = $this->wire('modules');
        $config = $this->wire('config');

        $statuses = array(
            'All' =>  $this->_('All Subscriptions'),
            'Active' => $this->_('Active'),
            'Paused' => $this->_('Paused'),
            'Canceled' => $this->_('Cancelled'), // 'Canceled' with a single "l" is not a typo here!
        );
        
        $filterSettings = array(
            'form' => '#SubscriptionsFilterForm',
        );

        // Hand over configuration to JS
        $config->js('filterSettings', $filterSettings);

        /** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm'); 
        $form->attr('id', 'SubscriptionsFilterForm');
        $form->method = 'post';
        $form->action = $this->currentUrl;

            /** @var InputfieldFieldset $fsSnipWire */
            $fieldset = $modules->get('InputfieldFieldset');
            $fieldset->label = $this->_('Search for Subscriptions');
            $fieldset->icon = 'search';
            if (
                ($filter['status'] && $filter['status'] != 'All') ||
                $filter['userDefinedPlanName'] ||
                $filter['userDefinedCustomerNameOrEmail']
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
                $f->addOptions($statuses);

            $fieldset->add($f);

                /** @var InputfieldText $f */
                $f = $modules->get('InputfieldText');
                $f->attr('name', 'userDefinedPlanName');
                $f->label = $this->_('Plan Name');
                $f->value = $filter['userDefinedPlanName'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 33;

            $fieldset->add($f);

                /** @var InputfieldText $f */
                $f = $modules->get('InputfieldText');
                $f->attr('name', 'userDefinedCustomerNameOrEmail');
                $f->label = $this->_('Subscriber');
                $f->value = $filter['userDefinedCustomerNameOrEmail'];
                $f->placeholder = $this->_('First name, last name or email');
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
     * Render the subscriptions table.
     *
     * @param array $items
     * @return markup MarkupAdminDataTable | custom html with `no items` display 
     *
     */
    private function _renderTableSubscriptions($items) {
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
            $table->headerRow(array(
                $this->_('Plan'),
                $this->_('Creation Date'),
                $this->_('Subscriber'),
                $this->_('Status'),
            ));
            foreach ($items as $item) {
                $panelLink =
                '<a href="' . $this->snipWireRootUrl . 'subscription/' . $item['id'] . '"
                    class="pw-panel"
                    data-panel-width="85%">' .
                        wireIconMarkup(self::iconSubscription, 'fa-right-margin') . $item['name'] .
                '</a>';
                $panelLink2 =
                '<a href="' . $this->snipWireRootUrl . 'customer/' . $item['user']['id'] . '"
                    class="pw-panel"
                    data-panel-width="85%">' .
                        $item['user']['email'] .
                '</a>';
                $creationDate = '<span class="tooltip" title="';
                $creationDate .= wireDate('Y-m-d H:i:s', $item['creationDate']);
                $creationDate .= '">';
                $creationDate .= wireDate('relative', $item['creationDate']);
                $creationDate .= '</span>';

                $table->row(array(
                    $panelLink,
                    $creationDate,
                    $panelLink2,
                    $item['status'],
                    //$item['paymentStatus'],
                    //CurrencyFormat::format($item['total'], $item['currency']),
                ));
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

    /**
     * Render the subscription detail view.
     *
     * @param array $item
     * @return markup 
     *
     */
    private function _renderDetailSubscription($item) {
        $modules = $this->wire('modules');

        if (!empty($item)) {


            $out = '<pre>' . print_r($item, true) . '</pre>';


        } else {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No subscription selected') .
            '</div>';
        }

        return $out;
    }
}
