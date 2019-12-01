<?php namespace ProcessWire;

/**
 * Products trait - sections file for ProcessSnipWire.module.php.
 * (This file is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

trait Products {
    /**
     * The SnipWire Snipcart Products page.
     *
     * @return page markup
     *
     */
    public function ___executeProducts() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sanitizer = $this->wire('sanitizer');
        $session = $this->wire('session');
        $sniprest = $this->wire('sniprest');
        
        $this->browserTitle($this->_('Snipcart Products'));
        $this->headline($this->_('Snipcart Products'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }

        $forceRefresh = false;
        $limit = 20;
        $offset = ($input->pageNum - 1) * $limit;

        $currency = $this->_getCurrency();
        $action = $this->_getInputAction();
        if ($action == 'refresh') {
            $this->message(SnipREST::getMessagesText('cache_refreshed'));
            $forceRefresh = true;
        } elseif ($action == 'refresh_all') {
            $sniprest->resetFullCache();
            $this->message(SnipREST::getMessagesText('full_cache_refreshed'));
        }

        $userDefinedId = $sanitizer->text($input->userDefinedId);
        $keywords = $sanitizer->text($input->keywords);
        $archived = $sanitizer->bool($input->archived);
        $filter = array(
            'userDefinedId' => $userDefinedId ? $userDefinedId : '',
            'keywords' => $keywords ? $keywords : '',
            'archived' => $archived ? 'true' : 'false',
        );

        $defaultSelector = array(
            'offset' => $offset,
            'limit' => $limit,
        );

        $selector = array_merge($defaultSelector, $filter);

        $response = $sniprest->getProducts(
            '',
            $selector,
            SnipREST::cacheExpireDefault,
            $forceRefresh
        );

        $products = isset($response[SnipRest::resPathProducts][WireHttpExtended::resultKeyContent])
            ? $response[SnipRest::resPathProducts][WireHttpExtended::resultKeyContent]
            : array();

        $total = isset($products['totalItems']) ? $products['totalItems'] : 0;
        $items = isset($products['items']) ? $products['items'] : array();
        $count = count($items);

        // Pagination out of bound
        if (!$count && $input->pageNum > 1) {
            $session->redirect($this->processUrl);
            return '';
        }

        $out = $this->_buildProductsFilter($filter);

        $pageArray = $this->_prepareItemListerPagination($total, $count, $limit, $offset);
        $headline = $pageArray->getPaginationString(array(
            'label' => $this->_('Products'),
            'zeroLabel' => $this->_('No products found'), // 3.0.127+ only
        ));

        $pager = $modules->get('MarkupPagerNav');
        $pager->setBaseUrl($this->processUrl);
        $pager->setGetVars($filter);
        $pagination = $pager->render($pageArray);

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Products');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconProduct;
        $f->value = $this->_wrapItemListerHeadline($headline);
        $f->value .= $pagination;
        $f->value .= $this->_renderTableProducts($items, $currency);
        $f->value .= $pagination;
        $f->collapsed = Inputfield::collapsedNever;

        $out .= $f->render();

        $out .= $this->_renderActionButtons();

        return $this->_wrapDashboardOutput($out);
    }

    /**
     * The SnipWire Snipcart Product detail page.
     *
     * @return page markup
     *
     */
    public function ___executeProduct() {
        $modules = $this->wire('modules');
        $user = $this->wire('user');
        $config = $this->wire('config');
        $input = $this->wire('input');
        $sniprest = $this->wire('sniprest');
        
        $id = $input->urlSegment(2); // Get Snipcart product id
        
        $this->browserTitle($this->_('Snipcart Product'));
        $this->headline($this->_('Snipcart Product'));

        $this->breadcrumb($this->snipWireRootUrl, $this->_('SnipWire Dashboard'));
        $this->breadcrumb($this->snipWireRootUrl . 'products/', $this->_('Snipcart Products'));
        
        if (!$user->hasPermission('snipwire-dashboard')) {
            $this->error($this->_('You dont have permisson to use the SnipWire Dashboard - please contact your admin!'));
            return '';
        }
        
        $response = $sniprest->getProduct($id);
        $product = isset($response[SnipRest::resPathProducts . '/' . $id][WireHttpExtended::resultKeyContent])
            ? $response[SnipRest::resPathProducts . '/' . $id][WireHttpExtended::resultKeyContent]
            : array();

        /** @var InputfieldMarkup $f */
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Snipcart Product');
        $f->skipLabel = Inputfield::skipLabelHeader;
        $f->icon = self::iconProduct;
        $f->value = $this->_renderDetailProduct($product);
        $f->collapsed = Inputfield::collapsedNever;

        $out = $f->render();

        $out .= $this->_renderActionButtons();

        return $this->_wrapDashboardOutput($out);
    }

    /**
     * Build the products filter form.
     *
     * @param array $filter The current filter values
     * @return markup InputfieldForm
     *
     */
    private function _buildProductsFilter($filter) {
        $modules = $this->wire('modules');
        $config = $this->wire('config');

        $filterSettings = array(
            'form' => '#ProductsFilterForm',
        );

        // Hand over configuration to JS
        $config->js('filterSettings', $filterSettings);

        /** @var InputfieldForm $form */
        $form = $modules->get('InputfieldForm'); 
        $form->attr('id', 'ProductsFilterForm');
        $form->method = 'post';
        $form->action = $this->currentUrl;

            /** @var InputfieldFieldset $fsSnipWire */
            $fieldset = $modules->get('InputfieldFieldset');
            $fieldset->label = $this->_('Search for Products');
            $fieldset->icon = 'search';
            if (
                $filter['userDefinedId'] ||
                $filter['keywords'] ||
                ($filter['archived'] && $filter['archived'] != 'false')
            ) {
                $fieldset->collapsed = Inputfield::collapsedNo;
            } else {
                $fieldset->collapsed = Inputfield::collapsedYes;
            }

                /** @var InputfieldText $f */
                $f = $modules->get('InputfieldText');
                $f->attr('name', 'userDefinedId');
                $f->label = $this->_('SKU');
                $f->value = $filter['userDefinedId'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 33;

            $fieldset->add($f);

                /** @var InputfieldText $f */
                $f = $modules->get('InputfieldText');
                $f->attr('name', 'keywords');
                $f->label = $this->_('Keywords');
                $f->value = $filter['keywords'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 33;

            $fieldset->add($f);

                /** @var InputfieldSelect $f */
                $f = $modules->get('InputfieldSelect');
                $f->addClass('filter-form-select');
                $f->attr('name', 'archived'); 
                $f->label = $this->_('Status'); 
                $f->value = $filter['archived'];
                $f->collapsed = Inputfield::collapsedNever;
                $f->columnWidth = 34;
                $f->required = true;
                $f->addOption('false', $this->_('Not archived'));
                $f->addOption('true', $this->_('Archived'));

            $fieldset->add($f);

                $buttonsWrapper = $modules->get('InputfieldMarkup');
                $buttonsWrapper->markupText = $this->_getFilterFormButtons($this->processUrl);

            $fieldset->add($buttonsWrapper);

        $form->add($fieldset);

        return $form->render(); 
    }

    /**
     * Render the products table.
     *
     * @param array $items
     * @param string $currency Currency tag
     * @return markup MarkupAdminDataTable | custom html with `no items` display 
     *
     */
    private function _renderTableProducts($items, $currency) {
        $pages = $this->wire('pages');
        $modules = $this->wire('modules');
        $snipwireConfig = $this->snipwireConfig;

        if (!empty($items)) {
            $modules->get('JqueryTableSorter')->use('widgets');

            /** @var MarkupAdminDataTable $table */
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->setID('ProductsTable');
            $table->setClass('ItemLister');
            $table->setSortable(false);
            $table->setResizable(true);
            $table->setResponsive(true);
            $table->headerRow(array(
                $this->_('SKU'),
                $this->_('Thumb'),
                $this->_('Name'),
                $this->_('Stock'),
                $this->_('Price'),
                $this->_('# Sales'),
                //$this->_('Sales'), // not usable at the moment as Snipcart doesn't support multi currency for statistics
                $this->_('Last modified'),
                '&nbsp;',
            ));

            foreach ($items as $item) {
                $panelLink =
                '<a href="' . $this->snipWireRootUrl . 'product/' . $item['id'] . '"
                    class="pw-panel pw-panel-links"
                    data-panel-width="75%">' .
                        wireIconMarkup(self::iconProduct, 'fa-right-margin') . $item['userDefinedId'] .
                '</a>';
                $thumb = $this->getProductImg($item['image']);

                $product = $pages->findOne('snipcart_item_id="' . $item['userDefinedId'] . '"');
                if ($product->url) {
                    if ($product->editable()) {
                        $editLink =
                        '<a href="' . $product->editUrl . '"
                            class="pw-panel pw-panel-links"
                            data-panel-width="75%">' .
                                wireIconMarkup('pencil-square-o') .
                        '</a>';
                    } else {
                        $editLink =
                        '<span
                            class="pw-tooltip"
                            title="' . $this->_('Product not editable') .'">' .
                                wireIconMarkup('pencil-square-o') .
                        '</span>';
                    }
                } else {
                    // If for some reason the Snipcart "userDefinedId" no longer matches the ID of the ProcessWire field "snipcart_item_id"
                    $editLink =
                    '<span
                        class="pw-tooltip"
                        title="' . $this->_('No matching ProcessWire page found.') .'">' . 
                            wireIconMarkup('exclamation-triangle') .
                    '</span>';
                }

                $stock = isset($item['stock'])
                    ? $item['stock']
                    : '-';

                $table->row(array(
                    $panelLink,
                    ($thumb ? $thumb : '-'),
                    $item['name'],
                    $stock,
                    CurrencyFormat::format($item['price'], $currency),
                    $item['statistics']['numberOfSales'],
                    //CurrencyFormat::format($item['statistics']['totalSales'], 'usd'),  // not usable at the moment as Snipcart doesn't support multi currency for statistics
                    wireDate('Y-m-d H:i:s', $item['modificationDate']),
                    $editLink,
                ));
            }
            $out = $table->render();
        } else {
            $out =
            '<div class="snipwire-no-items">' . 
                $this->_('No products found') .
            '</div>';
        }
        return '<div class="ItemListerTable">' . $out . '</div>';
    }

    /**
     * Render the product detail view.
     *
     * @param array $item
     * @return markup 
     *
     */
    private function _renderDetailProduct($item) {
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
