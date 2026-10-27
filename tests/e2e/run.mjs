/**
 * End-to-end test: real side panel + background + content scripts in headless Chromium against a
 * local WordPress with the plugin (stub provider) and a sanitised mock inbox.
 *
 * The chrome.* extension API is replaced by tests/e2e/chrome-mock.js (headless shell cannot load
 * extensions), so this proves the panel ↔ background ↔ REST ↔ content-script wiring, not Chrome's
 * own extension plumbing. Load-unpacked in real Chrome remains a manual check (docs/06_Plan.md).
 *
 * Environment:
 *   IAA_E2E_ORIGIN  origin of the WP test server (default http://127.0.0.1:8899)
 *   WP_LOAD         wp-load.php of that site (for seeding)
 *   IAA_E2E_DOCROOT docroot of that site; ext/ and e2e/ symlinks are created there
 *   IAA_E2E_EXT_DIR extension folder to test (default: extension/; use an unzipped release to test the ZIP)
 */

import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import { existsSync, symlinkSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import puppeteer from 'puppeteer-core';
import chromium from '@sparticuz/chromium';

const here = dirname( fileURLToPath( import.meta.url ) );
const repo = resolve( here, '..', '..' );
const ORIGIN = process.env.IAA_E2E_ORIGIN || 'http://127.0.0.1:8899';
const DOCROOT = process.env.IAA_E2E_DOCROOT;
const WP_LOAD = process.env.WP_LOAD;
if ( ! DOCROOT || ! WP_LOAD ) {
	console.error( 'Set IAA_E2E_DOCROOT and WP_LOAD' );
	process.exit( 2 );
}
for ( const [ name, target ] of [ [ 'ext', process.env.IAA_E2E_EXT_DIR || join( repo, 'extension' ) ], [ 'e2e', here ] ] ) {
	const link = join( DOCROOT, name );
	if ( ! existsSync( link ) ) {
		symlinkSync( target, link );
	}
}

const require = createRequire( import.meta.url );
const axeSource = readFileSync( require.resolve( 'axe-core/axe.min.js' ), 'utf8' );
const a11yIssues = [];
const a11yViews = [];
/** axe-core (WCAG 2 A/AA) on the panel frame; serious/critical violations are collected. */
async function a11y( frame, view ) {
	await frame.evaluate( () => Promise.all( document.getAnimations().map( ( a ) => a.finished.catch( () => null ) ) ) );
	await frame.evaluate( axeSource );
	const v = await frame.evaluate( async () => ( await window.axe.run( document, { runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ] } } ) ).violations
		.filter( ( x ) => x.impact === 'serious' || x.impact === 'critical' )
		.map( ( x ) => `${ x.id } (${ x.nodes.length }): ${ x.nodes.slice( 0, 2 ).map( ( n ) => n.target.join( ' ' ) ).join( ', ' ) }` ) );
	a11yViews.push( view );
	v.forEach( ( x ) => a11yIssues.push( `${ view }: ${ x }` ) );
}

const php = ( file, ...args ) => JSON.parse( execFileSync( 'php', [ join( here, file ), ...args ], { env: { ...process.env, WP_LOAD }, encoding: 'utf8' } ).trim().split( '\n' ).pop() );
const mock = readFileSync( join( here, 'chrome-mock.js' ), 'utf8' );
const BASE = `${ ORIGIN }/index.php?rest_route=/rar-iaa/v1`;
const CONV_A = 'asset_id=111&selected_item_id=222&business_id=333&mailbox_id=&thread_type=FB_MESSAGE';
const CONV_B = 'asset_id=111&selected_item_id=999&business_id=333&mailbox_id=&thread_type=FB_MESSAGE';

let passed = 0;
const errors = [];
const results = [];
async function step( name, fn ) {
	try {
		await fn();
		passed++;
		results.push( `ok   ${ name }` );
	} catch ( e ) {
		results.push( `FAIL ${ name }: ${ e.message }${ errors.length ? '\n     page errors: ' + errors.join( ' | ' ) : '' }` );
		throw e;
	}
}

const browser = await puppeteer.launch( {
	executablePath: await chromium.executablePath(),
	args: [ ...chromium.args, '--no-sandbox' ],
	headless: 'shell',
	defaultViewport: { width: 1400, height: 900 },
} );

