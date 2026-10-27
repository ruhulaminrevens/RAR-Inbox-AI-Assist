import test from 'node:test';
import assert from 'node:assert/strict';
import { Api, buildUrl, apiBaseFor, TOKEN_RE } from '../../extension/lib/api.js';

function memoryStorage( init = {} ) {
	const data = { ...init };
	return {
		data,
		async get( keys ) {
			const out = {};
			for ( const k of [].concat( keys ) ) {
				if ( k in data ) {
					out[ k ] = data[ k ];
				}
			}
			return out;
		},
		async set( obj ) {
			Object.assign( data, obj );
		},
		async remove( keys ) {
			for ( const k of [].concat( keys ) ) {
				delete data[ k ];
			}
		},
	};
}
const TOKEN = 'iaa_abcdefgh_' + 'A'.repeat( 40 );

test( 'url building for both API base styles', () => {
	assert.equal( apiBaseFor( 'https://nabiad.com/' ), 'https://nabiad.com/wp-json/rar-iaa/v1' );
	assert.equal( buildUrl( 'https://nabiad.com/wp-json/rar-iaa/v1', '/dealers', { search: 'ra h' } ), 'https://nabiad.com/wp-json/rar-iaa/v1/dealers?search=ra+h' );
	assert.equal( buildUrl( 'http://x/index.php?rest_route=/rar-iaa/v1', '/dealers', { search: 'r' } ), 'http://x/index.php?rest_route=/rar-iaa/v1/dealers&search=r' );
	assert.equal( TOKEN_RE.test( TOKEN ), true );
	assert.equal( TOKEN_RE.test( 'iaa_ABCDEFGH_' + 'A'.repeat( 40 ) ), false );
} );

test( 'request sends both auth headers, omits cookies, parses errors', async () => {
	const calls = [];
	const fetchFn = async ( url, init ) => {
		calls.push( { url, init } );
		return new Response( JSON.stringify( { code: 'RATE_LIMITED', message: 'x', data: { status: 429, retry_after: 7 } } ), { status: 429, headers: { 'X-IAA-Request-Id': 'rid-1', 'Content-Type': 'application/json' } } );
	};
	const api = new Api( memoryStorage( { 'iaa.token': TOKEN } ), fetchFn );
	const r = await api.request( 'POST', '/drafts', { body: { a: 1 }, headers: { 'Idempotency-Key': 'k' } } );
	assert.equal( r.ok, false );
	assert.equal( r.error.code, 'RATE_LIMITED' );
	assert.equal( r.error.data.retry_after, 7 );
	assert.equal( r.requestId, 'rid-1' );
	const h = calls[ 0 ].init.headers;
	assert.equal( h.Authorization, `Bearer ${ TOKEN }` );
	assert.equal( h[ 'X-IAA-Token' ], TOKEN );
	assert.equal( h[ 'Idempotency-Key' ], 'k' );
	assert.match( h[ 'X-IAA-Client' ], /^ext\/\d+\.\d+\.\d+$/ );
	assert.equal( calls[ 0 ].init.credentials, 'omit' );
	assert.equal( calls[ 0 ].init.cache, 'no-store' );
	assert.equal( calls[ 0 ].url, 'https://nabiad.com/wp-json/rar-iaa/v1/drafts' );
} );

test( 'no token → AUTH_MISSING without a network call; network errors are reported', async () => {
	let called = false;
	const api = new Api( memoryStorage(), async () => {
		called = true;
	} );
	const r = await api.request( 'GET', '/me' );
	assert.equal( r.error.code, 'AUTH_MISSING' );
	assert.equal( called, false );
	const api2 = new Api( memoryStorage( { 'iaa.token': TOKEN } ), async () => {
		throw new TypeError( 'Failed to fetch' );
	} );
	assert.equal( ( await api2.request( 'GET', '/me' ) ).error.code, 'NETWORK' );
	const ok = new Api( memoryStorage( { 'iaa.token': TOKEN } ), async () => new Response( null, { status: 204 } ) );
	assert.equal( ( await ok.request( 'POST', '/x' ) ).ok, true );
} );

test( 'saveConnection / forget', async () => {
	const st = memoryStorage();
	const api = new Api( st, async () => {} );
	await api.saveConnection( TOKEN, 'https://nabiad.com' );
	assert.equal( ( await api.config() ).base, 'https://nabiad.com/wp-json/rar-iaa/v1' );
	await api.forget();
	assert.equal( ( await api.config() ).token, null );
} );
