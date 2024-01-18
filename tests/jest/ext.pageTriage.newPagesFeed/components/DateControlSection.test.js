const { mount } = require( '@vue/test-utils' );
let DateControlSection;
let wrapper;
describe( 'DateControlSection.vue', () => {
	beforeEach( () => {
		DateControlSection = require( '../../../../modules/ext.pageTriage.newPagesFeed/components/DateControlSection.vue' );
		wrapper = mount( DateControlSection );
	} );
	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
	} );
} );
