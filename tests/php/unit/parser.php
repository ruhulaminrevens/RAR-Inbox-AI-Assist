<?php
/** Response parser fails closed (v0.2 §19). */
use RarIaa\Draft\ResponseParser;

$exp = array( 'professional', 'friendly', 'short' );
$ok  = json_encode( array( 'suggestions' => t_sug( 'hello', array( 'kb:1', 'kb:999' ) ) ) );
$r   = ResponseParser::parse( $ok, $exp, array( 'kb:1' ) );
t_ok( is_array( $r ) && 3 === count( $r ), 'valid parses' );
t_eq( array( 'kb:1' ), $r[0]['facts_used'], 'unknown fact ids dropped' );
t_ok( is_array( ResponseParser::parse( "```json\n{$ok}\n```", $exp, array() ) ), 'code fence stripped' );
t_ok( is_array( ResponseParser::parse( "Here you go: {$ok} thanks", $exp, array() ) ), 'surrounding text tolerated' );
$two = json_encode( array( 'suggestions' => array_slice( t_sug( 'x' ), 0, 2 ) ) );
t_eq( null, ResponseParser::parse( $two, $exp, array() ), 'wrong count fails' );
$dup        = t_sug( 'x' );
$dup[2]['variant_id'] = 'friendly';
t_eq( null, ResponseParser::parse( json_encode( array( 'suggestions' => $dup ) ), $exp, array() ), 'duplicate id fails' );
$bad = t_sug( 'x' );
$bad[1]['variant_id'] = 'aggressive';
t_eq( null, ResponseParser::parse( json_encode( array( 'suggestions' => $bad ) ), $exp, array() ), 'unknown id fails' );
$empty = t_sug( '   ' );
t_eq( null, ResponseParser::parse( json_encode( array( 'suggestions' => $empty ) ), $exp, array() ), 'empty reply fails' );
t_eq( null, ResponseParser::parse( 'not json', $exp, array() ), 'garbage fails' );
$four = array_merge( t_sug( 'x' ), array( array( 'variant_id' => 'sales_focused', 'reply' => 'y' ) ) );
t_ok( is_array( ResponseParser::parse( json_encode( array( 'suggestions' => $four ) ), array_merge( $exp, array( 'sales_focused' ) ), array() ) ), 'four variants when configured' );
$reason = t_sug( 'x', array(), array( 'needs_human' => true, 'handoff_reason' => 'nonsense' ) );
$r      = ResponseParser::parse( json_encode( array( 'suggestions' => $reason ) ), $exp, array() );
t_eq( null, $r[0]['handoff_reason'], 'unknown handoff reason nulled' );
