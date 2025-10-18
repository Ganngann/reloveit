<?php
/**
 * Gemini API handler class.
 *
 * @package Relovit
 */

namespace Relovit;

/**
 * Class Gemini_API
 *
 * @package Relovit
 */
class Gemini_API {

    /**
     * Get the Gemini API URL.
     *
     * @return string|\WP_Error
     */
    private function get_api_url( $model = 'gemini-2.5-flash' ) {
        $api_key = Settings::get( 'gemini_api_key' );
        if ( empty( $api_key ) ) {
            return new \WP_Error( 'api_key_missing', __( 'The Gemini API key is missing. Please add it in the Relovit settings.', 'relovit' ) );
        }
        return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $api_key;
    }

    /**
     * Replace placeholders in a string.
     *
     * @param string $string The string with placeholders.
     * @param array  $replacements An associative array of placeholder => value.
     * @return string The string with placeholders replaced.
     */
    private function replace_placeholders( $string, $replacements = [] ) {
        $defaults = [
            '{language}'      => Settings::get( 'language', 'français' ),
            '{store_context}' => Settings::get( 'store_context' ),
            '{price_min}'     => Settings::get( 'price_min', 0 ),
            '{price_max}'     => Settings::get( 'price_max', 1000 ),
        ];
        $replacements = wp_parse_args( $replacements, $defaults );

        foreach ( $replacements as $placeholder => $value ) {
            $string = str_replace( $placeholder, $value, $string );
        }
        return $string;
    }

    /**
     * Identify objects in an image.
     *
     * @param string $image_path Path to the image file.
     * @return array|WP_Error
     */
    public function identify_objects( $image_path ) {
        if ( ! file_exists( $image_path ) ) {
            return new \WP_Error( 'file_not_found', 'The image file was not found.' );
        }

        $prompt = $this->replace_placeholders( Settings::get( 'prompt_identify' ) );

        $response_text = $this->call_vision_api( $prompt, [ $image_path ], false );

        if ( is_wp_error( $response_text ) ) {
            return $response_text;
        }

        $items = array_map( 'trim', explode( ',', $response_text ) );
        return $items;
    }

    /**
     * Generate a product description from images.
     *
     * @param string $product_name The name of the product.
     * @param array  $image_paths  An array of paths to the image files.
     * @return string|WP_Error
     */
    public function generate_description( $product_name, $image_paths ) {
        $prompt = $this->replace_placeholders(
            Settings::get( 'prompt_description' ),
            [ '{product_name}' => esc_html( $product_name ) ]
        );
        return $this->call_vision_api( $prompt, $image_paths );
    }

    /**
     * Generate a product price from images.
     *
     * @param string $product_name The name of the product.
     * @param array  $image_paths  An array of paths to the image files.
     * @return string|WP_Error
     */
    public function generate_price( $product_name, $image_paths ) {
        $prompt = $this->replace_placeholders(
            Settings::get( 'prompt_price' ),
            [ '{product_name}' => esc_html( $product_name ) ]
        );
        return $this->call_vision_api( $prompt, $image_paths );
    }

