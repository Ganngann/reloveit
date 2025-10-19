(function ($) {
    'use strict';

    $(function () {
        const enrichBtn = $('#relovit-enrich-btn');
        const spinner = $('#relovit-ai-spinner');
        const form = $('#relovit-edit-product-form');
        const noticeWrapper = form.prev('.woocommerce-notices-wrapper');

        if (!enrichBtn.length) {
            return;
        }

        enrichBtn.on('click', function () {
            const tasks = $('input[name="relovit_tasks[]"]:checked').map(function () {
                return this.value;
            }).get();

            if (tasks.length === 0) {
                alert(relovit_edit_product.no_tasks_selected);
                return;
            }

            const formData = new FormData(form[0]);
            formData.append('_wpnonce', relovit_edit_product.nonce);

            spinner.show();
            enrichBtn.prop('disabled', true);

            $.ajax({
                url: relovit_edit_product.api_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success && response.data) {
                        if(response.data.title) $('#relovit_product_title').val(response.data.title);
                        if(response.data.description) $('#relovit_product_description').val(response.data.description);
                        if(response.data.price) $('#relovit_product_price').val(response.data.price);

                        if (response.data.message) {
                            // Ensure the notice wrapper exists
                            let noticeTarget = noticeWrapper.length ? noticeWrapper : form.before('<div class="woocommerce-notices-wrapper"></div>').prev();
                            noticeTarget.html('<div class="woocommerce-message" role="alert">' + response.data.message + '</div>');
                            $('html, body').animate({ scrollTop: noticeTarget.offset().top - 100 }, 'slow');
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
        });
    });

})(jQuery);