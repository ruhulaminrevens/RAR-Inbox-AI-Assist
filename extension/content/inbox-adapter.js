/**
 * The only module (with composer.js) that knows Meta's page structure (TRD §2.1).
 *
 * STATUS: heuristic, NOT yet verified against the live Business Suite inbox (gate G0, tasks
 * T0.02/T0.03) and NOT authorised for live use until Meta permission evidence exists (T0.09).
 * The panel calls capture() only when the administrator has turned on "Live inbox read & insert".
 * Selection/paste mode does not use this module except key() (URL only) and selection().
 *
 * Classic script (content scripts cannot be ES modules); exposes globalThis.__IAA_ADAPTER.
 */
( function () {
	'use strict';

	const VERSION = 'heuristic-1';
	const SAFE = /^[A-Za-z0-9_.-]{1,128}$/;

	function key() {
		const p = new URLSearchParams( location.search );
		const asset = p.get( 'asset_id' ) || '';
		const item = p.get( 'selected_item_id' ) || '';
		const business = p.get( 'business_id' ) || '';
		if ( ! SAFE.test( asset ) || ! SAFE.test( item ) ) {
			return null;
		}
		return { key: asset + ':' + item, asset, item, business };
	}

	function visible( el ) {
		if ( ! el || ! el.getBoundingClientRect ) {
			return false;
		}
		const r = el.getBoundingClientRect();
		if ( r.width < 2 || r.height < 2 ) {
			return false;
		}
		const cs = getComputedStyle( el );
		return cs.visibility !== 'hidden' && cs.display !== 'none';
	}

	/** Reply box: a visible contenteditable textbox, bottom-most on the page. */
	function composer() {
		const all = Array.from( document.querySelectorAll( '[contenteditable="true"][role="textbox"], div[contenteditable="true"]' ) ).filter( visible );
		if ( ! all.length ) {
			return null;
		}
		all.sort( ( a, b ) => b.getBoundingClientRect().bottom - a.getBoundingClientRect().bottom );
		return all[ 0 ];
	}

	/** Region that holds the open conversation: the scrollable area just above the composer. */
	function threadRegion( box ) {
		let el = box ? box.parentElement : null;
		while ( el && el !== document.body ) {
			const texts = el.querySelectorAll( '[dir="auto"]' );
			if ( texts.length >= 2 && el.getBoundingClientRect().height > 200 ) {
				return el;
			}
			el = el.parentElement;
		}
		return document.querySelector( '[role="main"]' ) || document.body;
	}

	function capture() {
		const box = composer();
		const region = threadRegion( box );
		const rr = region.getBoundingClientRect();
		const mid = rr.left + rr.width / 2;
		const boxTop = box ? box.getBoundingClientRect().top : Infinity;
		const seen = new Set();
		const items = [];
		region.querySelectorAll( '[dir="auto"]' ).forEach( ( el ) => {
			if ( ! visible( el ) || ( box && ( box.contains( el ) || el.contains( box ) ) ) ) {
				return;
			}
			if ( el.closest( '[contenteditable="true"], [role="navigation"], [role="banner"], header, nav, aside, button, a' ) ) {
				return;
			}
			if ( el.querySelector( '[dir="auto"]' ) ) {
				return; // keep innermost text blocks only
			}
			const text = ( el.innerText || el.textContent || '' ).trim();
			const r = el.getBoundingClientRect();
			if ( ! text || r.top >= boxTop || seen.has( el ) ) {
				return;
			}
			seen.add( el );
			const center = r.left + r.width / 2;
			items.push( { top: r.top, side: center > mid ? 'business' : 'customer', text: text.slice( 0, 2000 ) } );
		} );
		items.sort( ( a, b ) => a.top - b.top );
		// Merge consecutive fragments from the same side and drop UI labels such as timestamps.
		const msgs = [];
		for ( const it of items ) {
			if ( /^\d{1,2}:\d{2}$/.test( it.text ) || it.text.length < 1 ) {
				continue;
			}
			const last = msgs[ msgs.length - 1 ];
			if ( last && last.side === it.side && Math.abs( it.top - last._top ) < 4 ) {
				last.text += ' ' + it.text;
			} else {
				msgs.push( { side: it.side, text: it.text, at: null, _top: it.top } );
			}
		}
		const out = msgs.slice( -20 ).map( ( m ) => ( { side: m.side, text: m.text, at: null } ) );
		return { key: key(), messages: out, adapter: { version: VERSION, strategy: 'geometry' } };
	}

	function selection() {
		const sel = window.getSelection();
		return { key: key(), text: sel ? String( sel.toString() ).trim().slice( 0, 4000 ) : '' };
	}

	function selfTest() {
		let messages = false;
		try {
			messages = capture().messages.length > 0;
		} catch ( e ) {
			messages = false;
		}
		return { version: VERSION, key: !! key(), composer: !! composer(), messages };
	}

	globalThis.__IAA_ADAPTER = { VERSION, key, composer, capture, selection, selfTest };
}() );
