(function ($) {
    'use strict';

    // This is a minimal script for testing purposes.
    // Its only goal is to write a message to the on-page debug log.

    $(function () {
        const debugList = $('#relovit-debug-list');
        if (debugList.length) {
            debugList.append('<li style="color: green;">SUCCESS: my-account-edit-product.js was loaded and executed.</li>');
        } else {
            // Fallback in case the debug list itself is the problem
            alert('Relovit Debug: JS Loaded, but debug log area not found!');
        }
    });

})(jQuery);