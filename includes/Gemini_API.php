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
    private function get_api_url() {
        $api_key = get_option( 'relovit_gemini_api_key' );
        if ( empty( $api_key ) ) {
            return new \WP_Error( 'api_key_missing', __( 'The Gemini API key is missing. Please add it in the Relovit settings.', 'relovit' ) );
        }
        return 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;
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

        $image_mime_type = mime_content_type( $image_path );
        $image_data      = base64_encode( file_get_contents( $image_path ) );

        $body = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => "Listez tous les objets distincts et vendables présents dans cette image. Répondez uniquement avec une liste d'éléments séparés par des virgules.",
                        ],
                        [
                            'inline_data' => [
                                'mime_type' => $image_mime_type,
                                'data'      => $image_data,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $api_url = $this->get_api_url();
        if ( is_wp_error( $api_url ) ) {
            return $api_url;
        }

        $response = wp_remote_post(
            $api_url,
            [
                'body'    => json_encode( $body ),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
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
            return new \WP_Error( 'api_invalid_response', __( 'Invalid response from Gemini API. The API key might be invalid or the API might be unavailable.', 'relovit' ), $data );
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'];
        $items = array_map( 'trim', explode( ',', $text ) );

        return $items;
    }
}