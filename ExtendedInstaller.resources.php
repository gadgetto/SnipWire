<?php namespace ProcessWire;

/**
 * Returns extended installation resources for ProcessSnipWire.
 * (This file is part of the SnipWire package)
 *
 */

$resources = array(
    'templates' => array(
        'snipcart-shop' => array(
            'name' => 'snipcart-shop',
            'label' => 'Snipcart Shop',
            'noChildren' => 0,
            'tags' => 'Snipcart',
            '_allowedChildTemplates' => 'snipcart-product', // comma separated list of allowed child template names
        ),
        'snipcart-product' => array(
            'name' => 'snipcart-product',
            'label' => 'Snipcart Product',
            'noChildren' => 1,
            'tags' => 'Snipcart',
            '_allowedParentTemplates' => 'snipcart-shop', // comma separated list of allowed parent template names
        ),
    ),
    
    'files' => array(
        'snipcart-shop' => array(
            'name' => 'snipcart-shop.php',
            'type' => 'templates' // destination folder
        ),
        'snipcart-product' => array(
            'name' => 'snipcart-product.php',
            'type' => 'templates' // destination folder
        ),
    ),

    /*
    Snipcart fields: https://docs.snipcart.com/configuration/product-definition
    
    Required fields:
    ================
    
    data-item-id: integer (ProcessWire Page ID)
    data-item-name: string (ProcessWire Page title)
    data-item-price: decimal (For multi-currency feature read: https://docs.snipcart.com/configuration/multi-currency)
    data-item-url: string (URL where Snipcart crawler will find the Buy button. For single-page websites, provide only the basic domain name, such as www.example.com, or a simple slash bar /.)
    
    Optional fields:
    ================
    
    data-item-description: string
    data-item-image: string (Thumbnail of product in the cart. This must be an absolute URL.)
    data-item-categories: string (The categories this product belongs to. Example: data-item-categories="cat1, cat2, cat3")
    data-item-weight: integer? (Required only if using shipping rates. Using grams as weight units.)
    data-item-width: integer? (Using centimeters as dimension unit and this attribute is required to use Australia Post)
    data-item-length: integer? (Using centimeters as dimension unit and this attribute is required to use Australia Post)
    data-item-height: integer? (Using centimeters as dimension unit and this attribute is required to use Australia Post)
    data-item-max-quantity: integer (Maximum allowed quantity of product)
    data-item-min-quantity: integer (Minimum allowed quantity for product)
    data-item-stackable:
    data-item-quantity-step:
    data-item-shippable:
    data-item-quantity: integer (Set a default quantity for the item that you are about to add.)
    data-item-taxable: boolean
    data-item-taxes: 
    data-item-has-taxes-included: boolean
    data-item-metadata: json-object (Example usage: data-item-metadata='{"key": "value"}')
    data-item-file-guid: 
    data-item-payment-interval: 
    data-item-payment-interval-count: 
    data-item-payment-trial: 
    data-item-recurring-shipping: boolean
    */
    
    'fields' => array(
        'snipcart_item_price' => array(
            'name' => 'snipcart_item_price',
            'type' => 'FieldtypeText',
            'label' => __('Product Price'),
            'description' => __('The product price as decimal number.'),
            'notes' => __('Do not format the number you provide. Use a simple decimal with a dot (.) as a separator. Simply define the price regardless of the currency you\'re using.'),
            'maxlength' => 20,
            //'columnWidth' => 50,
            'required' => true,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_description' => array(
            'name' => 'snipcart_item_description',
            'type' => 'FieldtypeTextarea',
            'label' => __('Product Description'),
            'description' => __('The product description that your customers will see on product pages in cart and during checkout.'),
            'notes' => __('Provide a short description of your product without HTML tags.'),
            'maxlength' => 400,
            //'columnWidth' => 50,
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_image' => array(
            'name' => 'snipcart_item_image',
            'type' => 'FieldtypeImage',
            'label' => __('Product Image(s)'),
            'description' => __('The product image(s) your customers will see on product pages in cart and during checkout.'),
            'notes' => __('The image on first position will be used as the Snipcart thumbnail image. Only this image will be used in cart and during checkout'),
            //'columnWidth' => 50,
            'required' => false,
            'extensions' => 'gif jpg jpeg png',
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        
        
        
        
        
    ),
    
    'pages' => array(
        'snipcart-shop' => array(
            'name' => 'snipcart-shop',
            'title' => 'Snipcart Shop',
            'template' => 'snipcart-shop',
            'parent' => '/', // needs to be page path
            '_uninstall' => 'delete', // "trash" or "delete" or "no"
        ),
        'lorem-ipsum-dolor' => array(
            'name' => 'lorem-ipsum-dolor',
            'title' => 'Lorem Ipsum Dolor',
            'template' => 'snipcart-product',
            'parent' => '/snipcart-shop/', // needs to be page path
            'fields' => array(
                'snipcart_item_price' => '99.98',
                'snipcart_item_description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
                'snipcart_item_image' => 'sample_images/cake.jpg', // source file from module directory
            ),
            '_uninstall' => 'delete', // "trash" or "delete" or "no"
        ),
        'ut-enim-ad-minim' => array(
            'name' => 'ut-enim-ad-minim',
            'title' => 'Ut Enim ad Minim',
            'template' => 'snipcart-product',
            'parent' => '/snipcart-shop/', // needs to be page path
            'fields' => array(
                'snipcart_item_price' => '23.90',
                'snipcart_item_description' => 'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
                'snipcart_item_image' => 'sample_images/cookies.jpg', // source file from module directory
            ),
            '_uninstall' => 'delete', // "trash" or "delete" or "no"
        ),
        'duis-aute-irure-dolor' => array(
            'name' => 'duis-aute-irure-dolor',
            'title' => 'Duis Aute Irure Dolor',
            'template' => 'snipcart-product',
            'parent' => '/snipcart-shop/', // needs to be page path
            'fields' => array(
                'snipcart_item_price' => '119',
                'snipcart_item_description' => 'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.',
                'snipcart_item_image' => 'sample_images/pastries.jpg', // source file from module directory
            ),
            '_uninstall' => 'delete', // "trash" or "delete" or "no"
        ),
    ),
);




