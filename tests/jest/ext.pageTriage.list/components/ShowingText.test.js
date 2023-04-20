const { mount } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
const mixins = require( '../../../mocks/mixins.js' );
let ShowingText;
let wrapper;
describe( 'ShowingText.vue', () => {
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
		ShowingText = require( '../../../../modules/ext.pageTriage.list/components/ShowingText.vue' );
		wrapper = mount( ShowingText, {
			global: {
				mixins: [ mixins ],
				plugins: [ createTestingPinia() ]
			}
		} );
	} );
	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
	} );
} );
