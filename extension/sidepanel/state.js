/**
 * Pure panel logic (AppFlow §7, §11): labels, guards for Insert, stale detection, outcomes.
 * No DOM and no chrome.* here, so it is unit-tested in Node.
 */

import { parseKey, sameConversation } from '../lib/ids.js';
import { editAdvisory, squash } from '../lib/text.js';

export const VARIANT_LABELS = {
	professional: 'পেশাদার (Professional)',
	friendly: 'আন্তরিক (Friendly)',
	short: 'সংক্ষিপ্ত (Short)',
	sales_focused: 'বিক্রয় সহায়ক (Sales-focused)',
};

export const HANDOFF_LABELS = {
	order_confirmation: 'অর্ডার নিশ্চিত করা',
	credit_outstanding: 'বকেয়া / ক্রেডিট',
	discount_negotiation: 'ছাড় / দরদাম',
	complaint: 'অভিযোগ',
	damage_return: 'ভাঙা / ফেরত',
	unknown_dealer_price: 'ডিলার যাচাই ছাড়া দাম',
	missing_facts: 'তথ্য নেই',
	other: 'অন্য কারণ',
};

export const GUARD_LABELS = {
	pass: 'যাচাই হয়েছে',
	warned: 'সতর্কতা আছে',
	blocked: 'অননুমোদিত দাম আটকানো হয়েছে',
};

export function warningText( w ) {
	switch ( w.code ) {
		case 'UNVERIFIED_NUMBER':
			return `অনুমোদিত তথ্যে নেই এমন সংখ্যা: ${ w.value }` + ( w.source === 'customer' ? ' (কাস্টমারের লেখা থেকে — দাম হিসেবে নিশ্চিত করবেন না)' : '' );
		case 'PRICE_UNIT_UNCLEAR':
			return `দামের একক (কার্টন/পিস ইত্যাদি) স্পষ্ট নয়: ${ w.value }`;
		case 'BANNED_PHRASE':
			return `সতর্ক শব্দ: "${ w.value }"`;
		case 'COMMERCIAL_CLAIM':
			return `ছাড়/কমিশনের কথা আছে: "${ w.value }" — যাচাই করুন`;
		case 'FACTS_CHANGED':
			return 'দাম বা তথ্য বদলেছে — নতুন করে সাজেশন নিন';
		case 'INPUT_TRIMMED':
			return 'লম্বা কথোপকথন: পুরোনো কিছু মেসেজ বাদ দেওয়া হয়েছে';
		case 'NEW_PRICE':
			return `আপনি নতুন দাম লিখেছেন: ${ w.value } — Insert বন্ধ, যাচাই করে Copy করুন`;
		case 'NEW_PERCENT':
			return `আপনি নতুন শতাংশ লিখেছেন: ${ w.value } — Insert বন্ধ, যাচাই করে Copy করুন`;
		case 'NEW_NUMBER':
			return `নতুন সংখ্যা যোগ হয়েছে: ${ w.value } — ঠিক আছে কিনা দেখুন`;
		case 'NEW_COMMERCIAL':
			return `ছাড়/কমিশনের কথা যোগ হয়েছে: "${ w.value }" — যাচাই করুন`;
		default:
			return w.code;
	}
}

export function errorText( code, data = {} ) {
	switch ( code ) {
		case 'NETWORK':
		case 'CLIENT_TIMEOUT':
			return 'সংযোগ সমস্যা। আবার চেষ্টা করুন।';
		case 'REDIRECT':
			return 'সাইটের ঠিকানাটি অন্য ঠিকানায় চলে যায়। উন্নত (Advanced) অংশে সঠিক ঠিকানা দিন, যেমন https://www.nabiad.com';
		case 'RATE_LIMITED':
			return `একটু অপেক্ষা করুন (${ data.retry_after || 10 } সেকেন্ড)।`;
		case 'BUSY':
		case 'REQUEST_IN_PROGRESS':
			return 'সার্ভার ব্যস্ত, কয়েক সেকেন্ড পর চেষ্টা করুন।';
		case 'DAILY_LIMIT':
			return 'আজকের সীমা শেষ।';
		case 'BUDGET_EXCEEDED':
			return 'এই মাসের AI বাজেট শেষ।';
		case 'AI_DISABLED':
		case 'PROVIDER_NOT_CONFIGURED':
			return 'AI এখন বন্ধ আছে। দ্রুত উত্তর ব্যবহার করুন।';
		case 'PROVIDER_ERROR':
		case 'PROVIDER_TIMEOUT':
		case 'PROVIDER_BAD_OUTPUT':
			return 'AI সার্ভিস এখন সাড়া দিচ্ছে না (ফ্রি সীমা শেষ হতে পারে)। একটু পরে চেষ্টা করুন বা দ্রুত উত্তর ব্যবহার করুন।';
		case 'FORBIDDEN':
			return 'আপনার অনুমতি নেই।';
		case 'SERVICE_DISABLED':
			return 'সার্ভিস বন্ধ আছে (অ্যাডমিন বন্ধ করেছেন)।';
		case 'CLIENT_TOO_OLD':
			return `Extension আপডেট করুন (ন্যূনতম ${ data.min_version || '' })।`;
		case 'VALIDATION_FAILED':
			return 'লেখা পড়া যায়নি বা ফরম্যাট ঠিক নেই।';
		case 'REQUEST_ALREADY_PROCESSED':
			return 'এই অনুরোধ আগেই শেষ হয়েছে। আবার তৈরি করুন।';
		case 'LINK_CONFLICT':
			return 'অন্য কেউ ডিলার লিংক বদলেছেন। নতুন লিংক দেখানো হলো।';
		case 'NOT_FOUND':
			return 'পাওয়া যায়নি।';
		case 'AUTH_INVALID':
		case 'AUTH_REVOKED':
		case 'AUTH_EXPIRED':
		case 'AUTH_MISSING':
			return 'Token বাতিল বা মেয়াদ শেষ। আবার সংযোগ করুন।';
		default:
			return `ত্রুটি হয়েছে (${ code })।`;
	}
}

