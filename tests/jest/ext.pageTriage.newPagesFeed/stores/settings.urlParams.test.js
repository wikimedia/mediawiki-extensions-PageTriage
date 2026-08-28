const { setActivePinia, createPinia } = require( 'pinia' );

function mockParams( params ) {
	mw.util.getParamValue = jest.fn( ( name ) => (
		Object.prototype.hasOwnProperty.call( params, name ) ? params[ name ] : null
	) );
}

describe( 'settings store URL params', () => {
	let settings;
	let saveOptionSpy;
	const originalGetParamValue = mw.util.getParamValue;
	const originalConfigGet = mw.config.get;
	const originalOptionsGet = mw.user.options.get;
	const originalIsNamed = mw.user.isNamed;

	beforeEach( () => {
		mw.config.get = jest.fn( ( key, fallback ) => {
			switch ( key ) {
				case 'wgPageTriageDraftNamespaceId':
					return 118;
				case 'wgNamespaceIds':
					return { draft: 118 };
				default:
					return fallback;
			}
		} );
		mw.user.options.get = jest.fn( ( key, fallback ) => {
			switch ( key ) {
				case 'timecorrection':
					return 'ZoneInfo|-480|America/Los_Angeles';
				default:
					return fallback || null;
			}
		} );
		mw.user.options.set = jest.fn();
		mw.user.isNamed = jest.fn().mockReturnValue( true );
		mw.storage.set = jest.fn();
		saveOptionSpy = jest.spyOn( mw.Api.prototype, 'saveOption' ).mockResolvedValue( true );
		mockParams( {} );

		const { useSettingsStore } = require( '../../../../modules/ext.pageTriage.newPagesFeed/stores/settings.js' );
		setActivePinia( createPinia() );
		settings = useSettingsStore();
	} );

	afterEach( () => {
		mw.util.getParamValue = originalGetParamValue;
		mw.config.get = originalConfigGet;
		mw.user.options.get = originalOptionsGet;
		mw.user.isNamed = originalIsNamed;
		saveOptionSpy.mockRestore();
	} );

	it( 'overlays username from the URL after saved prefs without persisting', () => {
		mockParams( { username: 'Jimbo_Wales' } );
		settings.loadApiParams();

		expect( settings.urlOverridesActive ).toBe( true );
		expect( settings.controlMenuOpen ).toBe( false );
		expect( settings.immediate.queueMode ).toBe( 'npp' );
		expect( settings.applied.nppFilter ).toBe( 'username' );
		expect( settings.applied.nppFilterUser ).toBe( 'Jimbo Wales' );
		expect( settings.applied.afcFilterUser ).toBe( 'Jimbo Wales' );
		expect( settings.params.username ).toBe( 'Jimbo Wales' );
		expect( saveOptionSpy ).not.toHaveBeenCalled();
		expect( mw.user.options.set ).not.toHaveBeenCalled();
	} );

	it( 'keeps the username filter when switching queue mode', () => {
		mockParams( { username: 'Alice' } );
		settings.loadApiParams();
		saveOptionSpy.mockClear();

		settings.updateImmediate( 'queueMode', 'afc' );

		expect( settings.immediate.queueMode ).toBe( 'afc' );
		expect( settings.params.username ).toBe( 'Alice' );
		expect( settings.applied.afcFilter ).toBe( 'username' );
		expect( saveOptionSpy ).not.toHaveBeenCalled();
	} );

	it( 'persists when the user explicitly saves filters', () => {
		mockParams( { username: 'Alice' } );
		settings.loadApiParams();
		saveOptionSpy.mockClear();
		mw.user.options.set.mockClear();

		settings.saveFilters();

		expect( settings.urlOverridesActive ).toBe( false );
		expect( settings.controlMenuOpen ).toBe( false );
		expect( saveOptionSpy ).toHaveBeenCalled();
		expect( mw.user.options.set ).toHaveBeenCalled();
	} );

	it( 'applies feed=afc without opening the filter menu', () => {
		mockParams( { feed: 'afc' } );
		settings.loadApiParams();

		expect( settings.immediate.queueMode ).toBe( 'afc' );
		expect( settings.urlOverridesActive ).toBe( true );
		expect( settings.controlMenuOpen ).toBe( false );
		expect( saveOptionSpy ).not.toHaveBeenCalled();
	} );
} );
