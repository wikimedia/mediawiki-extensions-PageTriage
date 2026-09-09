const { setActivePinia, createPinia } = require( 'pinia' );

function mockParams( params ) {
	mw.util.getParamValue = jest.fn( ( name ) => (
		Object.prototype.hasOwnProperty.call( params, name ) ? params[ name ] : null
	) );
}

function savedFilterOptions( extra ) {
	return JSON.stringify( Object.assign( {
		mode: 'npp',
		namespace: 0,
		showunreviewed: 1,
		showothers: 1,
		format: 'json',
		formatversion: 2,
		version: 2
	}, extra ) );
}

describe( 'settings store exclude filter', () => {
	let settings;
	let saveOptionSpy;
	const originalGetParamValue = mw.util.getParamValue;
	const originalConfigGet = mw.config.get;
	const originalOptionsGet = mw.user.options.get;
	const originalIsNamed = mw.user.isNamed;
	const originalIsAnon = mw.user.isAnon;

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
		mw.user.isAnon = jest.fn().mockReturnValue( false );
		mw.storage.get = jest.fn().mockReturnValue( null );
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
		mw.user.isAnon = originalIsAnon;
		saveOptionSpy.mockRestore();
	} );

	it( 'sends hideownpages when a named user excludes their own pages', () => {
		settings.unsaved.excludeFilter = 'own';
		settings.saveFilters();

		expect( settings.params.hideownpages ).toBe( 1 );
		expect( settings.applied.excludeFilter ).toBe( 'own' );
	} );

	it( 'omits hideownpages when no creator exclusion is selected', () => {
		settings.unsaved.excludeFilter = 'own';
		settings.saveFilters();
		settings.unsaved.excludeFilter = 'none';
		settings.saveFilters();

		expect( settings.params.hideownpages ).toBeUndefined();
		expect( settings.applied.excludeFilter ).toBe( 'none' );
	} );

	it( 'does not send hideownpages for anonymous users', () => {
		mw.user.isAnon.mockReturnValue( true );
		settings.unsaved.excludeFilter = 'own';
		settings.saveFilters();

		expect( settings.params.hideownpages ).toBeUndefined();
	} );

	it( 'restores the exclude radio from saved hideownpages for a logged-in user', () => {
		mw.user.options.get = jest.fn( ( key, fallback ) => {
			switch ( key ) {
				case 'timecorrection':
					return 'ZoneInfo|-480|America/Los_Angeles';
				case 'userjs-NewPagesFeedFilterOptions':
					return savedFilterOptions( { hideownpages: 1 } );
				default:
					return fallback || null;
			}
		} );
		const { useSettingsStore } = require( '../../../../modules/ext.pageTriage.newPagesFeed/stores/settings.js' );
		setActivePinia( createPinia() );
		settings = useSettingsStore();
		settings.loadApiParams();

		expect( settings.applied.excludeFilter ).toBe( 'own' );
		expect( settings.params.hideownpages ).toBe( 1 );
	} );

	it( 'ignores saved hideownpages for anonymous users', () => {
		mw.user.isAnon.mockReturnValue( true );
		mw.user.isNamed.mockReturnValue( false );
		mw.user.options.get = jest.fn( ( key, fallback ) => {
			switch ( key ) {
				case 'timecorrection':
					return 'ZoneInfo|-480|America/Los_Angeles';
				case 'userjs-NewPagesFeedFilterOptions':
					return savedFilterOptions( { hideownpages: 1 } );
				default:
					return fallback || null;
			}
		} );
		const { useSettingsStore } = require( '../../../../modules/ext.pageTriage.newPagesFeed/stores/settings.js' );
		setActivePinia( createPinia() );
		settings = useSettingsStore();
		settings.loadApiParams();

		expect( settings.applied.excludeFilter ).toBe( 'none' );
		expect( settings.params.hideownpages ).toBeUndefined();
	} );
} );
