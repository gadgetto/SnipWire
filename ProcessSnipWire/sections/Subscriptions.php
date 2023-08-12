<?php
namespace SnipWire\ProcessSnipWire\Sections;

/**
 * Subscriptions trait - sections file for ProcessSnipWire.module.php.
 * (This file is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2023 by Martin Gartner
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
        
        $this->_setSubscriptionJSConfigValues();
        
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
        $filter = [
            'status' => $status ? $status : 'All',
            'userDefinedPlanName' => $userDefinedPlanName ? $userDefinedPlanName : '',
            'userDefinedCustomerNameOrEmail' => $userDefinedCustomerNameOrEmail ? $userDefinedCustomerNameOrEmail : '',
        ];

        $defaultSelector = [
            'offset' => $offset,
            'limit' => $limit,
        ];
 
        $selector = array_merge($defaultSelector, $filter);

        $response = $sniprest->getSubscriptions(
            '',
            $selector,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );

        $dataKey = SnipREST::resPathSubscriptions;
        $subscriptions = isset($response[$dataKey][WireHttpExtended::resultKeyContent])
            ? $response[$dataKey][WireHttpExtended::resultKeyContent]
            : [];

        $total = isset($subscriptions['totalItems']) ? $subscriptions['totalItems'] : 0;
        $items = isset($subscriptions['items']) ? $subscriptions['items'] : [];
        $count = count($items);
        
        // Pagination out of bound
        if (!$count && $input->pageNum > 1) {
            $session->redirect($this->processUrl);
            return '';
        }

        $out = $this->_buildSubscriptionsFilter($filter);

        $pageArray = $this->_prepareItemListerPagination($total, $count, $limit, $offset);
        $headline = $pageArray->getPaginationString([
            'label' => $this->_('Subscriptions'),
            'zeroLabel' => $this->_('No subscriptions found'), // 3.0.127+ only
        ]);

        $pager = $modules->get('MarkupPagerNav');
        $pager->setBaseUrl($this->processUrl);
        $pager->setGetVars($filter);
        $pagination = $pager->render($pageArray);

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Subscriptions');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconSubscription;
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
        
        $this->browserTitle($this->_('Snipcart Subscription'));
        $this->headline($this->_('Snipcart Subscription'));
        
        $this->breadcrumb($this->snipWireRootUrl, $this->_('SnipWire Dashboard'));
        $this->breadcrumb($this->snipWireRootUrl . 'subscriptions/', $this->_('Snipcart Subscriptions'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }
        
        $this->_setSubscriptionJSConfigValues();
        
        // Determine if request comes from within another page in a modal panel.
        // In this case there will be an input param "ret" (can be GET or POST) which holds the return URL.
        $ret = urldecode($input->ret);
        
        $id = $input->urlSegment(2); // Get Snipcart subscription ID
        $forceRefresh = false;
        
        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        } elseif ($action == 'refresh_all') {
            $sniprest->deleteFullCache();
            $this->message(SnipREST::getMessagesText('full_cache_refreshed'));
        } elseif ($action == 'pause_subscription') {
            $this->_pauseSubscription($id);
        } elseif ($action == 'resume_subscription') {
            $this->_resumeSubscription($id);
        } elseif ($action == 'cancel_subscription') {
            $this->_cancelSubscription($id);
        }
        
        $response = $sniprest->getSubscription(
            $id,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );
        $dataKey = SnipREST::resPathSubscriptions . '/' . $id;
        $subscription = isset($response[$dataKey][WireHttpExtended::resultKeyContent])
            ? $response[$dataKey][WireHttpExtended::resultKeyContent]
            : [];
        unset($response, $dataKey);
        
        $response = $sniprest->getSubscriptionInvoices(
            $id,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );
        $dataKey = \ProcessWire\wirePopulateStringTags(
            SnipREST::resPathSubscriptionsInvoices,
            ['id' => $id]
        );
        $subscriptionInvoices = isset($response[$dataKey][WireHttpExtended::resultKeyContent])
            ? $response[$dataKey][WireHttpExtended::resultKeyContent]
            : [];
        unset($response, $dataKey);
        
        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Subscription');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconSubscription;
        $f->value = $this->_renderDetailSubscription($subscription, $subscriptionInvoices, $ret);
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

        $filterSettings = [
            'form' => '#SubscriptionsFilterForm',
        ];

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
                $f->addOptions($this->getSubscriptionStatuses());

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
            $table->headerRow([
                $this->_('Plan'),
                $this->_('Subscription Date'),
                $this->_('Subscriber'),
                $this->_('Interval'),
                $this->_('Price'),
                $this->_('Total Spent'),
                $this->_('Status'),
                '&nbsp;',
            ]);
            foreach ($items as $item) {
                $panelLink =
                '<a href="' . $this->snipWireRootUrl . 'subscription/' . $item['id'] . '"
                    class="pw-panel pw-panel-links"
                    data-panel-width="85%">' .
                        \ProcessWire\wireIconMarkup(self::iconSubscription, 'fa-right-margin') . $item['name'] .
                '</a>';
                $creationDate = '<span class="tooltip" title="';
                $creationDate .= \ProcessWire\wireDate('Y-m-d H:i:s', $item['creationDate']);
                $creationDate .= '">';
                $creationDate .= \ProcessWire\wireDate('relative', $item['creationDate']);
                $creationDate .= '</span>';
                $subscriber =
                '<a href="' . $this->snipWireRootUrl . 'customer/' . $item['user']['id'] . '"
                    class="pw-panel pw-panel-links"
                    data-panel-width="85%">' .
                        $item['user']['email'] .
                '</a>';
                $interval = $item['schedule']['intervalCount'] . '&nbsp;/&nbsp;' . $item['schedule']['interval'];
                $amount =
                '<strong class="price-field">' .
                    CurrencyFormat::format($item['amount'], $item['currency']) .
                '</strong>';
                $totalSpent =
                '<strong class="price-field">' .
                    CurrencyFormat::format($item['totalSpent'], $item['currency']) .
                '</strong>';
                if ($item['cancelledOn']) {
                    $status = '<span class="warning-color-dark">' . $this->getSubscriptionStatus('canceled') . '</span>';
                } elseif ($item['pausedOn']) {
                    $status = '<span class="info-color-dark">' . $this->getSubscriptionStatus('paused') . '</span>';
                } else {
                    $status = $this->getSubscriptionStatus('active');
                }
                $initialOrder =
                '<a href="' . $this->snipWireRootUrl . 'order/' . $item['initialOrderToken'] . '"
                    class="pw-panel pw-panel-links pw-tooltip"
                    title="' . $this->_('Open initial oder') .'"
                    data-panel-width="85%">' .
                        \ProcessWire\wireIconMarkup(self::iconOrder, 'fa-right-margin') .
                '</a>';
                $table->row([
                    $panelLink,
                    $creationDate,
                    $subscriber,
                    $interval,
                    $amount,
                    $totalSpent,
                    $status,
                    $initialOrder,
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

    /**
     * Render the subscription detail view.
     *
     * @param array $item
     * @param array $invoices The invoices of this subscription
     * @param string $ret A return URL (optional)
     * @return markup 
     *
     */
    private function _renderDetailSubscription($item, $invoices, $ret = '') {
        $modules = $this->wire('modules');
        $sanitizer = $this->wire('sanitizer');

        if (empty($item)) {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No subscription selected') .
            '</div>';
            return $out;
        }
        
        $id = $item['id'];
        $invoicesCount = count($invoices);   
        
        $out = '';
        
        if ($ret) {
            $out .=
            '<div class="ItemDetailBackLink">' . 
                '<a href="' .$ret . '?modal=1"
                    class="pw-panel-links">' .
                        \ProcessWire\wireIconMarkup('times-circle', 'fa-right-margin') .
                        $this->_('Close subscription details') .
                '</a>' .
            '</div>';
        }
        
        $out .=
        '<div class="ItemDetailHeader">' .
            '<h2 class="ItemDetailTitle">' .
                \ProcessWire\wireIconMarkup(self::iconSubscription, 'fa-right-margin') .
                $this->_('Subscription') . ': ' .
                $item['name'] .
            '</h2>' .
            '<div class="ItemDetailActionButtons">' .
                $this->_getSubscriptionDetailActionButtons($item, $ret) .
            '</div>' .
        '</div>';
        
        /** @var InputfieldForm $wrapper */
        $wrapper = $modules->get('InputfieldForm');
            
            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Plan Info');
            $f->entityEncodeLabel = false;
            if ($item['cancelledOn']) {
                $f->label .= ' <span class="snipwire-badge snipwire-badge-warning">' . $this->_('Cancelled') . '</span>';
            } elseif ($item['pausedOn']) {
                $f->label .= ' <span class="snipwire-badge snipwire-badge-info">' . $this->_('Paused') . '</span>';
            }
            $f->icon = self::iconSubscription;
            $f->value = $this->_renderPlanInfo($item);
            $f->columnWidth = 50;
            
        $wrapper->add($f);
        
            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Subscriber Info');
            $f->icon = self::iconCustomer;
            $f->value = $this->_renderSubscriberInfo($item);
            $f->columnWidth = 50;
            
        $wrapper->add($f);
                
        $out .= $wrapper->render();
        
        /** @var InputfieldForm $wrapper */
        $wrapper = $modules->get('InputfieldForm');
            
            $notificationsBadge = 
            ' <span class="snipwire-badge snipwire-badge-info">' .
                sprintf($this->_n("%d invoice", "%d invoices", $invoicesCount), $invoicesCount) .
            '</span>';
            
            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->entityEncodeLabel = false;
            $f->label = $this->_('Invoices');
            $f->label .= $notificationsBadge;
            $f->icon = self::iconOrder;
            $f->value = $this->_renderTableSubscriptionInvoices($invoices, $id);
            
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
                $f->value .= '<pre>' . $sanitizer->entities(print_r($invoices, true)) . '</pre>';
                
            $wrapper->add($f);
            
            $out .= $wrapper->render();
        }
        
        return $out;
    }
    
    /**
     * Render subscription detail action buttons.
     *
     * (Currently uses custom button markup as there is a PW bug which 
     * triggers href targets twice + we need to attach JavaScript events on button click)
     *
     * @param array $item The subscription item
     * @param string $ret A return URL (optional)
     * @return buttons markup 
     *
     */
    private function _getSubscriptionDetailActionButtons($item, $ret = '') {
        $pauseUrl = $this->currentUrl . '?action=pause_subscription&modal=1';
        if ($ret) $pauseUrl .= '&ret=' . urlencode($ret);
        $pauseButton =
        '<a href="' . $pauseUrl . '"
            class="PauseSubscriptionButton ui-button ui-widget ui-corner-all ui-state-default ui-priority-secondary"
            role="button">' .
                '<span class="ui-button-text">' .
                    \ProcessWire\wireIconMarkup('pause') . ' ' . $this->_('Pause') .
                '</span>' .
        '</a>';
        
        $resumeUrl = $this->currentUrl . '?action=resume_subscription&modal=1';
        if ($ret) $resumeUrl .= '&ret=' . urlencode($ret);
        $resumeButton =
        '<a href="' . $resumeUrl . '"
            class="ResumeSubscriptionButton ui-button ui-widget ui-corner-all ui-state-default ui-priority-secondary"
            role="button">' .
                '<span class="ui-button-text">' .
                    \ProcessWire\wireIconMarkup('play') . ' ' . $this->_('Resume') .
                '</span>' .
        '</a>';
        
        $cancelUrl = $this->currentUrl . '?action=cancel_subscription&modal=1';
        if ($ret) $cancelUrl .= '&ret=' . urlencode($ret);
        $cancelButton =
        '<a href="' . $cancelUrl . '"
            class="CancelSubscriptionButton ui-button ui-widget ui-corner-all ui-state-default ui-priority-danger"
            role="button">' .
                '<span class="ui-button-text">' .
                    \ProcessWire\wireIconMarkup('ban') . ' ' . $this->_('Cancel') .
                '</span>' .
        '</a>';
        
        if ($item['cancelledOn']) {
            // pause & resume not possible!
            $out = '';
        } elseif ($item['pausedOn']) {
            $out = $resumeButton . $cancelButton;
        } else {
            $out = $pauseButton . $cancelButton;
        }
        
        return $out;
    }
    
    /**
     * Render the subscription plan info block.
     *
     * @param array $item
     * @return markup 
     *
     */
    private function _renderPlanInfo($item) {
        $infoCaptions = [
            'planName' => $this->_('Plan name'),
            'interval' => $this->_('Interval'),
            'intervalCount' => $this->_('Interval count'),
            'trialPeriodInDays' => $this->_('Trial period'),
            'creationDate' => $this->_('Subscription Date'),
            'startsOn' => $this->_('Starts on'),
        ];
        
        if ($item['cancelledOn']) {
            $infoCaptions['cancelledOn'] = $this->_('Cancelled on');
        } elseif ($item['pausedOn']) {
            $infoCaptions['pausedOn'] = $this->_('Paused on');
        }
        
        $planInfo = [];
        $planInfo['planName'] = $item['name'];
        $planInfo['interval'] = $item['schedule']['interval'];
        $planInfo['intervalCount'] = $item['schedule']['intervalCount'];
        $trialPeriodInDays = $item['schedule']['trialPeriodInDays'];
        if (!empty($trialPeriodInDays)) {
            $planInfo['trialPeriodInDays'] = sprintf($this->_n("%d day", "%d days", $trialPeriodInDays), $trialPeriodInDays);
        } else {
            $planInfo['trialPeriodInDays'] = $this->_('No trial');
        }
        $planInfo['creationDate'] = \ProcessWire\wireDate('Y-m-d H:i:s', $item['creationDate']);
        $planInfo['startsOn'] = \ProcessWire\wireDate('Y-m-d H:i:s', $item['schedule']['startsOn']);
        $planInfo['pausedOn'] = \ProcessWire\wireDate('Y-m-d H:i:s', $item['pausedOn']);
        $planInfo['cancelledOn'] = \ProcessWire\wireDate('Y-m-d H:i:s', $item['cancelledOn']);
        
        $data = [];
        foreach ($infoCaptions as $key => $caption) {
            $data[$caption] = !empty($planInfo[$key]) ? $planInfo[$key] : '-';
        }
        
        return $this->renderDataSheet($data);
    }
    
    /**
     * Render the subscriber info block.
     *
     * @param array $item
     * @return markup 
     *
     */
    private function _renderSubscriberInfo($item) {
        $infoCaptions = [
            'customer' => $this->_('Customer'),
            'email' => $this->_('Email'),
            'country' => $this->_('Country'),
            'creationDate' => $this->_('Created on'),
        ];
        
        $subscriber = $item['user'];
        $subscriberInfo = [];
        
        $subscriberInfo['customer'] = $subscriber['billingAddressFirstName'] . ' ' . $subscriber['billingAddressName'];
        $subscriberInfo['email'] =
        '<a href="mailto:' . $subscriber['email'] . '"
            class="pw-tooltip"
            title="' . $this->_('Send email to customer') .'">' .
                $subscriber['email'] .
        '</a>';
        $subscriberInfo['country'] = Countries::getCountry($subscriber['billingAddressCountry']);
        $subscriberInfo['creationDate'] = \ProcessWire\wireDate('Y-m-d H:i:s', $subscriber['creationDate']);
        
        $data = [];
        foreach ($infoCaptions as $key => $caption) {
            $data[$caption] = !empty($subscriberInfo[$key]) ? $subscriberInfo[$key] : '-';
        }
        
        return $this->renderDataSheet($data);
    }
    
    /**
     * Render the subscription invoices table.
     *
     * @param array $invoices
     * @param string $subscriptionId The subscription id
     * @return markup MarkupAdminDataTable 
     *
     */
    private function _renderTableSubscriptionInvoices($invoices, $subscriptionId) {
        $modules = $this->wire('modules');
        
        /** @var MarkupAdminDataTable $table */
        $table = $modules->get('MarkupAdminDataTable');
        $table->setEncodeEntities(false);
        $table->setID('SubscriptionInvoicesTable');
        $table->setClass('ItemListerTable');
        $table->setSortable(false);
        $table->setResizable(false);
        $table->setResponsive(true);
        $table->headerRow([
            $this->_('Invoice #'),
            $this->_('Placed on'),
            $this->_('Total'),
            $this->_('Status'),
        ]);
        
        foreach ($invoices as $invoice) {
            // Need to attach a return URL to be able to stay in modal panel when order detail is opened
            $ret = urlencode($this->snipWireRootUrl . 'subscription/' . $subscriptionId);
            $invoiceNumber =
            '<a href="' . $this->snipWireRootUrl . 'order/' . $invoice['orderToken'] . '?modal=1&ret=' . $ret . '"
                class="pw-panel-links">' .
                    \ProcessWire\wireIconMarkup(self::iconOrder, 'fa-right-margin') . $invoice['number'] .
            '</a>';
            $placedOn = \ProcessWire\wireDate('Y-m-d H:i:s', $invoice['creationDate']);
            $total = CurrencyFormat::format($invoice['total'], $invoice['subscription']['currency']);
            $status = $invoice['paid'] ? $this->getPaymentStatus('Paid') : $this->getPaymentStatus('Open');

            $table->row([
                $invoiceNumber,
                $placedOn,
                $total,
                $status,
            ]);
        }
        
        $out = $table->render();
        
        return $out;
    }
    
    /**
     * Pause an active subscription.
     *
     * @param string $id The subscription id
     * @return void 
     *
     */
    private function _pauseSubscription($id) {
        $sniprest = $this->wire('sniprest');

        if (empty($id)) return;

        $response = $sniprest->postSubscriptionPause($id);
        $dataKey = $id;
        if (
            $response[$dataKey][WireHttpExtended::resultKeyHttpCode] != 200 &&
            $response[$dataKey][WireHttpExtended::resultKeyHttpCode] != 201
        ) {
            $this->error(
                $this->_('The subscription could not be paused! The following error occurred: ') .
                $response[$dataKey][WireHttpExtended::resultKeyError]);
        } else {
            $sniprest->deleteSubscriptionCache();
            $this->message($this->_('The subscription has been paused.'));
        }
    }
    
    /**
     * Resume a paused subscription.
     *
     * @param string $id The subscription id
     * @return void 
     *
     */
    private function _resumeSubscription($id) {
        $sniprest = $this->wire('sniprest');
        
        if (empty($id)) return;
        
        $response = $sniprest->postSubscriptionResume($id);
        $dataKey = $id;
        if (
            $response[$dataKey][WireHttpExtended::resultKeyHttpCode] != 200 &&
            $response[$dataKey][WireHttpExtended::resultKeyHttpCode] != 201
        ) {
            $this->error(
                $this->_('The subscription could not be resumed! The following error occurred: ') .
                $response[$dataKey][WireHttpExtended::resultKeyError]);
        } else {
            $sniprest->deleteSubscriptionCache();
            $this->message($this->_('The subscription has been resumed.'));
        }
    }
    
    /**
     * Cancel a subscription.
     *
     * @param string $id The subscription id
     * @return void 
     *
     */
    private function _cancelSubscription($id) {
        $sniprest = $this->wire('sniprest');
        
        if (empty($id)) return;
        
        $response = $sniprest->deleteSubscription($id);
        $dataKey = $id;
        if (
            $response[$dataKey][WireHttpExtended::resultKeyHttpCode] != 200 &&
            $response[$dataKey][WireHttpExtended::resultKeyHttpCode] != 201
        ) {
            $this->error(
                $this->_('The subscription could not be cancelled! The following error occurred: ') .
                $response[$dataKey][WireHttpExtended::resultKeyError]);
        } else {
            $sniprest->deleteSubscriptionCache();
            $this->message($this->_('The subscription has been cancelled.'));
        }
    }
    
    /**
     * Set JS config values for subscription pages.
     *
     * @return void 
     *
     */
    private function _setSubscriptionJSConfigValues() {
        $this->wire('config')->js('subscriptionActionStrings', [
            'confirm_pause_subscription' => $this->_('Do you want to pause this subscription?'),
            'confirm_resume_subscription' => $this->_('Do you want to resume this subscription?'),
            'confirm_cancel_subscription' => $this->_("Do you want to cancel this subscription?\nThis can not be undone!"),
        ]);
    }
}
