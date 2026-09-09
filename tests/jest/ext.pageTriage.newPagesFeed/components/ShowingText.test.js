const { mount } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
const mixins = require( '../../../mocks/mixins.js' );
let ShowingText;
let wrapper;

describe( 'ShowingText.vue', () => {
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
		ShowingText = require( '../../../../modules/ext.pageTriage.newPagesFeed/components/ShowingText.vue' );
		wrapper = mount( ShowingText, {
			global: {
				mixins: [ mixins ],
				plugins: [ createTestingPinia( {
					stubActions: false
				} ) ]
			}
		} );
	} );

	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
	} );

	// T422315: Show NPP and AfC filters as active filter chips.

	it( 'shows the NPP unreferenced filter chip', () => {
		const settings = wrapper.vm.settings;
		settings.immediate.queueMode = 'npp';
		settings.applied.nppFilter = 'unreferenced';

		expect( wrapper.vm.showingObj.top ).toContain( 'pagetriage-filter-stat-unreferenced' );
	} );

	it( 'shows the AfC unreferenced filter chip', () => {
		const settings = wrapper.vm.settings;
		settings.immediate.queueMode = 'afc';
		settings.applied.afcFilter = 'unreferenced';

		expect( wrapper.vm.showingObj.top ).toContain( 'pagetriage-filter-stat-unreferenced' );
	} );

	it( 'shows a chip when pages the user created are excluded', () => {
		const { useSettingsStore } = require( '../../../../modules/ext.pageTriage.newPagesFeed/stores/settings.js' );
		const settings = useSettingsStore();
		settings.applied.excludeFilter = 'own';
		expect( wrapper.vm.showingObj.exclude ).toContain( 'pagetriage-filter-stat-hide-own-pages' );
	} );
} );