async function open( inboxQuery ) {
	const page = await browser.newPage();
	page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
	page.on( 'console', ( m ) => {
		if ( m.type() === 'error' ) {
			errors.push( 'console: ' + m.text() );
		}
	} );
	await page.evaluateOnNewDocument( mock );
	await page.goto( `${ ORIGIN }/e2e/harness.html?inbox=${ encodeURIComponent( inboxQuery ) }`, { waitUntil: 'networkidle0' } );
	const frame = ( n ) => page.frames().find( ( f ) => f.name() === n );
	return { page, panel: frame( 'panel' ), inbox: frame( 'content' ) };
}

async function clickText( frame, text ) {
	await frame.waitForFunction( ( t ) => [ ...document.querySelectorAll( 'button' ) ].some( ( b ) => b.textContent.trim().includes( t ) && ! b.disabled ), { timeout: 15000 }, text );
	await frame.evaluate( ( t ) => [ ...document.querySelectorAll( 'button' ) ].find( ( b ) => b.textContent.trim().includes( t ) && ! b.disabled ).click(), text );
}

const hasText = ( frame, text ) => frame.evaluate( ( t ) => document.body.textContent.includes( t ), text );
const waitText = ( frame, text, timeout = 20000 ) => frame.waitForFunction( ( t ) => document.body.textContent.includes( t ), { timeout }, text );
const buttons = ( frame ) => frame.evaluate( () => [ ...document.querySelectorAll( '#iaa-actionbar button' ) ].map( ( b ) => ( { t: b.textContent.trim(), d: b.disabled } ) ) );

async function connect( panel, token ) {
	await panel.waitForSelector( '#iaa-token' );
	await panel.type( '#iaa-token', token );
	await panel.evaluate( ( o ) => {
		document.getElementById( 'iaa-site' ).value = o.origin;
		document.getElementById( 'iaa-base' ).value = o.base;
	}, { origin: ORIGIN, base: BASE } );
	await clickText( panel, 'সংযোগ' );
}

