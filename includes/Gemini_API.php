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

    /**
     * Generate a product description from images.
     *
     * @param string $product_name The name of the product.
     * @param array  $image_paths  An array of paths to the image files.
     * @return string|WP_Error
     */
    public function generate_description( $product_name, $image_paths ) {
        $prompt = "En tant qu'expert en vente d'occasion, rédigez une description détaillée, honnête et commerciale d'un " . esc_html( $product_name ) . " basé sur les images fournies, en insistant sur son état et sa valeur pour un acheteur d'occasion. Utilisez un ton engageant. Maximum 300 mots.";
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
        $prompt = "En tenant compte du marché actuel des objets d'occasion et de l'état apparent de l'objet " . esc_html( $product_name ) . " dans ces images, proposez un prix de vente raisonnable en EUR. Répondez uniquement avec le prix au format numérique (exemple: 45.99).";
        return $this->call_vision_api( $prompt, $image_paths );
    }

    /**
     * Call the Gemini Vision API with a prompt and images.
     *
     * @param string $prompt      The text prompt.
     * @param array  $image_paths An array of paths to the image files.
     * @return string|WP_Error
     */
    private function call_vision_api( $prompt, $image_paths ) {
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

        $body = [
			'contents' => [ [ 'parts' => $parts ] ],
			'generationConfig' => [
				'responseMimeType' => 'application/json',
			]
		];

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

        // Clean the response: remove markdown code blocks if present.
        $text = preg_replace( '/^```(html|json)?\s*/', '', $text );
        $text = preg_replace( '/\s*```$/', '', $text );

        return trim( $text );
    }

    /**
     * Helper function to get a text representation of the product category tree.
     *
     * @param int    $parent_id The parent category ID.
     * @param string $prefix    The prefix for the current level.
     * @return string The text representation of the category tree.
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

    /**
     * Generate product categories (hierarchical) and tags from images.
     *
     * @param string $product_name The name of the product.
     * @param array  $image_paths  An array of paths to the image files.
     * @return array|WP_Error An array containing 'category' (array of names) and 'tags' (array of strings), or an error.
     */
    public function generate_taxonomy_terms( $product_name, $image_paths ) {
        $category_tree = $this->get_category_tree_text();
        $prompt        = "En tant qu'expert en e-commerce et SEO, analyse le produit '" . esc_html( $product_name ) . "' à partir des images fournies.
En te basant sur l'arborescence de catégories suivante :
---
$category_tree
---
1.  **Catégorie :** Choisis le chemin de catégorie le plus pertinent. Si une catégorie adéquate n'existe pas, tu peux en proposer une nouvelle. Le chemin doit être une liste de noms, du parent à l'enfant (ex: [\"Vêtements\", \"Chemises\"]).
2.  **Tags :** Suggère une liste de 3 à 5 tags pertinents (en français) qui décrivent les caractéristiques, le style, le matériau ou l'usage de l'objet (ex: [\"coton\", \"manches longues\", \"formel\", \"vintage\"]).

Réponds **uniquement** avec un objet JSON valide contenant deux clés : 'category' (un tableau de chaînes de caractères pour le chemin de la catégorie) et 'tags' (un tableau de chaînes de caractères pour les tags).
Exemple de format de réponse :
{
  \"category\": [\"Maison\", \"Décoration\", \"Vases\"],
  \"tags\": [\"céramique\", \"moderne\", \"décoratif\", \"fleurs\"]
}";

        $response_json = $this->call_vision_api( $prompt, $image_paths );

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
     * Generate a new product image with a clean background.
     *
     * @param string $image_path Path to the original image.
     * @return string|WP_Error The URL of the generated image, or an error.
     */
    public function generate_image( $image_path ) {
        $api_key = get_option( 'relovit_gemini_api_key' );
        if ( empty( $api_key ) ) {
            return new \WP_Error( 'api_key_missing', __( 'The Gemini API key is missing.', 'relovit' ) );
        }

        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent?key=' . $api_key;
        $prompt  = "Extrayez l'objet principal de cette image et placez-le sur un fond de studio blanc et propre. L'image générée doit être photoréaliste et de haute qualité.";

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
                'timeout' => 180, // Image generation can be very slow.
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
            return new \WP_Error( 'image_gen_invalid_response', __( 'Invalid response from Image Generation API. No parts found.', 'relovit' ), $data );
        }

        foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
            if ( isset( $part['inlineData']['data'] ) ) {
                return $part['inlineData']['data'];
            }
        }

        return new \WP_Error( 'image_gen_no_image_data', __( 'No image data found in the API response.', 'relovit' ), $data );
    }
}