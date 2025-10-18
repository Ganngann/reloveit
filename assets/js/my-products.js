(function($) {
    'use strict';

    $(function() {
        $('.delete').on('click', function(e) {
            e.preventDefault();

            if (!confirm(relovit_my_products.confirm_delete)) {
                return;
            }

            var productId = $(this).data('product-id');
            var row = $(this).closest('tr');

            $.ajax({
                url: relovit_my_products.delete_url.replace('(?P<id>\\d+)', productId),
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', relovit_my_products.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function(response) {
                    alert(response.responseJSON.message);
                }
            });
        });
    });

})(jQuery);