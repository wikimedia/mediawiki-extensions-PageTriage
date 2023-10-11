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
		const App = require( '../../../../modules/ext.pageTriage.views.toolbar/vue/App.vue' );
		wrapper = mount( App );
	} );
	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
	} );
} );
