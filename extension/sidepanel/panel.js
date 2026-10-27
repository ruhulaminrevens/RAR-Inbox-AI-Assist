/**
 * Side panel (AppFlow §4, §7, §11). All server and customer text is rendered with textContent.
 * The panel never sends a Meta message: Insert only fills the reply box (live mode) and Copy
 * puts text on the clipboard; the staff member presses Send in Meta.
 */

import { icon } from './ui/icons.js';
import { parseKey, isInboxUrl, uuid4 } from '../lib/ids.js';
import { editDistance, squash } from '../lib/text.js';
import {
	VARIANT_LABELS, HANDOFF_LABELS, GUARD_LABELS, ERROR_ACTIONS,
	warningText, errorText, isStale, insertCheck, selectedSuggestion, advisoryFor,
	outcomeFor, messagesFromText, nextVariant, aiNote,
} from './state.js';

/* ================================================================== State */

const S = {
	view: 'loading',
	me: null,
	site: null,
	live: false,
	tab: { id: null, url: '' },
	link: null,
	draft: null, // { id, suggestions, audience, dealer, thread_bound, warnings, conv, requestId }
	selected: null,
	edited: '',
	undo: null,
	stale: false,
	error: null,
	lastInput: null, // { body, conv }
	pasteOpen: false,
	gen: 0,
	quick: { q: '', results: [], lang: 'bn' },
	busy: false,
};

const app = document.getElementById( 'iaa-app' );
const bar = document.getElementById( 'iaa-actionbar' );

/* ================================================================== DOM helpers (text only) */

function h( tag, attrs, ...kids ) {
	const el = document.createElement( tag );
	for ( const [ k, v ] of Object.entries( attrs || {} ) ) {
		if ( v === false || v === null || v === undefined ) {
			continue;
		}
		if ( k === 'class' ) {
			el.className = v;
		} else if ( k.startsWith( 'on' ) ) {
			el.addEventListener( k.slice( 2 ), v );
		} else if ( k === 'text' ) {
			el.textContent = v;
		} else {
			el.setAttribute( k, v === true ? '' : String( v ) );
		}
	}
	for ( const kid of kids.flat() ) {
		if ( kid === null || kid === undefined || kid === false ) {
			continue;
		}
		el.append( typeof kid === 'string' || typeof kid === 'number' ? document.createTextNode( String( kid ) ) : kid );
	}
	return el;
}

function btn( label, onclick, variant = 'secondary', iconName = null, extra = {} ) {
	return h( 'button', { type: 'button', class: `iaa-btn iaa-btn--${ variant }${ extra.sm ? ' iaa-btn--sm' : '' }`, onclick, disabled: extra.disabled || false, 'aria-label': extra.aria || null, title: extra.title || null }, iconName ? icon( iconName, 18 ) : null, label ? h( 'span', {}, label ) : null );
}

function alertBox( kind, iconName, ...kids ) {
	return h( 'div', { class: `iaa-alert iaa-alert--${ kind }`, role: kind === 'danger' || kind === 'warning' ? 'alert' : 'status' }, icon( iconName, 16 ), h( 'div', {}, ...kids ) );
}

function announce( text ) {
	const live = document.getElementById( 'iaa-live' );
	live.textContent = '';
	setTimeout( () => {
		live.textContent = text;
	}, 30 );
}

let toastTimer = null;
function toast( text, isError = false ) {
	const root = document.getElementById( 'iaa-toast' );
	root.replaceChildren( h( 'div', { class: `iaa-toast${ isError ? ' iaa-toast--error' : '' }`, role: 'status' }, h( 'span', { class: 'iaa-grow' }, text ), btn( '', () => root.replaceChildren(), 'ghost', 'x', { sm: true, aria: 'বন্ধ' } ) ) );
	clearTimeout( toastTimer );
	toastTimer = setTimeout( () => root.replaceChildren(), isError ? 8000 : 4000 );
}

/* ================================================================== Messaging */

function bg( cmd, args = {} ) {
	return chrome.runtime.sendMessage( { channel: 'iaa.panel', cmd, args } );
}

async function content( cmd, extra = {} ) {
	if ( ! S.tab.id || ! isInboxUrl( S.tab.url ) ) {
		return { ok: false, code: 'NOT_INBOX' };
	}
	try {
		const r = await chrome.tabs.sendMessage( S.tab.id, { channel: 'iaa.content', cmd, live: S.live, ...extra } );
		return r || { ok: false, code: 'ADAPTER_FAILED' };
	} catch ( e ) {
		return { ok: false, code: 'ADAPTER_FAILED' };
	}
}

async function refreshTab() {
	try {
		const [ tab ] = await chrome.tabs.query( { active: true, currentWindow: true } );
		S.tab = { id: tab ? tab.id : null, url: tab && tab.url ? tab.url : '' };
	} catch ( e ) {
		S.tab = { id: null, url: '' };
	}
}

function conv() {
	return parseKey( S.tab.url );
}

function can( cap ) {
	return !! ( S.me && Array.isArray( S.me.caps ) && S.me.caps.includes( cap ) );
}

/* ================================================================== Boot */

async function boot() {
	try {
		const t = ( await chrome.storage.local.get( 'iaa.theme' ) )[ 'iaa.theme' ];
		if ( t === 'light' || t === 'dark' ) {
			document.documentElement.setAttribute( 'data-theme', t );
		}
	} catch ( e ) {
		// default theme
	}
	document.getElementById( 'iaa-quick' ).append( icon( 'book-open', 18 ) );
	document.getElementById( 'iaa-settings' ).append( icon( 'settings', 18 ) );
	document.getElementById( 'iaa-quick' ).addEventListener( 'click', () => go( 'quick' ) );
	document.getElementById( 'iaa-settings' ).addEventListener( 'click', () => go( 'diag' ) );
	await refreshTab();
	await loadStatus();
}

async function loadStatus() {
	const st = await bg( 'status' );
	S.site = st && st.site;
	if ( ! st || ! st.connected ) {
		S.me = null;
		go( 'connect', st && st.error ? errorText( st.error.code, st.error.data ) : null );
		return;
	}
	S.me = st.me;
	S.live = !! ( st.me.settings && st.me.settings.live_inbox );
	if ( ! st.me.service.enabled ) {
		go( 'disabled' );
		return;
	}
	if ( ! can( 'use' ) ) {
		S.error = { code: 'FORBIDDEN', data: {} };
		go( 'error' );
		return;
	}
	await loadLink();
	go( 'ready' );
}

