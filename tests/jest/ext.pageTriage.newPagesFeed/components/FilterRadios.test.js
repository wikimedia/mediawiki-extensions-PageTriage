const { mount } = require( '@vue/test-utils' );
const mixins = require( '../../../mocks/mixins.js' );
let FilterRadios;
let wrapper;
describe( 'FilterRadios.vue', () => {
	beforeEach( () => {
		FilterRadios = require( '../../../../modules/ext.pageTriage.newPagesFeed/components/FilterRadios.vue' );
		wrapper = mount( FilterRadios, {
			mixins: [ mixins ]
		} );
	} );
	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
	} );
} );
