(function ($) {
    'use strict';

    $(function () {
        $('.button.delete').on('click', function (e) {
            e.preventDefault();

            if (!confirm(relovit_my_products.confirm_delete)) {
                return false;
            }

            const productId = $(this).data('product-id');
            const row = $(this).closest('tr');

            $.ajax({
                url: relovit_my_products.delete_url + productId,
                type: 'DELETE',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', relovit_my_products.nonce);
                },
                success: function (response) {
                    if (response.success) {
                        row.fadeOut(300, function () {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || 'An error occurred.');
                    }
                },
                error: function () {
                    alert('An error occurred while trying to delete the product.');
                }
            });
        });
    });

})(jQuery);