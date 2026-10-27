<?php
/** T1.05 / T1.10 — REST token auth over real HTTP, cross-namespace denial, headers. */
t_reset();
$s     = t_seed();
$shop  = t_user( 'shop_manager', 'iaa_shop' );
$sub   = t_user( 'subscriber', 'iaa_sub' );
$tok   = t_token( $shop );
$r     = t_http( 'GET', '/me', $tok );
t_eq( 200, $r['status'], '/me 200 with Bearer' );
t_eq( $shop, $r['json']['user']['id'], '/me user' );
t_ok( in_array( 'use', $r['json']['caps'], true ), '/me caps' );
t_ok( false === $r['json']['settings']['live_inbox'], 'live inbox off by default' );
t_ok( (bool) preg_match( '/Cache-Control:[^\n]*no-store/i', $r['headers'] ), 'no-store header' );
t_ok( (bool) preg_match( '/X-LiteSpeed-Cache-Control: no-cache/i', $r['headers'] ), 'LiteSpeed no-cache header' );
t_ok( (bool) preg_match( '/X-IAA-Request-Id: [0-9a-f-]{36}/i', $r['headers'] ), 'request id header' );
// Fallback header.
$base = getenv( 'IAA_TEST_URL' ) ?: 'http://127.0.0.1:8899/index.php?rest_route=/rar-iaa/v1';
$r    = t_http( 'GET', '/me', null, null, array( 'X-IAA-Token' => $tok ) );
t_eq( 200, $r['status'], 'X-IAA-Token header works' );
// Missing / invalid / revoked.
$r = t_http( 'GET', '/me' );
t_eq( 401, $r['status'], 'no token 401' );
t_eq( 'AUTH_MISSING', $r['json']['code'], 'AUTH_MISSING' );
t_ok( isset( $r['json']['data']['request_id'] ), 'error body carries request id' );
$r = t_http( 'GET', '/me', 'iaa_abcdefgh_' . str_repeat( 'A', 40 ) );
t_eq( 'AUTH_INVALID', $r['json']['code'], 'unknown token AUTH_INVALID' );
// Cross-namespace: the token must not authenticate WP core or any other namespace (F-14).
$core = str_replace( '/rar-iaa/v1', '', $base );
$r    = t_http( 'GET', $core . '/wp/v2/users/me', $tok );
t_eq( 401, $r['status'], 'token does not authenticate /wp/v2/users/me' );
$r = t_http( 'GET', $core . '/wp/v2/settings', $tok );
t_ok( in_array( $r['status'], array( 401, 403 ), true ), 'token cannot read /wp/v2/settings' );
$r = t_http( 'GET', $base . '/../../wp/v2/users/me', $tok );
t_ok( 200 !== $r['status'] || ! isset( $r['json']['id'] ), 'path traversal does not authenticate' );
// Subscriber token: authenticated but forbidden.
$tsub = t_token( $sub );
$r    = t_http( 'POST', '/drafts', $tsub, array( 'messages' => array( array( 'side' => 'customer', 'text' => 'hi' ) ) ), array( 'Idempotency-Key' => t_uuid() ) );
t_eq( 403, $r['status'], 'no capability → 403' );
t_eq( 'FORBIDDEN', $r['json']['code'], 'FORBIDDEN code' );
// Role removed → next call 403.
$u = new WP_User( $shop );
$u->set_role( 'subscriber' );
$r = t_http( 'POST', '/drafts', $tok, array( 'messages' => array( array( 'side' => 'customer', 'text' => 'hi' ) ) ), array( 'Idempotency-Key' => t_uuid() ) );
t_eq( 403, $r['status'], 'capability removed takes effect immediately' );
$u->set_role( 'shop_manager' );
// Old client version.
$r = t_http( 'GET', '/me', $tok, null, array( 'X-IAA-Client' => 'ext/0.0.1' ) );
t_eq( 426, $r['status'], 'CLIENT_TOO_OLD' );
// Payload too large.
$r = t_http( 'POST', '/drafts', $tok, str_repeat( 'a', 70000 ), array( 'Idempotency-Key' => t_uuid() ) );
t_eq( 413, $r['status'], 'PAYLOAD_TOO_LARGE' );
// Self revoke.
$r = t_http( 'POST', '/tokens/self/revoke', $tok );
t_eq( 204, $r['status'], 'self revoke 204' );
$r = t_http( 'GET', '/me', $tok );
t_eq( 'AUTH_REVOKED', $r['json']['code'], 'revoked token refused' );
