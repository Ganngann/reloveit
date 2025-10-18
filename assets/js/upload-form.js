(function($) {
    'use strict';

    $(function() {
        var resizedImageBlob = null;
        var originalFileName = '';
        var imageInput = $('#relovit-image-upload');
        var resultsDiv = $('#relovit-results');
        var previewContainer = $('#relovit-image-preview-container');
        var previewImg = $('#relovit-image-preview');
        var uploadOptions = $('.relovit-upload-options');

        // --- Button Click Handlers ---

        $('#relovit-capture-btn').on('click', function() {
            imageInput.attr('capture', 'environment');
            imageInput.click();
        });

        $('#relovit-library-btn').on('click', function() {
            imageInput.removeAttr('capture');
            imageInput.click();
        });

        // --- Image Selection and Processing ---

        imageInput.on('change', function(e) {
            var file = e.target.files[0];

            resizedImageBlob = null;
            previewContainer.hide();
            resultsDiv.empty();

            if (!file) {
                return;
            }

            if (!file.type.startsWith('image/')) {
                resultsDiv.html('<p style="color: red;">Veuillez sélectionner un fichier image valide.</p>');
                $(this).val('');
                return;
            }

            originalFileName = file.name;
            resultsDiv.html('<p>Préparation de l\'image...</p>');

            var reader = new FileReader();
            reader.onload = function(event) {
                // Show preview
                previewImg.attr('src', event.target.result);

                var img = new Image();
                img.onload = function() {
                    var MAX_WIDTH = 1920;
                    var MAX_HEIGHT = 1920;
                    var width = img.width;
                    var height = img.height;

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

                    canvas.toBlob(function(blob) {
                        resizedImageBlob = blob;
                        resultsDiv.html('<p style="color: green;">Image prête. Cliquez sur "Identifier" pour continuer.</p>');
                        previewContainer.show();
                        uploadOptions.hide();
                    }, 'image/jpeg', 0.85);
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });

        // --- Form Submission ---

        $('#relovit-upload-form').on('submit', function(e) {
            e.preventDefault();

            var submitButton = $('#relovit-submit-btn');

            if (!resizedImageBlob) {
                resultsDiv.html('<p style="color: red;">Aucune image n\'a été traitée. Veuillez en sélectionner une.</p>');
                return;
            }

            var formData = new FormData();
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
                    previewContainer.css('opacity', 0.7);
                },
                success: function(response) {
                    var items = response.data.items;
                    var html = '<h3>Objets identifiés :</h3><form id="relovit-select-form">';
                    items.forEach(function(item, index) {
                        var tempLabel = $('<label>').text(item.trim());
                        var safeValue = tempLabel.text();
                        html += '<div><input type="checkbox" id="item-' + index + '" name="items[]" value="' + safeValue.replace(/"/g, '&quot;') + '"> <label for="item-' + index + '">' + tempLabel.html() + '</label></div>';
                    });
                    html += '<input type="hidden" name="action" value="create_products">';
                    html += '<input type="hidden" name="attachment_id" value="' + response.data.attachment_id + '">';
                    html += '<button type="submit">Créer les brouillons</button></form>';
                    resultsDiv.html(html);
                    previewContainer.hide();
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
                    previewContainer.css('opacity', 1);
                }
            });
        });

        // --- Item Selection Form Submission ---

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
                    // Reset the initial UI state
                    uploadOptions.show();
                    imageInput.val(''); // Clear the file input
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