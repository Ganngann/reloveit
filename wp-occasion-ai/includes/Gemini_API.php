<?php
/**
 * Gemini API handler class.
 *
 * @package WPOccasionAI
 */

namespace WPOccasionAI;

/**
 * Class Gemini_API
 *
 * @package WPOccasionAI
 */
class Gemini_API {

    // TODO: Move this to a settings page in the WP admin.
    private const GEMINI_API_KEY = 'YOUR_GEMINI_API_KEY';
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro-vision:generateContent?key=' . self::GEMINI_API_KEY;

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

        $response = wp_remote_post(
            self::GEMINI_API_URL,
            [
                'body'    => json_encode( $body ),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 60,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_body = wp_remote_retrieve_body( $response );
        $data          = json_decode( $response_body, true );

        if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            return new \WP_Error( 'api_error', 'Invalid response from Gemini API.', $data );
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'];
        $items = array_map( 'trim', explode( ',', $text ) );

        return $items;
    }
}