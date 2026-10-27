/**
 * Identifiers: UUIDs and the Meta inbox conversation key (AppFlow §3).
 * Pure functions (unit-tested in tests/ext).
 */

export function uuid4() {
	if ( globalThis.crypto && typeof globalThis.crypto.randomUUID === 'function' ) {
		return globalThis.crypto.randomUUID();
	}
	const b = new Uint8Array( 16 );
	globalThis.crypto.getRandomValues( b );
	b[ 6 ] = ( b[ 6 ] & 0x0f ) | 0x40;
	b[ 8 ] = ( b[ 8 ] & 0x3f ) | 0x80;
	const h = [ ...b ].map( ( x ) => x.toString( 16 ).padStart( 2, '0' ) ).join( '' );
	return `${ h.slice( 0, 8 ) }-${ h.slice( 8, 12 ) }-${ h.slice( 12, 16 ) }-${ h.slice( 16, 20 ) }-${ h.slice( 20 ) }`;
}

const SAFE = /^[A-Za-z0-9_.-]{1,128}$/;

/** True for Meta Business Suite inbox URLs. */
export function isInboxUrl( url ) {
	try {
		const u = new URL( url );
		return u.protocol === 'https:' && u.hostname === 'business.facebook.com' && u.pathname.startsWith( '/latest/inbox' );
	} catch ( e ) {
		return false;
	}
}

/**
 * Conversation identity from an inbox URL.
 * Returns { key: "asset:item", asset, item, business } or null when not identifiable.
 */
export function parseKey( url ) {
	if ( ! isInboxUrl( url ) ) {
		return null;
	}
	const p = new URL( url ).searchParams;
	const asset = p.get( 'asset_id' ) || '';
	const item = p.get( 'selected_item_id' ) || '';
	const business = p.get( 'business_id' ) || '';
	if ( ! SAFE.test( asset ) || ! SAFE.test( item ) || asset.length > 64 ) {
		return null;
	}
	return { key: `${ asset }:${ item }`, asset, item, business };
}

/** Same conversation (same asset, item and, when both known, business). */
export function sameConversation( a, b ) {
	if ( ! a || ! b ) {
		return false;
	}
	if ( a.key !== b.key ) {
		return false;
	}
	return ! a.business || ! b.business || a.business === b.business;
}