    /**
     * Generate product categories and tags from images.
     *
     * @param string $product_name The name of the product.
     * @param array  $image_paths  An array of paths to the image files.
     * @return array|WP_Error
     */
    public function generate_taxonomy_terms( $product_name, $image_paths ) {
        $category_tree = $this->get_category_tree_text();
        $prompt = $this->replace_placeholders(
            Settings::get( 'prompt_taxonomy' ),
            [
                '{product_name}'  => esc_html( $product_name ),
                '{category_tree}' => $category_tree,
            ]
        );

        $response_json = $this->call_vision_api( $prompt, $image_paths, true );

        if ( is_wp_error( $response_json ) ) {
            return $response_json;
        }

        $result = json_decode( $response_json, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'json_decode_error', 'Could not decode the JSON response from the API.', [ 'response' => $response_json ] );
        }
        return $result;
    }

    /**
     * Generate a new product image.
     *
     * @param string $image_path Path to the original image.
     * @return string|WP_Error Base64 encoded image data or an error.
     */
    public function generate_image( $image_path ) {
        $prompt  = $this->replace_placeholders( Settings::get( 'prompt_image' ) );
        $api_url = $this->get_api_url( 'gemini-2.5-flash-image' );

        if ( is_wp_error( $api_url ) ) {
            return $api_url;
        }

        $body = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $prompt ],
                        [
                            'inline_data' => [
                                'mime_type' => mime_content_type( $image_path ),
                                'data'      => base64_encode( file_get_contents( $image_path ) ),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = wp_remote_post(
            $api_url,
            [
                'body'    => json_encode( $body ),
                'headers' => [ 'Content-Type' => 'application/json' ],
                'timeout' => 180,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'image_gen_request_failed', $response->get_error_message() );
        }

        $response_body = wp_remote_retrieve_body( $response );
        $data          = json_decode( $response_body, true );

        if ( isset( $data['error'] ) ) {
            return new \WP_Error( 'image_gen_api_error', $data['error']['message'], $data );
        }

        if ( ! isset( $data['candidates'][0]['content']['parts'] ) ) {
            return new \WP_Error( 'image_gen_invalid_response', __( 'Invalid response from Image Generation API.', 'relovit' ), $data );
        }

        foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
            if ( isset( $part['inlineData']['data'] ) ) {
                return $part['inlineData']['data'];
            }
        }

        return new \WP_Error( 'image_gen_no_image_data', __( 'No image data found in the API response.', 'relovit' ), $data );
    }

    /**
     * Call the Gemini Vision API with a prompt and images.
     *
     * @param string $prompt      The text prompt.
     * @param array  $image_paths An array of paths to the image files.
     * @param bool   $json_mode   Whether to expect a JSON response.
     * @return string|WP_Error
     */
    private function call_vision_api( $prompt, $image_paths, $json_mode = false ) {
        $api_url = $this->get_api_url();
        if ( is_wp_error( $api_url ) ) {
            return $api_url;
        }

        $parts = [ [ 'text' => $prompt ] ];
        foreach ( $image_paths as $image_path ) {
            if ( file_exists( $image_path ) ) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => mime_content_type( $image_path ),
                        'data'      => base64_encode( file_get_contents( $image_path ) ),
                    ],
                ];
            }
        }

        $body = [ 'contents' => [ [ 'parts' => $parts ] ] ];
        if ( $json_mode ) {
            $body['generationConfig'] = [ 'responseMimeType' => 'application/json' ];
        }

        $response = wp_remote_post(
            $api_url,
            [
                'body'    => json_encode( $body ),
                'headers' => [ 'Content-Type' => 'application/json' ],
                'timeout' => 60,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'api_request_failed', $response->get_error_message() );
        }

        $response_body = wp_remote_retrieve_body( $response );
        $data          = json_decode( $response_body, true );

        if ( isset( $data['error'] ) ) {
            return new \WP_Error( 'gemini_api_error', $data['error']['message'], $data );
        }

        if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            return new \WP_Error( 'api_invalid_response', __( 'Invalid response from Gemini API.', 'relovit' ), $data );
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'];

        if ( $json_mode ) {
            $text = preg_replace( '/^```(json)?\s*/', '', $text );
            $text = preg_replace( '/\s*```$/', '', $text );
        }

        return trim( $text );
    }

    /**
     * Helper function to get a text representation of the product category tree.
     */
    private function get_category_tree_text( $parent_id = 0, $prefix = '' ) {
        $terms = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'parent'     => $parent_id,
        ] );

        $tree = '';
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $tree .= $prefix . $term->name . "\n";
                $tree .= $this->get_category_tree_text( $term->term_id, $prefix . '- ' );
            }
        }
        return $tree;
    }
}