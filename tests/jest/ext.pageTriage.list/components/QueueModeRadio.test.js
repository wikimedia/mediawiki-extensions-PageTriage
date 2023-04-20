const { mount } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
let QueueModeRadio;
let useSettingsStore;
let settings;
let wrapper;
describe( 'QueueModeRadio.vue', () => {
	beforeEach( () => {
		mw.user.options.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'timecorrection':
					return 'ZoneInfo|-480|America/Los_Angeles';
				default:
					return null;
			}
		} );
		useSettingsStore = require( '../../../../modules/ext.pageTriage.list/stores/settings.js' ).useSettingsStore;
		QueueModeRadio = require( '../../../../modules/ext.pageTriage.list/components/QueueModeRadio.vue' );
		wrapper = mount( QueueModeRadio, {
			global: {
				plugins: [ createTestingPinia( {
					stubActions: false
				} ) ]
			}
		} );
		settings = useSettingsStore();
	} );
	it( 'mounts in npp queueMode', () => {
		settings.immediate.queueMode = 'npp';
		settings.updateImmediate();
		expect( wrapper.exists() ).toBe( true );
	} );
	it( 'mounts in afc queueMode', () => {
		settings.immediate.queueMode = 'afc';
		settings.updateImmediate();
		expect( wrapper.exists() ).toBe( true );
	} );
} );
