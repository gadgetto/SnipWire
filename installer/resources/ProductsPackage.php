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

$resources = [

    'config' => [
        'SnipWire' => [
            'name' => 'SnipWire',
            'options' => [
                'product_templates' => ['snipcart-product'],
            ],
        ],
    ],
    
    'templates' => [
        'snipcart-shop' => [
            'name' => 'snipcart-shop',
            'label' => 'Snipcart Shop',
            'icon' => 'tags', 
            'noChildren' => 0,
            'tags' => 'Snipcart',
            '_allowedChildTemplates' => 'snipcart-product',
        ],
        'snipcart-product' => [
            'name' => 'snipcart-product',
            'label' => 'Snipcart Product',
            'icon' => 'tag', 
            'noChildren' => 1,
            'tags' => 'Snipcart',
            '_allowedParentTemplates' => 'snipcart-shop',
        ],
    ],
    
    'files' => [
        'snipcart-shop' => [
            'name' => 'snipcart-shop.php',
            'type' => 'templates' // destination folder
        ],
        'snipcart-product' => [
            'name' => 'snipcart-product.php',
            'type' => 'templates' // destination folder
        ],
    ],

    // Snipcart product fields:
    // @see: https://docs.snipcart.com/v3/setup/products
    // @see: /MarkupSnipWire/MarkupSnipWire.module.php for product attributes definitions

    'fields' => [
        'title' => [
            'name' => 'title',
            '_templateFieldOptions' => [
                'snipcart-product' => [
                    'label' => __('Product Name (Title)'),
                    'notes' => __('Name of the product to be used in catalogue and cart.'),
                    'columnWidth' => 70,
                ],
            ],
            '_configureOnly' => true,
        ],
        'snipcart_item_id' => [
            'name' => 'snipcart_item_id',
            'type' => 'FieldtypeText',
            'label' => __('SKU'),
            'notes' => __('Individual ID for your product e.g. 1377 or NIKE_PEG-SW-43'),
            'maxlength' => 100,
            'required' => true,
            'pattern' => '^[\w\-_*+.,]+$',
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
            '_templateFieldOptions' => [
                'snipcart-product' => [
                    'columnWidth' => 30,
                ],
            ],
        ],
        'snipcart_item_price_eur' => [
            'name' => 'snipcart_item_price_eur',
            'type' => 'FieldtypeText',
            'label' => __('Product Price (EUR)'),
            'notes' => __('Decimal with a dot (.) as separator e.g. 19.99'),
            'maxlength' => 20,
            'required' => true,
            'pattern' => '[-+]?[0-9]*[.]?[0-9]+',
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
        ],
        'snipcart_item_description' => [
            'name' => 'snipcart_item_description',
            'type' => 'FieldtypeTextarea',
            'label' => __('Product Description'),
            'description' => __('The product description that your customers will see on product pages in cart and during checkout.'),
            'notes' => __('Provide a short description of your product without HTML tags.'),
            'maxlength' => 300,
            'rows' => 3,
            'showCount' => 1,
            'stripTags' => 1,
            'textformatters' => ['TextformatterEntities'],
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
        ],
        'snipcart_item_image' => [
            'name' => 'snipcart_item_image',
            'type' => 'FieldtypeImage',
            'label' => __('Product Image(s)'),
            'description' => __('The product image(s) your customers will see on product pages in cart and during checkout.'),
            'notes' => __('The image on first position will be used as the Snipcart thumbnail image. Only this image will be used in cart and during checkout'),
            'required' => false,
            'extensions' => 'gif jpg jpeg png',
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
        ],
        'snipcart_item_categories' => [
            'name' => 'snipcart_item_categories',
            'type' => 'FieldtypePage',
            'inputfield' => 'InputfieldAsmSelect',
            'labelFieldName' => 'title', // (used for AsmSelect)
            'usePageEdit' => true, // (used for AsmSelect)
            'addable' => true, // (used for AsmSelect)
            'label' => __('Categories'),
            'description' => __('The categories for this product.'),
            'derefAsPage' => 0, // (used for AsmSelect)
            'parent_id' => '/categories/', // will be converted to page ID by installer (used for AsmSelect)
            'template_id' => 'category', // will be converted to template ID by installer (used for AsmSelect)
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
        ],
        'snipcart_item_weight' => [
            'name' => 'snipcart_item_weight',
            'type' => 'FieldtypeInteger',
            'label' => __('Product Weight'),
            'description' => __('Set the weight for this product.'),
            'notes' => __('Uses grams as weight unit.'),
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
        ],
        'snipcart_item_width' => [
            'name' => 'snipcart_item_width',
            'type' => 'FieldtypeInteger',
            'label' => __('Product Width'),
            'description' => __('Set the width for this product.'),
            'notes' => __('Uses centimeters as unit.'),
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
        ],
        'snipcart_item_length' => [
            'name' => 'snipcart_item_length',
            'type' => 'FieldtypeInteger',
            'label' => __('Product Length'),
            'description' => __('Set the length for this product.'),
            'notes' => __('Uses centimeters as unit.'),
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
        ],
        'snipcart_item_height' => [
            'name' => 'snipcart_item_height',
            'type' => 'FieldtypeInteger',
            'label' => __('Product Height'),
            'description' => __('Set the height for this product.'),
            'notes' => __('Uses centimeters as unit.'),
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
        ],
        'snipcart_item_quantity' => [
            'name' => 'snipcart_item_quantity',
            'type' => 'FieldtypeInteger',
            'label' => __('Default Quantity'),
            'description' => __('The default quantity for the product that will be added to cart.'),
            'notes' => __('Integer number (min value = 1).'),
            'defaultValue' => 1,
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
        ],
        'snipcart_item_max_quantity' => [
            'name' => 'snipcart_item_max_quantity',
            'type' => 'FieldtypeInteger',
            'label' => __('Maximum Quantity'),
            'description' => __('Set the maximum allowed quantity for this product.'),
            'notes' => __('Leave empty for no limit.'),
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
        ],
        'snipcart_item_min_quantity' => [
            'name' => 'snipcart_item_min_quantity',
            'type' => 'FieldtypeInteger',
            'label' => __('Minimum Quantity'),
            'description' => __('Set the minimum allowed quantity for this product.'),
            'notes' => __('Leave empty for no limit.'),
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
        ],
        'snipcart_item_quantity_step' => [
            'name' => 'snipcart_item_quantity_step',
            'type' => 'FieldtypeInteger',
            'label' => __('Quantity Step'),
            'description' => __('The quantity of a product will increment by this value.'),
            'notes' => __('Integer number (min value = 1).'),
            'defaultValue' => 1,
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
        ],
        'snipcart_item_stackable' => [
            'name' => 'snipcart_item_stackable',
            'type' => 'FieldtypeCheckbox',
            'label' => __('Stackable'),
            'label2' => __('Product is stackable'),
            'description' => __('Uncheck, if this product should be added to cart in distinct items instead of increasing quantity.'),
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
        ],
        'snipcart_item_taxable' => [
            'name' => 'snipcart_item_taxable',
            'type' => 'FieldtypeCheckbox',
            'label' => __('Taxable'),
            'label2' => __('Product is taxable'),
            'description' => __('Uncheck, if this product should be excluded from taxes calculation.'),
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
        ],
        'snipcart_item_taxes' => [
            'name' => 'snipcart_item_taxes',
            'type' => 'FieldtypeSnipWireTaxSelector',
            'label' => __('VAT'),
            'description' => __('Select the tax which should be applied.'),
            'required' => false,
            'tags' => 'Snipcart',
            'taxesType' => 1, // Taxes::taxesTypeProducts
            '_addToTemplates' => 'snipcart-product',
        ],
        'snipcart_item_payment_interval' => [
            'name' => 'snipcart_item_payment_interval',
            'type' => 'FieldtypeOptions',
            'inputfield' => 'InputfieldSelect',
            'label' => __('Payment Interval'),
            'description' => __('Choose an interval for recurring payment.'),
            'required' => true,
            'tags' => 'Snipcart',
            '_optionsString' => "1=Day|Day\n2=Week|Week\n3=Month|Month\n4=Year|Year", // Used in SelectableOptionManager->setOptionsString; Needs to be in double quotes!
        ],
        'snipcart_item_payment_interval_count' => [
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
        ],
        'snipcart_item_payment_trial' => [
            'name' => 'snipcart_item_payment_trial',
            'type' => 'FieldtypeInteger',
            'label' => __('Trial Period'),
            'description' => __('Trial period for customers in days. Empty for no trial!'),
            'notes' => __('Integer number (min value = 1).'),
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
        ],
        'snipcart_item_recurring_shipping' => [
            'name' => 'snipcart_item_recurring_shipping',
            'type' => 'FieldtypeCheckbox',
            'label' => __('Recurring Shipping'),
            'label2' => __('Charge shipping only on initial order'),
            'description' => __('Uncheck to add shipping costs to every upcoming recurring payment'),
            'required' => false,
            'tags' => 'Snipcart',
        ],
        'snipcart_item_shippable' => [
            'name' => 'snipcart_item_shippable',
            'type' => 'FieldtypeCheckbox',
            'label' => __('Shippable'),
            'label2' => __('Product is shippable'),
            'description' => __('Uncheck, if this product should be flagged as not shippable.'),
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',
        ],
    ],

    'pages' => [
        'snipcart-shop' => [
            'name' => 'snipcart-shop',
            'title' => 'Snipcart Shop',
            'template' => 'snipcart-shop',
            'parent' => '/', // needs to be page path
            '_uninstall' => 'delete',
        ],
        'fuzzy-regalia' => [
            'name' => 'big-schlemel-stout',
            'title' => 'Big Schlemel Stout',
            'template' => 'snipcart-product',
            'parent' => '/snipcart-shop/', // needs to be page path
            'fields' => [
                'snipcart_item_id' => 'BEER-10001',
                'snipcart_item_price_eur' => '69.98',
                'snipcart_item_description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
                'snipcart_item_image' => 'sample_images/beer1.jpg', // source file from module directory
                'snipcart_item_taxable' => true,
                'snipcart_item_shippable' => true,
                'snipcart_item_stackable' => true,
            ],
            '_uninstall' => 'delete',
        ],
        'square-cream-hoax' => [
            'name' => 'festish-wet-warmer',
            'title' => 'Festish Wet Warmer',
            'template' => 'snipcart-product',
            'parent' => '/snipcart-shop/', // needs to be page path
            'fields' => [
                'snipcart_item_id' => 'BEER-10002',
                'snipcart_item_price_eur' => '19.90',
                'snipcart_item_description' => 'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
                'snipcart_item_image' => 'sample_images/beer2.jpg', // source file from module directory
                'snipcart_item_taxable' => true,
                'snipcart_item_shippable' => true,
                'snipcart_item_stackable' => true,
            ],
            '_uninstall' => 'delete',
        ],
        'axolotl-juicer' => [
            'name' => 'axolotl-juicer',
            'title' => 'Axolotl Juicer',
            'template' => 'snipcart-product',
            'parent' => '/snipcart-shop/', // needs to be page path
            'fields' => [
                'snipcart_item_id' => 'BEER-10003',
                'snipcart_item_price_eur' => '1199',
                'snipcart_item_description' => 'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.',
                'snipcart_item_image' => 'sample_images/beer3.jpg', // source file from module directory
                'snipcart_item_taxable' => true,
                'snipcart_item_shippable' => true,
                'snipcart_item_stackable' => true,
            ],
            '_uninstall' => 'delete',
        ],
    ],
];
