/* RAR Inbox AI Assist — admin: confirm dialogs, copy buttons, settings tabs. */
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

	// Settings: all sections stay in one form; tabs only show/hide (no data is lost on save).
	var nav = document.querySelector( '[data-iaa-tabs]' );
	var wrap = document.querySelector( '.iaa-wrap' );
	if ( ! nav || ! wrap ) {
		return;
	}
	wrap.classList.add( 'iaa-js' );
	var field = document.querySelector( '[data-iaa-tab-field]' );
	function show( key, push ) {
		var panes = document.querySelectorAll( '[data-iaa-pane]' );
		var found = false;
		panes.forEach( function ( p ) {
			var on = p.getAttribute( 'data-iaa-pane' ) === key;
			found = found || on;
			if ( on ) {
				p.setAttribute( 'data-active', '' );
			} else {
				p.removeAttribute( 'data-active' );
			}
		} );
		if ( ! found ) {
			return;
		}
		nav.querySelectorAll( '[data-iaa-tab]' ).forEach( function ( a ) {
			var on = a.getAttribute( 'data-iaa-tab' ) === key;
			a.classList.toggle( 'is-active', on );
			if ( on ) {
				a.setAttribute( 'aria-current', 'page' );
			} else {
				a.removeAttribute( 'aria-current' );
			}
		} );
		if ( field ) {
			field.value = key;
		}
		if ( push && window.history && window.history.replaceState ) {
			var url = new URL( window.location.href );
			url.searchParams.set( 'tab', key );
			window.history.replaceState( null, '', url.toString() );
		}
	}
	nav.addEventListener( 'click', function ( e ) {
		var a = e.target.closest( '[data-iaa-tab]' );
		if ( a ) {
			e.preventDefault();
			show( a.getAttribute( 'data-iaa-tab' ), true );
		}
	} );
	// Show the tab that holds the first field error, if any.
	var err = document.querySelector( '.iaa-tabpane .iaa-field-error' );
	if ( err ) {
		show( err.closest( '[data-iaa-pane]' ).getAttribute( 'data-iaa-pane' ), true );
	}
} )();