async function loadLink() {
	S.link = null;
	const c = conv();
	if ( ! c ) {
		return;
	}
	const r = await bg( 'linkGet', { thread: c.key } );
	if ( r && r.ok ) {
		S.link = r.data;
	}
}

function go( view, message = null ) {
	S.view = view;
	render( message );
}

/* ================================================================== Events from background */

chrome.runtime.onMessage.addListener( ( msg ) => {
	if ( ! msg || ! msg.type ) {
		return;
	}
	if ( msg.type === 'tab.url' ) {
		S.tab = { id: msg.tabId, url: msg.url };
		if ( S.draft && S.draft.conv ) {
			const stale = isStale( S.draft.conv, msg.url );
			if ( stale !== S.stale ) {
				S.stale = stale;
				if ( stale ) {
					announce( 'কথোপকথন বদলেছে — এই সাজেশন আগের কথোপকথনের।' );
				}
			}
		}
		if ( S.view === 'ready' ) {
			loadLink().then( () => render() );
		} else {
			render();
		}
	}
	if ( msg.type === 'command.draft' && S.view === 'ready' ) {
		S.live ? fromConversation() : fromSelection();
	}
} );

/* ================================================================== Actions: create */

async function fromSelection() {
	const r = await content( 'selection' );
	if ( ! r.ok ) {
		toast( isInboxUrl( S.tab.url ) ? 'পেজ থেকে লেখা পড়া যায়নি। পেজটি একবার রিফ্রেশ করুন অথবা পেস্ট ব্যবহার করুন।' : 'Meta Business Suite Inbox খুলুন অথবা পেস্ট ব্যবহার করুন।', true );
		return;
	}
	if ( ! r.text ) {
		toast( 'কাস্টমারের মেসেজটি মাউস দিয়ে select করুন, তারপর আবার চাপুন।', true );
		return;
	}
	await createDraft( { source: 'selection', messages: messagesFromText( r.text ) }, r.key );
}

async function fromConversation() {
	const r = await content( 'capture' );
	if ( ! r.ok || ! r.messages || ! r.messages.some( ( m ) => m.side === 'customer' ) ) {
		toast( 'কথোপকথন পড়া যায়নি। মেসেজ select করে "নির্বাচিত লেখা" ব্যবহার করুন।', true );
		return;
	}
	await createDraft( { source: 'capture', messages: r.messages, adapter: r.adapter }, r.key );
}

async function fromPaste( text ) {
	const msgs = messagesFromText( text );
	if ( ! msgs.length ) {
		toast( 'কাস্টমারের লেখা পেস্ট করুন।', true );
		return;
	}
	await createDraft( { source: 'paste', messages: msgs }, conv() );
}

async function createDraft( body, convAtCapture, idem = null ) {
	S.lastInput = { body, conv: convAtCapture || null };
	S.gen += 1;
	const gen = S.gen;
	S.error = null;
	go( 'generating' );
	announce( 'সাজেশন তৈরি হচ্ছে…' );
	const key = idem || uuid4();
	const r = await bg( 'draft', { body, thread: convAtCapture ? convAtCapture.key : null, idempotencyKey: key } );
	if ( gen !== S.gen ) {
		return; // cancelled
	}
	if ( ! r || ! r.ok ) {
		const err = r && r.error ? r.error : { code: 'NETWORK', data: {} };
		if ( err.code === 'BUSY' && ! idem ) {
			const wait = Math.min( 10, Number( err.data && err.data.retry_after ) || 3 );
			await new Promise( ( res ) => setTimeout( res, wait * 1000 ) );
			if ( gen === S.gen ) {
				await createDraft( body, convAtCapture, key );
			}
			return;
		}
		handleError( err );
		return;
	}
	const d = r.data;
	S.draft = {
		id: d.draft_id,
		suggestions: d.suggestions,
		audience: d.audience,
		dealer: d.dealer,
		thread_bound: d.thread_bound && !! convAtCapture,
		warnings: d.warnings || [],
		conv: convAtCapture || null,
		requestId: d.request_id,
		ai: d.ai || null,
		reported: false,
	};
	S.selected = null;
	S.edited = '';
	S.undo = null;
	S.stale = S.draft.conv ? isStale( S.draft.conv, S.tab.url ) : false;
	go( 'suggestions' );
	const warned = d.suggestions.filter( ( x ) => x.guard.result !== 'pass' ).length;
	announce( `${ d.suggestions.length }টি সাজেশন তৈরি` + ( warned ? `, ${ warned }টিতে সতর্কতা` : '' ) );
}

function handleError( err ) {
	if ( ERROR_ACTIONS.reconnect.includes( err.code ) ) {
		S.me = null;
		go( 'connect', errorText( err.code, err.data ) );
		return;
	}
	if ( err.code === 'SERVICE_DISABLED' ) {
		go( 'disabled' );
		return;
	}
	S.error = err;
	go( 'error' );
}

/* ================================================================== Actions: selected draft */

function select( id ) {
	const sug = S.draft.suggestions.find( ( x ) => x.variant_id === id );
	if ( ! sug ) {
		return;
	}
	S.selected = id;
	S.edited = sug.reply;
	S.undo = null;
	render();
	const card = app.querySelector( `[data-variant="${ id }"]` );
	if ( card ) {
		card.focus();
	}
	announce( `${ VARIANT_LABELS[ id ] } নির্বাচিত` );
}

async function report( outcome, extra = {} ) {
	if ( ! S.draft || S.draft.reported ) {
		return;
	}
	S.draft.reported = true;
	const sug = selectedSuggestion( S );
	await bg( 'outcome', {
		draftId: S.draft.id,
		body: {
			outcome,
			selected_variant_id: S.selected,
			reject_reason: extra.reason || null,
			insert_failed: !! extra.insertFailed,
			edit_distance: sug ? editDistance( sug.reply, S.edited ) : 0,
		},
	} );
}

async function copyText( text ) {
	try {
		await navigator.clipboard.writeText( text );
		return true;
	} catch ( e ) {
		return false;
	}
}

