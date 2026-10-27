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
	assert.equal( apiBaseFor( 'https://shop.example.com/' ), 'https://shop.example.com/wp-json/rar-iaa/v1' );
	assert.equal( buildUrl( 'https://shop.example.com/wp-json/rar-iaa/v1', '/dealers', { search: 'ra h' } ), 'https://shop.example.com/wp-json/rar-iaa/v1/dealers?search=ra+h' );
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
	const api = new Api( memoryStorage( { 'iaa.token': TOKEN, 'iaa.site': 'https://shop.example.com' } ), fetchFn );
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
	assert.equal( calls[ 0 ].url, 'https://shop.example.com/wp-json/rar-iaa/v1/drafts' );
} );

test( 'no token → AUTH_MISSING without a network call; network errors are reported', async () => {
	let called = false;
	const api = new Api( memoryStorage(), async () => {
		called = true;
	} );
	const r = await api.request( 'GET', '/me' );
	assert.equal( r.error.code, 'AUTH_MISSING' );
	assert.equal( called, false );
	const api2 = new Api( memoryStorage( { 'iaa.token': TOKEN, 'iaa.site': 'https://shop.example.com' } ), async () => {
		throw new TypeError( 'Failed to fetch' );
	} );
	assert.equal( ( await api2.request( 'GET', '/me' ) ).error.code, 'NETWORK' );
	const ok = new Api( memoryStorage( { 'iaa.token': TOKEN, 'iaa.site': 'https://shop.example.com' } ), async () => new Response( null, { status: 204 } ) );
	assert.equal( ( await ok.request( 'POST', '/x' ) ).ok, true );
} );

test( 'saveConnection / forget', async () => {
	const st = memoryStorage();
	const api = new Api( st, async () => {} );
	await api.saveConnection( TOKEN, 'https://shop.example.com' );
	assert.equal( ( await api.config() ).base, 'https://shop.example.com/wp-json/rar-iaa/v1' );
	await api.forget();
	assert.equal( ( await api.config() ).token, null );
} );

test( 'wwwTwin toggles www for https sites only', async () => {
	const { wwwTwin } = await import( '../../extension/lib/api.js' );
	assert.equal( wwwTwin( 'https://shop.example.com' ), 'https://www.shop.example.com' );
	assert.equal( wwwTwin( 'https://www.shop.example.com/' ), 'https://shop.example.com' );
	assert.equal( wwwTwin( 'http://shop.example.com' ), null );
	assert.equal( wwwTwin( 'not a url' ), null );
} );

test( 'requests never follow redirects; a redirect is reported as REDIRECT', async () => {
	let seen = null;
	const api = new Api( memoryStorage( { 'iaa.token': 'iaa_abcdefgh_' + 'x'.repeat( 40 ), 'iaa.site': 'https://shop.example.com' } ), async ( url, init ) => {
		seen = init;
		return { type: 'opaqueredirect', status: 0, ok: false, headers: new Map(), json: async () => null };
	} );
	const r = await api.request( 'GET', '/me' );
	assert.equal( seen.redirect, 'manual' );
	assert.equal( r.ok, false );
	assert.equal( r.error.code, 'REDIRECT' );
} );

test( 'no built-in site; host permissions requested per site (1.0.0)', async () => {
	const { DEFAULT_SITE, originPatterns } = await import( '../../extension/lib/api.js' );
	assert.equal( DEFAULT_SITE, '' );
	assert.deepEqual( originPatterns( 'https://shop.example.com/wp' ), [ 'https://shop.example.com/*', 'https://www.shop.example.com/*' ] );
	assert.deepEqual( originPatterns( 'http://127.0.0.1:8899/index.php' ), [ 'http://127.0.0.1/*' ] );
	assert.equal( originPatterns( 'http://shop.example.com' ), null, 'plain http only for local tests' );
	assert.equal( originPatterns( 'nope' ), null );
	const calls = [];
	const store = new Map();
	const storage = { get: async ( keys ) => Object.fromEntries( keys.filter( ( k ) => store.has( k ) ).map( ( k ) => [ k, store.get( k ) ] ) ), set: async ( o ) => Object.entries( o ).forEach( ( [ k, v ] ) => store.set( k, v ) ), remove: async () => {} };
	const api = new Api( storage, async ( u ) => calls.push( u ) );
	assert.deepEqual( await api.config(), { token: null, site: '', base: '' }, 'unconnected: no site, no base' );
} );
