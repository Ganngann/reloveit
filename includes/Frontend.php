<?php
/**
 * Frontend handler class.
 *
 * @package Relovit
 */

namespace Relovit;

/**
 * Class Frontend
 *
 * @package Relovit
 */
class Frontend {

    /**
     * Frontend constructor.
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'init', [ $this, 'register_shortcodes' ] );

        // WooCommerce hooks for "My Account" page.
        add_filter( 'woocommerce_account_menu_items', [ $this, 'relovit_account_menu_items' ] );
        add_action( 'init', [ $this, 'relovit_add_my_account_endpoint' ] );
        add_filter( 'query_vars', [ $this, 'relovit_add_query_vars' ], 0 );
        add_action( 'woocommerce_account_relovit-dashboard_endpoint', [ $this, 'relovit_dashboard_content' ] );
        add_action( 'woocommerce_account_relovit-settings_endpoint', [ $this, 'relovit_settings_content' ] );
        add_action( 'woocommerce_account_relovit-products_endpoint', [ $this, 'relovit_products_content' ] );
        add_action( 'template_redirect', [ $this, 'relovit_save_settings' ] );
        add_action( 'template_redirect', [ $this, 'relovit_save_product' ] );

        // Marketplace hooks.
        add_action( 'woocommerce_checkout_create_order', [ $this, 'assign_seller_to_order' ], 10, 2 );
    }

    /**
     * Save the product from the "Relovit Edit Product" page.
     */
    public function relovit_save_product() {
        if ( ! isset( $_POST['relovit_save_product'] ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'relovit_edit_product' ) ) {
            return;
        }

        $product_id = isset( $_POST['relovit_product_id'] ) ? intval( $_POST['relovit_product_id'] ) : 0;
        $product = wc_get_product( $product_id );

        if ( ! $product || get_post_field( 'post_author', $product->get_id() ) != get_current_user_id() ) {
            wc_add_notice( __( 'Invalid product.', 'relovit' ), 'error' );
            return;
        }

        $title = isset( $_POST['relovit_product_title'] ) ? sanitize_text_field( $_POST['relovit_product_title'] ) : '';
        $description = isset( $_POST['relovit_product_description'] ) ? wp_kses_post( $_POST['relovit_product_description'] ) : '';
        $price = isset( $_POST['relovit_product_price'] ) ? wc_format_decimal( $_POST['relovit_product_price'] ) : '';

        $product->set_name( $title );
        $product->set_description( $description );
        $product->set_regular_price( $price );

        // Handle file uploads
        if ( ! function_exists( 'media_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
        }

        if ( isset( $_FILES['relovit_main_image'] ) && $_FILES['relovit_main_image']['size'] > 0 ) {
            $attachment_id = media_handle_upload( 'relovit_main_image', $product_id );
            if ( ! is_wp_error( $attachment_id ) ) {
                $product->set_image_id( $attachment_id );
            }
        }

        if ( isset( $_FILES['relovit_gallery_images'] ) && ! empty( $_FILES['relovit_gallery_images']['name'][0] ) ) {
            $files = $_FILES['relovit_gallery_images'];
            $gallery_ids = $product->get_gallery_image_ids();

            foreach ( $files['name'] as $key => $value ) {
                if ( $files['name'][$key] ) {
                    $_FILES['single_gallery_image'] = [
                        'name'     => $files['name'][$key],
                        'type'     => $files['type'][$key],
                        'tmp_name' => $files['tmp_name'][$key],
                        'error'    => $files['error'][$key],
                        'size'     => $files['size'][$key]
                    ];
                    $attachment_id = media_handle_upload( 'single_gallery_image', $product_id );
                    if ( ! is_wp_error( $attachment_id ) ) {
                        $gallery_ids[] = $attachment_id;
                    }
                }
            }
            $product->set_gallery_image_ids( $gallery_ids );
        }

        $product->save();

        wc_add_notice( __( 'Product saved successfully.', 'relovit' ), 'success' );
        wp_redirect( add_query_arg( [ 'action' => 'edit', 'product_id' => $product->get_id() ], wc_get_account_endpoint_url( 'relovit-products' ) ) );
        exit;
    }

    /**
     * Assigns the seller to the order based on the first product in the cart.
     *
     * @param \WC_Order $order The order object.
     * @param array     $data  The posted checkout data.
     */
    public function assign_seller_to_order( $order, $data ) {
        $items = $order->get_items();

        if ( empty( $items ) ) {
            return;
        }

        // Get the first item from the order.
        $first_item = reset( $items );
        $product_id = $first_item->get_product_id();

        if ( ! $product_id ) {
            return;
        }

        // Get the author of the product.
        $seller_id = get_post_field( 'post_author', $product_id );

        if ( $seller_id ) {
            // Store the seller ID as order meta.
            $order->update_meta_data( '_seller_id', $seller_id );
        }
    }

    /**
     * Save the settings from the "Relovit Settings" page.
     */
    public function relovit_save_settings() {
        if ( ! isset( $_POST['relovit_save_settings'] ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'relovit_save_settings' ) ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        if ( isset( $_POST['relovit_price_range'] ) ) {
            $price_range = sanitize_text_field( $_POST['relovit_price_range'] );
            update_user_meta( $user_id, 'relovit_price_range', $price_range );

            wc_add_notice( __( 'Settings saved successfully.', 'relovit' ), 'success' );
            wp_redirect( wc_get_account_endpoint_url( 'relovit-settings' ) );
            exit;
        }
    }

    /**
     * Add "Relovit Settings" to the My Account menu.
     *
     * @param array $items Menu items.
     * @return array
     */
    public function relovit_account_menu_items( $items ) {
        $relovit_items = [
            'relovit-dashboard' => __( 'Dashboard', 'relovit' ),
            'relovit-products'  => __( 'My Products', 'relovit' ),
            'relovit-settings'  => __( 'Relovit Settings', 'relovit' ),
        ];

        // Add the custom items after the 'orders' item.
        return array_slice( $items, 0, array_search( 'orders', array_keys( $items ) ) + 1, true ) +
               $relovit_items +
               array_slice( $items, array_search( 'orders', array_keys( $items ) ) + 1, null, true );
    }

    /**
     * Add endpoint for the "Relovit Settings" page.
     */
    public function relovit_add_my_account_endpoint() {
        add_rewrite_endpoint( 'relovit-dashboard', EP_PAGES );
        add_rewrite_endpoint( 'relovit-settings', EP_PAGES );
        add_rewrite_endpoint( 'relovit-products', EP_PAGES );
    }

    /**
     * Add the custom query var to the public query variables.
     *
     * @param array $vars The array of whitelisted query variables.
     * @return array
     */
    public function relovit_add_query_vars( $vars ) {
        $vars[] = 'relovit-dashboard';
        $vars[] = 'relovit-settings';
        $vars[] = 'relovit-products';
        $vars[] = 'action';
        $vars[] = 'product_id';
        return $vars;
    }

    /**
     * Display the content for the "Relovit Dashboard" page.
     */
    public function relovit_dashboard_content() {
        $product_stats      = $this->get_seller_product_stats();
        $sales_stats        = $this->get_seller_sales_stats();
        $processing_orders  = $this->get_seller_processing_orders();
        ?>
        <div class="relovit-quick-actions">
            <h3 style="margin: 0; flex-grow: 1;"><?php esc_html_e( 'Seller Dashboard', 'relovit' ); ?></h3>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'relovit-products' ) ); ?>" class="button"><?php esc_html_e( 'Add New Product', 'relovit' ); ?></a>
        </div>
        <p><?php esc_html_e( 'Welcome to your dashboard. Here you will find a summary of your sales and products.', 'relovit' ); ?></p>

        <?php $this->render_processing_orders_list( $processing_orders ); ?>

        <h4><?php esc_html_e( 'Sales Overview', 'relovit' ); ?></h4>
        <div class="relovit-stat-widgets">
            <div class="relovit-stat-widget">
                <span class="stat-value"><?php echo wp_kses_post( wc_price( $sales_stats->total_revenue ) ); ?></span>
                <span class="stat-label"><?php esc_html_e( 'Total Revenue', 'relovit' ); ?></span>
            </div>
            <div class="relovit-stat-widget">
                <span class="stat-value"><?php echo esc_html( $sales_stats->order_count ); ?></span>
                <span class="stat-label"><?php esc_html_e( 'Orders', 'relovit' ); ?></span>
            </div>
        </div>

        <h4><?php esc_html_e( 'Your Products', 'relovit' ); ?></h4>
        <div class="relovit-stat-widgets">
            <div class="relovit-stat-widget">
                <span class="stat-value"><?php echo esc_html( $product_stats->publish ); ?></span>
                <span class="stat-label"><?php esc_html_e( 'Online', 'relovit' ); ?></span>
            </div>
            <div class="relovit-stat-widget">
                <span class="stat-value"><?php echo esc_html( $product_stats->draft ); ?></span>
                <span class="stat-label"><?php esc_html_e( 'Drafts', 'relovit' ); ?></span>
            </div>
            <div class="relovit-stat-widget">
                <span class="stat-value"><?php echo esc_html( $product_stats->pending ); ?></span>
                <span class="stat-label"><?php esc_html_e( 'Pending Review', 'relovit' ); ?></span>
            </div>
        </div>

        <?php
        $this->render_dashboard_recent_products();
    }