async function doCopy( insertFailed = false ) {
	const ok = await copyText( S.edited );
	if ( ! ok ) {
		toast( 'কপি করা যায়নি। লেখাটি নিজে select করে কপি করুন।', true );
		return;
	}
	await report( outcomeFor( 'copy', S ), { insertFailed } );
	toast( insertFailed ? 'বসানো যায়নি — কপি হয়েছে। Meta-র রিপ্লাই বক্সে Ctrl+V দিয়ে পেস্ট করে দেখে Send করুন।' : 'কপি হয়েছে। Meta-র রিপ্লাই বক্সে Ctrl+V দিয়ে পেস্ট করে দেখে Send করুন।' );
	announce( 'কপি হয়েছে' );
	render();
}

async function doInsert() {
	const check = insertCheck( S );
	if ( ! check.ok ) {
		return;
	}
	// Re-read the live conversation identity immediately before inserting (AppFlow §7, §11).
	const now = await content( 'key' );
	if ( ! now.ok || ! now.key || now.key.key !== S.draft.conv.key || ( now.key.business && S.draft.conv.business && now.key.business !== S.draft.conv.business ) ) {
		S.stale = true;
		render();
		toast( 'কথোপকথন বদলেছে — এই সাজেশন বসানো যাবে না। কপি করতে পারেন।', true );
		return;
	}
	const r = await content( 'insert', { text: S.edited, key: S.draft.conv.key } );
	if ( r.ok ) {
		await report( outcomeFor( 'insert', S ) );
		toast( 'রিপ্লাই বক্সে বসানো হয়েছে — দেখে নিজে Send করুন।' );
		announce( 'রিপ্লাই বক্সে বসানো হয়েছে' );
		render();
		return;
	}
	if ( r.code === 'STALE' ) {
		S.stale = true;
		render();
		toast( 'কথোপকথন বদলেছে — বসানো হয়নি।', true );
		return;
	}
	if ( r.code === 'COMPOSER_NOT_EMPTY' ) {
		toast( 'রিপ্লাই বক্সে আগে থেকেই লেখা আছে। সেটি মুছে আবার চাপুন, অথবা কপি করুন।', true );
		return;
	}
	await doCopy( true );
}

async function doReject( reason ) {
	await report( 'rejected', { reason } );
	toast( 'বাদ দেওয়া হয়েছে।' );
	S.draft = null;
	S.selected = null;
	go( 'ready' );
}

async function doRewrite( mode ) {
	const sug = selectedSuggestion( S );
	if ( ! sug || S.busy ) {
		return;
	}
	S.busy = true;
	render();
	announce( 'নতুন করে লেখা হচ্ছে…' );
	const r = await bg( 'rewrite', { draftId: S.draft.id, body: { mode, current_text: S.edited, variant_id: sug.variant_id } } );
	S.busy = false;
	if ( ! r || ! r.ok ) {
		toast( errorText( r && r.error ? r.error.code : 'NETWORK', r && r.error ? r.error.data : {} ), true );
		render();
		return;
	}
	const ns = r.data.suggestions[ 0 ];
	S.undo = { suggestion: { ...sug }, edited: S.edited };
	const i = S.draft.suggestions.findIndex( ( x ) => x.variant_id === sug.variant_id );
	S.draft.suggestions[ i ] = ns;
	S.edited = ns.reply;
	S.draft.warnings = ( r.data.warnings || [] ).concat( S.draft.warnings.filter( ( w ) => w.code !== 'FACTS_CHANGED' ) );
	render();
	announce( 'নতুন লেখা তৈরি' );
}

function doUndo() {
	if ( ! S.undo ) {
		return;
	}
	const i = S.draft.suggestions.findIndex( ( x ) => x.variant_id === S.undo.suggestion.variant_id );
	S.draft.suggestions[ i ] = S.undo.suggestion;
	S.edited = S.undo.edited;
	S.undo = null;
	render();
}

/* ================================================================== Render */

function render( message = null ) {
	app.setAttribute( 'aria-busy', S.view === 'loading' || S.view === 'generating' ? 'true' : 'false' );
	const dot = document.getElementById( 'iaa-dot' );
	dot.className = 'iaa-dot ' + ( S.view === 'disabled' ? 'iaa-dot--warn' : S.me ? 'iaa-dot--ok' : 'iaa-dot--bad' );
	document.getElementById( 'iaa-quick' ).hidden = ! S.me;
	bar.replaceChildren();
	const views = { loading: viewLoading, connect: viewConnect, ready: viewReady, generating: viewGenerating, suggestions: viewSuggestions, error: viewError, quick: viewQuick, diag: viewDiag, disabled: viewDisabled };
	app.replaceChildren( ...( views[ S.view ] || viewLoading )( message ).flat().filter( ( n ) => n !== null && n !== undefined && n !== false ) );
}

function viewLoading() {
	return [ h( 'div', { class: 'iaa-skel' } ), h( 'div', { class: 'iaa-skel' } ) ];
}

