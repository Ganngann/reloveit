<?php
/**
 * Product Manager class.
 *
 * @package Relovit
 */

namespace Relovit;

/**
 * Class Product_Manager
 *
 * @package Relovit
 */
class Product_Manager {

    /**
     * Create draft products in WooCommerce.
     *
     * @param array $items Array of product names.
     * @param int   $image_id Attachment ID of the uploaded image.
     * @return int Number of products created.
     */
    public function create_draft_products( $items, $image_id ) {
        $products_created = 0;

        foreach ( $items as $item_name ) {
            $product = new \WC_Product_Simple();
            $product->set_name( sanitize_text_field( $item_name ) );
            $product->set_status( 'draft' );
            $product->set_image_id( $image_id );

            // Add a meta key to identify our products.
            $product->add_meta_data( '_relovit_product', true );

            $product_id = $product->save();

            if ( $product_id ) {
                $products_created++;
            }
        }

        return $products_created;
    }
}