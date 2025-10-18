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
     */
    private function get_api_url( $model = 'gemini-2.5-flash' ) {
        $api_key = Settings::get( 'gemini_api_key' );
        if ( empty( $api_key ) ) {
            return new \WP_Error( 'api_key_missing', __( 'The Gemini API key is missing. Please add it in the Relovit settings.', 'relovit' ) );
        }
        return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $api_key;
    }

    /**
     * Identify objects in an image.
     */
    public function identify_objects( $image_path ) {
        if ( ! file_exists( $image_path ) ) {
            return new \WP_Error( 'file_not_found', 'The image file was not found.' );
        }
        // This prompt is purely technical and not exposed to the user.
        $prompt = "Listez tous les objets distincts et vendables présents dans cette image. Répondez uniquement avec une liste d'éléments séparés par des virgules.";
        $response_text = $this->call_vision_api( $prompt, [ $image_path ], false );

        if ( is_wp_error( $response_text ) ) {
            return $response_text;
        }

        $items = array_map( 'trim', explode( ',', $response_text ) );
        return $items;
    }

    /**
     * Generate a product description from images.
     */
    public function generate_description( $product_name, $image_paths ) {
        $tone = Settings::get('desc_tone');
        $keywords = Settings::get('desc_keywords');
        $language = Settings::get('language');
        $store_context = Settings::get('store_context');

        $prompt = "En tant qu'expert en vente d'occasion, rédigez une description " . esc_html($tone) . " d'un " . esc_html( $product_name ) . " basé sur les images fournies, " . esc_html($keywords) . ". Utilisez un ton engageant. Maximum 300 mots. Langue de la réponse : " . esc_html($language) . ". Contexte de la boutique : " . esc_html($store_context) . ".";

        return $this->call_vision_api( $prompt, $image_paths );
    }

    /**
     * Generate a product price from images.
     */
    public function generate_price( $product_name, $image_paths, $price_range = 'Moyen' ) {
        $price_range_text = Settings::get_price_range_prompt_text( $price_range );
        $store_context = Settings::get('store_context');

        $prompt = "En tenant compte du marché actuel des objets d'occasion, de l'état apparent de l'objet " . esc_html( $product_name ) . " dans ces images et du contexte de la boutique (" . esc_html($store_context) . "), proposez un prix dans la gamme '" . esc_html($price_range_text) . "' en EUR. Répondez uniquement avec le prix au format numérique (exemple: 45.99).";

        return $this->call_vision_api( $prompt, $image_paths );
    }

    /**
     * Generate product categories and tags from images.
     */
    public function generate_taxonomy_terms( $product_name, $image_paths ) {
        $category_tree = $this->get_category_tree_text();
        $language = Settings::get('language');
        $seo_focus = Settings::get('taxonomy_seo_focus');

        $prompt = "En tant qu'expert en e-commerce et SEO, analyse le produit '" . esc_html( $product_name ) . "' à partir des images fournies.
En te basant sur l'arborescence de catégories suivante :
---
$category_tree
---
1.  **Catégorie :** Choisis le chemin de catégorie le plus pertinent. Si une catégorie adéquate n'existe pas, tu peux en proposer une nouvelle. Le chemin doit être une liste de noms, du parent à l'enfant (ex: [\"Vêtements\", \"Chemises\"]).
2.  **Tags :** Suggère une liste de 3 à 5 tags " . esc_html($seo_focus) . " (en " . esc_html($language) . ") qui décrivent les caractéristiques, le style, le matériau ou l'usage de l'objet (ex: [\"coton\", \"manches longues\", \"formel\", \"vintage\"]).

Réponds **uniquement** avec un objet JSON valide contenant deux clés : 'category' (un tableau de chaînes de caractères pour le chemin de la catégorie) et 'tags' (un tableau de chaînes de caractères pour les tags).";

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
     */
    public function generate_image( $image_path ) {
        $bg_style = Settings::get('image_bg_style');
        $prompt  = "Extrayez l'objet principal de cette image et placez-le " . esc_html($bg_style) . ". L'image générée doit être photoréaliste et de haute qualité.";
        $api_url = $this->get_api_url( 'gemini-2.5-flash-image' );

        if ( is_wp_error( $api_url ) ) {
            return $api_url;
        }

        $body = [
            'contents' => [ [ 'parts' => [ [ 'text' => $prompt ], [ 'inline_data' => [ 'mime_type' => mime_content_type( $image_path ), 'data' => base64_encode( file_get_contents( $image_path ) ) ] ] ] ] ],
        ];

        $response = wp_remote_post(
            $api_url,
            [ 'body' => json_encode( $body ), 'headers' => [ 'Content-Type' => 'application/json' ], 'timeout' => 180 ]
        );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'image_gen_request_failed', $response->get_error_message() );
        }

        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

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
     */
    private function call_vision_api( $prompt, $image_paths, $json_mode = false ) {
        $api_url = $this->get_api_url();
        if ( is_wp_error( $api_url ) ) {
            return $api_url;
        }

        $parts = [ [ 'text' => $prompt ] ];
        foreach ( $image_paths as $image_path ) {
            if ( file_exists( $image_path ) ) {
                $parts[] = [ 'inline_data' => [ 'mime_type' => mime_content_type( $image_path ), 'data' => base64_encode( file_get_contents( $image_path ) ) ] ];
            }
        }

        $body = [ 'contents' => [ [ 'parts' => $parts ] ] ];
        if ( $json_mode ) {
            $body['generationConfig'] = [ 'responseMimeType' => 'application/json' ];
        }

        $response = wp_remote_post(
            $api_url,
            [ 'body' => json_encode( $body ), 'headers' => [ 'Content-Type' => 'application/json' ], 'timeout' => 60 ]
        );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'api_request_failed', $response->get_error_message() );
        }

        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

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
        $terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => $parent_id ] );
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