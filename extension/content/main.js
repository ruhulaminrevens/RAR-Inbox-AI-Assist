/**
 * Content-script message router. Inert until the side panel sends a command.
 * Never reads the page in the background; capture/insert run only when the panel says live
 * inbox mode is enabled by the administrator.
 */
( function () {
	'use strict';

	chrome.runtime.onMessage.addListener( ( msg, sender, sendResponse ) => {
		if ( ! msg || msg.channel !== 'iaa.content' || sender.id !== chrome.runtime.id ) {
			return false;
		}
		const A = globalThis.__IAA_ADAPTER;
		const C = globalThis.__IAA_COMPOSER;
		try {
			switch ( msg.cmd ) {
				case 'ping':
					sendResponse( { ok: true, version: A.VERSION } );
					break;
				case 'key':
					sendResponse( { ok: true, key: A.key() } );
					break;
				case 'selection':
					sendResponse( { ok: true, ...A.selection() } );
					break;
				case 'capture':
					sendResponse( msg.live === true ? { ok: true, ...A.capture() } : { ok: false, code: 'LIVE_DISABLED' } );
					break;
				case 'selftest':
					sendResponse( msg.live === true ? { ok: true, ...A.selfTest() } : { ok: false, code: 'LIVE_DISABLED' } );
					break;
				case 'insert':
					sendResponse( msg.live === true ? C.insert( String( msg.text || '' ), String( msg.key || '' ) ) : { ok: false, code: 'LIVE_DISABLED' } );
					break;
				default:
					sendResponse( { ok: false, code: 'UNKNOWN_COMMAND' } );
			}
		} catch ( e ) {
			sendResponse( { ok: false, code: 'ADAPTER_FAILED' } );
		}
		return false;
	} );
}() );
