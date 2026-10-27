import test from 'node:test';
import assert from 'node:assert/strict';
import { parseKey, isInboxUrl, sameConversation, uuid4 } from '../../extension/lib/ids.js';
import { numbers, editAdvisory, editDistance, normNumber, asciiDigits } from '../../extension/lib/text.js';

const URL1 = 'https://business.facebook.com/latest/inbox/all?asset_id=1063479641694&business_id=6443795&selected_item_id=t_99';

test( 'ids: inbox URL and conversation key', () => {
	assert.equal( isInboxUrl( URL1 ), true );
	assert.equal( isInboxUrl( 'https://www.facebook.com/messages' ), false );
	assert.equal( isInboxUrl( 'http://business.facebook.com/latest/inbox' ), false );
	assert.deepEqual( parseKey( URL1 ), { key: '1063479641694:t_99', asset: '1063479641694', item: 't_99', business: '6443795' } );
	assert.equal( parseKey( 'https://business.facebook.com/latest/inbox/all?asset_id=1' ), null );
	assert.equal( parseKey( 'https://business.facebook.com/latest/inbox/all?asset_id=1&selected_item_id=a b' ), null );
	assert.equal( sameConversation( parseKey( URL1 ), parseKey( URL1.replace( 't_99', 't_100' ) ) ), false );
	assert.equal( sameConversation( parseKey( URL1 ), parseKey( URL1.replace( '6443795', '1' ) ) ), false, 'different business account' );
	assert.match( uuid4(), /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/ );
} );

test( 'text: numbers and classification mirror the server guard', () => {
	assert.equal( asciiDigits( '১২৫০' ), '1250' );
	assert.equal( normNumber( '১,২৫০.০০' ), '1250' );
	const n = numbers( 'দাম ৳১,২৫০ প্রতি কার্টন, ১০% ছাড়, ডেলিভারি ৩ দিনে' );
	assert.deepEqual( n.map( ( x ) => [ x.value, x.price, x.percent ] ), [ [ '1250', true, false ], [ '10', false, true ], [ '3', false, false ] ] );
} );

test( 'text: edit advisory blocks new prices and percentages, notes other numbers', () => {
	const orig = 'জাম্বো কার্টনের দাম ৳১,২৫০।';
	assert.deepEqual( editAdvisory( orig, orig ), { block: false, notes: [] } );
	assert.equal( editAdvisory( orig, 'জাম্বো কার্টনের দাম ৳১,২০০।' ).block, true );
	assert.equal( editAdvisory( orig, orig + ' ৫% ছাড়।' ).block, true );
	const a = editAdvisory( orig, orig + ' ৭ দিনে ডেলিভারি।' );
	assert.equal( a.block, false );
	assert.equal( a.notes[ 0 ].code, 'NEW_NUMBER' );
	assert.equal( editAdvisory( orig, orig + ' ২টি' ).notes.length, 0, 'small counts ignored' );
	assert.equal( editAdvisory( 'ঠিক আছে', 'ঠিক আছে, discount দেব' ).notes[ 0 ].code, 'NEW_COMMERCIAL' );
} );

test( 'text: edit distance', () => {
	assert.equal( editDistance( 'abc', 'abc' ), 0 );
	assert.equal( editDistance( 'kitten', 'sitting' ), 3 );
	assert.equal( editDistance( 'আমি', 'আমরা' ), 2 );
	assert.equal( editDistance( 'a'.repeat( 10 ), 'b'.repeat( 10 ), 5 ), 5 );
} );
