/**
 * Service worker: holds the API client, forwards tab URL changes to the panel, handles the
 * keyboard command. Only extension pages (the side panel) may call the API through it —
 * messages from content scripts are refused.
 */

import { Api, TOKEN_RE, apiBaseFor, DEFAULT_SITE } from './lib/api.js';
import { uuid4 } from './lib/ids.js';

const api = new Api( chrome.storage.local, ( ...a ) => fetch( ...a ) );

async function restrictStorage() {
	// Chrome storage is not encrypted; keep the device token away from content scripts (audit F-07).
	try {
		await chrome.storage.local.setAccessLevel( { accessLevel: 'TRUSTED_CONTEXTS' } );
	} catch ( e ) {
		// Older Chrome: local storage is not exposed to content scripts by default.
	}
}

chrome.runtime.onInstalled.addListener( () => {
	restrictStorage();
	chrome.sidePanel.setPanelBehavior( { openPanelOnActionClick: true } ).catch( () => {} );
} );
chrome.runtime.onStartup.addListener( restrictStorage );
restrictStorage();

/* ------------------------------------------------------------------ Tab tracking → panel */

function notifyPanel( msg ) {
	chrome.runtime.sendMessage( msg ).catch( () => {} );
}

chrome.tabs.onUpdated.addListener( ( tabId, change, tab ) => {
	if ( change.url && tab.active ) {
		notifyPanel( { type: 'tab.url', tabId, url: change.url } );
	}
} );
chrome.tabs.onActivated.addListener( async ( { tabId } ) => {
	try {
		const tab = await chrome.tabs.get( tabId );
		notifyPanel( { type: 'tab.url', tabId, url: tab.url || '' } );
	} catch ( e ) {
		notifyPanel( { type: 'tab.url', tabId, url: '' } );
	}
} );

chrome.commands.onCommand.addListener( async ( command, tab ) => {
	if ( command !== 'draft' ) {
		return;
	}
	try {
		if ( tab && tab.windowId !== undefined ) {
			await chrome.sidePanel.open( { windowId: tab.windowId } );
		}
	} catch ( e ) {
		// Panel may already be open.
	}
	setTimeout( () => notifyPanel( { type: 'command.draft' } ), 300 );
} );

/* ------------------------------------------------------------------ Diagnostics (no text) */

async function remember( kind, res ) {
	try {
		const v = await chrome.storage.local.get( 'iaa.diag' );
		const list = Array.isArray( v[ 'iaa.diag' ] ) ? v[ 'iaa.diag' ] : [];
		list.unshift( { at: new Date().toISOString(), kind, status: res.status, code: res.error ? res.error.code : 'OK', request_id: res.requestId || ( res.error && res.error.data && res.error.data.request_id ) || null } );
		await chrome.storage.local.set( { 'iaa.diag': list.slice( 0, 5 ) } );
	} catch ( e ) {
		// Diagnostics are best-effort.
	}
}

/* ------------------------------------------------------------------ Outcome retry */

async function sendOutcome( draftId, body, attempt = 0 ) {
	const res = await api.request( 'POST', `/drafts/${ draftId }/outcome`, { body } );
	if ( ! res.ok && ( res.status === 0 || res.status >= 500 ) && attempt < 3 ) {
		setTimeout( () => sendOutcome( draftId, body, attempt + 1 ), [ 1000, 3000, 9000 ][ attempt ] );
	}
	return res;
}

/* ------------------------------------------------------------------ Panel commands */

const HANDLERS = {
	async status() {
		const cfg = await api.config();
		if ( ! cfg.token ) {
			return { connected: false, site: cfg.site };
		}
		const res = await api.request( 'GET', '/me' );
		await remember( 'me', res );
		return { connected: res.ok, site: cfg.site, me: res.data, error: res.error };
	},

	async connect( { token, site, base } ) {
		if ( ! TOKEN_RE.test( String( token || '' ) ) ) {
			return { ok: false, error: { code: 'TOKEN_FORMAT', message: 'Token format is wrong' } };
		}
		const s = ( site || DEFAULT_SITE ).replace( /\/+$/, '' );
		const b = base || apiBaseFor( s );
		const res = await api.request( 'GET', '/me', { token, base: b } );
		await remember( 'connect', res );
		if ( res.ok ) {
			await api.saveConnection( token, s, b );
		}
		return res;
	},

	async disconnect() {
		const res = await api.request( 'POST', '/tokens/self/revoke' );
		await api.forget();
		return { ok: true, revoked: res.ok };
	},

	async draft( { body, thread, idempotencyKey } ) {
		const headers = { 'Idempotency-Key': idempotencyKey || uuid4() };
		if ( thread ) {
			headers[ 'X-IAA-Thread' ] = thread;
		}
		const res = await api.request( 'POST', '/drafts', { body, headers } );
		await remember( 'draft', res );
		return res;
	},

	async rewrite( { draftId, body } ) {
		const res = await api.request( 'POST', `/drafts/${ draftId }/rewrite`, { body, headers: { 'Idempotency-Key': uuid4() } } );
		await remember( 'rewrite', res );
		return res;
	},

	async outcome( { draftId, body } ) {
		return sendOutcome( draftId, body );
	},

	async linkGet( { thread } ) {
		return api.request( 'GET', '/threads/current/link', { headers: { 'X-IAA-Thread': thread } } );
	},

	async linkPut( { thread, dealerId, expected } ) {
		return api.request( 'PUT', '/threads/current/link', { headers: { 'X-IAA-Thread': thread }, body: { dealer_id: dealerId, expected_dealer_id: expected } } );
	},

	async linkDelete( { thread } ) {
		return api.request( 'DELETE', '/threads/current/link', { headers: { 'X-IAA-Thread': thread } } );
	},

	async dealers( { search } ) {
		return api.request( 'GET', '/dealers', { params: { search, page: 1, per_page: 10 } } );
	},

	async kbSearch( { q } ) {
		return api.request( 'GET', '/kb/search', { params: { q } } );
	},

	async kbUsed( { id } ) {
		return api.request( 'POST', `/kb/${ Number( id ) }/used` );
	},

	async diag() {
		const v = await chrome.storage.local.get( [ 'iaa.diag', 'iaa.site', 'iaa.base' ] );
		let commands = [];
		try {
			commands = await chrome.commands.getAll();
		} catch ( e ) {
			commands = [];
		}
		return { diag: v[ 'iaa.diag' ] || [], site: v[ 'iaa.site' ] || DEFAULT_SITE, base: v[ 'iaa.base' ] || null, commands: commands.map( ( c ) => ( { name: c.name, shortcut: c.shortcut } ) ), version: chrome.runtime.getManifest().version };
	},
};

chrome.runtime.onMessage.addListener( ( msg, sender, sendResponse ) => {
	// Only our own extension pages (not content scripts, not web pages) may use the API.
	if ( ! msg || msg.channel !== 'iaa.panel' || sender.id !== chrome.runtime.id || sender.tab ) {
		return false;
	}
	const fn = HANDLERS[ msg.cmd ];
	if ( ! fn ) {
		sendResponse( { ok: false, error: { code: 'UNKNOWN_COMMAND' } } );
		return false;
	}
	fn( msg.args || {} ).then( sendResponse, ( e ) => sendResponse( { ok: false, error: { code: 'INTERNAL_ERROR', message: String( e && e.message ) } } ) );
	return true;
} );
