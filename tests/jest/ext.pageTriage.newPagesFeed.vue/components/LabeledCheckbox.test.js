const { mount } = require( '@vue/test-utils' );
let LabeledCheckbox;
let wrapper;
describe( 'LabeledCheckbox.vue', () => {
	beforeEach( () => {
		LabeledCheckbox = require( '../../../../modules/ext.pageTriage.newPagesFeed/components/LabeledCheckbox.vue' );
		wrapper = mount( LabeledCheckbox );
	} );
	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
	} );
} );
