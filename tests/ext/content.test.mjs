import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parseHTML } from 'linkedom';
import vm from 'node:vm';

const root = new URL( '../../extension/', import.meta.url );
const src = ( p ) => readFileSync( new URL( p, root ), 'utf8' );

test( 'never-send: no Enter key events, no clicks, no form submits in content scripts', () => {
	for ( const f of [ 'content/composer.js', 'content/inbox-adapter.js', 'content/main.js' ] ) {
		const code = src( f ).replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /\/\/.*$/gm, '' );
		assert.doesNotMatch( code, /KeyboardEvent|keydown|keypress|keyup/, `${ f }: keyboard events` );
		assert.doesNotMatch( code, /\.click\s*\(|dispatchEvent\(\s*new\s+MouseEvent|\.submit\s*\(|requestSubmit/, `${ f }: clicks/submits` );
		assert.doesNotMatch( code, /['"]Enter['"]/, `${ f }: Enter key` );
		assert.doesNotMatch( code, /fetch\s*\(|XMLHttpRequest|chrome\.storage/, `${ f }: no network or storage access` );
	}
} );

function loadContent( url, html ) {
	const { window, document } = parseHTML( html );
	const listeners = [];
	const sandbox = {
		window, document, location: new URL( url ), URLSearchParams, getComputedStyle: () => ( { visibility: 'visible', display: 'block' } ),
		chrome: { runtime: { id: 'ext', onMessage: { addListener: ( fn ) => listeners.push( fn ) } } },
		DataTransfer: class { setData() {} }, ClipboardEvent: class {},
	};
	sandbox.globalThis = sandbox;
	window.getSelection = () => ( { toString: () => '  Jumbo er dam koto?  ' } );
	sandbox.window = window;
	vm.createContext( sandbox );
	for ( const f of [ 'content/inbox-adapter.js', 'content/composer.js', 'content/main.js' ] ) {
		vm.runInContext( src( f ), sandbox, { filename: f } );
	}
	const send = ( msg, sender = { id: 'ext' } ) => {
		let out;
		listeners[ 0 ]( msg, sender, ( r ) => {
			out = r;
		} );
		return out;
	};
	return { send, sandbox };
}

const URL1 = 'https://business.facebook.com/latest/inbox/all?asset_id=1063479&business_id=9&selected_item_id=t_5';

test( 'content router: key and selection work; capture/insert/selftest need live mode', () => {
	const { send } = loadContent( URL1, '<html><body><main><div dir="auto">hi</div></main></body></html>' );
	assert.deepEqual( JSON.parse( JSON.stringify( send( { channel: 'iaa.content', cmd: 'key' } ).key ) ), { key: '1063479:t_5', asset: '1063479', item: 't_5', business: '9' } );
	const sel = send( { channel: 'iaa.content', cmd: 'selection' } );
	assert.equal( sel.text, 'Jumbo er dam koto?' );
	for ( const cmd of [ 'capture', 'insert', 'selftest' ] ) {
		assert.equal( send( { channel: 'iaa.content', cmd } ).code, 'LIVE_DISABLED', cmd );
		assert.equal( send( { channel: 'iaa.content', cmd, live: 'true' } ).code, 'LIVE_DISABLED', `${ cmd } needs boolean true` );
	}
	assert.equal( send( { channel: 'iaa.content', cmd: 'key' }, { id: 'other-extension' } ), undefined, 'foreign sender ignored' );
	assert.equal( send( { channel: 'other', cmd: 'key' } ), undefined, 'wrong channel ignored' );
} );

test( 'composer refuses a different conversation and a non-empty reply box', () => {
	const { send } = loadContent( URL1, '<html><body><div contenteditable="true" role="textbox">already typed</div></body></html>' );
	assert.equal( send( { channel: 'iaa.content', cmd: 'insert', live: true, text: 'x', key: '1063479:t_OTHER' } ).code, 'STALE' );
} );

test( 'manifest permissions are exactly as documented (TRD §2)', () => {
	const m = JSON.parse( src( 'manifest.json' ) );
	assert.equal( m.manifest_version, 3 );
	assert.deepEqual( m.permissions.sort(), [ 'clipboardWrite', 'sidePanel', 'storage' ] );
	assert.deepEqual( m.host_permissions.sort(), [ 'https://business.facebook.com/*', 'https://nabiad.com/*', 'https://www.nabiad.com/*' ] );
	assert.deepEqual( m.content_scripts[ 0 ].matches, [ 'https://business.facebook.com/latest/inbox/*' ] );
	assert.match( m.content_security_policy.extension_pages, /script-src 'self'/ );
	assert.ok( m.key, 'stable extension id key present' );
	const pkg = JSON.parse( readFileSync( new URL( '../../package.json', import.meta.url ), 'utf8' ) );
	assert.equal( m.version, pkg.version, 'extension and package versions match' );
} );

test( 'panel renders server text with textContent only (no innerHTML)', () => {
	for ( const f of [ 'sidepanel/panel.js', 'sidepanel/state.js', 'background.js', 'lib/api.js', 'lib/text.js', 'lib/ids.js' ] ) {
		assert.doesNotMatch( src( f ), /innerHTML|outerHTML|insertAdjacentHTML|document\.write/, f );
	}
} );
