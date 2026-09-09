const { mount } = require( '@vue/test-utils' );
const mixins = require( '../../../mocks/mixins.js' );
let ExcludeRadios;
let wrapper;
describe( 'ExcludeRadios.vue', () => {
	beforeEach( () => {
		ExcludeRadios = require( '../../../../modules/ext.pageTriage.newPagesFeed/components/ExcludeRadios.vue' );
		wrapper = mount( ExcludeRadios, {
			mixins: [ mixins ],
			props: {
				filter: 'none',
				type: 'npp'
			}
		} );
	} );
	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
	} );
	it( 'defaults to none', () => {
		expect( wrapper.vm.selected ).toBe( 'none' );
	} );
} );
