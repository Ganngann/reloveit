(function ($) {
    'use strict';

    const debugList = $('#relovit-debug-list');
    let logCounter = 1;

    function log(message, isError = false) {
        if (debugList.length) {
            const color = isError ? 'red' : 'green';
            const entry = `<li style="color: ${color}; border-bottom: 1px dashed #ddd; padding: 2px 0;">${logCounter++}: ${message}</li>`;
            debugList.append(entry);
            // Auto-scroll to the bottom
            debugList.parent().scrollTop(debugList.parent()[0].scrollHeight);
        } else {
            // Fallback to console if the debug area is missing
            isError ? console.error(message) : console.log(message);
        }
    }

    try {
        log('Script file my-account-edit-product.js loaded.');

        $(function () {
            log('Document ready. Initializing...');

            const enrichBtn = $('#relovit-enrich-btn');
            const spinner = $('#relovit-ai-spinner');
            const form = $('#relovit-edit-product-form');
            const noticeWrapper = $('.woocommerce-notices-wrapper').first();

            if (enrichBtn.length) {
                log('SUCCESS: "Enrich with AI" button found.');
            } else {
                log('ERROR: "Enrich with AI" button #relovit-enrich-btn NOT FOUND.', true);
                return;
            }

            log('Attaching click event listener to the button...');
            enrichBtn.on('click', function () {
                log('EVENT: "Enrich with AI" button CLICKED.');
                try {
                    const tasks = $('input[name="relovit_tasks[]"]:checked').map(function () {
                        return this.value;
                    }).get();

                    log(`Tasks selected: [${tasks.join(', ')}]`);

                    if (tasks.length === 0) {
                        log('Validation failed: No tasks selected.', true);
                        alert(relovit_edit_product.no_tasks_selected);
                        return;
                    }

                    const formData = new FormData(form[0]);
                    // Add the nonce to the form data for explicit verification
                    formData.append('relovit_nonce', relovit_edit_product.nonce);
                    log('FormData created and nonce appended.');

                    spinner.show();
                    enrichBtn.prop('disabled', true);
                    log('Spinner shown, button disabled. Initiating AJAX call...');

                    $.ajax({
                        url: relovit_edit_product.api_url,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            log('AJAX SUCCESS received.');
                            if (response.success && response.data) {
                                log('API returned success. Updating fields.');
                                if(response.data.title) $('#relovit_product_title').val(response.data.title);
                                if(response.data.description) $('#relovit_product_description').val(response.data.description);
                                if(response.data.price) $('#relovit_product_price').val(response.data.price);

                                if (response.data.message) {
                                    noticeWrapper.html('<div class="woocommerce-message" role="alert">' + response.data.message + '</div>');
                                    $('html, body').animate({ scrollTop: noticeWrapper.offset().top - 100 }, 'slow');
                                }
                            } else {
                                const errorMessage = response.data && response.data.message ? response.data.message : relovit_edit_product.error_message;
                                log(`API returned error: ${errorMessage}`, true);
                                alert(errorMessage);
                            }
                        },
                        error: function (jqXHR) {
                            let errorMessage = relovit_edit_product.error_message;
                            if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                                errorMessage = jqXHR.responseJSON.message;
                            }
                            log(`AJAX call FAILED. Status: ${jqXHR.status}. Message: ${errorMessage}`, true);
                            alert(errorMessage);
                        },
                        complete: function () {
                            log('AJAX complete. Re-enabling button and hiding spinner.');
                            spinner.hide();
                            enrichBtn.prop('disabled', false);
                        }
                    });
                } catch (e) {
                    log(`CRITICAL ERROR inside click handler: ${e.message}`, true);
                    alert('A critical error occurred. See debug log for details.');
                    spinner.hide();
                    enrichBtn.prop('disabled', false);
                }
            });
            log('SUCCESS: Click event listener attached.');
        });
    } catch (e) {
        log(`CRITICAL ERROR in script execution: ${e.message}`, true);
    }

})(jQuery);