    /**
     * Get product statistics for the current seller.
     *
     * @return object An object containing product counts by status.
     */
    private function get_seller_product_stats() {
        global $wpdb;
        $user_id = get_current_user_id();

        $stats = [
            'publish' => 0,
            'draft'   => 0,
            'pending' => 0,
        ];

        $sql = "
            SELECT post_status, COUNT( * ) AS num_posts
            FROM {$wpdb->posts}
            WHERE post_type = 'product'
            AND post_author = %d
            AND post_status IN ( 'publish', 'draft', 'pending' )
            GROUP BY post_status
        ";

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $user_id ) );

        if ( $results ) {
            foreach ( $results as $row ) {
                $stats[ $row->post_status ] = (int) $row->num_posts;
            }
        }

        return (object) $stats;
    }

    /**
     * Get sales statistics for the current seller.
     *
     * @return object An object containing total revenue and order count.
     */
    private function get_seller_sales_stats() {
        global $wpdb;
        $user_id = get_current_user_id();

        $sql = "
            SELECT
                SUM(pm.meta_value) as total_revenue,
                COUNT(p.ID) as order_count
            FROM
                {$wpdb->posts} as p
            INNER JOIN
                {$wpdb->postmeta} as pm ON p.ID = pm.post_id
            INNER JOIN
                {$wpdb->postmeta} as pm2 ON p.ID = pm2.post_id
            WHERE
                p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-processing')
                AND pm.meta_key = '_order_total'
                AND pm2.meta_key = '_seller_id'
                AND pm2.meta_value = %d
        ";

        $results = $wpdb->get_row( $wpdb->prepare( $sql, $user_id ) );

        return (object) [
            'total_revenue' => $results->total_revenue ?? 0,
            'order_count'   => $results->order_count ?? 0,
        ];
    }

    /**
     * Get processing orders for the current seller.
     *
     * @return \WC_Order[] An array of order objects.
     */
    private function get_seller_processing_orders() {
        return wc_get_orders( [
            'status'     => 'wc-processing',
            'meta_key'   => '_seller_id',
            'meta_value' => get_current_user_id(),
            'orderby'    => 'date',
            'order'      => 'DESC',
        ] );
    }

    /**
     * Render the recent products list for the dashboard.
     */
    private function render_dashboard_recent_products() {
        $products_query = new \WP_Query( [
            'author'         => get_current_user_id(),
            'post_type'      => 'product',
            'posts_per_page' => 5,
            'post_status'    => [ 'draft', 'pending', 'publish' ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        if ( ! $products_query->have_posts() ) {
            return;
        }
        ?>
        <h4><?php esc_html_e( 'Recent Products', 'relovit' ); ?></h4>
        <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
            <thead>
                <tr>
                    <th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Product', 'relovit' ); ?></th>
                    <th class="woocommerce-table__product-table product-status"><?php esc_html_e( 'Status', 'relovit' ); ?></th>
                    <th class="woocommerce-table__product-table product-actions"><?php esc_html_e( 'Actions', 'relovit' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                while ( $products_query->have_posts() ) {
                    $products_query->the_post();
                    $product = wc_get_product( get_the_ID() );
                    $edit_url = add_query_arg( [
                        'action'     => 'edit',
                        'product_id' => $product->get_id(),
                    ], wc_get_account_endpoint_url( 'relovit-products' ) );
                    ?>
                    <tr class="woocommerce-table__line-item order_item">
                        <td class="woocommerce-table__product-name product-name">
                            <a href="<?php echo esc_url( $edit_url ); ?>"><?php the_title(); ?></a>
                        </td>
                        <td class="woocommerce-table__product-status product-status">
                            <?php
                            $status_object = get_post_status_object( $product->get_status() );
                            echo esc_html( $status_object->label ?? ucfirst( $product->get_status() ) );
                            ?>
                        </td>
                        <td class="woocommerce-table__product-actions product-actions">
                            <a href="<?php echo esc_url( $edit_url ); ?>" class="button edit"><?php esc_html_e( 'Edit', 'relovit' ); ?></a>
                            <a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" class="button view" target="_blank"><?php esc_html_e( 'View', 'relovit' ); ?></a>
                        </td>
                    </tr>
                    <?php
                }
                wp_reset_postdata();
                ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render the processing orders list for the dashboard.
     *
     * @param \WC_Order[] $orders An array of order objects.
     */
    private function render_processing_orders_list( $orders ) {
        ?>
        <h4><?php esc_html_e( 'Orders to Process', 'relovit' ); ?></h4>
        <?php if ( empty( $orders ) ) : ?>
            <p><?php esc_html_e( 'You have no orders to process at the moment.', 'relovit' ); ?></p>
        <?php else : ?>
            <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                <thead>
                    <tr>
                        <th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Order', 'relovit' ); ?></th>
                        <th class="woocommerce-table__product-table product-date"><?php esc_html_e( 'Date', 'relovit' ); ?></th>
                        <th class="woocommerce-table__product-table product-customer"><?php esc_html_e( 'Customer', 'relovit' ); ?></th>
                        <th class="woocommerce-table__product-table product-total"><?php esc_html_e( 'Total', 'relovit' ); ?></th>
                        <th class="woocommerce-table__product-table product-actions"><?php esc_html_e( 'Actions', 'relovit' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $orders as $order ) : ?>
                        <tr class="woocommerce-table__line-item order_item">
                            <td class="woocommerce-table__product-name product-name">
                                <a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>" target="_blank">
                                    #<?php echo esc_html( $order->get_order_number() ); ?>
                                </a>
                            </td>
                            <td class="woocommerce-table__product-date product-date">
                                <time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( $order->get_date_created()->date_i18n( 'd M Y' ) ); ?></time>
                            </td>
                            <td class="woocommerce-table__product-customer product-customer">
                                <?php echo esc_html( $order->get_formatted_billing_full_name() ); ?>
                            </td>
                            <td class="woocommerce-table__product-total product-total">
                                <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
                            </td>
                            <td class="woocommerce-table__product-actions product-actions">
                                <a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>" class="button view" target="_blank"><?php esc_html_e( 'View Details', 'relovit' ); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    /**
     * Display the content for the "Relovit Products" page.
     */
    public function relovit_products_content() {
        if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && ! empty( $_GET['product_id'] ) ) {
            // Enqueue the script for the edit page.
            $product_id = intval( $_GET['product_id'] );
            wp_enqueue_script( 'relovit-edit-product', RELOVIT_PLUGIN_URL . 'assets/js/my-account-edit-product.js', [ 'jquery' ], RELOVIT_VERSION, true );
            wp_localize_script( 'relovit-edit-product', 'relovit_edit_product', [
                'api_url'           => esc_url_raw( rest_url( 'relovit/v1/enrich-product' ) ),
                'nonce'             => wp_create_nonce( 'wp_rest' ),
                'product_id'        => $product_id,
                'no_tasks_selected' => __( 'Please select at least one AI task.', 'relovit' ),
                'error_message'     => __( 'An error occurred. Please try again.', 'relovit' ),
            ] );

            $this->relovit_render_edit_product_form();
        } else {
            // Enqueue the script for the products list page.
            wp_enqueue_script( 'relovit-my-products', RELOVIT_PLUGIN_URL . 'assets/js/my-products.js', [ 'jquery' ], RELOVIT_VERSION, true );
            wp_localize_script( 'relovit-my-products', 'relovit_my_products', [
                'delete_url'     => esc_url_raw( rest_url( 'relovit/v1/products/' ) ),
                'nonce'          => wp_create_nonce( 'wp_rest' ),
                'confirm_delete' => __( 'Are you sure you want to delete this product?', 'relovit' ),
            ] );

            $this->relovit_render_products_list();
        }
    }

    /**
     * Render the list of products.
     */
    private function relovit_render_products_list() {
        wc_print_notices();
        $user_id = get_current_user_id();
        $args = [
            'author' => $user_id,
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => ['draft', 'pending', 'publish']
        ];
        $products_query = new \WP_Query($args);

        if ( ! $products_query->have_posts() ) {
            echo '<h3>' . esc_html__( 'My Products', 'relovit' ) . '</h3>';
            echo '<p>' . esc_html__( 'You have not created any products yet.', 'relovit' ) . '</p>';
            return;
        }
        ?>
        <h3><?php esc_html_e( 'My Products', 'relovit' ); ?></h3>
        <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
            <thead>
                <tr>
                    <th class="woocommerce-table__product-name product-thumbnail"><?php esc_html_e( 'Image', 'relovit' ); ?></th>
                    <th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Product', 'relovit' ); ?></th>
                    <th class="woocommerce-table__product-table product-status"><?php esc_html_e( 'Status', 'relovit' ); ?></th>
                    <th class="woocommerce-table__product-table product-price"><?php esc_html_e( 'Price', 'relovit' ); ?></th>
                    <th class="woocommerce-table__product-table product-actions"><?php esc_html_e( 'Actions', 'relovit' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                while ( $products_query->have_posts() ) {
                    $products_query->the_post();
                    $product = wc_get_product( get_the_ID() );
                    ?>
                    <tr class="woocommerce-table__line-item order_item">
                        <td class="woocommerce-table__product-thumbnail product-thumbnail">
                            <?php echo $product->get_image( 'thumbnail' ); ?>
                        </td>
                        <td class="woocommerce-table__product-name product-name">
                            <a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit', 'product_id' => $product->get_id() ], wc_get_account_endpoint_url( 'relovit-products' ) ) ); ?>"><?php the_title(); ?></a>
                        </td>
                        <td class="woocommerce-table__product-status product-status">
                            <?php
                            $status_object = get_post_status_object( $product->get_status() );
                            echo esc_html( $status_object->label ?? ucfirst( $product->get_status() ) );
                            ?>
                        </td>
                        <td class="woocommerce-table__product-price product-price">
                            <?php echo wp_kses_post( $product->get_price_html() ); ?>
                        </td>
                        <td class="woocommerce-table__product-actions product-actions">
                            <a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit', 'product_id' => $product->get_id() ], wc_get_account_endpoint_url( 'relovit-products' ) ) ); ?>" class="button edit"><?php esc_html_e( 'Edit', 'relovit' ); ?></a>
                            <a href="#" class="button delete" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"><?php esc_html_e( 'Delete', 'relovit' ); ?></a>
                        </td>
                    </tr>
                    <?php
                }
                wp_reset_postdata();
                ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render the edit product form.
     */
    private function relovit_render_edit_product_form() {
        $product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;
        $product = wc_get_product( $product_id );

        if ( ! $product || get_post_field( 'post_author', $product->get_id() ) != get_current_user_id() ) {
            wc_add_notice( __( 'Invalid product.', 'relovit' ), 'error' );
            echo '<a href="' . esc_url( wc_get_account_endpoint_url( 'relovit-products' ) ) . '">' . __( 'Go back to your products', 'relovit' ) . '</a>';
            return;
        }

        ?>
        <h3><?php printf( esc_html__( 'Edit Product: %s', 'relovit' ), $product->get_name() ); ?></h3>
        <form id="relovit-edit-product-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'relovit_edit_product' ); ?>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="relovit_product_title"><?php esc_html_e( 'Title', 'relovit' ); ?></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="relovit_product_title" id="relovit_product_title" value="<?php echo esc_attr( $product->get_name() ); ?>">
            </p>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="relovit_product_description"><?php esc_html_e( 'Description', 'relovit' ); ?></label>
                <textarea class="woocommerce-Input woocommerce-Input--textarea input-text" name="relovit_product_description" id="relovit_product_description" rows="5"><?php echo esc_textarea( $product->get_description() ); ?></textarea>
            </p>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="relovit_product_price"><?php esc_html_e( 'Price', 'relovit' ); ?></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="relovit_product_price" id="relovit_product_price" value="<?php echo esc_attr( $product->get_price() ); ?>">
            </p>
            <div class="relovit-product-images">
                <h4><?php esc_html_e( 'Product Images', 'relovit' ); ?></h4>
                <div class="relovit-main-image">
                    <h5><?php esc_html_e( 'Main Image', 'relovit' ); ?></h5>
                    <?php echo $product->get_image(); ?>
                    <p>
                        <label for="relovit_main_image"><?php esc_html_e( 'Change main image', 'relovit' ); ?></label>
                        <input type="file" name="relovit_main_image" id="relovit_main_image">
                    </p>
                </div>
                <div class="relovit-gallery-images">
                    <h5><?php esc_html_e( 'Gallery Images', 'relovit' ); ?></h5>
                    <div class="relovit-gallery-thumbnails">
                        <?php
                        $gallery_ids = $product->get_gallery_image_ids();
                        foreach ( $gallery_ids as $gallery_id ) {
                            echo wp_get_attachment_image( $gallery_id, 'thumbnail' );
                        }
                        ?>
                    </div>
                    <p>
                        <label for="relovit_gallery_images"><?php esc_html_e( 'Add gallery images', 'relovit' ); ?></label>
                        <input type="file" name="relovit_gallery_images[]" id="relovit_gallery_images" multiple>
                    </p>
                </div>
            </div>
            <div id="relovit-ai-enrichment" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <h3><?php esc_html_e( 'AI Enrichment', 'relovit' ); ?></h3>
                <p><?php esc_html_e( 'Use AI to improve your product listing. Select the tasks you want to perform and click "Enrich with AI".', 'relovit' ); ?></p>
                <div id="relovit-ai-tasks">
                    <p>
                        <label><input type="checkbox" name="relovit_tasks[]" value="description"> <?php esc_html_e( 'Generate Description', 'relovit' ); ?></label>
                    </p>
                    <p>
                        <label><input type="checkbox" name="relovit_tasks[]" value="price"> <?php esc_html_e( 'Suggest Price', 'relovit' ); ?></label>
                    </p>
                    <p>
                        <label><input type="checkbox" name="relovit_tasks[]" value="category"> <?php esc_html_e( 'Suggest Category', 'relovit' ); ?></label>
                    </p>
                    <p>
                        <label><input type="checkbox" name="relovit_tasks[]" value="image"> <?php esc_html_e( 'Generate a new main image', 'relovit' ); ?></label>
                    </p>
                </div>
                <div id="relovit-ai-spinner" style="display: none;">
                    <p><?php esc_html_e( 'AI is working, please wait...', 'relovit' ); ?></p>
                </div>
            </div>
            <p>
                <button type="submit" class="woocommerce-Button button" name="relovit_save_product" value="<?php esc_attr_e( 'Save changes', 'relovit' ); ?>"><?php esc_html_e( 'Save changes', 'relovit' ); ?></button>
                <button type="button" id="relovit-enrich-btn" class="woocommerce-Button button"><?php esc_html_e( 'Enrich with AI', 'relovit' ); ?></button>
                <input type="hidden" name="relovit_product_id" value="<?php echo esc_attr( $product_id ); ?>">
            </p>
        </form>
        <?php
    }

    /**
     * Display the content for the "Relovit Settings" page.
     */
    public function relovit_settings_content() {
        wc_print_notices();
        $user_id = get_current_user_id();
        $current_price_range = get_user_meta( $user_id, 'relovit_price_range', true );
        ?>
        <h3><?php esc_html_e( 'Relovit Settings', 'relovit' ); ?></h3>
        <form method="post">
            <?php wp_nonce_field( 'relovit_save_settings' ); ?>
            <p>
                <label for="relovit_price_range"><?php esc_html_e( 'Default Price Range', 'relovit' ); ?></label>
                <select id="relovit_price_range" name="relovit_price_range" class="woocommerce-Input woocommerce-Input--select">
                    <option value="Bon marché" <?php selected( $current_price_range, 'Bon marché' ); ?>><?php esc_html_e( 'Inexpensive', 'relovit' ); ?></option>
                    <option value="Moyen" <?php selected( $current_price_range, 'Moyen' ); ?>><?php esc_html_e( 'Average', 'relovit' ); ?></option>
                    <option value="Cher" <?php selected( $current_price_range, 'Cher' ); ?>><?php esc_html_e( 'Expensive', 'relovit' ); ?></option>
                </select>
            </p>
            <p>
                <button type="submit" class="woocommerce-Button button" name="relovit_save_settings" value="<?php esc_attr_e( 'Save changes', 'relovit' ); ?>"><?php esc_html_e( 'Save changes', 'relovit' ); ?></button>
            </p>
        </form>
        <?php
    }

    /**
     * Register shortcodes.
     */
    public function register_shortcodes() {
        add_shortcode( 'relovit_upload_form', [ $this, 'render_upload_form' ] );
    }

    /**
     * Render the upload form.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_upload_form( $atts ) {
        ob_start();
        ?>
        <div id="relovit-app">
            <h2>Vendez vos objets en un clin d'œil</h2>
            <p>Téléversez une photo de vos objets et laissez notre IA faire le reste !</p>
            <div class="relovit-upload-options">
                 <button type="button" id="relovit-capture-btn" class="button">Prendre une photo</button>
                 <button type="button" id="relovit-library-btn" class="button">Choisir dans la photothèque</button>
            </div>
            <form id="relovit-upload-form" enctype="multipart/form-data" style="display: none;">
                <input type="file" id="relovit-image-upload" name="relovit_image" accept="image/*">
            </form>
             <div id="relovit-image-preview-container" style="display:none; text-align: center; margin-top: 15px;">
                <img id="relovit-image-preview" src="" alt="Aperçu de l'image" style="max-width: 100%; max-height: 300px; border: 1px solid #ddd; padding: 5px;"/>
                <button type="submit" form="relovit-upload-form" id="relovit-submit-btn" class="button button-primary" style="margin-top: 10px;">Identifier les objets</button>
             </div>
            <div id="relovit-results"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue scripts and styles.
     */
    public function enqueue_scripts() {
        global $post;

        // Enqueue dashboard styles.
        if ( is_account_page() && is_wc_endpoint_url( 'relovit-dashboard' ) ) {
            wp_enqueue_style(
                'relovit-dashboard',
                RELOVIT_PLUGIN_URL . 'assets/css/dashboard.css',
                [],
                RELOVIT_VERSION
            );
        }

        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'relovit_upload_form' ) ) {
            wp_enqueue_script(
                'relovit-upload-form',
                RELOVIT_PLUGIN_URL . 'assets/js/upload-form.js',
                [ 'jquery' ],
                RELOVIT_VERSION,
                true
            );

            wp_localize_script(
                'relovit-upload-form',
                'relovit_ajax',
                [
                    'identify_url' => rest_url( 'relovit/v1/identify-objects' ),
                    'create_url'   => rest_url( 'relovit/v1/create-products' ),
                    'nonce'        => wp_create_nonce( 'wp_rest' ),
                ]
            );
        }
    }
}