const { mount, config } = require( '@vue/test-utils' );
const ToolWikiLove = require( '../../../../modules/ext.pageTriage.toolbar/vue/components/ToolWikiLove.vue' );

describe( 'ToolWikiLove.vue', () => {
	const messages = {
		'comma-separator': ', ',
		'pagetriage-toolbar-close': 'Close',
		'pagetriage-toolbar-learn-more': 'Learn more »',
		'pagetriage-wikilove-edit-count': '$1 edits',
		'pagetriage-wikilove-helptext': 'Select the names of editors you wish to thank.',
		'pagetriage-wikilove-page-creator': 'Page Creator',
		'pagetriage-wikilove-tooltip': 'Send appreciation to the authors',
		wikilove: 'WikiLove',
		'wikilove-button-send': 'Send WikiLove'
	};

	mw.msg = ( key, ...args ) => {
		let msg = messages[ key ];

		if ( args.length > 0 ) {
			for ( let i = 0; i < args.length; i++ ) {
				msg = msg.replace( new RegExp( '\\$' + ( i + 1 ), 'g' ), args[ i ] );
			}
		}

		return msg;
	};

	config.global.mocks.$i18n = ( key, ...args ) => ( {
		// eslint-disable-next-line mediawiki/msg-doc
		text: () => mw.msg( key, ...args )
	} );

	mw.user.getName = () => 'Myself';

	test( 'renders properly', async () => {
		let ready;
		const wrapper = mount( ToolWikiLove, {
			props: {
				article: {
					on( name, callback ) {
						if ( name === 'ready' ) {
							ready = callback;
						}
					},
					get( property ) {
						if ( property === 'user_name' ) {
							return 'John Doe';
						}
					},
					revisions: {
						each( callback ) {
							callback( {
								get( property ) {
									if ( property === 'user' ) {
										return 'Jane Doe';
									}
								},
								has( property ) {
									return property !== 'userhidden';
								}
							} );
						}
					}
				},
				eventBus: {}
			}
		} );

		await wrapper.vm.$nextTick();
		ready();

		expect( wrapper.element ).toMatchSnapshot();
	} );

	test( 'sorts editors and excludes hidden users', async () => {
		let ready;
		const wrapper = mount( ToolWikiLove, {
			propsData: {
				article: {
					on( event, callback ) {
						if ( event === 'ready' ) {
							ready = callback;
						}
					},
					get( property ) {
						if ( property === 'user_name' ) {
							return 'John Doe';
						}
					},
					revisions: {
						each( callback ) {
							callback( {
								get( property ) {
									if ( property === 'user' ) {
										return 'Jane Doe';
									}
								},
								has( property ) {
									if ( property === 'userhidden' ) {
										return false;
									}
								}
							} );

							callback( {
								get( property ) {
									if ( property === 'user' ) {
										return 'Invisible User';
									}
								},
								has( property ) {
									if ( property === 'userhidden' ) {
										return true;
									}
								}
							} );
						}
					}
				},
				eventBus: {}
			}
		} );

		await wrapper.vm.$nextTick();
		ready();

		expect( wrapper.vm.editors ).toStrictEqual( [
			{
				username: 'John Doe',
				count: 1,
				isCreator: true
			},
			{
				username: 'Jane Doe',
				count: 1,
				isCreator: false
			}
		] );
	} );

	test( 'toggles send button based on selection count', async () => {
		const wrapper = mount( ToolWikiLove, {
			propsData: {
				article: { on() {} },
				eventBus: {}
			}
		} );

		const sendButton = wrapper.find( '.cdx-button' );

		wrapper.vm.selected = [];
		await wrapper.vm.$nextTick();
		expect( sendButton.isDisabled() ).toBe( true );

		wrapper.vm.selected = [ 'Stranger' ];
		await wrapper.vm.$nextTick();
		expect( sendButton.isDisabled() ).toBe( false );
	} );

	test( 'getEditorUrl', () => {
		mw.config.get = ( name ) => {
			if ( name === 'wgNamespaceIds' ) {
				return { user: 2 };
			} else if ( name === 'wgFormattedNamespaces' ) {
				return { 2: 'User' };
			}
		};

		const editorUrl = ToolWikiLove.methods.getEditorUrl( { username: 'Random Person' } );
		expect( editorUrl ).toBe( '/wiki/User:Random Person' );
	} );

	test( 'getEditorInfo', () => {
		const creatorInfo = ToolWikiLove.methods.getEditorInfo( {
			count: 123,
			isCreator: true
		} );
		expect( creatorInfo ).toBe( '– 123 edits, Page Creator' );

		const nonCreatorInfo = ToolWikiLove.methods.getEditorInfo( {
			count: 456,
			isCreator: false
		} );
		expect( nonCreatorInfo ).toBe( '– 456 edits' );
	} );
} );