/* ---------------- S-01 Connect */
function viewConnect( message ) {
	const tokenInput = h( 'input', { class: 'iaa-input', id: 'iaa-token', type: 'password', autocomplete: 'off', spellcheck: 'false', placeholder: 'iaa_xxxxxxxx_…' } );
	const siteInput = h( 'input', { class: 'iaa-input', id: 'iaa-site', type: 'url', value: S.site || 'https://nabiad.com' } );
	const baseInput = h( 'input', { class: 'iaa-input', id: 'iaa-base', type: 'url', placeholder: '(ঐচ্ছিক) API base' } );
	const err = h( 'p', { class: 'iaa-error-text', id: 'iaa-token-err', role: 'alert' } );
	const connect = async () => {
		const token = tokenInput.value.trim();
		if ( ! /^iaa_[a-z0-9]{8}_[A-Za-z0-9]{40}$/.test( token ) ) {
			err.textContent = 'Token ঠিক নেই। wp-admin → Inbox AI Assist → Access tokens থেকে নতুন token নিন।';
			tokenInput.setAttribute( 'aria-invalid', 'true' );
			return;
		}
		let site = siteInput.value.trim().replace( /\/+$/, '' ) || 'https://nabiad.com';
		const base = baseInput.value.trim() || null;
		try {
			const origin = new URL( base || site ).origin;
			if ( ! [ 'https://nabiad.com', 'https://www.nabiad.com' ].includes( origin ) ) {
				const granted = await chrome.permissions.request( { origins: [ origin + '/*' ] } );
				if ( ! granted ) {
					err.textContent = 'এই সাইটে সংযোগের অনুমতি দেওয়া হয়নি।';
					return;
				}
			}
		} catch ( e ) {
			err.textContent = 'সাইটের ঠিকানা ঠিক নেই।';
			return;
		}
		err.textContent = 'সংযোগ করা হচ্ছে…';
		const r = await bg( 'connect', { token, site, base } );
		if ( r && r.ok ) {
			tokenInput.value = '';
			toast( r.movedTo ? `সংযোগ হয়েছে (${ new URL( r.movedTo ).hostname })।` : 'সংযোগ হয়েছে।' );
			await loadStatus();
			return;
		}
		const code = r && r.error ? r.error.code : 'NETWORK';
		err.textContent = code === 'NETWORK' || code === 'CLIENT_TIMEOUT' ? 'সাইটে পৌঁছানো যাচ্ছে না। ইন্টারনেট দেখুন।' : errorText( code, r.error.data );
		tokenInput.setAttribute( 'aria-invalid', 'true' );
	};
	tokenInput.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Enter' ) {
			connect();
		}
	} );
	return [
		h( 'h2', { class: 'iaa-h2' }, 'সংযোগ করুন' ),
		message ? alertBox( 'warning', 'triangle-alert', h( 'p', {}, message ) ) : null,
		h( 'ol', { class: 'iaa-steps' },
			h( 'li', {}, h( 'strong', {}, 'nabiad.com-এ লগইন করুন' ), h( 'span', {}, 'নিজের অ্যাকাউন্টে → wp-admin → Inbox AI Assist → Access tokens' ) ),
			h( 'li', {}, h( 'strong', {}, '"Create token" চাপুন ও কপি করুন' ), h( 'span', {}, 'Token একবারই দেখানো হয়' ) ),
			h( 'li', {}, h( 'strong', {}, 'নিচে পেস্ট করে সংযোগ করুন' ), h( 'span', {}, 'www থাকলে বা না থাকলেও নিজে থেকে ঠিক ঠিকানা খুঁজে নেবে' ) ) ),
		h( 'div', { class: 'iaa-field' }, h( 'label', { class: 'iaa-label', for: 'iaa-token' }, 'Token' ), tokenInput, err ),
		h( 'details', {}, h( 'summary', { class: 'iaa-muted' }, 'উন্নত (Advanced)' ), h( 'div', { class: 'iaa-stack' }, h( 'label', { class: 'iaa-label', for: 'iaa-site' }, 'সাইট' ), siteInput, h( 'label', { class: 'iaa-label', for: 'iaa-base' }, 'API base (শুধু টেস্টের জন্য)' ), baseInput ) ),
		btn( 'সংযোগ করুন', connect, 'primary', 'plug' ),
	];
}

/* ---------------- Dealer chip + link dialog (S-09) */
function dealerChip() {
	const c = conv();
	if ( ! c ) {
		return null;
	}
	const linked = S.link && S.link.linked && S.link.dealer && S.link.dealer.active;
	const chip = h( 'div', { class: `iaa-dealer${ linked ? '' : ' iaa-dealer--unverified' }` }, h( 'span', { class: 'iaa-dealer__dot', 'aria-hidden': 'true' } ),
		h( 'span', { class: 'iaa-grow' }, linked ? `ডিলার: ${ S.link.dealer.code } · ${ S.link.dealer.name } · Tier ${ S.link.dealer.tier }` : 'যাচাই হয়নি — শুধু সাধারণ তথ্য (ডিলার লিংক নেই)' ),
		can( 'link_dealer' ) ? btn( linked ? 'বদলান' : 'ডিলার লিংক', () => openLinkDialog( c ), 'ghost', 'link-2', { sm: true } ) : null );
	return h( 'div', { class: 'iaa-panel' }, chip );
}

function openLinkDialog( c ) {
	const input = h( 'input', { class: 'iaa-input', type: 'search', placeholder: 'কোড বা নাম (কমপক্ষে ২ অক্ষর)', 'aria-label': 'ডিলার খুঁজুন' } );
	const list = h( 'ul', { class: 'iaa-list', role: 'listbox', 'aria-label': 'ডিলার' } );
	const status = h( 'p', { class: 'iaa-help', role: 'status' } );
	let results = [];
	let active = -1;
	let timer = null;
	const current = S.link && S.link.linked ? S.link.dealer : null;
	const drawList = () => {
		list.replaceChildren( ...results.map( ( d, i ) => h( 'li', { role: 'none' }, h( 'button', { type: 'button', class: 'iaa-list__item', role: 'option', 'aria-selected': i === active ? 'true' : 'false', onclick: () => choose( d ) }, `${ d.code } · ${ d.name } · Tier ${ d.tier }` + ( d.district ? ` · ${ d.district }` : '' ) ) ) ) );
	};
	const search = async () => {
		const q = input.value.trim();
		if ( q.length < 2 ) {
			results = [];
			drawList();
			return;
		}
		status.textContent = 'খোঁজা হচ্ছে…';
		const r = await bg( 'dealers', { search: q } );
		results = r && r.ok ? r.data : [];
		active = results.length ? 0 : -1;
		status.textContent = results.length ? `${ results.length }টি পাওয়া গেছে` : 'কোনো ডিলার পাওয়া যায়নি';
		drawList();
	};
	const dialog = h( 'dialog', { class: 'iaa-dialog', 'aria-labelledby': 'iaa-link-title' } );
	const close = () => {
		dialog.close();
		dialog.remove();
	};
	const choose = async ( d ) => {
		const r = await bg( 'linkPut', { thread: c.key, dealerId: d.id, expected: current ? current.id : null } );
		if ( r && r.ok ) {
			S.link = r.data;
			toast( 'ডিলার লিংক হয়েছে।' );
			close();
			render();
			return;
		}
		if ( r && r.error && r.error.code === 'LINK_CONFLICT' ) {
			await loadLink();
			toast( errorText( 'LINK_CONFLICT' ), true );
			close();
			render();
			return;
		}
		status.textContent = errorText( r && r.error ? r.error.code : 'NETWORK' );
	};
	const unlink = async () => {
		const r = await bg( 'linkDelete', { thread: c.key } );
		if ( r && r.ok ) {
			S.link = { linked: false };
			toast( 'লিংক সরানো হয়েছে।' );
			close();
			render();
		}
	};
	input.addEventListener( 'input', () => {
		clearTimeout( timer );
		timer = setTimeout( search, 300 );
	} );
	input.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
			e.preventDefault();
			if ( results.length ) {
				active = ( active + ( e.key === 'ArrowDown' ? 1 : -1 ) + results.length ) % results.length;
				drawList();
			}
		} else if ( e.key === 'Enter' && active >= 0 ) {
			e.preventDefault();
			choose( results[ active ] );
		}
	} );
	dialog.addEventListener( 'cancel', ( e ) => {
		e.preventDefault();
		close();
	} );
	dialog.append(
		h( 'div', { class: 'iaa-stack' },
			h( 'h2', { class: 'iaa-h2', id: 'iaa-link-title' }, 'ডিলার লিংক করুন' ),
			h( 'p', { class: 'iaa-help' }, 'নিশ্চিত হয়ে নিন এই কথোপকথন সত্যিই এই ডিলারের। লিংক হলে এই কথোপকথনে ডিলারের টায়ারের দাম ব্যবহার হবে।' ),
			input, status, list,
			h( 'div', { class: 'iaa-row' }, current ? btn( 'লিংক সরান', unlink, 'danger', 'x', { sm: true } ) : null, btn( 'বাতিল', close, 'ghost', null, { sm: true } ) ) )
	);
	document.body.append( dialog );
	dialog.showModal();
	input.focus();
}

