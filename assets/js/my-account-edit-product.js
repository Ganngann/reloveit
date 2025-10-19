(function ($) {
    'use strict';

    $(function () {
        const enrichBtn = $('#relovit-enrich-btn');
        const spinner = $('#relovit-ai-spinner');
        const form = $('#relovit-edit-product-form');
        const noticeWrapper = $('.woocommerce-notices-wrapper').first();

        if (!enrichBtn.length) {
            return;
        }

        enrichBtn.on('click', function () {
            try {
                const tasks = $('input[name="relovit_tasks[]"]:checked').map(function () {
                    return this.value;
                }).get();

                if (tasks.length === 0) {
                    alert(relovit_edit_product.no_tasks_selected);
                    return;
                }

                const formData = new FormData(form[0]);

                spinner.show();
                enrichBtn.prop('disabled', true);

                $.ajax({
                    url: relovit_edit_product.api_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', relovit_edit_product.nonce);
                    },
                    success: function (response) {
                        if (response.success && response.data) {
                            // Update fields dynamically
                            if(response.data.title) $('#relovit_product_title').val(response.data.title);
                            if(response.data.description) $('#relovit_product_description').val(response.data.description);
                            if(response.data.price) $('#relovit_product_price').val(response.data.price);

                            // Add a success notice
                            if (response.data.message) {
                                noticeWrapper.html('<div class="woocommerce-message" role="alert">' + response.data.message + '</div>');
                                // Scroll to top to make notice visible
                                $('html, body').animate({ scrollTop: noticeWrapper.offset().top - 100 }, 'slow');
                            }
                        } else {
                            const errorMessage = response.data && response.data.message ? response.data.message : relovit_edit_product.error_message;
                            alert(errorMessage);
                        }
                    },
                    error: function (jqXHR) {
                        let errorMessage = relovit_edit_product.error_message;
                        if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                            errorMessage = jqXHR.responseJSON.message;
                        }
                        alert(errorMessage);
                    },
                    complete: function () {
                        spinner.hide();
                        enrichBtn.prop('disabled', false);
                    }
                });
            } catch (e) {
                console.error('An unexpected error occurred in the click handler:', e);
                alert('A critical error occurred. Please check the browser console.');
                spinner.hide();
                enrichBtn.prop('disabled', false);
            }
        });
    });

})(jQuery);