const { mount } = require( '@vue/test-utils' );
let ControlSection;
let wrapper;
describe( 'ControlSection.vue', () => {
	beforeEach( () => {
		ControlSection = require( '../../../../modules/ext.pageTriage.newPagesFeed/components/ControlSection.vue' );
		wrapper = mount( ControlSection );
	} );
	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
	} );
} );
