# Relovit - Plugin de Vente d'Occasion par IA

Relovit est un plugin pour WordPress et WooCommerce qui simplifie la création de fiches produits pour les articles d'occasion. Il vous suffit de téléverser une image, et notre intelligence artificielle identifie les objets et crée des brouillons de produits pour vous.

## Prérequis

*   WordPress (dernière version)
*   WooCommerce (dernière version)
*   Une clé d'API Google Gemini (pour l'analyse d'images)

## Installation

1.  **Télécharger le plugin :**
    *   Cliquez sur le bouton "Code" en haut de cette page, puis sur "Download ZIP".

2.  **Installer sur votre site WordPress :**
    *   Connectez-vous à votre tableau de bord WordPress.
    *   Allez dans `Extensions` > `Ajouter`.
    *   Cliquez sur le bouton `Téléverser une extension` en haut de la page.
    *   Choisissez le fichier `.zip` que vous venez de télécharger et cliquez sur `Installer maintenant`.
    *   Une fois l'installation terminée, cliquez sur `Activer l'extension`.

## Configuration

Pour que le plugin fonctionne, vous devez configurer votre clé d'API Google Gemini.

1.  **Obtenir une clé d'API Gemini :**
    *   Allez sur le site de [Google AI for Developers](https://ai.google.dev/) et suivez les instructions pour créer une clé d'API pour le modèle Gemini.

2.  **Ajouter la clé au plugin :**
    *   Dans votre tableau de bord WordPress, allez dans `Réglages` > `Relovit`.
    *   Collez votre clé d'API Gemini dans le champ `Gemini API Key`.
    *   Cliquez sur `Enregistrer les modifications`.

## Utilisation

Pour afficher le formulaire de téléversement d'images, vous devez utiliser un shortcode.

1.  **Créez une nouvelle page :**
    *   Allez dans `Pages` > `Ajouter`. Donnez-lui un titre, par exemple "Vendre mes objets".
    *   Pour l'intégrer dans la section "Mon Compte" de WooCommerce, vous pouvez définir la page parente sur "Mon compte".

2.  **Ajoutez le shortcode :**
    *   Dans l'éditeur de page, ajoutez un bloc "Shortcode".
    *   Copiez et collez le shortcode suivant dans le bloc :
        ```
        [relovit_upload_form]
        ```

3.  **Publiez la page.**

Vos utilisateurs peuvent maintenant se rendre sur cette page pour téléverser leurs photos et commencer à vendre leurs articles ! Les produits créés apparaîtront en tant que brouillons dans votre section `Produits` WooCommerce, prêts à être vérifiés et publiés.