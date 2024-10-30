const { mount } = require( '@vue/test-utils' );
let KeywordSearch;
let wrapper;
describe( 'KeywordSearch.vue', () => {
	beforeEach( () => {
		KeywordSearch = require( '../../../../modules/ext.pageTriage.newPagesFeed/components/KeywordSearch.vue' );
		wrapper = mount( KeywordSearch );
	} );
	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
	} );
} );
