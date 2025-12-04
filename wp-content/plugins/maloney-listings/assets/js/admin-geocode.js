/**
 * Admin Geocoding JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        $('#geocode_address').on('click', function(e) {
            e.preventDefault();
            
            const address = $('#listing_address').val();
            if (!address) {
                alert('Please enter an address first.');
                return;
            }
            
            const button = $(this);
            button.prop('disabled', true).text('Geocoding...');
            
            $.ajax({
                url: maloneyGeocode.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'geocode_address',
                    address: address,
                    nonce: maloneyGeocode.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#listing_latitude').val(response.data.latitude);
                        $('#listing_longitude').val(response.data.longitude);
                        button.text('Geocoded!');
                    } else {
                        alert('Error: ' + response.data);
                        button.prop('disabled', false).text('Geocode Address');
                    }
                },
                error: function() {
                    alert('Error geocoding address. Please try again.');
                    button.prop('disabled', false).text('Geocode Address');
                }
            });
        });
    });
    
})(jQuery);

