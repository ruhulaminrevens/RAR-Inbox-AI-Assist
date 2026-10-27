/**
 * The only module that knows the REST API (TRD §5). Runs in the service worker.
 * Storage: chrome.storage.local, restricted to TRUSTED_CONTEXTS (no content-script access).
 */

export const DEFAULT_SITE = 'https://nabiad.com';
export const VERSION = '0.1.0';
const TIMEOUT_MS = 25000;
export const TOKEN_RE = /^iaa_[a-z0-9]{8}_[A-Za-z0-9]{40}$/;

/** Build an endpoint URL for either /wp-json/... or ?rest_route=... style bases. */
export function buildUrl( base, path, params ) {
	let url;
	if ( base.includes( 'rest_route=' ) ) {
		url = base + path;
		const q = new URLSearchParams( params || {} ).toString();
		return q ? `${ url }&${ q }` : url;
	}
	url = base.replace( /\/+$/, '' ) + path;
	const q = new URLSearchParams( params || {} ).toString();
	return q ? `${ url }?${ q }` : url;
}

export function apiBaseFor( site ) {
	return `${ site.replace( /\/+$/, '' ) }/wp-json/rar-iaa/v1`;
}

export class Api {
	/**
	 * @param {object} storage chrome.storage.local-like ({ get, set, remove } returning promises)
	 * @param {Function} fetchFn fetch implementation
	 */
	constructor( storage, fetchFn ) {
		this.storage = storage;
		this.fetch = fetchFn;
	}

	async config() {
		const v = await this.storage.get( [ 'iaa.token', 'iaa.site', 'iaa.base' ] );
		const site = v[ 'iaa.site' ] || DEFAULT_SITE;
		return { token: v[ 'iaa.token' ] || null, site, base: v[ 'iaa.base' ] || apiBaseFor( site ) };
	}

	async saveConnection( token, site, base ) {
		await this.storage.set( { 'iaa.token': token, 'iaa.site': site, 'iaa.base': base || apiBaseFor( site ) } );
	}

	async forget() {
		await this.storage.remove( [ 'iaa.token' ] );
	}

	/**
	 * @returns {Promise<{ok:boolean,status:number,data:any,error:?{code:string,message:string,data:object},requestId:?string}>}
	 */
	async request( method, path, { body, params, headers, token, base } = {} ) {
		const cfg = await this.config();
		const tk = token || cfg.token;
		if ( ! tk ) {
			return { ok: false, status: 401, data: null, error: { code: 'AUTH_MISSING', message: 'Not connected', data: {} }, requestId: null };
		}
		const ctrl = new AbortController();
		const timer = setTimeout( () => ctrl.abort(), TIMEOUT_MS );
		const h = { Authorization: `Bearer ${ tk }`, 'X-IAA-Token': tk, 'X-IAA-Client': `ext/${ VERSION }`, Accept: 'application/json', ...( headers || {} ) };
		if ( body !== undefined ) {
			h[ 'Content-Type' ] = 'application/json';
		}
		let res;
		try {
			res = await this.fetch( buildUrl( base || cfg.base, path, params ), {
				method,
				headers: h,
				body: body === undefined ? undefined : JSON.stringify( body ),
				credentials: 'omit',
				cache: 'no-store',
				signal: ctrl.signal,
			} );
		} catch ( e ) {
			clearTimeout( timer );
			const code = e && e.name === 'AbortError' ? 'CLIENT_TIMEOUT' : 'NETWORK';
			return { ok: false, status: 0, data: null, error: { code, message: String( e && e.message ), data: {} }, requestId: null };
		}
		clearTimeout( timer );
		const requestId = res.headers.get( 'X-IAA-Request-Id' );
		let data = null;
		if ( res.status !== 204 ) {
			try {
				data = await res.json();
			} catch ( e ) {
				data = null;
			}
		}
		if ( res.ok ) {
			return { ok: true, status: res.status, data, error: null, requestId };
		}
		const err = data && typeof data === 'object' && data.code ? { code: String( data.code ), message: String( data.message || '' ), data: data.data || {} } : { code: res.status === 404 ? 'NOT_FOUND' : 'INTERNAL_ERROR', message: `HTTP ${ res.status }`, data: {} };
		if ( ! err.data.request_id && requestId ) {
			err.data.request_id = requestId;
		}
		return { ok: false, status: res.status, data: null, error: err, requestId };
	}
}
