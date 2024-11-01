<?php
ob_start(); // Start output buffering
/* if ( ! defined( 'ABSPATH' ) ) exit;
define('WP_DEBUG', false); */
// Init Options Global

global $w2a_options;
// $inside_app_cookie = isInAppUser();
// echo $inside_app_cookie;

// Ensure WooCommerce is active
if (!class_exists('WooCommerce')) {
    echo json_encode(['error' => 'WooCommerce is not activated.']);
    exit;
}

// Get all products excluding variations
$args = array(
    'post_type' => 'product',
    'posts_per_page' => -1, // -1 to get all products
    'post_status' => 'publish',
    'tax_query' => array(
        array(
            'taxonomy' => 'product_type',
            'field' => 'slug',
            'terms' => array('simple', 'grouped', 'external'), // Exclude variable products
        ),
    ),
);

$products = get_posts($args);
$product_data = array();

foreach ($products as $product_post) {
    $product = wc_get_product($product_post->ID);

    // Get the main product data
    $data = array(
        'id' => $product->get_id(),
        'name' => htmlspecialchars($product->get_name(), ENT_QUOTES, 'UTF-8'),
        'sku' => htmlspecialchars($product->get_sku(), ENT_QUOTES, 'UTF-8'),
        'price' => htmlspecialchars($product->get_price(), ENT_QUOTES, 'UTF-8'),
        'regular_price' => htmlspecialchars($product->get_regular_price(), ENT_QUOTES, 'UTF-8'),
        'sale_price' => htmlspecialchars($product->get_sale_price(), ENT_QUOTES, 'UTF-8'),
        'stock_status' => htmlspecialchars($product->get_stock_status(), ENT_QUOTES, 'UTF-8'),
        'stock_quantity' => intval($product->get_stock_quantity()),
        'product_link' => htmlspecialchars(get_permalink($product->get_id()), ENT_QUOTES, 'UTF-8'),
        'image_link' => htmlspecialchars(wp_get_attachment_url($product->get_image_id()), ENT_QUOTES, 'UTF-8'),
    );

    $product_data[] = $data; // Add product data to the array
}

// Get and clear any unwanted output
$unexpected_output = ob_get_clean();

// Check if there was unexpected output
if (!empty($unexpected_output)) {
    error_log("Unexpected output detected: " . $unexpected_output);
}

// Output the final JSON encoded array
header('Content-Type: application/json');
echo json_encode($product_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
