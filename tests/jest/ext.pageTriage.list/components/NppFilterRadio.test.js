const { mount } = require( '@vue/test-utils' );
const mixins = require( '../../../mocks/mixins.js' );
let NppFilterRadio;
let wrapper;
describe( 'NppFilterRadio.vue', () => {
	beforeEach( () => {
		NppFilterRadio = require( '../../../../modules/ext.pageTriage.list/components/NppFilterRadio.vue' );
		wrapper = mount( NppFilterRadio, {
			mixins: [ mixins ]
		} );
	} );
	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
	} );
} );
