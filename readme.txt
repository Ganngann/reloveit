=== Relovit ===
Contributors: Jules, Morgan Schaefer
Tags: woocommerce, products, ai, gemini, second-hand
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Simplifie la création de fiches produits pour les articles d'occasion. Téléversez une image, et notre IA identifie les objets et crée des brouillons de produits pour vous.

== Description ==

Relovit est un plugin pour WordPress et WooCommerce qui simplifie la création de fiches produits pour les articles d'occasion. Il vous suffit de téléverser une image, et notre intelligence artificielle identifie les objets et crée des brouillons de produits pour vous.

== Installation ==

1. **Téléverser le plugin sur votre site WordPress :**
    *   Connectez-vous à votre tableau de bord WordPress.
    *   Allez dans `Extensions` > `Ajouter`.
    *   Cliquez sur le bouton `Téléverser une extension` en haut de la page.
    *   Choisissez le fichier `.zip` du plugin et cliquez sur `Installer maintenant`.
    *   Une fois l'installation terminée, cliquez sur `Activer l'extension`.

2. **Configurer la clé d'API :**
    *   Pour que le plugin fonctionne, vous devez configurer votre clé d'API Google Gemini.
    *   Obtenez une clé d'API sur le site de [Google AI for Developers](https://ai.google.dev/).
    *   Dans votre tableau de bord WordPress, allez dans `Réglages` > `Relovit`.
    *   Collez votre clé d'API Gemini dans le champ `Gemini API Key`.
    *   Cliquez sur `Enregistrer les modifications`.

== Utilisation ==

Pour afficher le formulaire de téléversement d'images, vous devez utiliser un shortcode.

1. **Créez une nouvelle page :**
    *   Allez dans `Pages` > `Ajouter`. Donnez-lui un titre, par exemple "Vendre mes objets".
    *   Pour l'intégrer dans la section "Mon Compte" de WooCommerce, vous pouvez définir la page parente sur "Mon compte".

2. **Ajoutez le shortcode :**
    *   Dans l'éditeur de page, ajoutez un bloc "Shortcode".
    *   Copiez et collez le shortcode suivant dans le bloc : `[relovit_upload_form]`

3. **Publiez la page.**

Vos utilisateurs peuvent maintenant se rendre sur cette page pour téléverser leurs photos et commencer à vendre leurs articles ! Les produits créés apparaîtront en tant que brouillons dans votre section `Produits` WooCommerce, prêts à être vérifiés et publiés.

== Changelog ==

= 1.0.0 =
*   Première version du plugin.
*   Fonctionnalité d'upload d'image.
*   Identification des objets via l'API Gemini.
*   Création de produits brouillons dans WooCommerce.
*   Page de réglages pour la clé API.
*   Shortcode `[relovit_upload_form]` pour l'affichage du formulaire.