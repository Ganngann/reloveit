(function ($) {
    'use strict';

    $(function () {
        const enrichBtn = $('#relovit-enrich-btn');
        const spinner = $('#relovit-ai-spinner');

        enrichBtn.on('click', function () {
            const tasks = $('input[name="relovit_tasks[]"]:checked').map(function () {
                return this.value;
            }).get();

            if (tasks.length === 0) {
                alert(relovit_edit_product.no_tasks_selected);
                return;
            }

            const data = {
                'product_id': relovit_edit_product.product_id,
                'relovit_tasks': tasks,
            };

            spinner.show();
            enrichBtn.prop('disabled', true);

            $.ajax({
                url: relovit_edit_product.api_url,
                type: 'POST',
                data: JSON.stringify(data),
                contentType: 'application/json; charset=utf-8',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', relovit_edit_product.nonce);
                },
                success: function (response) {
                    if (response.success) {
                        const product = response.data.product;
                        if (product.description) {
                            $('#relovit_product_description').val(product.description);
                        }
                        if (product.price) {
                            $('#relovit_product_price').val(product.price);
                        }
                        // Reload to see all changes, including category and image.
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function () {
                    alert(relovit_edit_product.error_message);
                },
                complete: function () {
                    spinner.hide();
                    enrichBtn.prop('disabled', false);
                }
            });
        });
    });

})(jQuery);