const { mount } = require( '@vue/test-utils' );
let ListStatsNav;
let wrapper;
describe( 'ListStatsNav.vue', () => {
	beforeEach( () => {
		ListStatsNav = require( '../../../../modules/ext.pageTriage.newPagesFeed/components/ListStatsNav.vue' );
		wrapper = mount( ListStatsNav );
		wrapper.vm.calculateDiff = jest.fn().mockReturnValue( 42 );
	} );
	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
		expect( wrapper.vm.showStats ).toBe( false );
	} );
} );
