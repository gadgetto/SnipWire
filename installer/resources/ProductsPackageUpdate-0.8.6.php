<?php
namespace ProcessWire;

/**
 * Returns products package resources for SnipWire (required).
 * (This file is part of the SnipWire package)
 *
 * 'templates' - special array keys:
 *
 *  - _allowedChildTemplates: comma separated list of allowed child template names
 *
 * 'fields' - special array keys:
 *
 *  - _addToTemplates: comma separated list of template names the field should be added to
 *  - _templateFieldOptions: field options in template context
 *  - _configureOnly: field will not be installed - only configured (add to template, configure in template context, ...)
 *
 * 'pages' - special array keys:
 *
 *  - _uninstall: what should happen when the page is uninstalled (possible values "trash", "delete", "no")
 *
 */

$resources = array(

    // Additional Snipcart product fields since version 0.8.6:
    // @see: https://docs.snipcart.com/v3/setup/products
    // @see: /MarkupSnipWire/MarkupSnipWire.module.php for product attributes definitions

    'fields' => array(
        'snipcart_item_payment_interval' => array(
            'name' => 'snipcart_item_payment_interval',
            'type' => 'FieldtypeOptions',
            'inputfield' => 'InputfieldSelect',
            'label' => __('Payment Interval'),
            'description' => __('Choose an interval for recurring payment.'),
            'required' => true,
            'tags' => 'Snipcart',
            '_optionsString' => "1=Day|Day\n2=Week|Week\n3=Month|Month\n4=Year|Year", // Used in SelectableOptionManager->setOptionsString; Needs to be in double quotes!
        ),
        'snipcart_item_payment_interval_count' => array(
            'name' => 'snipcart_item_payment_interval_count',
            'type' => 'FieldtypeInteger',
            'label' => __('Interval Count'),
            'description' => __('Changes the payment interval count.'),
            'notes' => __('Integer number (min value = 1).'),
            'defaultValue' => 1,
            'min' => 1,
            'inputType' => 'number',
            'required' => true,
            'tags' => 'Snipcart',
        ),
        'snipcart_item_payment_trial' => array(
            'name' => 'snipcart_item_payment_trial',
            'type' => 'FieldtypeInteger',
            'label' => __('Trial Period'),
            'description' => __('Trial period for customers in days. Empty for no trial!'),
            'notes' => __('Integer number (min value = 1).'),
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
        ),
        'snipcart_item_recurring_shipping' => array(
            'name' => 'snipcart_item_recurring_shipping',
            'type' => 'FieldtypeCheckbox',
            'label' => __('Recurring Shipping'),
            'label2' => __('Charge shipping only on initial order'),
            'description' => __('Uncheck to add shipping costs to every upcoming recurring payment'),
            'required' => false,
            'tags' => 'Snipcart',
        ),
    ),
);
