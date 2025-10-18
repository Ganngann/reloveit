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
                    submitButton.prop('disabled', false);

                    var product = response.data.product;

                    // Update fields based on which tasks were performed.
                    var tasks = [];
                    $('input[name="relovit_tasks[]"]:checked').each(function() {
                        tasks.push($(this).val());
                    });

                    if (tasks.includes('title') && product.title) {
                        $('#title').val(product.title).trigger('change');
                        // Also update the "slug" which is generated from the title
                        if (typeof slugL10n !== 'undefined') {
                             $('#post_name').val(product.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''));
                        }
                    }

                    if (tasks.includes('description') && product.description) {
                         if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                            tinyMCE.get('content').setContent(product.description);
                        } else {
                            $('#content').val(product.description);
                        }
                    }

                    if (tasks.includes('price') && product.price) {
                        $('#_regular_price').val(product.price).trigger('change');
                    }

                    if (tasks.includes('category')) {
                        // For categories and tags, we just reload, as updating the UI is complex
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }

                    if (tasks.includes('image')) {
                        // Reloading is the safest way to show image changes (main image, gallery)
                         setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }
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