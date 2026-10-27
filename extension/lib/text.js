/**
 * Text helpers shared by the panel: number extraction and the pre-insert advisory
 * (audit F-12, AppFlow §11). Mirrors the server FactGuard number rules. Pure functions.
 */

const BN = '০১২৩৪৫৬৭৮৯';

export function asciiDigits( s ) {
	return String( s ).replace( /[০-৯]/g, ( d ) => String( BN.indexOf( d ) ) );
}

export function normNumber( raw ) {
	let s = asciiDigits( raw ).replace( /,/g, '' );
	if ( s.includes( '.' ) ) {
		s = s.replace( /0+$/, '' ).replace( /\.$/, '' );
	}
	s = s.replace( /^0+/, '' );
	return s === '' ? '0' : s;
}

const PRICE_CUE = /(৳|টাকা|টাঃ|tk\b|tk\.|taka|bdt|\/-|দাম|মূল্য|price|rate|রেট|mrp)/iu;
const PERCENT_CUE = /^\s?(%|শতাংশ|percent|পার্সেন্ট|পারসেন্ট)/iu;
const COMMERCIAL = /(discount|ডিসকাউন্ট|ছাড় দে|ছাড় দি|ছাড় পাব|কমিশন|commission|% off|ফ্রি দে|free gift)/iu;

/** Numbers in a text with classification. */
export function numbers( text ) {
	const out = [];
	const chars = Array.from( String( text ) );
	const joined = chars.join( '' );
	const re = /[0-9০-৯]+(?:[.,][0-9০-৯]+)*/gu;
	let m;
	while ( ( m = re.exec( joined ) ) ) {
		const pos = Array.from( joined.slice( 0, m.index ) ).length;
		const len = Array.from( m[ 0 ] ).length;
		// Cue windows stop at sentence boundaries so "৳১,২৫০। ৭ দিনে" does not make 7 a price.
		const before = chars.slice( Math.max( 0, pos - 12 ), pos ).join( '' ).split( /[।!?\n]|\.\s/ ).pop();
		const after = chars.slice( pos + len, pos + len + 12 ).join( '' ).split( /[।!?\n]|\.\s/ )[ 0 ];
		out.push( {
			raw: m[ 0 ],
			value: normNumber( m[ 0 ] ),
			price: PRICE_CUE.test( `${ before } ${ after }` ),
			percent: PERCENT_CUE.test( after ),
		} );
	}
	return out;
}

/**
 * Compare an edited text with the guarded suggestion it came from.
 * Returns { block: bool, notes: [ { code, value } ] }.
 *  - a new price-like number or percentage → block Insert (Copy stays available)
 *  - any other new number → note
 *  - a new discount/commission phrase → note
 */
export function editAdvisory( original, edited ) {
	const known = new Set( numbers( original ).map( ( n ) => n.value ) );
	const notes = [];
	let block = false;
	for ( const n of numbers( edited ) ) {
		if ( known.has( n.value ) ) {
			continue;
		}
		if ( n.price || n.percent ) {
			block = true;
			notes.push( { code: n.percent ? 'NEW_PERCENT' : 'NEW_PRICE', value: n.raw } );
		} else if ( ! ( /^\d+$/.test( n.value ) && Number( n.value ) <= 3 ) ) {
			notes.push( { code: 'NEW_NUMBER', value: n.raw } );
		}
	}
	if ( COMMERCIAL.test( edited ) && ! COMMERCIAL.test( original ) ) {
		notes.push( { code: 'NEW_COMMERCIAL', value: ( edited.match( COMMERCIAL ) || [ '' ] )[ 0 ] } );
	}
	return { block, notes };
}

/** Levenshtein distance capped at max (for the outcome edit_distance). */
export function editDistance( a, b, max = 2000 ) {
	const s = Array.from( String( a ) );
	const t = Array.from( String( b ) );
	if ( Math.abs( s.length - t.length ) >= max ) {
		return max;
	}
	let prev = Array.from( { length: t.length + 1 }, ( _, i ) => i );
	for ( let i = 1; i <= s.length; i++ ) {
		const cur = [ i ];
		let rowMin = i;
		for ( let j = 1; j <= t.length; j++ ) {
			cur[ j ] = Math.min( prev[ j ] + 1, cur[ j - 1 ] + 1, prev[ j - 1 ] + ( s[ i - 1 ] === t[ j - 1 ] ? 0 : 1 ) );
			rowMin = Math.min( rowMin, cur[ j ] );
		}
		if ( rowMin >= max ) {
			return max;
		}
		prev = cur;
	}
	return Math.min( prev[ t.length ], max );
}

/** Normalised text for insert verification. */
export function squash( s ) {
	return String( s ).replace( /\s+/g, ' ' ).trim();
}

/** Bangla digits for small UI counters. */
export function bnDigits( n ) {
	return String( n ).replace( /[0-9]/g, ( d ) => BN[ Number( d ) ] );
}
