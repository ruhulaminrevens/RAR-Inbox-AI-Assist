/**
 * Insert text into Meta's reply box (TRD §2.2). NEVER sends: no Enter key events, no clicks.
 * Exposes globalThis.__IAA_COMPOSER.
 */
( function () {
	'use strict';

	function squash( s ) {
		return String( s ).replace( /\s+/g, ' ' ).trim();
	}

	function contains( box, text ) {
		return squash( box.innerText || box.textContent || '' ).includes( squash( text ) );
	}

	/**
	 * @param {string} text          Text to insert.
	 * @param {string} expectedKey   Conversation key the text was made for.
	 * @returns {{ok:boolean, code?:string}}
	 */
	function insert( text, expectedKey ) {
		const A = globalThis.__IAA_ADAPTER;
		const now = A.key();
		if ( ! now || ! expectedKey || now.key !== expectedKey ) {
			return { ok: false, code: 'STALE' };
		}
		const box = A.composer();
		if ( ! box ) {
			return { ok: false, code: 'INSERT_FAILED' };
		}
		if ( squash( box.innerText || '' ) !== '' ) {
			return { ok: false, code: 'COMPOSER_NOT_EMPTY' };
		}
		box.focus();
		let done = false;
		try {
			done = document.execCommand( 'insertText', false, text ) && contains( box, text );
		} catch ( e ) {
			done = false;
		}
		if ( ! done ) {
			try {
				const dt = new DataTransfer();
				dt.setData( 'text/plain', text );
				box.dispatchEvent( new ClipboardEvent( 'paste', { clipboardData: dt, bubbles: true, cancelable: true } ) );
				done = contains( box, text );
			} catch ( e ) {
				done = false;
			}
		}
		return done ? { ok: true } : { ok: false, code: 'INSERT_FAILED' };
	}

	globalThis.__IAA_COMPOSER = { insert };
}() );
