const { mount } = require( '@vue/test-utils' );
let CreatorByline;
let wrapper;
describe( 'CreatorByline.vue', () => {
	beforeEach( () => {
		CreatorByline = require( '../../../../modules/ext.pageTriage.newPagesFeed.vue/components/CreatorByline.vue' );
		wrapper = mount( CreatorByline );
	} );
	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
	} );
} );
