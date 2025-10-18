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
        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }

    /**
     * Get all default settings.
     *
     * @return array
     */
    public static function get_defaults() {
        return [
            'gemini_api_key' => '',
            'language' => 'français',
            'price_min' => 0,
            'price_max' => 1000,
            'store_context' => "Je suis un vendeur d'articles d'occasion, spécialisé dans les objets vintage et de collection.",
            'prompt_identify' => "Listez tous les objets distincts et vendables présents dans cette image. Répondez uniquement avec une liste d'éléments séparés par des virgules.",
            'prompt_description' => "En tant qu'expert en vente d'occasion, rédigez une description détaillée, honnête et commerciale d'un {product_name} basé sur les images fournies, en insistant sur son état et sa valeur pour un acheteur d'occasion. Utilisez un ton engageant. Maximum 300 mots. Langue de la réponse : {language}. Contexte de la boutique : {store_context}.",
            'prompt_price' => "En tenant compte du marché actuel des objets d'occasion, de l'état apparent de l'objet {product_name} dans ces images et du contexte de la boutique ({store_context}), proposez un prix de vente raisonnable en EUR. Le prix doit être compris entre {price_min} et {price_max} EUR. Répondez uniquement avec le prix au format numérique (exemple: 45.99).",
            'prompt_taxonomy' => "En tant qu'expert en e-commerce et SEO, analyse le produit '{product_name}' à partir des images fournies.\nEn te basant sur l'arborescence de catégories suivante :\n---\n{category_tree}\n---\n1. **Catégorie :** Choisis le chemin de catégorie le plus pertinent. Si une catégorie adéquate n'existe pas, tu peux en proposer une nouvelle. Le chemin doit être une liste de noms, du parent à l'enfant (ex: [\"Vêtements\", \"Chemises\"]).\n2. **Tags :** Suggère une liste de 3 à 5 tags pertinents (en {language}) qui décrivent les caractéristiques, le style, le matériau ou l'usage de l'objet (ex: [\"coton\", \"manches longues\", \"formel\", \"vintage\"]).\n\nRéponds **uniquement** avec un objet JSON valide contenant deux clés : 'category' (un tableau de chaînes de caractères pour le chemin de la catégorie) et 'tags' (un tableau de chaînes de caractères pour les tags).\nExemple de format de réponse :\n{\n  \"category\": [\"Maison\", \"Décoration\", \"Vases\"],\n  \"tags\": [\"céramique\", \"moderne\", \"décoratif\", \"fleurs\"]\n}",
            'prompt_image' => "Extrayez l'objet principal de cette image et placez-le sur un fond de studio blanc et propre. L'image générée doit être photoréaliste et de haute qualité.",
        ];
    }
}