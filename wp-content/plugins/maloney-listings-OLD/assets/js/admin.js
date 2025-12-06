/**
 * Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Select all checkbox
        $('#cb-select-all').on('change', function() {
            $('input[name="listing_ids[]"]').prop('checked', $(this).prop('checked'));
        });
    });
    
})(jQuery);

