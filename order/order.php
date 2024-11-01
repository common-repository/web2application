<?php


add_action('woocommerce_checkout_order_processed', 'order_made_inside_app', 10, 1);

function order_made_inside_app($order_id)
{

    $myValue = isInAppUser();
    update_post_meta( $order_id, 'order_made_inside_app', $myValue);

}

function order_phone_backend($order){
    echo "<p><strong>isInAppUser:-</strong> " . get_post_meta( $order->id, 'order_made_inside_app', true ) . "</p><br>";
} 
add_action( 'woocommerce_admin_order_data_after_billing_address','order_phone_backend', 10, 1 );

//abandone carts for engage
//create table for abandone carts






// track cart data
function track_cart_activity() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'w2a_abandoned_carts';

    // Ensure the WC_Cart object is available
    if (!WC()->cart) {
        error_log('WC_Cart object not available.');
        return;
    }

    // Get user data
    $user_id = is_user_logged_in() ? get_current_user_id() : 0;
    $user_email = is_user_logged_in() ? wp_get_current_user()->user_email : '';
    $user_cookie_id = isset($_COOKIE['aUserCookieID']) ? sanitize_text_field($_COOKIE['aUserCookieID']) : '';

    // Get cart data
    $cart_contents = WC()->cart->get_cart_contents();
    if (!$cart_contents) {
        error_log('Cart contents not available or empty.');
        return;
    }
    
    $cart_total = WC()->cart->get_cart_total();
    $created_at = current_time('mysql');

    // Initialize an array to store detailed cart contents
    $detailed_cart_contents = [];

    // Loop through the cart items
    foreach ($cart_contents as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $product = wc_get_product($product_id);

        if (!$product) {
            error_log("Product not found for product_id: $product_id");
            continue; // Skip this iteration if the product is not found
        }

        // Get product name and image URL
        $product_name = $product->get_name();
        $product_image_url = wp_get_attachment_url($product->get_image_id());

        // Add the data to the detailed cart contents array
        $detailed_cart_contents[$cart_item_key] = [
            'product_id' => $product_id,
            'product_name' => $product_name,
            'product_image_url' => $product_image_url,
            'quantity' => $cart_item['quantity'],
            'line_subtotal' => $cart_item['line_subtotal'],
            'line_total' => $cart_item['line_total']
        ];
    }

    // Encode the detailed cart contents as JSON
    $detailed_cart_contents_json = json_encode($detailed_cart_contents);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON encoding error: ' . json_last_error_msg());
        return;
    }

    // Check if there's an existing cart for this user or cookie ID that hasn't been completed
    $existing_cart = null;
    if ($user_email != '') {
        $existing_cart = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE (user_id = %d OR user_cookie_id = %s) AND completed = 0 AND sent = 0", $user_id, $user_cookie_id));
    } else {
        $existing_cart = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_cookie_id = %s AND completed = 0 AND sent = 0", $user_cookie_id));
    }

    if ($existing_cart) {
        // Update existing cart
        $wpdb->update(
            $table_name,
            array(
                'cart_contents' => $detailed_cart_contents_json,
                'cart_total' => $cart_total,
                'user_email' => $user_email,
                'created_at' => $created_at
            ),
            array('id' => $existing_cart->id)
        );
    } else {
        // Insert new cart entry
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'user_email' => $user_email,
                'user_cookie_id' => $user_cookie_id,
                'cart_contents' => $detailed_cart_contents_json,
                'cart_total' => $cart_total,
                'created_at' => $created_at,
                'completed' => 0
            )
        );
    }

    // Log any errors that might occur with the database operations
    if ($wpdb->last_error) {
        error_log("Database error: " . $wpdb->last_error);
    }
}



if ($w2a_options['w2a_engage_enable'] == '1') {
	//echo "check if i have the option in order.php : ".$w2a_options['w2a_engage_enable'];
	add_action('woocommerce_cart_updated', 'track_cart_activity');
}

// mark cart that become orders as completed
function mark_cart_as_completed($order_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'w2a_abandoned_carts';

    // Get user data
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    $user_cookie_id = isset($_COOKIE['aUserCookieID']) ? sanitize_text_field($_COOKIE['aUserCookieID']) : '';

    // Mark the cart as completed
    $wpdb->update(
        $table_name,
        array('completed' => 1),
        array('user_cookie_id' => $user_cookie_id, 'completed' => 0)
    );
}
if ($w2a_options['w2a_engage_enable'] == '1') {
	add_action('woocommerce_checkout_order_processed', 'mark_cart_as_completed', 10, 1);
}





















