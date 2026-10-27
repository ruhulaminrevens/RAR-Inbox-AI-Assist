<?php
/** Util helpers. */
use RarIaa\Util;

t_eq( '1,250', Util::bd_group( 1250 ), 'bd_group 1250' );
t_eq( '1,25,000', Util::bd_group( 125000 ), 'bd_group lakh' );
t_eq( '12,34,56,789', Util::bd_group( 123456789 ), 'bd_group crore' );
t_eq( '1,250.50', Util::bd_group( 1250.5 ), 'bd_group decimals' );
t_eq( '999', Util::bd_group( 999 ), 'bd_group small' );
t_eq( '1250', Util::ascii_digits( '১২৫০' ), 'ascii digits' );
t_eq( '১২৫০', Util::bn_digits( '1250' ), 'bn digits' );
t_ok( Util::is_uuid( Util::uuid4() ), 'uuid4 valid' );
t_ok( ! Util::is_uuid( 'abc' ), 'uuid invalid' );
t_eq( array( 'jumbo', 'coil', 'er', 'dam' ), Util::tokens( 'Jumbo coil-er DAM?' ), 'tokens' );
