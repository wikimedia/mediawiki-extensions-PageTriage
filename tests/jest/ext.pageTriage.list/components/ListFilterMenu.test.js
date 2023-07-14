const { mount } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
let ListFilterMenu;
let useSettingsStore;
let settings;
let wrapper;
describe( 'ListFilterMenu.vue', () => {
	beforeEach( () => {
		mw.config.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'pageTriageNamespaces':
					return [ 0, 118 ];
				case 'wgPageTriageDraftNamespaceId':
					return 118;
				default:
					return null;
			}
		} );
		mw.user.options.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'timecorrection':
					return 'ZoneInfo|-480|America/Los_Angeles';
				default:
					return null;
			}
		} );
		useSettingsStore = require( '../../../../modules/ext.pageTriage.list/stores/settings.js' ).useSettingsStore;
		ListFilterMenu = require( '../../../../modules/ext.pageTriage.list/components/ListFilterMenu.vue' );
		wrapper = mount( ListFilterMenu, {
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
