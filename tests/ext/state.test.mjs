import test from 'node:test';
import assert from 'node:assert/strict';
import { insertCheck, isStale, outcomeFor, advisoryFor, nextVariant, messagesFromText, errorText, warningText, noteFor } from '../../extension/sidepanel/state.js';
import { parseKey } from '../../extension/lib/ids.js';

const URL1 = 'https://business.facebook.com/latest/inbox/all?asset_id=1&business_id=9&selected_item_id=t_1';
const URL2 = 'https://business.facebook.com/latest/inbox/all?asset_id=1&business_id=9&selected_item_id=t_2';
const sug = ( id, reply ) => ( { variant_id: id, reply, language: 'bn', guard: { result: 'pass', warnings: [], blocked_values: [] }, handoff: { needed: false, reason: null }, facts_used: [] } );
const base = () => ( {
	live: true,
	stale: false,
	draft: { id: 'd', conv: parseKey( URL1 ), thread_bound: true, suggestions: [ sug( 'professional', 'দাম ৳১,২৫০ প্রতি কার্টন।' ), sug( 'friendly', 'জি!' ), sug( 'short', 'হ্যাঁ' ) ] },
	selected: 'professional',
	edited: 'দাম ৳১,২৫০ প্রতি কার্টন।',
} );

test( 'insert allowed only when live, bound, current and unchanged prices', () => {
	assert.equal( insertCheck( base() ).ok, true );
	assert.equal( insertCheck( { ...base(), live: false } ).reason, 'live_off' );
	assert.equal( insertCheck( { ...base(), stale: true } ).reason, 'stale' );
	assert.equal( insertCheck( { ...base(), draft: { ...base().draft, conv: null } } ).reason, 'unbound' );
	assert.equal( insertCheck( { ...base(), selected: null } ).reason, 'nothing_selected' );
	assert.equal( insertCheck( { ...base(), edited: '   ' } ).reason, 'nothing_selected' );
	assert.equal( insertCheck( { ...base(), edited: 'দাম ৳১,১০০ প্রতি কার্টন।' } ).reason, 'advisory_block' );
} );

test( 'stale detection on conversation change', () => {
	assert.equal( isStale( parseKey( URL1 ), URL1 ), false );
	assert.equal( isStale( parseKey( URL1 ), URL2 ), true );
	assert.equal( isStale( parseKey( URL1 ), 'https://www.google.com' ), true );
	assert.equal( isStale( null, URL2 ), false );
} );

test( 'outcomes reflect edits', () => {
	assert.equal( outcomeFor( 'insert', base() ), 'inserted' );
	assert.equal( outcomeFor( 'copy', base() ), 'copied' );
	assert.equal( outcomeFor( 'insert', { ...base(), edited: 'অন্য লেখা' } ), 'edited_inserted' );
	assert.equal( outcomeFor( 'copy', { ...base(), edited: 'অন্য লেখা' } ), 'edited_copied' );
	assert.equal( advisoryFor( { ...base(), selected: null } ).block, false );
} );

test( 'radiogroup navigation wraps', () => {
	const ids = [ 'professional', 'friendly', 'short' ];
	assert.equal( nextVariant( ids, 'short', 1 ), 'professional' );
	assert.equal( nextVariant( ids, 'professional', -1 ), 'short' );
	assert.equal( nextVariant( ids, null, 1 ), 'professional' );
} );

test( 'text helpers and copy', () => {
	assert.deepEqual( messagesFromText( '  hi  ' ), [ { side: 'customer', text: 'hi', at: null } ] );
	assert.deepEqual( messagesFromText( '' ), [] );
	assert.match( errorText( 'RATE_LIMITED', { retry_after: 12 } ), /12/ );
	assert.match( errorText( 'SOMETHING_NEW' ), /SOMETHING_NEW/ );
	assert.match( warningText( { code: 'UNVERIFIED_NUMBER', value: '50', source: 'customer' } ), /কাস্টমারের/ );
} );

test( 'staff note: only for the conversation it was typed for, single line, max 200 (FR-29)', () => {
	const k1 = parseKey( URL1 ).key;
	const k2 = parseKey( URL2 ).key;
	assert.equal( noteFor( { text: '  Jaguar Classic Red\n100ml ', conv: k1 }, k1 ), 'Jaguar Classic Red 100ml' );
	assert.equal( noteFor( { text: 'Jaguar', conv: k1 }, k2 ), null, 'other conversation → no note' );
	assert.equal( noteFor( { text: '   ', conv: k1 }, k1 ), null, 'blank → null' );
	assert.equal( noteFor( { text: 'x'.repeat( 300 ), conv: null }, null ).length, 200 );
	assert.equal( noteFor( null, k1 ), null );
} );
