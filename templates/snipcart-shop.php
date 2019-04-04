<?php namespace ProcessWire;

/**
 * SnipWire sample shop parent template for "site-regular" site-profile.
 * 
 * The purpose of this template file is to provide a product overview for your Snipcart shop.
 * 
 */

if (!defined('PROCESSWIRE')) die();
?>

<div id="content">
    <?php
    echo ukHeading1(page()->title, 'divider'); 
    $products = page()->children('limit=10');
    echo ukProductOverview($products); 
    ?>
</div>
<aside id="sidebar">
	<?php 
	$categories = pages()->get('/categories/'); 
	echo ukNav($categories->children, [ 'header' => $categories->title ]); 
	?>		
</aside>

<?php
/**
 * Render a shop product overview (uk-cards)
 *
 * @param PageArray $products
 * @return string
 *
 */
function ukProductOverview(PageArray $products) {

    if(!$products->count) return '';
    
    $out = '<div class="uk-child-width-1-2@s uk-grid" uk-grid="">';

    foreach ($products as $product) {
        $out .= '<div>';
        $out .= '    <div class="uk-card uk-card-default">';
        $out .= '        <div class="uk-card-media-top">';
        $out .= '            <img src="' . $product->snipcart_item_image->url . '" alt="' . $product->title . '">';
        $out .= '        </div>';
        $out .= '        <div class="uk-card-body">';
        $out .= '            <h3 class="uk-card-title">' . $product->title . '</h3>';
        $out .= '            <p>' . $product->snipcart_item_description . '</p>';
        $out .= '        </div>';
        $out .= '        <div class="uk-card-footer">';
        $out .= '            <p>';
        $out .= '                ' . wire('snipwire')->anchor($product, 'Buy now', 'uk-button uk-button-primary');
        $out .= '            </p>';
        $out .= '        </div>';        
        $out .= '    </div>';        
        $out .= '</div>';
    }

    $out .= '</div>';

    return $out;
}
?>
