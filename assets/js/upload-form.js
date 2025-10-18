(function($) {
    'use strict';

    $(function() {
        // Variable to store the resized image blob
        var resizedImageBlob = null;
        var originalFileName = '';

        // Handle file selection and resizing
        $('#relovit-image-upload').on('change', function(e) {
            var file = e.target.files[0];
            var resultsDiv = $('#relovit-results');

            resizedImageBlob = null; // Reset on new file selection

            if (!file) {
                return;
            }

            // Check if it's an image
            if (!file.type.startsWith('image/')) {
                resultsDiv.html('<p style="color: red;">Veuillez sélectionner un fichier image valide.</p>');
                $(this).val(''); // Reset the input
                return;
            }

            originalFileName = file.name;
            resultsDiv.html('<p>Préparation de l\'image...</p>');


            var reader = new FileReader();
            reader.onload = function(event) {
                var img = new Image();
                img.onload = function() {
                    var MAX_WIDTH = 1920;
                    var MAX_HEIGHT = 1920;
                    var width = img.width;
                    var height = img.height;

                    // Only resize if the image is larger than the max dimensions
                    if (width > MAX_WIDTH || height > MAX_HEIGHT) {
                        if (width > height) {
                            if (width > MAX_WIDTH) {
                                height *= MAX_WIDTH / width;
                                width = MAX_WIDTH;
                            }
                        } else {
                            if (height > MAX_HEIGHT) {
                                width *= MAX_HEIGHT / height;
                                height = MAX_HEIGHT;
                            }
                        }
                    }

                    var canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    var ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    // Convert canvas to blob
                    canvas.toBlob(function(blob) {
                        resizedImageBlob = blob;
                        resultsDiv.html('<p style="color: green;">Image prête à être envoyée.</p>');
                    }, 'image/jpeg', 0.85); // Use JPEG with 85% quality
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });


        $('#relovit-upload-form').on('submit', function(e) {
            e.preventDefault();

            var resultsDiv = $('#relovit-results');
            var submitButton = $(this).find('button[type="submit"]');

            // Check if an image has been selected and processed
            if (!resizedImageBlob) {
                // Check if a file is selected but not yet processed
                if ($('#relovit-image-upload').val()) {
                     resultsDiv.html('<p style="color: red;">Veuillez attendre la fin de la préparation de l\'image.</p>');
                } else {
                     resultsDiv.html('<p style="color: red;">Veuillez sélectionner une image.</p>');
                }
                return;
            }

            var formData = new FormData();
            // Use the original file name for better context on the server
            formData.append('relovit_image', resizedImageBlob, originalFileName);

            $.ajax({
                url: relovit_ajax.identify_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', relovit_ajax.nonce);
                    resultsDiv.html('<p>Analyse de l\'image en cours...</p>');
                    submitButton.prop('disabled', true);
                },
                success: function(response) {
                    var items = response.data.items;
                    var html = '<h3>Objets identifiés :</h3><form id="relovit-select-form">';
                    items.forEach(function(item, index) {
                        // Use .text() to prevent XSS and handle quotes correctly, then grab the text for the value
                        var tempLabel = $('<label>').text(item.trim());
                        var safeValue = tempLabel.text();
                        html += '<div><input type="checkbox" id="item-' + index + '" name="items[]" value="' + safeValue.replace(/"/g, '&quot;') + '"> <label for="item-' + index + '">' + tempLabel.html() + '</label></div>';
                    });
                    html += '<input type="hidden" name="action" value="create_products">';
                    html += '<input type="hidden" name="attachment_id" value="' + response.data.attachment_id + '">';
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
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', relovit_ajax.nonce);
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