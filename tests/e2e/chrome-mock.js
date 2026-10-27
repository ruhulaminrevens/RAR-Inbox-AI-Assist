/* Injected into every frame before page scripts (puppeteer evaluateOnNewDocument). */
( function () {
	if ( window === window.top ) {
		const bus = {
			storage: {},
			panel: [],
			background: [],
			content: [],
			onUpdated: [],
			onActivated: [],
			onCommand: [],
			tabUrl: '',
			clipboard: '',
			outcomes: [],
		};
		const deliver = ( listeners, msg, sender ) => new Promise( ( resolve ) => {
			let answered = false;
			const respond = ( r ) => {
				if ( ! answered ) {
					answered = true;
					resolve( r );
				}
			};
			let asyncReply = false;
			for ( const fn of listeners ) {
				if ( fn( msg, sender, respond ) === true ) {
					asyncReply = true;
				}
			}
			if ( ! asyncReply ) {
				setTimeout( () => respond( undefined ), 0 );
			}
		} );
		bus.changeUrl = ( url ) => {
			bus.tabUrl = url;
			bus.onUpdated.forEach( ( fn ) => fn( 1, { url }, { id: 1, url, active: true } ) );
		};
		bus.make = ( role ) => ( {
			runtime: {
				id: 'ext',
				getManifest: () => ( { version: '0.1.0' } ),
				onInstalled: { addListener() {} },
				onStartup: { addListener() {} },
				onMessage: { addListener: ( fn ) => bus[ role === 'background' ? 'background' : role === 'content' ? 'content' : 'panel' ].push( fn ) },
				sendMessage: ( msg ) => role === 'panel' ? deliver( bus.background, msg, { id: 'ext' } ) : deliver( bus.panel, msg, { id: 'ext' } ),
			},
			tabs: {
				query: async () => [ { id: 1, url: bus.tabUrl } ],
				get: async () => ( { id: 1, url: bus.tabUrl } ),
				update: async ( id, p ) => bus.changeUrl( p.url ),
				sendMessage: ( id, msg ) => deliver( bus.content, msg, { id: 'ext' } ),
				onUpdated: { addListener: ( fn ) => bus.onUpdated.push( fn ) },
				onActivated: { addListener: ( fn ) => bus.onActivated.push( fn ) },
			},
			storage: {
				local: {
					get: async ( keys ) => {
						const out = {};
						for ( const k of [].concat( keys ) ) {
							if ( k in bus.storage ) {
								out[ k ] = bus.storage[ k ];
							}
						}
						return out;
					},
					set: async ( o ) => Object.assign( bus.storage, o ),
					remove: async ( keys ) => [].concat( keys ).forEach( ( k ) => delete bus.storage[ k ] ),
					setAccessLevel: async ( o ) => {
						bus.accessLevel = o.accessLevel;
					},
				},
			},
			sidePanel: { setPanelBehavior: async () => {}, open: async () => {} },
			commands: { getAll: async () => [ { name: 'draft', shortcut: 'Alt+Shift+D' } ], onCommand: { addListener: ( fn ) => bus.onCommand.push( fn ) } },
			permissions: { request: async () => true },
		} );
		window.__bus = bus;
		window.chrome = bus.make( 'background' );
	} else {
		const role = window.name === 'content' ? 'content' : 'panel';
		window.chrome = window.top.__bus.make( role );
		if ( role === 'panel' ) {
			Object.defineProperty( navigator, 'clipboard', { value: { writeText: async ( t ) => {
				window.top.__bus.clipboard = t;
			} } } );
		}
	}
}() );
