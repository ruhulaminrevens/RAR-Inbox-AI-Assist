import test from 'node:test';
import { strict as assert } from 'node:assert';
import { ICONS } from '../../extension/sidepanel/ui/icons.js';

const USED = [ 'sparkles', 'clipboard-copy', 'corner-down-left', 'wand-sparkles', 'chevron-down', 'info', 'plug', 'x', 'triangle-alert', 'shield-alert', 'circle-check', 'book-open', 'clipboard-paste', 'text-select', 'user-round-check', 'power', 'refresh-cw', 'settings', 'search', 'link-2', 'circle-x' ];

/** Minimal well-formedness check: only self-closing shape elements, no nested <svg>, no scripts. */
function wellFormed( markup ) {
	const stack = [];
	const re = /<(\/?)([a-z]+)\b[^>]*?(\/?)>/g;
	let m;
	let rest = markup;
	while ( ( m = re.exec( markup ) ) ) {
		rest = rest.replace( m[ 0 ], '' );
		if ( m[ 1 ] ) {
			if ( stack.pop() !== m[ 2 ] ) {
				return false;
			}
		} else if ( ! m[ 3 ] ) {
			stack.push( m[ 2 ] );
		}
	}
	return stack.length === 0 && rest.trim() === '';
}

test( 'icon markup is inner SVG content only and well-formed', () => {
	for ( const [ name, markup ] of Object.entries( ICONS ) ) {
		assert.ok( ! /<svg/i.test( markup ), `${ name }: must not contain its own <svg> wrapper` );
		assert.ok( ! /script|on[a-z]+=|href/i.test( markup ), `${ name }: no active content` );
		assert.ok( /^<(path|circle|rect|line|polyline|polygon|ellipse)\b/.test( markup ), `${ name }: starts with a shape` );
		assert.ok( wellFormed( markup ), `${ name }: well-formed` );
	}
} );

test( 'every icon the panel uses exists', () => {
	for ( const n of USED ) {
		assert.ok( ICONS[ n ], n );
	}
} );
