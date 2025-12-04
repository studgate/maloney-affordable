'use strict';

jQuery( function( $ ) {

	$( document ).on( 'click', '#wpforms-entry-user-journey .parameter-toggle', function( event ) {

		event.preventDefault();
		$( this ).closest( 'td' ).find( '.parameters' ).toggle();
	} );
} );