/* ---------------- S-02 / S-03 Ready */
function viewReady() {
	const onInbox = isInboxUrl( S.tab.url );
	const pasteArea = h( 'textarea', { class: 'iaa-textarea', id: 'iaa-paste', maxlength: '2000', lang: 'bn', placeholder: 'কাস্টমারের মেসেজ এখানে পেস্ট করুন' } );
	const out = [];
	if ( ! onInbox ) {
		out.push( h( 'div', { class: 'iaa-empty' }, icon( 'info', 32 ), h( 'p', {}, 'Meta Business Suite Inbox খুলুন।' ), btn( 'Inbox খুলুন', () => chrome.tabs.update( S.tab.id, { url: 'https://business.facebook.com/latest/inbox/all' } ), 'secondary' ), h( 'p', { class: 'iaa-help' }, 'অথবা নিচে পেস্ট করে সাজেশন নিন।' ) ) );
	} else {
		out.push( dealerChip() );
		out.push( h( 'ol', { class: 'iaa-steps' },
			S.live
				? h( 'li', {}, h( 'strong', {}, 'কথোপকথন খুলুন' ), h( 'span', {}, '"কথোপকথন থেকে সাজেশন" চাপুন, অথবা মেসেজ select করে "নির্বাচিত লেখা থেকে"' ) )
				: h( 'li', {}, h( 'strong', {}, 'কাস্টমারের মেসেজ select করুন' ), h( 'span', {}, 'মাউস দিয়ে মেসেজের লেখাটি টেনে নির্বাচন করুন' ) ),
			h( 'li', {}, h( 'strong', {}, S.live ? 'একটি সাজেশন বেছে নিন' : '"নির্বাচিত লেখা থেকে সাজেশন" চাপুন' ), h( 'span', {}, S.live ? 'দরকার হলে এডিট করুন' : 'বা Alt+Shift+D — ৩টি যাচাইকৃত সাজেশন আসবে' ) ),
			h( 'li', {}, h( 'strong', {}, S.live ? 'বসান, দেখে নিজে Send করুন' : 'বেছে নিন → কপি → Meta-তে পেস্ট করে নিজে Send' ), h( 'span', {}, 'এক্সটেনশন কখনো নিজে মেসেজ পাঠায় না' ) ) ) );
		if ( S.live ) {
			out.push( btn( 'কথোপকথন থেকে সাজেশন', fromConversation, 'primary', 'sparkles' ) );
			out.push( btn( 'নির্বাচিত লেখা থেকে', fromSelection, 'secondary', 'text-select' ) );
		} else {
			out.push( btn( 'নির্বাচিত লেখা থেকে সাজেশন', fromSelection, 'primary', 'sparkles' ) );
		}
	}
	out.push( h( 'details', { open: ! onInbox || S.pasteOpen ? true : null, ontoggle: ( e ) => { S.pasteOpen = e.target.open; } },
		h( 'summary', { class: 'iaa-label' }, 'পেস্ট করে সাজেশন' ),
		h( 'div', { class: 'iaa-stack' }, pasteArea, btn( 'পেস্ট করা লেখা থেকে সাজেশন', () => fromPaste( pasteArea.value ), onInbox ? 'secondary' : 'primary', 'clipboard-paste' ),
			h( 'p', { class: 'iaa-help' }, 'পেস্ট করা লেখা থেকে তৈরি সাজেশন শুধু কপি করা যাবে।' ) ) ) );
	if ( ! S.live ) {
		out.push( alertBox( 'info', 'info', h( 'p', {}, 'নিরাপদ মোড: সাজেশন কপি করে Meta-র রিপ্লাই বক্সে পেস্ট করুন। স্বয়ংক্রিয়ভাবে বসানো বন্ধ আছে।' ) ) );
	}
	return out;
}

/* ---------------- S-04 Generating */
function viewGenerating() {
	bar.append( h( 'div', { class: 'iaa-actionbar' }, btn( 'বাতিল', () => {
		S.gen += 1;
		go( 'ready' );
	}, 'ghost', 'x' ) ) );
	return [ h( 'p', { class: 'iaa-muted', role: 'status' }, 'সাজেশন তৈরি হচ্ছে…' ), h( 'div', { class: 'iaa-skel' } ), h( 'div', { class: 'iaa-skel' } ), h( 'div', { class: 'iaa-skel' } ) ];
}

/* ---------------- S-05/06/07 Suggestions */
function guardBlock( sug ) {
	const items = [];
	if ( sug.guard.result === 'blocked' ) {
		items.push( alertBox( 'danger', 'shield-alert', h( 'p', {}, 'এই সাজেশনে অনুমোদিত তথ্যের বাইরে দাম/শতাংশ ছিল (' + sug.guard.blocked_values.join( ', ' ) + '), তাই নিরাপদ অপেক্ষার উত্তর দেওয়া হলো।' ) ) );
	}
	if ( sug.guard.warnings.length ) {
		items.push( alertBox( 'warning', 'triangle-alert', h( 'ul', {}, sug.guard.warnings.map( ( w ) => h( 'li', {}, warningText( w ) ) ) ) ) );
	}
	if ( sug.handoff.needed ) {
		items.push( alertBox( 'warning', 'user-round-check', h( 'p', {}, `ম্যানেজারকে জানান — ${ HANDOFF_LABELS[ sug.handoff.reason ] || sug.handoff.reason }` ) ) );
	}
	return items;
}

