const utils = require( '@vue/test-utils' );
const UsernameLookup = require( '../../../../modules/ext.pageTriage.newPagesFeed/components/UsernameLookup.vue' );
let wrapper;
describe( 'UsernameLookup.vue', () => {
	it( 'mounts', () => {
		wrapper = utils.mount( UsernameLookup );
		expect( wrapper.exists() ).toBe( true );
	} );

	it( 'mounts and sets username if prop set', () => {
		wrapper = utils.mount( UsernameLookup, {
			clone: false,
			propsData: {
				username: 'test-user'
			}
		} );
		expect( wrapper.vm.username ).toBe( 'test-user' );
		expect( wrapper.vm.usernameVal ).toBe( 'test-user' );
		expect( wrapper.vm.currentSearchTerm ).toBe( 'test-user' );
	} );
} );
