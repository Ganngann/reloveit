(function($) {
    'use strict';

    $(function() {
        $('#relovit-upload-form').on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            var resultsDiv = $('#relovit-results');
            var submitButton = $(this).find('button[type="submit"]');

            // Basic validation
            if ( ! $('#relovit-image-upload').val() ) {
                resultsDiv.html('<p style="color: red;">Veuillez sélectionner une image.</p>');
                return;
            }

            $.ajax({
                url: relovit_ajax.identify_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    resultsDiv.html('<p>Analyse de l\'image en cours...</p>');
                    submitButton.prop('disabled', true);
                },
                success: function(response) {
                    var items = response.data.items;
                    var html = '<h3>Objets identifiés :</h3><form id="relovit-select-form">';
                    items.forEach(function(item, index) {
                        html += '<div><input type="checkbox" id="item-' + index + '" name="items[]" value="' + item.trim() + '"> <label for="item-' + index + '">' + item.trim() + '</label></div>';
                    });
                    html += '<input type="hidden" name="action" value="create_products">';
                    html += '<button type="submit">Créer les brouillons</button></form>';
                    resultsDiv.html(html);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    var message = 'Une erreur est survenue lors de la communication avec le serveur.';
                    if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        message = jqXHR.responseJSON.message;
                    }
                    resultsDiv.html('<p style="color: red;">' + message + '</p>');
                },
                complete: function() {
                    submitButton.prop('disabled', false);
                }
            });
        });

        // Handle the second form submission (item selection)
        $(document).on('submit', '#relovit-select-form', function(e) {
            e.preventDefault();

            var formData = $(this).serialize();
            var resultsDiv = $('#relovit-results');

            $.ajax({
                url: relovit_ajax.create_url,
                type: 'POST',
                data: formData,
                beforeSend: function() {
                    resultsDiv.html('<p>Création des brouillons en cours...</p>');
                },
                success: function(response) {
                    resultsDiv.html('<p style="color: green;">' + response.data.message + '</p>');
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    var message = 'Une erreur est survenue lors de la communication avec le serveur.';
                    if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        message = jqXHR.responseJSON.message;
                    }
                    resultsDiv.html('<p style="color: red;">' + message + '</p>');
                }
            });
        });
    });

})(jQuery);