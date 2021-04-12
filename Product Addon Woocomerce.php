// Display additional product fields (+ jQuery code)
add_action( 'woocommerce_before_add_to_cart_button', 'display_additional_product_fields', 9 );
function display_additional_product_fields(){
    ?>
    <p class="form-row validate-required" id="image" >
        <label for="file_field"><?php echo __("Upload Image") . ': '; ?>
            <input type='file' name='image' accept='image/*'>
        </label>
    </p>
    <?php
}

// Add custom fields data as the cart item custom data
add_filter( 'woocommerce_add_cart_item_data', 'add_custom_fields_data_as_custom_cart_item_data', 10, 2 );
function add_custom_fields_data_as_custom_cart_item_data( $cart_item, $product_id ){
    if( isset($_FILES['image']) && ! empty($_FILES['image']) ) {
        $upload       = wp_upload_bits( $_FILES['image']['name'], null, file_get_contents( $_FILES['image']['tmp_name'] ) );
        $filetype     = wp_check_filetype( basename( $upload['file'] ), null );
        $upload_dir   = wp_upload_dir();
        $upl_base_url = is_ssl() ? str_replace('http://', 'https://', $upload_dir['baseurl']) : $upload_dir['baseurl'];
        $base_name    = basename( $upload['file'] );

        $cart_item['file_upload'] = array(
            'guid'      => $upl_base_url .'/'. _wp_relative_upload_path( $upload['file'] ), // Url
            'file_type' => $filetype['type'], // File type
            'file_name' => $base_name, // File name
            'title'     => ucfirst( preg_replace('/\.[^.]+$/', '', $base_name ) ), // Title
        );
        $cart_item['unique_key'] = md5( microtime().rand() ); // Avoid merging items
    }
    return $cart_item;
}

// Display custom cart item data in cart (optional)
add_filter( 'woocommerce_get_item_data', 'display_custom_item_data', 10, 2 );
function display_custom_item_data( $cart_item_data, $cart_item ) {
    if ( isset( $cart_item['file_upload']['title'] ) ){
        $cart_item_data[] = array(
            'name' => __( 'Image uploaded', 'woocommerce' ),
            'value' =>  str_pad($cart_item['file_upload']['title'], 16, 'X', STR_PAD_LEFT) . '…',
        );
    }
    return $cart_item_data;
}

// Save Image data as order item meta data
add_action( 'woocommerce_checkout_create_order_line_item', 'custom_field_update_order_item_meta', 20, 4 );
function custom_field_update_order_item_meta( $item, $cart_item_key, $values, $order ) {
    if ( isset( $values['file_upload'] ) ){
        $item->update_meta_data( '_img_file',  $values['file_upload'] );
    }
}

// Admin orders: Display a linked button + the link of the image file
add_action( 'woocommerce_after_order_itemmeta', 'backend_image_link_after_order_itemmeta', 10, 3 );
function backend_image_link_after_order_itemmeta( $item_id, $item, $product ) {
    // Only in backend for order line items (avoiding errors)
    if( is_admin() && $item->is_type('line_item') && $file_data = $item->get_meta( '_img_file' ) ){
        echo '<p><a href="'.$file_data['guid'].'" target="_blank" class="button">'.__("Open Image") . '</a></p>'; // Optional
        echo '<p><code>'.$file_data['guid'].'</code></p>'; // Optional
    }
}

// Admin new order email: Display a linked button + the link of the image file
add_action( 'woocommerce_email_after_order_table', 'wc_email_new_order_custom_meta_data', 10, 4);
function wc_email_new_order_custom_meta_data( $order, $sent_to_admin, $plain_text, $email ){
    // On "new order" email notifications
    if ( 'new_order' === $email->id ) {
        foreach ($order->get_items() as $item ) {
            if ( $file_data = $item->get_meta( '_img_file' ) ) {
                echo '<p>
                    <a href="'.$file_data['guid'].'" target="_blank" class="button">'.__("Download Image") . '</a><br>
                    <pre><code style="font-size:12px; background-color:#eee; padding:5px;">'.$file_data['guid'].'</code></pre>
                </p><br>';
            }
        }
    }
}