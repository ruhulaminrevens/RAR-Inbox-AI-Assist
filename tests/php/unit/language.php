<?php
/** Language detection (FR-10) and greeting rule (BR-12). */
use RarIaa\Draft\Language;

$cases = array(
	array( 'আপনাদের অফিস কয়টা পর্যন্ত খোলা?', 'bn' ),
	array( 'Assalamu alaikum. Apnader store ajke khola koyta porjonto?', 'banglish' ),
	array( 'vai dam koto?', 'banglish' ),
	array( 'What is the price of Jumbo carton?', 'en' ),
	array( 'Hello', 'en' ),
	array( 'ok', 'en' ),
	array( 'jumbo coil er dam koto bhai', 'banglish' ),
	array( 'দাম কত? price?', 'bn' ),
	array( '১২৩', 'bn' ),
	array( 'Price koto?', 'banglish' ),
	array( 'koto?', 'banglish' ),
	array( 'Is it available na?', 'en' ),
);
foreach ( $cases as $c ) {
	t_eq( $c[1], Language::detect( $c[0] ), "detect: {$c[0]}" );
}
// Recent messages: a short English-looking last message follows the previous ones (v0.2.1).
t_eq( 'bn', Language::detect_recent( array( 'এই প্রোডাক্ট টা available আছে?', 'Price?' ) ), 'short "Price?" after Bangla → bn' );
t_eq( 'bn', Language::detect_recent( array( 'এই প্রোডাক্ট টা available আছে?', 'Hello' ) ), 'short "Hello" after Bangla → bn' );
t_eq( 'banglish', Language::detect_recent( array( 'vai dam koto?', 'ok' ) ), 'short "ok" after Banglish → banglish' );
t_eq( 'en', Language::detect_recent( array( 'এই প্রোডাক্ট টা available আছে?', 'Can you deliver it to Chittagong by Friday?' ) ), 'long English last message stays en' );
t_eq( 'en', Language::detect_recent( array( 'Hello' ) ), 'single Hello → en' );
t_eq( 'bn', Language::detect_recent( array() ), 'no text → bn' );
t_eq( 'en', Language::detect_recent( array( 'What is the price?', 'ok' ) ), 'English history stays en' );
t_eq( array( 'bn', false ), Language::reply_language( 'banglish', 'auto', 'bn' ), 'banglish → bn script' );
t_eq( array( 'bn', true ), Language::reply_language( 'banglish', 'auto', 'latin' ), 'banglish → latin when set' );
t_eq( array( 'en', false ), Language::reply_language( 'bn', 'en', 'bn' ), 'override en' );
t_ok( Language::greeted_salam( 'Assalamu alaikum' ), 'salam latin' );
t_ok( Language::greeted_salam( 'আসসালামু আলাইকুম ভাই' ), 'salam bangla' );
t_ok( ! Language::greeted_salam( 'hello' ), 'no salam' );
