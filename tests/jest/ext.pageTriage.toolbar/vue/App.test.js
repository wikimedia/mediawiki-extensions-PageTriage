const { mount } = require( '@vue/test-utils' );

describe( 'App.vue', () => {
	let wrapper;
	beforeEach( () => {
		mw.user.options.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'timecorrection':
					return 'ZoneInfo|-480|America/Los_Angeles';
				default:
					return null;
			}
		} );
		mw.util.wikiScript = jest.fn( () => '' );
		$.pageTriageTagsOptions = { all: {} };
		const App = require( '../../../../modules/ext.pageTriage.toolbar/vue/App.vue' );
		wrapper = mount( App );
	} );
	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
	} );
} );

describe( 'App.vue resize', () => {
	let App, context;
	beforeEach( () => {
		mw.user.options.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'timecorrection':
					return 'ZoneInfo|-480|America/Los_Angeles';
				default:
					return null;
			}
		} );
		App = require( '../../../../modules/ext.pageTriage.toolbar/vue/App.vue' );
		// Same as the rule in App.vue, which isn't loaded here
		document.head.innerHTML = '<style>.ve-activated #mwe-pt-toolbar { display: none; }</style>';
		document.body.innerHTML = '<div id="mwe-pt-toolbar"></div>';
		context = {
			toolbar: document.getElementById( 'mwe-pt-toolbar' ),
			updatePosition: jest.fn()
		};
	} );
	afterEach( () => {
		document.documentElement.classList.remove( 've-activated' );
		document.head.innerHTML = '';
		document.body.innerHTML = '';
	} );

	it( 'keeps the toolbar inside the window', () => {
		App.methods.resize.call( context );
		expect( context.updatePosition ).toHaveBeenCalled();
	} );

	it( 'does not move the toolbar while VisualEditor is open (T441997)', () => {
		document.documentElement.classList.add( 've-activated' );
		App.methods.resize.call( context );
		expect( context.updatePosition ).not.toHaveBeenCalled();
	} );

	it( 'does not move the toolbar while it is closed', () => {
		context.toolbar.style.display = 'none';
		App.methods.resize.call( context );
		expect( context.updatePosition ).not.toHaveBeenCalled();
	} );
} );
