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

            const form = $('#relovit-edit-product-form');
            const formData = new FormData(form[0]);
            formData.append('relovit_tasks', tasks.join(','));

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
                    if (response.success) {
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