export const ERROR_ACTIONS = {
	retry: [ 'NETWORK', 'CLIENT_TIMEOUT', 'RATE_LIMITED', 'BUSY', 'REQUEST_IN_PROGRESS', 'PROVIDER_ERROR', 'PROVIDER_TIMEOUT', 'PROVIDER_BAD_OUTPUT', 'INTERNAL_ERROR', 'REQUEST_ALREADY_PROCESSED' ],
	quick: [ 'NETWORK', 'CLIENT_TIMEOUT', 'DAILY_LIMIT', 'BUDGET_EXCEEDED', 'AI_DISABLED', 'PROVIDER_NOT_CONFIGURED', 'PROVIDER_ERROR', 'PROVIDER_TIMEOUT', 'PROVIDER_BAD_OUTPUT' ],
	reconnect: [ 'AUTH_INVALID', 'AUTH_REVOKED', 'AUTH_EXPIRED', 'AUTH_MISSING' ],
};

/** Is the current tab still the conversation the draft was made for? */
export function isStale( draftConv, tabUrl ) {
	if ( ! draftConv ) {
		return false; // unbound drafts are never insertable anyway
	}
	return ! sameConversation( draftConv, parseKey( tabUrl ) );
}

/**
 * Whether Insert may be offered, with a reason when not.
 * @returns {{ ok: boolean, reason: string }}
 */
export function insertCheck( s ) {
	if ( ! s.live ) {
		return { ok: false, reason: 'live_off' };
	}
	if ( ! s.draft || ! s.draft.conv || ! s.draft.thread_bound ) {
		return { ok: false, reason: 'unbound' };
	}
	if ( s.stale ) {
		return { ok: false, reason: 'stale' };
	}
	if ( ! s.selected || ! squash( s.edited ) ) {
		return { ok: false, reason: 'nothing_selected' };
	}
	if ( advisoryFor( s ).block ) {
		return { ok: false, reason: 'advisory_block' };
	}
	return { ok: true, reason: '' };
}

export function selectedSuggestion( s ) {
	if ( ! s.draft || ! s.selected ) {
		return null;
	}
	return s.draft.suggestions.find( ( x ) => x.variant_id === s.selected ) || null;
}

export function advisoryFor( s ) {
	const sug = selectedSuggestion( s );
	if ( ! sug ) {
		return { block: false, notes: [] };
	}
	return editAdvisory( sug.reply, s.edited );
}

export function outcomeFor( action, s ) {
	const sug = selectedSuggestion( s );
	const edited = sug ? squash( sug.reply ) !== squash( s.edited ) : false;
	if ( action === 'insert' ) {
		return edited ? 'edited_inserted' : 'inserted';
	}
	return edited ? 'edited_copied' : 'copied';
}

/** Messages to send for selection/paste text: one customer message. */
export function messagesFromText( text ) {
	const t = String( text || '' ).trim().slice( 0, 2000 );
	return t ? [ { side: 'customer', text: t, at: null } ] : [];
}

/** Next variant id for arrow-key navigation inside the radiogroup. */
export function nextVariant( ids, current, delta ) {
	if ( ! ids.length ) {
		return null;
	}
	const i = ids.indexOf( current );
	const n = i < 0 ? 0 : ( i + delta + ids.length ) % ids.length;
	return ids[ n ];
}

/** Small note under suggestions: which AI answered (no model internals). */
export function aiNote( ai ) {
	if ( ! ai || ! ai.label ) {
		return '';
	}
	const name = String( ai.label ).replace( /\s*\(.*\)$/, '' );
	return `AI: ${ name }${ ai.free ? ' (ফ্রি)' : '' }${ ai.fallback ? ' · ব্যাকআপ AI ব্যবহৃত' : '' }`;
}

/**
 * Staff note for the next request (v0.2.1, FR-29): what the customer means, e.g. the product in a
 * photo. Only kept for the conversation it was typed for; trimmed, single line, max 200 chars.
 * Returns the text to send, or null.
 */
export function noteFor( note, convKey ) {
	if ( ! note || typeof note.text !== 'string' ) {
		return null;
	}
	if ( ( note.conv || null ) !== ( convKey || null ) ) {
		return null;
	}
	const t = note.text.replace( /[\u0000-\u001F\u007F]+/g, ' ' ).replace( /\s+/g, ' ' ).trim().slice( 0, 200 );
	return t || null;
}
