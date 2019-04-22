<?php namespace ProcessWire;

/**
 * SnipWire sample shop product detail template for "regular" site-profile.
 * 
 * The purpose of this template file is to provide a product detail template for your Snipcart shop.
 *
 * We are using the "regular" site-profile as it offers Markup Regions as its output 
 * strategy and so it's easy to demonstrate how SnipCart works.
 * 
 */

if (!defined('PROCESSWIRE')) die();

if (!modules()->isInstalled('SnipWire')) {
    ?>
    <div id="content">
        <strong>SnipWire</strong> module is not installed. Module is required to render this page!
    </div>
    <?php
    return;
}
?>
<!--
The mini cart display shows the "Items in cart" count and the "total" cart value.

Notice that the container element has a "snipcart-summary" class. Add the markup you want within 
this container, then add "snipcart-total-items" and "snipcart-total-price" classes to the elements
that will contain cart information.

Wrap the container element with a link having the class "snipcart-checkout" and the cart will pop 
when your visitors click on it.

The complete markup is up to you - it just needs to have the described classes included!

More here: https://docs.snipcart.com/getting-started/the-cart
-->
<p id="masthead-tagline" class="-uk-text-small -uk-text-muted uk-margin-remove uk-text-center snipcart-summary">
    <a href="#" class="uk-link-reset snipcart-checkout" aria-label="Shopping cart">
        <?=ukIcon('cart')?>
        <span class="uk-badge snipcart-total-items uk-text-middle" aria-label="Items in cart"></span>
        <span class="snipcart-total-price uk-text-middle" aria-label="Total"></span>
    </a>
</p>

<!--
The content element holds your product detail view.
-->
<div id="content">
    <?php
    echo ukHeading1(page()->title, 'divider');
    
    // We use the first image in snipcart_item_image field for demo
    $image = page()->snipcart_item_image->first();
    $productImageLarge = $image->size(800, 0, array('quality' => 70));

    // This is the part where we render the Snipcart anchor (buy button)
    // with all data-item-* attributes required by Snipcart.
    // The anchor method is provided by MarkupSnipWire module and can be called 
    // via custom API variable: $snipwire->anchor()
    $options = array(
        'label' => ukIcon('cart'),
        'class' => 'uk-button uk-button-primary',
        'attr' => array('aria-label' => __('Add item to cart')),
    );
    $anchor = wire('snipwire')->anchor(page(), $options);

    // Get the formatted product price.
    // The getProductPriceFormatted method is provided by MarkupSnipWire module and can be called 
    // via custom API variable: $snipwire->getProductPriceFormatted()
    $priceFormatted = wire('snipwire')->getProductPriceFormatted(page());

    $out =
    '<div class="uk-margin-medium-bottom" uk-grid>' .
        '<div class="uk-width-2-5@s">' .
            '<img src="' . $productImageLarge->url . '" alt="' . page()->title . '">' .
        '</div>' .
        '<div class="uk-width-3-5@s">' .
            '<p>' .
                '<span class="uk-text-primary uk-text-large">' . $priceFormatted . '</span>' .
            '</p>' .
            '<p>' . page()->snipcart_item_description . '</p>' .
            $anchor .
        '</div>' .
    '</div>' .
    '<div>' .
        'Detailed content...' .
    '</div>';
    
    echo $out;
    ?>
</div>

<!--
We remove the <aside> element which is not used in our shop sample.
-->
<aside id="sidebar" pw-remove></aside>
