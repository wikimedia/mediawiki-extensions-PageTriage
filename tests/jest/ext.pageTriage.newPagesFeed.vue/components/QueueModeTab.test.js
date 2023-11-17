const { mount } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
const mixins = require( '../../../mocks/mixins.js' );
let QueueModeTab;
let settings;
let wrapper;
describe( 'QueueModeTab.vue', () => {
	beforeEach( () => {
		mw.user.options.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'timecorrection':
					return 'ZoneInfo|-480|America/Los_Angeles';
				default:
					return null;
			}
		} );
		const { useSettingsStore } = require( '../../../../modules/ext.pageTriage.newPagesFeed.vue/stores/settings.js' );
		QueueModeTab = require( '../../../../modules/ext.pageTriage.newPagesFeed.vue/components/QueueModeTab.vue' );
		wrapper = mount( QueueModeTab, {
			global: {
				mixins: [ mixins ],
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
