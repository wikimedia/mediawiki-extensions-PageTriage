const { mount } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
const mixins = require( '../../../mocks/mixins.js' );
let NppSortDirRadio;
let wrapper;
describe( 'NppSortDirRadio.vue', () => {
	beforeEach( () => {
		mw.user.options.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'timecorrection':
					return 'ZoneInfo|-480|America/Los_Angeles';
				default:
					return null;
			}
		} );
		NppSortDirRadio = require( '../../../../modules/ext.pageTriage.newPagesFeed.vue/components/NppSortDirRadio.vue' );
		wrapper = mount( NppSortDirRadio, {
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