function factChips( sug ) {
	if ( ! sug.facts_used.length ) {
		return h( 'p', { class: 'iaa-muted' }, 'কোনো যাচাইকৃত তথ্য ব্যবহার হয়নি' );
	}
	return h( 'div', { class: 'iaa-chips', 'aria-label': 'ব্যবহৃত তথ্য' }, sug.facts_used.map( ( f ) => h( 'span', { class: 'iaa-chip', title: f.label }, icon( f.type === 'kb' ? 'book-open' : 'info', 14 ), h( 'span', {}, f.label ) ) ) );
}

function viewSuggestions() {
	const d = S.draft;
	const ids = d.suggestions.map( ( x ) => x.variant_id );
	const out = [];
	out.push( h( 'div', { class: `iaa-dealer${ d.audience === 'dealer' ? '' : ' iaa-dealer--unverified' }` }, h( 'span', { class: 'iaa-dealer__dot', 'aria-hidden': 'true' } ),
		h( 'span', {}, d.audience === 'dealer' && d.dealer ? `ডিলার: ${ d.dealer.code } · ${ d.dealer.name } · Tier ${ d.dealer.tier } (ডিলার-দাম ব্যবহার করা যাবে)` : 'যাচাই হয়নি — শুধু সাধারণ তথ্য, ডিলার-দাম দেওয়া হয়নি' ) ) );
	if ( S.stale ) {
		out.push( alertBox( 'warning', 'triangle-alert', h( 'p', {}, 'কথোপকথন বদলেছে — এই সাজেশন আগের কথোপকথনের। বসানো বন্ধ, কপি করা যাবে।' ) ) );
	}
	for ( const w of d.warnings ) {
		out.push( alertBox( 'info', 'info', h( 'p', {}, warningText( w ) ) ) );
	}
	const group = h( 'div', { class: 'iaa-cards', role: 'radiogroup', 'aria-label': 'রিপ্লাই সাজেশন' } );
	d.suggestions.forEach( ( sug ) => {
		const checked = S.selected === sug.variant_id;
		const card = h( 'div', {
			class: `iaa-card${ sug.guard.result === 'blocked' ? ' iaa-card--blocked' : '' }`,
			role: 'radio',
			'aria-checked': checked ? 'true' : 'false',
			tabindex: checked || ( ! S.selected && sug.variant_id === ids[ 0 ] ) ? '0' : '-1',
			'data-variant': sug.variant_id,
			onclick: () => select( sug.variant_id ),
			onkeydown: ( e ) => {
				if ( e.key === ' ' || e.key === 'Enter' ) {
					e.preventDefault();
					select( sug.variant_id );
				} else if ( [ 'ArrowDown', 'ArrowRight', 'ArrowUp', 'ArrowLeft' ].includes( e.key ) ) {
					e.preventDefault();
					select( nextVariant( ids, sug.variant_id, e.key === 'ArrowDown' || e.key === 'ArrowRight' ? 1 : -1 ) );
				}
			},
		},
		h( 'div', { class: 'iaa-card__head' }, h( 'span', { class: 'iaa-radio', 'aria-hidden': 'true' } ), h( 'span', { class: 'iaa-card__title' }, VARIANT_LABELS[ sug.variant_id ] || sug.variant_id ),
			h( 'span', { class: `iaa-badge iaa-badge--${ sug.guard.result }` }, icon( sug.guard.result === 'pass' ? 'circle-check' : sug.guard.result === 'blocked' ? 'shield-alert' : 'triangle-alert', 12 ), GUARD_LABELS[ sug.guard.result ] ),
			sug.handoff.needed ? h( 'span', { class: 'iaa-badge iaa-badge--handoff' }, 'ম্যানেজার' ) : null ),
		h( 'p', { class: 'iaa-card__text', lang: sug.language }, sug.reply ),
		...guardBlock( sug ),
		factChips( sug ) );
		group.append( card );
	} );
	out.push( group );

	if ( S.selected ) {
		const sug = selectedSuggestion( S );
		const ta = h( 'textarea', { class: 'iaa-textarea', id: 'iaa-edit', maxlength: '2000', lang: sug.language, 'aria-label': 'নির্বাচিত উত্তর (এডিট করা যাবে)' } );
		ta.value = S.edited;
		const adv = h( 'div', { id: 'iaa-adv' } );
		const counter = h( 'p', { class: 'iaa-help', id: 'iaa-count' } );
		const refresh = () => {
			const a = advisoryFor( S );
			adv.replaceChildren( ...( a.notes.length ? [ alertBox( a.block ? 'danger' : 'warning', 'triangle-alert', h( 'ul', {}, a.notes.map( ( n ) => h( 'li', {}, warningText( n ) ) ) ) ) ] : [] ) );
			const len = Array.from( S.edited ).length;
			counter.textContent = len > 1800 ? `${ len } / 2000` : '';
			drawBar();
		};
		ta.addEventListener( 'input', () => {
			S.edited = ta.value;
			refresh();
		} );
		ta.addEventListener( 'keydown', ( e ) => {
			if ( e.ctrlKey && e.key === 'Enter' ) {
				e.preventDefault();
				doInsert();
			} else if ( e.ctrlKey && e.shiftKey && ( e.key === 'C' || e.key === 'c' ) ) {
				e.preventDefault();
				doCopy();
			}
		} );
		out.push( h( 'div', { class: 'iaa-panel iaa-stack' }, h( 'label', { class: 'iaa-label', for: 'iaa-edit' }, 'নির্বাচিত উত্তর — দেখে প্রয়োজনে এডিট করুন' ), ta, counter, adv ) );
		setTimeout( refresh, 0 );
	} else {
		out.push( h( 'p', { class: 'iaa-help' }, 'একটি সাজেশন বেছে নিন (ক্লিক অথবা তীর-কী + Space)।' ) );
		drawBar();
	}
	out.push( h( 'p', { class: 'iaa-muted iaa-meta' }, aiNote( d.ai ) ? h( 'span', {}, aiNote( d.ai ) ) : null, h( 'span', {}, 'Request ID: ', h( 'span', { class: 'iaa-mono' }, d.requestId || '' ) ) ) );
	return out;
}