try {
	/* ------------------------------------------------------------ 1. Safe mode (default) */
	let seed = php( 'seed.php' );
	let { page, panel, inbox } = await open( CONV_A );

	await step( 'wrong token format is rejected locally', async () => {
		await panel.waitForSelector( '#iaa-token' );
		await panel.type( '#iaa-token', 'not-a-token' );
		await clickText( panel, 'সংযোগ' );
		await waitText( panel, 'Token ঠিক নেই' );
		await a11y( panel, 'S-01 connect (error)' );
		await panel.evaluate( () => {
			document.getElementById( 'iaa-token' ).value = '';
		} );
	} );

	await step( 'connect with a real token → ready view in safe mode', async () => {
		await connect( panel, seed.token );
		await waitText( panel, 'নিরাপদ মোড' );
		const bus = await page.evaluate( () => ( { token: window.__bus.storage[ 'iaa.token' ], level: window.__bus.accessLevel } ) );
		assert.equal( bus.token, seed.token );
		assert.equal( bus.level, 'TRUSTED_CONTEXTS' );
		assert.equal( await hasText( panel, 'কথোপকথন থেকে সাজেশন' ), false, 'no capture button when live is off' );
		await a11y( panel, 'S-03 ready (safe mode)' );
	} );

	await step( 'selection → three suggestions, unverified audience, no Insert button', async () => {
		await inbox.evaluate( () => {
			const r = document.createRange();
			r.selectNodeContents( document.getElementById( 'last-customer' ) );
			getSelection().removeAllRanges();
			getSelection().addRange( r );
		} );
		await clickText( panel, 'নির্বাচিত লেখা থেকে সাজেশন' );
		await panel.waitForSelector( '[data-variant="short"]', { timeout: 30000 } );
		const ids = await panel.$$eval( '[role=radio]', ( els ) => els.map( ( e ) => e.dataset.variant ) );
		assert.deepEqual( ids, [ 'professional', 'friendly', 'short' ] );
		assert.ok( await hasText( panel, 'যাচাই হয়নি' ), 'unverified chip' );
		assert.ok( await hasText( panel, '৯টা' ), 'approved hours fact used' );
		assert.ok( await hasText( panel, 'AI: Anthropic' ), 'panel shows which AI answered' );
		if ( process.env.IAA_E2E_SHOTS ) {
			await page.screenshot( { path: join( process.env.IAA_E2E_SHOTS, 'safe-suggestions.png' ) } );
		}
		await panel.$eval( '[data-variant="friendly"]', ( e ) => e.click() );
		await panel.waitForSelector( '#iaa-edit' );
		const bar = await buttons( panel );
		assert.ok( ! bar.some( ( b ) => b.t.includes( 'বসান' ) ), 'Insert hidden in safe mode' );
		await a11y( panel, 'S-05 suggestions + edit' );
	} );

	await step( 'copy puts the edited text on the clipboard and records the outcome', async () => {
		await panel.evaluate( () => {
			const ta = document.getElementById( 'iaa-edit' );
			ta.value = ta.value + ' ধন্যবাদ।';
			ta.dispatchEvent( new Event( 'input' ) );
		} );
		await clickText( panel, 'কপি' );
		await waitText( panel, 'কপি হয়েছে' );
		const clip = await page.evaluate( () => window.__bus.clipboard );
		assert.ok( clip.endsWith( 'ধন্যবাদ।' ), 'edited text copied' );
		const sent = await inbox.evaluate( () => ( { sent: window.__sent, enter: window.__enter, composer: document.getElementById( 'composer' ).textContent } ) );
		assert.deepEqual( sent, { sent: false, enter: 0, composer: '' }, 'nothing typed or sent in Meta' );
	} );

	await step( 'a price-like edit shows the advisory and blocks Insert (copy still allowed)', async () => {
		await panel.evaluate( () => {
			const ta = document.getElementById( 'iaa-edit' );
			ta.value = 'দাম ৳৯৯৯ প্রতি কার্টন।';
			ta.dispatchEvent( new Event( 'input' ) );
		} );
		await panel.waitForFunction( () => document.getElementById( 'iaa-adv' ).textContent.length > 0 );
		const bar = await buttons( panel );
		assert.ok( bar.find( ( b ) => b.t.includes( 'কপি' ) && ! b.d ), 'copy enabled' );
	} );

	await step( 'safe mode: one copied outcome, no customer text stored', async () => {
		const db = php( 'check-db.php' );
		assert.deepEqual( db.hits, [] );
		assert.equal( db.drafts, 1 );
		assert.equal( db.outcomes.length, 1 );
		assert.equal( db.outcomes[ 0 ], 'edited_copied' );
	} );
	await page.close();

	/* ------------------------------------------------------------ 2. Live mode (admin flag on) */
	seed = php( 'seed.php', 'live' );
	( { page, panel, inbox } = await open( CONV_A ) );

	await step( 'live mode: capture conversation → bound suggestions with Insert', async () => {
		await connect( panel, seed.token );
		await clickText( panel, 'কথোপকথন থেকে সাজেশন' );
		await panel.waitForSelector( '[data-variant="short"]', { timeout: 30000 } );
		await panel.$eval( '[data-variant="professional"]', ( e ) => e.click() );
		await panel.waitForSelector( '#iaa-edit' );
		const bar = await buttons( panel );
		assert.ok( bar.find( ( b ) => b.t.includes( 'বসান' ) && ! b.d ), 'Insert enabled' );
		await new Promise( ( r ) => setTimeout( r, 50 ) );
		assert.equal( await panel.evaluate( () => /\b(null|undefined|NaN)\b/.test( document.getElementById( 'iaa-app' ).textContent ) ), false, 'no null/undefined text rendered' );
		if ( process.env.IAA_E2E_SHOTS ) {
			await page.screenshot( { path: join( process.env.IAA_E2E_SHOTS, 'live-suggestions.png' ) } );
		}
	} );

	await step( 'insert fills the reply box but never sends', async () => {
		const text = await panel.evaluate( () => document.getElementById( 'iaa-edit' ).value );
		await clickText( panel, 'বসান' );
		await waitText( panel, 'রিপ্লাই বক্সে বসানো হয়েছে' );
		const st = await inbox.evaluate( () => ( { sent: window.__sent, enter: window.__enter, composer: document.getElementById( 'composer' ).textContent } ) );
		assert.equal( st.composer.trim(), text.trim() );
		assert.equal( st.sent, false, 'Send not clicked' );
		assert.equal( st.enter, 0, 'no Enter dispatched' );
	} );

	await step( 'composer not empty → refused, text left untouched', async () => {
		await clickText( panel, 'বসান' );
		await waitText( panel, 'আগে থেকেই লেখা আছে' );
		const st = await inbox.evaluate( () => ( { sent: window.__sent, n: document.getElementById( 'composer' ).textContent.length } ) );
		assert.equal( st.sent, false );
		assert.ok( st.n > 0 );
	} );

	await step( 'switching conversation marks the suggestion stale and disables Insert', async () => {
		await inbox.evaluate( () => {
			document.getElementById( 'composer' ).textContent = '';
		} );
		await page.evaluate( ( q ) => window.__bus.changeUrl( 'https://business.facebook.com/latest/inbox/all?' + q ), CONV_B );
		await waitText( panel, 'কথোপকথন বদলেছে' );
		const bar = await buttons( panel );
		const ins = bar.find( ( b ) => b.t.includes( 'বসান' ) );
		assert.ok( ! ins || ins.d, 'Insert disabled when stale' );
		assert.equal( await inbox.evaluate( () => document.getElementById( 'composer' ).textContent ), '' );
	} );

	await step( 'reject records the outcome and returns to ready', async () => {
		await page.evaluate( ( q ) => window.__bus.changeUrl( 'https://business.facebook.com/latest/inbox/all?' + q ), CONV_A );
		await panel.$eval( '#iaa-actionbar button[aria-label="আরও"]', ( e ) => e.click() );
		const items = await panel.$$eval( '.iaa-menu__item', ( els ) => els.map( ( e ) => e.textContent ) );
		const reject = items.find( ( t ) => t.includes( 'বাদ দিন: ভুল তথ্য' ) );
		assert.ok( reject, 'reject item present: ' + items.join( ' | ' ) );
		await panel.evaluate( ( t ) => [ ...document.querySelectorAll( '.iaa-menu__item' ) ].find( ( e ) => e.textContent === t ).click(), reject );
		await waitText( panel, 'বাদ দেওয়া হয়েছে' );
	} );

	await step( 'unlinked price question → no dealer price (holding/neutral reply)', async () => {
		await inbox.evaluate( () => {
			document.getElementById( 'last-customer' ).textContent = 'Jumbo carton er dam koto?';
		} );
		await clickText( panel, 'কথোপকথন থেকে সাজেশন' );
		await panel.waitForSelector( '[data-variant="short"]', { timeout: 30000 } );
		const text = await panel.$eval( '[data-variant="professional"] .iaa-card__text', ( e ) => e.textContent );
		assert.ok( ! /1,250|১,২৫০|1250/.test( text ), 'no dealer price when unlinked: ' + text );
		assert.ok( await hasText( panel, 'যাচাই হয়নি' ) );
		await panel.$eval( '#iaa-actionbar button[aria-label="আরও"]', ( e ) => e.click() );
		await panel.evaluate( () => [ ...document.querySelectorAll( '.iaa-menu__item' ) ].find( ( e ) => e.textContent === 'নতুন মেসেজ' ).click() );
	} );

	await step( 'unknown product: Bangla reply, no manager badge; staff note names the product (v0.2.1)', async () => {
		await inbox.evaluate( () => {
			document.getElementById( 'last-customer' ).textContent = 'Price koto?';
		} );
		await clickText( panel, 'কথোপকথন থেকে সাজেশন' );
		await panel.waitForSelector( '[data-variant="short"]', { timeout: 30000 } );
		assert.equal( await panel.$eval( '[data-variant="professional"] .iaa-card__text', ( e ) => e.getAttribute( 'lang' ) ), 'bn', '"Price koto?" answered in Bangla' );
		assert.equal( await hasText( panel, 'ডিলার যাচাই ছাড়া দাম' ), false, 'no manager badge for an unknown product' );
		await panel.$eval( '#iaa-actionbar button[aria-label="আরও"]', ( e ) => e.click() );
		await panel.evaluate( () => [ ...document.querySelectorAll( '.iaa-menu__item' ) ].find( ( e ) => e.textContent === 'নতুন মেসেজ' ).click() );
		await panel.waitForSelector( '#iaa-note' );
		await a11y( panel, 'S-03 ready with note field' );
		await panel.type( '#iaa-note', 'E2E-NOTE A TO Z Jumbo' );
		await clickText( panel, 'কথোপকথন থেকে সাজেশন' );
		await panel.waitForSelector( '[data-variant="short"]', { timeout: 30000 } );
		const text = await panel.$eval( '[data-variant="professional"] .iaa-card__text', ( e ) => e.textContent );
		assert.ok( ! /1,250|১,২৫০|1250/.test( text ), 'still no dealer price when unlinked: ' + text );
		assert.ok( await hasText( panel, 'ডিলার যাচাই ছাড়া দাম' ), 'recognised product without public price → manager badge' );
		await panel.$eval( '#iaa-actionbar button[aria-label="আরও"]', ( e ) => e.click() );
		await panel.evaluate( () => [ ...document.querySelectorAll( '.iaa-menu__item' ) ].find( ( e ) => e.textContent === 'নতুন মেসেজ' ).click() );
		assert.equal( await panel.$eval( '#iaa-note', ( e ) => e.value ), '', '"নতুন মেসেজ" clears the note' );
		await inbox.evaluate( () => {
			document.getElementById( 'last-customer' ).textContent = 'Jumbo carton er dam koto?';
		} );
	} );

	await step( 'link dealer dialog: search by code, link this conversation', async () => {
		await clickText( panel, 'ডিলার লিংক' );
		await panel.waitForSelector( 'dialog[open] input[type=search]' );
		await panel.type( 'dialog[open] input[type=search]', 'D-014' );
		await panel.waitForSelector( 'dialog[open] .iaa-list__item', { timeout: 10000 } );
		await a11y( panel, 'S-09 link dialog' );
		await panel.$eval( 'dialog[open] .iaa-list__item', ( e ) => e.click() );
		await waitText( panel, 'ডিলার: D-014' );
	} );

	await step( 'linked dealer price question → tier price with fact chip, guard pass', async () => {
		await clickText( panel, 'কথোপকথন থেকে সাজেশন' );
		await panel.waitForSelector( '[data-variant="short"]', { timeout: 30000 } );
		const card = await panel.$eval( '[data-variant="professional"]', ( e ) => ( { text: e.querySelector( '.iaa-card__text' ).textContent, badge: e.querySelector( '.iaa-badge' ).className, chips: e.querySelector( '.iaa-chips' ) ? e.querySelector( '.iaa-chips' ).textContent : '' } ) );
		assert.ok( /1,250/.test( card.text ), 'dealer price quoted: ' + card.text );
		assert.ok( card.badge.includes( 'pass' ), 'guard pass' );
		assert.ok( card.chips.length > 0, 'fact chip shown' );
		assert.ok( await hasText( panel, 'ডিলার: D-014' ) );
	} );

	await step( 'quick answers: approved KB search and copy, no AI call', async () => {
		await panel.$eval( '#iaa-quick', ( e ) => e.click() );
		await panel.waitForSelector( 'input[type=search]' );
		await panel.type( 'input[type=search]', 'office' );
		await waitText( panel, 'Office hours' );
		await clickText( panel, 'কপি' );
		await waitText( panel, 'কপি হয়েছে' );
		assert.ok( ( await page.evaluate( () => window.__bus.clipboard ) ).includes( '৯টা' ) );
		await a11y( panel, 'S-11 quick answers' );
	} );

	await step( 'diagnostics view shows mode and shortcut without conversation text', async () => {
		await panel.$eval( '#iaa-settings', ( e ) => e.click() );
		await waitText( panel, 'স্বয়ংক্রিয় পড়া/বসানো' );
		assert.ok( await hasText( panel, 'Alt+Shift+D' ) );
		assert.equal( await hasText( panel, 'dam koto' ), false );
		await a11y( panel, 'S-12 diagnostics' );
	} );
	await page.close();

	await step( 'no customer text stored; outcomes recorded', async () => {
		await new Promise( ( r ) => setTimeout( r, 500 ) );
		const db = php( 'check-db.php' );
		assert.deepEqual( db.hits, [], 'customer text found in tables: ' + db.hits.join( ', ' ) );
		assert.equal( db.drafts, 5 );
		assert.ok( db.outcomes.includes( 'inserted' ), 'insert outcome recorded (first outcome wins)' );
	} );

	await step( 'icons render as SVG (no XML parse errors)', async () => {
		( { page, panel } = await open( CONV_A ) );
		await panel.waitForSelector( '.iaa-icon' );
		const r = await panel.evaluate( () => ( {
			parsererror: document.querySelectorAll( 'parsererror' ).length,
			empty: [ ...document.querySelectorAll( 'svg.iaa-icon' ) ].filter( ( s ) => ! s.querySelector( 'path,circle,rect,line,polyline,polygon' ) ).length,
			big: [ ...document.querySelectorAll( 'svg.iaa-icon' ) ].filter( ( s ) => s.getBoundingClientRect().width > 40 ).length,
		} ) );
		assert.deepEqual( r, { parsererror: 0, empty: 0, big: 0 } );
		assert.equal( await panel.evaluate( () => /\b(null|undefined|NaN)\b/.test( document.getElementById( 'iaa-app' ).textContent ) ), false, 'no null text on connect view' );
		assert.equal( await panel.evaluate( () => getComputedStyle( document.getElementById( 'iaa-quick' ) ).display ), 'none', 'quick answers hidden until connected' );
		if ( process.env.IAA_E2E_SHOTS ) {
			await page.screenshot( { path: join( process.env.IAA_E2E_SHOTS, 'connect.png' ) } );
		}
		await page.close();
	} );

	await step( 'wp-admin plugin pages load and pass axe (plugin content only)', async () => {
		const admin = await browser.newPage();
		admin.on( 'pageerror', ( e ) => errors.push( 'admin: ' + String( e ) ) );
		await admin.goto( `${ seed.siteurl }/wp-login.php`, { waitUntil: 'networkidle0' } );
		await admin.type( '#user_login', seed.admin_login );
		await admin.type( '#user_pass', seed.admin_pw );
		await Promise.all( [ admin.waitForNavigation(), admin.click( '#wp-submit' ) ] );
		for ( const slug of [ 'rar-iaa', 'rar-iaa-kb', 'rar-iaa-dealers', 'rar-iaa-dealers&tab=tiers', 'rar-iaa-prices', 'rar-iaa-prices&tab=products', 'rar-iaa-prices&tab=lists', 'rar-iaa-prices&action=import', 'rar-iaa-tokens', 'rar-iaa-logs', 'rar-iaa-settings', 'rar-iaa-settings&tab=limits' ] ) {
			const r = await admin.goto( `${ seed.siteurl }/wp-admin/admin.php?page=${ slug }`, { waitUntil: 'networkidle0' } );
			assert.equal( r.status(), 200, slug );
			assert.ok( await admin.$( '.wrap h1' ), `${ slug } has a page heading` );
			const body = await admin.evaluate( () => document.body.textContent );
			assert.ok( ! /Fatal error|Warning:|Notice:|Deprecated:/.test( body ), `${ slug } has no PHP errors` );
			await admin.evaluate( axeSource );
			const v = await admin.evaluate( async () => ( await window.axe.run( { include: [ '#wpbody-content .wrap' ] }, { runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ] } } ) ).violations
				.filter( ( x ) => x.impact === 'serious' || x.impact === 'critical' )
				.map( ( x ) => `${ x.id } (${ x.nodes.length }): ${ x.nodes.slice( 0, 2 ).map( ( n ) => n.target.join( ' ' ) ).join( ', ' ) }` ) );
			a11yViews.push( 'admin ' + slug );
			v.forEach( ( x ) => a11yIssues.push( `admin ${ slug }: ${ x }` ) );
		}
		// Plugins screen: "Dashboard | Settings" next to Deactivate.
		await admin.goto( `${ seed.siteurl }/wp-admin/plugins.php`, { waitUntil: 'networkidle0' } );
		const links = await admin.evaluate( () => [ ...document.querySelectorAll( 'tr[data-slug="rar-inbox-ai-assist"] .row-actions a' ) ].map( ( a ) => a.textContent.trim() ) );
		assert.deepEqual( links.slice( 0, 2 ), [ 'Dashboard', 'Settings' ], 'plugin action links: ' + links.join( ' | ' ) );
		if ( process.env.IAA_E2E_SHOTS ) {
			for ( const [ name, slug ] of [ [ 'overview', 'rar-iaa' ], [ 'products', 'rar-iaa-prices' ], [ 'linked', 'rar-iaa-prices&tab=products' ], [ 'settings', 'rar-iaa-settings' ] ] ) {
				await admin.goto( `${ seed.siteurl }/wp-admin/admin.php?page=${ slug }`, { waitUntil: 'networkidle0' } );
				await admin.screenshot( { path: join( process.env.IAA_E2E_SHOTS, `admin-${ name }.png` ), fullPage: true } );
			}
		}
		await admin.close();
	} );

	await step( 'accessibility: no serious/critical axe violations (WCAG 2.1 A/AA)', async () => {
		assert.ok( a11yViews.length >= 6, 'views checked: ' + a11yViews.join( ', ' ) );
		assert.deepEqual( a11yIssues, [] );
	} );

	await step( 'no uncaught page errors', async () => {
		assert.deepEqual( errors, [] );
	} );
} catch ( e ) {
	// Reported below.
} finally {
	await browser.close();
	console.log( results.join( '\n' ) );
	const failed = results.some( ( r ) => r.startsWith( 'FAIL' ) );
	console.log( `\n# e2e pass ${ passed } fail ${ failed ? 1 : 0 }` );
	process.exit( failed ? 1 : 0 );
}
