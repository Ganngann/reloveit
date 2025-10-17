(function($) {
    'use strict';

    $(function() {
        $('#relovit-enrich-button').on('click', function(e) {
            e.preventDefault();

            var resultsDiv = $('#relovit-enrichment-results');
            var submitButton = $(this);

            var formData = new FormData();
            formData.append('product_id', $('#relovit-product-id').val());

            var files = $('#relovit-images')[0].files;
            for (var i = 0; i < files.length; i++) {
                formData.append('relovit_images[]', files[i]);
            }

            $('input[name="relovit_tasks[]"]:checked').each(function() {
                formData.append('relovit_tasks[]', $(this).val());
            });

            // Basic validation
            if ( $('#relovit-images')[0].files.length === 0 ) {
                resultsDiv.html('<p style="color: red;">Veuillez sélectionner au moins une image.</p>');
                return;
            }
             if ( $('#relovit-images')[0].files.length > 3 ) {
                resultsDiv.html('<p style="color: red;">Vous ne pouvez pas téléverser plus de 3 images.</p>');
                return;
            }

            $.ajax({
                url: relovit_ajax.enrich_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', relovit_ajax.nonce);
                    resultsDiv.html('<p>Enrichissement par l\'IA en cours... Cela peut prendre un moment.</p>');
                    submitButton.prop('disabled', true);
                },
                success: function(response) {
                    resultsDiv.html('<p style="color: green;">' + response.data.message + '</p>');
                    // Optionally, reload the page to see the changes
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    var message = 'Une erreur est survenue lors de la communication avec le serveur.';
                    if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        message = jqXHR.responseJSON.message;
                    } else if (textStatus === 'timeout') {
                        message = 'Le serveur a mis trop de temps à répondre. Veuillez réessayer.';
                    } else if (errorThrown) {
                        message = errorThrown;
                    }
                    resultsDiv.html('<p style="color: red;">' + message + '</p>');
                    submitButton.prop('disabled', false);
                }
            });
        });
    });

})(jQuery);