function drawBar() {
	if ( S.view !== 'suggestions' ) {
		return;
	}
	const check = insertCheck( S );
	const children = [];
	if ( S.live && S.draft.thread_bound ) {
		children.push( btn( 'বসান', doInsert, 'primary', 'corner-down-left', { disabled: ! check.ok || S.busy, title: check.ok ? 'Meta-র রিপ্লাই বক্সে বসান (Ctrl+Enter)' : insertReason( check.reason ) } ) );
	}
	children.push( btn( 'কপি', () => doCopy(), S.live && S.draft.thread_bound ? 'secondary' : 'primary', 'clipboard-copy', { disabled: ! S.selected || ! squash( S.edited ) || S.busy } ) );
	children.push( rewriteMenu() );
	children.push( moreMenu() );
	bar.replaceChildren( h( 'div', { class: 'iaa-actionbar' }, ...children ) );
}

function insertReason( reason ) {
	return { live_off: 'স্বয়ংক্রিয়ভাবে বসানো বন্ধ', unbound: 'এই সাজেশন কোনো খোলা কথোপকথনের সাথে যুক্ত নয়', stale: 'কথোপকথন বদলেছে', nothing_selected: 'আগে একটি সাজেশন বেছে নিন', advisory_block: 'নতুন দাম/শতাংশ যোগ হয়েছে — কপি করে যাচাই করুন' }[ reason ] || '';
}

function menu( label, iconName, items, disabled, name ) {
	const list = h( 'ul', { class: 'iaa-menu__list', role: 'menu', hidden: true } );
	const trigger = btn( label, () => {
		const open = list.hidden;
		list.hidden = ! open;
		trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		if ( open ) {
			const first = list.querySelector( 'button' );
			if ( first ) {
				first.focus();
			}
		}
	}, 'ghost', iconName, { disabled } );
	trigger.setAttribute( 'aria-haspopup', 'menu' );
	trigger.setAttribute( 'aria-label', name || label );
	trigger.setAttribute( 'title', name || label );
	trigger.setAttribute( 'aria-expanded', 'false' );
	items.forEach( ( [ text, fn ] ) => list.append( h( 'li', { role: 'none' }, h( 'button', { type: 'button', class: 'iaa-menu__item', role: 'menuitem', onclick: () => {
		list.hidden = true;
		fn();
	} }, text ) ) ) );
	list.addEventListener( 'keydown', ( e ) => {
		const btns = [ ...list.querySelectorAll( 'button' ) ];
		const i = btns.indexOf( document.activeElement );
		if ( e.key === 'Escape' ) {
			list.hidden = true;
			trigger.focus();
		} else if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
			e.preventDefault();
			btns[ ( i + ( e.key === 'ArrowDown' ? 1 : -1 ) + btns.length ) % btns.length ].focus();
		}
	} );
	return h( 'div', { class: 'iaa-menu' }, trigger, list );
}

function rewriteMenu() {
	const items = [
		[ 'ছোট করুন', () => doRewrite( 'shorter' ) ],
		[ 'আরও ভদ্র', () => doRewrite( 'polite' ) ],
		[ 'ফরমাল', () => doRewrite( 'formal' ) ],
		[ 'বাংলায়', () => doRewrite( 'bn' ) ],
		[ 'English', () => doRewrite( 'en' ) ],
	];
	if ( S.undo ) {
		items.push( [ 'আগের লেখায় ফিরুন', doUndo ] );
	}
	return menu( '', 'wand-sparkles', items, ! S.selected || S.busy, 'আবার লিখুন (Rewrite)' );
}

function moreMenu() {
	return menu( '', 'chevron-down', [
		[ 'আবার তৈরি করুন', () => S.lastInput && createDraft( S.lastInput.body, S.lastInput.conv ) ],
		[ 'বাদ দিন: ভুল তথ্য', () => doReject( 'wrong_fact' ) ],
		[ 'বাদ দিন: ভাষা ভালো না', () => doReject( 'tone' ) ],
		[ 'বাদ দিন: প্রাসঙ্গিক না', () => doReject( 'irrelevant' ) ],
		[ 'বাদ দিন: অন্য', () => doReject( 'other' ) ],
		[ 'নতুন মেসেজ', () => {
			S.draft = null;
			go( 'ready' );
		} ],
	], S.busy, 'আরও' );
}

/* ---------------- S-08 Error */
function viewError() {
	const e = S.error || { code: 'INTERNAL_ERROR', data: {} };
	const actions = [];
	if ( ERROR_ACTIONS.retry.includes( e.code ) && S.lastInput ) {
		actions.push( btn( 'আবার চেষ্টা', () => createDraft( S.lastInput.body, S.lastInput.conv ), 'primary', 'refresh-cw' ) );
	}
	if ( ERROR_ACTIONS.quick.includes( e.code ) ) {
		actions.push( btn( 'দ্রুত উত্তর', () => go( 'quick' ), 'secondary', 'book-open' ) );
	}
	actions.push( btn( 'ফিরে যান', () => go( 'ready' ), 'ghost' ) );
	return [
		alertBox( 'danger', 'circle-x', h( 'p', {}, errorText( e.code, e.data || {} ) ), e.data && e.data.request_id ? h( 'p', { class: 'iaa-mono' }, 'ID: ' + e.data.request_id ) : null ),
		h( 'div', { class: 'iaa-row' }, ...actions ),
	];
}

/* ---------------- S-13 Disabled */
function viewDisabled() {
	return [
		h( 'div', { class: 'iaa-empty' }, icon( 'power', 32 ), h( 'p', {}, 'সার্ভিস বন্ধ আছে (অ্যাডমিন বন্ধ করেছেন)।' ), btn( 'আবার দেখুন', loadStatus, 'secondary', 'refresh-cw' ) ),
	];
}

