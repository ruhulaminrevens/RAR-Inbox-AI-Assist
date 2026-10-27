/* RAR Inbox AI Assist — admin: confirm dialogs and copy buttons. */
( function () {
	'use strict';
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-iaa-confirm]' );
		if ( btn && ! window.confirm( btn.getAttribute( 'data-iaa-confirm' ) ) ) {
			e.preventDefault();
			return;
		}
		var copy = e.target.closest( '[data-iaa-copy]' );
		if ( copy ) {
			var input = document.querySelector( copy.getAttribute( 'data-iaa-copy' ) );
			if ( input ) {
				input.select();
				if ( navigator.clipboard ) {
					navigator.clipboard.writeText( input.value ).then( function () { copy.textContent = 'Copied'; } );
				} else {
					document.execCommand( 'copy' );
					copy.textContent = 'Copied';
				}
			}
		}
	} );
} )();
