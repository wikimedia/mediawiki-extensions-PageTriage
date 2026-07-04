const { mount } = require( '@vue/test-utils' );
const { createTestingPinia } = require( '@pinia/testing' );
let ListFilterMenu;
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
				case 'wgNamespaceIds':
					return { draft: 118 };
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
		const { useSettingsStore } = require( '../../../../modules/ext.pageTriage.newPagesFeed/stores/settings.js' );
		ListFilterMenu = require( '../../../../modules/ext.pageTriage.newPagesFeed/components/ListFilterMenu.vue' );
		wrapper = mount( ListFilterMenu, {
			global: {
				plugins: [ createTestingPinia( {
					stubActions: false
				} ) ]
			}
		} );
		settings = useSettingsStore();
	} );
	afterEach( () => {
		// Unmount so the component's document click listener is removed
		// between tests.
		wrapper.unmount();
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
	it( 'saves and dismisses the overlay when clicking outside of it', () => {
		// Stand in for the menu container that the template ref points to; the
		// component template is not rendered in this test environment.
		const menuContainer = document.createElement( 'div' );
		document.body.appendChild( menuContainer );
		wrapper.vm.menuToggle = menuContainer;
		settings.controlMenuOpen = true;
		const updateSpy = jest.spyOn( settings, 'update' );
		updateSpy.mockClear();

		// A click outside the menu container should save and close it.
		document.body.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) );

		expect( updateSpy ).toHaveBeenCalledWith( settings.unsaved );
		expect( settings.controlMenuOpen ).toBe( false );
		menuContainer.remove();
	} );
	it( 'stays open when clicking inside the overlay', () => {
		const menuContainer = document.createElement( 'div' );
		const insideElement = document.createElement( 'span' );
		menuContainer.appendChild( insideElement );
		document.body.appendChild( menuContainer );
		wrapper.vm.menuToggle = menuContainer;
		settings.controlMenuOpen = true;
		const updateSpy = jest.spyOn( settings, 'update' );
		updateSpy.mockClear();

		// A click inside the menu container should leave it open.
		insideElement.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) );

		expect( updateSpy ).not.toHaveBeenCalled();
		expect( settings.controlMenuOpen ).toBe( true );
		menuContainer.remove();
	} );
} );
