const { mount } = require( '@vue/test-utils' );
let ListStatsNav;
let wrapper;
describe( 'ListStatsNav.vue', () => {
	beforeEach( () => {
		ListStatsNav = require( '../../../../modules/ext.pageTriage.list/components/ListStatsNav.vue' );
		wrapper = mount( ListStatsNav );
	} );
	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
	} );
} );