/* ---------------- S-11 Quick answers */
function viewQuick() {
	const input = h( 'input', { class: 'iaa-input', type: 'search', value: S.quick.q, placeholder: 'যেমন: সময়, ডেলিভারি, পেমেন্ট', 'aria-label': 'দ্রুত উত্তর খুঁজুন' } );
	const list = h( 'div', { class: 'iaa-stack', role: 'status' } );
	const draw = () => {
		if ( ! S.quick.results.length ) {
			list.replaceChildren( h( 'p', { class: 'iaa-muted' }, S.quick.q ? 'কিছু পাওয়া যায়নি।' : 'অনুমোদিত উত্তর খুঁজুন (AI ছাড়া, খরচ নেই)।' ) );
			return;
		}
		list.replaceChildren( ...S.quick.results.map( ( r ) => {
			const text = S.quick.lang === 'en' && r.answer_en ? r.answer_en : r.answer_bn;
			return h( 'div', { class: 'iaa-panel iaa-stack' }, h( 'strong', {}, r.title ), h( 'p', { class: 'iaa-card__text', lang: S.quick.lang }, text ),
				h( 'div', { class: 'iaa-row' }, btn( 'কপি', async () => {
					if ( await copyText( text ) ) {
						bg( 'kbUsed', { id: r.id } );
						toast( 'কপি হয়েছে। Meta-র রিপ্লাই বক্সে পেস্ট করুন।' );
					}
				}, 'secondary', 'clipboard-copy', { sm: true } ) ) );
		} ) );
	};
	const search = async () => {
		S.quick.q = input.value.trim();
		if ( S.quick.q.length < 2 ) {
			S.quick.results = [];
			draw();
			return;
		}
		const r = await bg( 'kbSearch', { q: S.quick.q } );
		S.quick.results = r && r.ok ? r.data : [];
		if ( r && ! r.ok ) {
			list.replaceChildren( alertBox( 'danger', 'circle-x', h( 'p', {}, errorText( r.error.code, r.error.data ) ) ) );
			return;
		}
		draw();
	};
	let t = null;
	input.addEventListener( 'input', () => {
		clearTimeout( t );
		t = setTimeout( search, 300 );
	} );
	draw();
	setTimeout( () => input.focus(), 0 );
	const langToggle = h( 'div', { class: 'iaa-row', role: 'group', 'aria-label': 'ভাষা' },
		btn( 'বাংলা', () => { S.quick.lang = 'bn'; draw(); }, S.quick.lang === 'bn' ? 'primary' : 'ghost', null, { sm: true } ),
		btn( 'English', () => { S.quick.lang = 'en'; draw(); }, S.quick.lang === 'en' ? 'primary' : 'ghost', null, { sm: true } ) );
	return [ h( 'h2', { class: 'iaa-h2' }, 'দ্রুত উত্তর' ), input, langToggle, list, btn( 'ফিরে যান', () => go( S.draft ? 'suggestions' : 'ready' ), 'ghost' ) ];
}

/* ---------------- S-12 Diagnostics */
function viewDiag() {
	const wrap = h( 'div', { class: 'iaa-stack' }, h( 'p', { class: 'iaa-muted' }, 'লোড হচ্ছে…' ) );
	( async () => {
		const d = await bg( 'diag' );
		let self = null;
		if ( S.live && isInboxUrl( S.tab.url ) ) {
			self = await content( 'selftest' );
		}
		const cmd = ( d.commands || [] ).find( ( c ) => c.name === 'draft' );
		const themeSel = h( 'select', { class: 'iaa-select', id: 'iaa-theme' }, ...[ [ '', 'সিস্টেম' ], [ 'light', 'Light' ], [ 'dark', 'Dark' ] ].map( ( [ v, l ] ) => h( 'option', { value: v, selected: ( document.documentElement.getAttribute( 'data-theme' ) || '' ) === v ? true : null }, l ) ) );
		themeSel.addEventListener( 'change', async () => {
			const v = themeSel.value;
			if ( v ) {
				document.documentElement.setAttribute( 'data-theme', v );
			} else {
				document.documentElement.removeAttribute( 'data-theme' );
			}
			await chrome.storage.local.set( { 'iaa.theme': v } );
		} );
		const report = {
			version: d.version,
			site: d.site,
			user: S.me ? S.me.user.name : null,
			server_version: S.me ? S.me.version : null,
			live_inbox: S.live,
			on_inbox: isInboxUrl( S.tab.url ),
			conversation_key_present: !! conv(),
			selftest: self,
			shortcut: cmd ? cmd.shortcut || '(বসানো নেই)' : null,
			recent: d.diag,
		};
		wrap.replaceChildren(
			h( 'h2', { class: 'iaa-h2' }, 'সেটিংস ও ডায়াগনস্টিক' ),
			h( 'p', {}, `Extension ${ d.version } · ${ d.site }` ),
			S.me ? h( 'p', {}, `ব্যবহারকারী: ${ S.me.user.name } · সার্ভার ${ S.me.version }` ) : null,
			h( 'p', {}, `স্বয়ংক্রিয় পড়া/বসানো: ${ S.live ? 'চালু (অ্যাডমিন)' : 'বন্ধ — select/পেস্ট ও কপি' }` ),
			h( 'p', {}, `শর্টকাট: ${ cmd && cmd.shortcut ? cmd.shortcut : 'বসানো নেই — chrome://extensions/shortcuts থেকে সেট করুন' }` ),
			self ? h( 'p', {}, `Self-test: কথোপকথন ${ self.key ? '✔' : '✖' } · মেসেজ ${ self.messages ? '✔' : '✖' } · রিপ্লাই বক্স ${ self.composer ? '✔' : '✖' }` ) : null,
			h( 'div', { class: 'iaa-field' }, h( 'label', { class: 'iaa-label', for: 'iaa-theme' }, 'থিম' ), themeSel ),
			h( 'h3', { class: 'iaa-label' }, 'সাম্প্রতিক অনুরোধ' ),
			h( 'ul', {}, ( d.diag || [] ).map( ( x ) => h( 'li', { class: 'iaa-mono' }, `${ x.at.slice( 11, 19 ) } ${ x.kind } ${ x.code } ${ x.request_id ? x.request_id.slice( 0, 8 ) : '' }` ) ) ),
			h( 'div', { class: 'iaa-row' },
				btn( 'ডায়াগনস্টিক কপি', async () => {
					if ( await copyText( JSON.stringify( report, null, 2 ) ) ) {
						toast( 'কপি হয়েছে (কোনো মেসেজ বা token নেই)।' );
					}
				}, 'secondary', 'clipboard-copy', { sm: true } ),
				S.me ? btn( 'সংযোগ বিচ্ছিন্ন', async () => {
					await bg( 'disconnect' );
					S.me = null;
					go( 'connect' );
				}, 'danger', 'power', { sm: true } ) : null ),
			btn( 'ফিরে যান', () => go( S.me ? ( S.draft ? 'suggestions' : 'ready' ) : 'connect' ), 'ghost' )
		);
	} )();
	return [ wrap ];
}

boot();
