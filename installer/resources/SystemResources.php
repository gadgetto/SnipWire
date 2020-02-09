<?php
namespace ProcessWire;

/**
 * Returns system resources for ProcessSnipWire.
 * (This file is part of the SnipWire package)
 *
 */

$cartCustomVal  = 'data-cart-custom1-name="By checking this box, I have read and agree to the <a href=\'https://www.domain.com/terms-and-conditions\' class=\'js-real-link\' target=\'_blank\'>Terms &amp; Conditions</a>"' . PHP_EOL;
$cartCustomVal .= 'data-cart-custom1-options="true|false"' . PHP_EOL;
$cartCustomVal .= 'data-cart-custom1-required="true"';

$resources = array(
    'templates' => array(
        'snipcart-cart' => array(
            'name' => 'snipcart-cart',
            'label' => 'Snipcart Cart (System)',
            'icon' => 'cog',
            'noChildren' => 1,
            'noParents' => 1,
            'tags' => 'Snipcart',
        ),
    ),
    'fields' => array(
        // `title` is an alredy available field which only needs to be configured in template context
        'title' => array(
            'name' => 'title',
            '_templateFieldOptions' => array(
                'snipcart-cart' => array(
                    'collapsed' => 4, //Inputfield::collapsedHidden
                ),
            ),
            '_configureOnly' => true, // will not be installed - only configured
        ),
        'snipcart_cart_custom_fields' => array(
            'name' => 'snipcart_cart_custom_fields',
            'type' => 'FieldtypeTextarea',
            'label' => __('Custom Cart Fields Setup'),
            'icon' => 'code',
            'description' => __('You can add custom fields to the checkout process. Whenever you define custom cart fields, a new tab/step called `Order infos` will be inserted before the `Billing address` during the checkout process.'),
            'notes' => __('For detailed infos about custom cart fields setup, please visit [Snipcart v2.0 Custom Fields](https://docs.snipcart.com/v2/configuration/custom-fields).'),
            'rows' => 12,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-cart', // comma separated list of template names
        ),
        // This field will be preinstalled only and needs to be added manually to the desired product template(s)
        'snipcart_item_custom_fields' => array(
            'name' => 'snipcart_item_custom_fields',
            'type' => 'FieldtypeTextarea',
            'label' => __('Custom Product Fields Setup'),
            'icon' => 'code',
            'description' => __('You can add custom fields to this product. Whenever you define custom fields, a new input element will be added to each of these products in cart.'),
            'notes' => __('For detailed infos about custom fields setup, please visit [Snipcart v2.0 Custom Fields](https://docs.snipcart.com/v2/configuration/custom-fields).'),
            'rows' => 12,
            'collapsed' => 1, // Inputfield::collapsedYes
            'tags' => 'Snipcart',
        ),
    ),            
    'pages' => array(
        'custom-cart-fields' => array(
            'name' => 'custom-cart-fields',
            'title' => 'Custom Cart Fields',
            'template' => 'snipcart-cart',
            'parent' => '{snipWireRootUrl}', // needs to be page path (in this case we use a "string tag" which will be resolved by installer)
            'status' => 1040, // Page::statusSystem | Page::statusHidden
            'fields' => array(
                'snipcart_cart_custom_fields' => $cartCustomVal,
            ),
            '_uninstall' => 'delete', // "trash" or "delete" or "no"
        ),
    ),
);
