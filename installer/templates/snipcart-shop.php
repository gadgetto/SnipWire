<?php
namespace ProcessWire;

/**
 * SnipWire sample shop parent template for "regular" site-profile.
 * 
 * The purpose of this template file is to provide a product overview for your Snipcart shop.
 *
 * We are using the "regular" site-profile as it offers Markup Regions as its output 
 * strategy and so it's easy to demonstrate how SnipCart works.
 * 
 */

if (!defined('PROCESSWIRE')) die();

if (!modules()->isInstalled('SnipWire')) {
    ?>
    <div id="content">
        <strong>SnipWire</strong> is not installed. Module is required to render this page!
    </div>
    <?php
    return;
}
?>
<!--
Adding a show cart button + cart summary + customer dashboard links.

The key is to have elements with specific Snipcart classes in your markup:

 - "snipcart-summary"      -- wrapper element for all elements with Snipcart classes supplied (optional but recommended!)
 - "snipcart-total-items"  -- displays total items currently in cart
 - "snipcart-total-price"' -- displays the current total cart price
 - "snipcart-user-profile" -- triggers the apparition of the users dashboard (orders history, subscriptions)
 - "snipcart-user-email"   -- displays the users email address (previous content within this element will be overridden)
 - "snipcart-user-logout"  -- enables a logout link/button (+ elements with this class will be hidden until the user is logged in)
 - "snipcart-edit-profile" -- triggers the apparition of the users profile editor (billing address, shipping address)

The complete markup is up to you - it just needs to have the described classes included!

More here: https://docs.snipcart.com/getting-started/the-cart
and here: https://docs.snipcart.com/getting-started/customer-dashboard
-->
<div class="uk-text-center snipcart-summary" pw-after="masthead-logo">
    <a href="#" class="uk-link-reset snipcart-checkout" aria-label="Shopping cart">
        <?=ukIcon('cart')?>
        <span class="uk-badge uk-text-middle snipcart-total-items" aria-label="Items in cart"></span>
        <span class=" uk-text-middle snipcart-total-price" aria-label="Total"></span>
    </a>
    <button class="uk-button uk-button-default uk-button-small snipcart-user-profile" type="button">
        <?=ukIcon('user', 'ratio: .8')?> <span class="snipcart-user-email">My Account</span>
    </button>
    <div class="uk-inline snipcart-user-logout">
        <button class="uk-button uk-button-default uk-button-small snipcart-edit-profile" type="button"><?=ukIcon('pencil', 'ratio: .8')?> Edit Profile</button>
        <button class="uk-button uk-button-default uk-button-small snipcart-user-logout" type="button"><?=ukIcon('sign-out', 'ratio: .8')?> Logout</button>
    </div>
</div>

<!--
We remove the masthead-tagline region to save space in this sample.
-->
<p id="masthead-tagline" pw-remove></p>

<!--
The content element holds your products catalogue.
-->
<div id="content">
    <?php
    echo ukHeading1(page()->title, 'divider'); 
    $products = page()->children('limit=9');
    echo ukProductOverview($products); 
    ?>
</div>

<!--
We remove the <aside> element which is not used in our shop sample.
-->
<aside id="sidebar" pw-remove></aside>

<?php
/**
 * Render a shop product overview (uk-cards)
 *
 * @param PageArray $products
 * @return string
 *
 */
function ukProductOverview(PageArray $products) {

    if (!$products->count) return '';
    
    $out = '<div class="uk-child-width-1-2@s uk-child-width-1-3@m" uk-grid>';

    foreach ($products as $product) {
        
        // We use the first image in snipcart_item_image field for demo
        $imageMedia = '';
        if ($image = $product->snipcart_item_image->first()) {
            $productImageMedium = $image->size(600, 0, array('quality' => 70));
            $imageDesc = $productImageMedium->description ? $productImageMedium->description : $product->title;
            $imageMedia = '<img src="' . $productImageMedium->url . '" alt="' . $imageDesc . '">';
        } else {
            $imageMedia = 
            '<div class="uk-width-1-1 uk-height-small uk-background-muted uk-text-muted uk-flex uk-flex-center uk-flex-middle">' .
                '<div title="' . __('No product image available') . '">' . 
                    ukIcon('image', array('ratio' => 3)) . 
                '</div>' .
            '</div>';
            
        }
        
        // This is the part where we render the Snipcart anchor (buy button)
        // with all data-item-* attributes required by Snipcart.
        // The anchor method is provided by MarkupSnipWire module and can be called 
        // via custom API variable: $snipwire->anchor()
        $options = array(
            'label' => ukIcon('cart'),
            'class' => 'uk-button uk-button-primary',
            'attr' => array('aria-label' => __('Add item to cart')),
        );
        $anchor = wire('snipwire')->anchor($product, $options);
        
        // Get the formatted product price.
        // The getProductPriceFormatted method is provided by MarkupSnipWire module and can be called 
        // via custom API variable: $snipwire->getProductPriceFormatted()
        $priceFormatted = wire('snipwire')->getProductPriceFormatted($product);

        $out .=
        '<a class="uk-link-reset" href="' . $product->url . '">' .
            '<div class="uk-card uk-card-small uk-card-default uk-card-hover">' .
                '<div class="uk-card-media-top">' .
                    $imageMedia .
                '</div>' .
                '<div class="uk-card-body">' .
                    '<h3 class="uk-card-title">' . $product->title . '</h3>' .
                    '<p>' . $product->snipcart_item_description . '</p>' .
                '</div>' .
                '<div class="uk-card-footer">' .
                    $anchor .
                    '<span class="uk-align-right uk-text-primary">' . $priceFormatted . '</span>' .
                '</div>' .
            '</div>' .
        '</a>';
    }

    $out .= '</div>';

    return $out;
}
?>
