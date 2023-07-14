const { mount } = require( '@vue/test-utils' );
let LoadMoreBar;
let wrapper;
describe( 'LoadMoreBar.vue', () => {
	beforeEach( () => {
		const mockIntersectionObserver = jest.fn();
		mockIntersectionObserver.mockReturnValue( {
			observe: () => null,
			unobserve: () => null,
			disconnect: () => null
		} );
		window.IntersectionObserver = mockIntersectionObserver;
		LoadMoreBar = require( '../../../../modules/ext.pageTriage.list/components/LoadMoreBar.vue' );
		wrapper = mount( LoadMoreBar );
	} );
	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
	} );
} );
