const { mount } = require( '@vue/test-utils' );
describe( 'App.vue', () => {
	beforeEach( () => {
		mw.config.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'pageTriageNamespaces':
					return [ 0, 118 ];
				case 'wgPageTriageDraftNamespaceId':
					return 118;
				default:
					return null;
			}
		} );
		mw.user.options.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'timecorrection':
					return 'ZoneInfo|-480|America/Los_Angeles';
				default:
					return null;
			}
		} );
	} );
	it( 'exists', () => {
		const App = require( '../../../modules/ext.pageTriage.list/App.vue' );
		const wrapper = mount( App );
		expect( wrapper.exists() ).toBe( true );
	} );
} );
