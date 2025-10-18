<?php
/**
 * Handles the plugin settings.
 *
 * @package Relovit
 */

namespace Relovit;

/**
 * Class Settings
 *
 * @package Relovit
 */
class Settings {

    /**
     * Get a setting value.
     *
     * @param string $key The setting key.
     * @param mixed  $default The default value.
     * @return mixed The setting value.
     */
    public static function get( $key, $default = null ) {
        $options = get_option( 'relovit_settings', [] );
        $defaults = self::get_defaults();

        // If no default is provided to the function, get it from our defaults array.
        if ( is_null( $default ) ) {
            $default = isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;
        }

        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }

    /**
     * Get all default settings for user-configurable parts.
     *
     * @return array
     */
    public static function get_defaults() {
        return [
            'gemini_api_key'      => '',
            'language'            => 'français',
            'store_context'       => "Je suis un vendeur d'articles d'occasion, spécialisé dans les objets vintage et de collection.",
            'price_range'         => 'medium', // 'low', 'medium', 'high'
            'desc_tone'           => 'détaillée, honnête et commerciale',
            'desc_keywords'       => "insistant sur son état et sa valeur pour un acheteur d'occasion",
            'taxonomy_seo_focus'  => "pertinents pour le SEO",
            'image_bg_style'      => 'sur un fond de studio blanc et propre',
        ];
    }

    /**
     * Get the available price ranges.
     *
     * @return array
     */
    public static function get_price_ranges() {
        return [
            'low'    => __( 'Bon marché', 'relovit' ),
            'medium' => __( 'Moyen / Compétitif', 'relovit' ),
            'high'   => __( 'Haut de gamme / Cher', 'relovit' ),
        ];
    }

    /**
     * Get the text description for a given price range key.
     *
     * @param string $key The price range key ('low', 'medium', 'high').
     * @return string The descriptive text for the AI.
     */
    public static function get_price_range_prompt_text($key) {
        switch ($key) {
            case 'low':
                return "un prix attractif et bon marché, visant une vente rapide";
            case 'high':
                return "un prix dans la fourchette haute du marché, reflétant une qualité ou une rareté supérieure";
            case 'medium':
            default:
                return "un prix de vente compétitif et raisonnable par rapport au marché actuel";
        }
    }
}