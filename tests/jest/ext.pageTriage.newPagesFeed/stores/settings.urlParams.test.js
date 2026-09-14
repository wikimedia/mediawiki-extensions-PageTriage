const { setActivePinia, createPinia } = require( 'pinia' );

function unitMs( unit ) {
	switch ( unit ) {
		case 'minute':
		case 'minutes':
			return 60 * 1000;
		case 'second':
		case 'seconds':
			return 1000;
		case 'day':
		case 'days':
			return 24 * 60 * 60 * 1000;
		default:
			throw new Error( 'Unsupported moment unit: ' + unit );
	}
}

/**
 * Minimal utc() stub that honors add/subtract and toISOString the way
 * addDateFilters() uses moment. Restored in afterEach so it does not leak.
 *
 * @param {string} val
 * @return {Object}
 */
function createMomentUtc( val ) {
	let ms;
	if ( /^\d{4}-\d{2}-\d{2}$/.test( val ) ) {
		ms = Date.UTC(
			Number( val.slice( 0, 4 ) ),
			Number( val.slice( 5, 7 ) ) - 1,
			Number( val.slice( 8, 10 ) )
		);
	} else {
		ms = Date.parse( val );
	}
	const api = {
		subtract: ( amount, unit ) => {
			ms -= amount * unitMs( unit );
			return api;
		},
		add: ( amount, unit ) => {
			ms += amount * unitMs( unit );
			return api;
		},
		toISOString: () => new Date( ms ).toISOString(),
		format: () => new Date( ms ).toISOString().slice( 0, 10 )
	};
	return api;
}

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
	const originalMoment = global.moment;

	beforeEach( () => {
		global.moment = { utc: createMomentUtc };
		mw.config.get = jest.fn( ( key, fallback ) => {
			switch ( key ) {
				case 'wgPageTriageDraftNamespaceId':
					return 118;
				case 'wgNamespaceIds':
					return { draft: 118 };
				case 'wgShowOresFilters':
					return true;
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
		global.moment = originalMoment;
		saveOptionSpy.mockRestore();
	} );

	it( 'overlays username from the URL after saved prefs without persisting', () => {
		mockParams( { username: 'Jimbo_Wales' } );
		settings.loadApiParams();

		expect( settings.urlOverridesActive ).toBe( true );
		expect( settings.controlMenuOpen ).toBe( false );
		expect( settings.immediate.queueMode ).toBe( 'npp' );
		expect( settings.applied.nppFilter ).toBe( 'username' );
		expect( settings.applied.nppFilterUser ).toEqual( [ 'Jimbo Wales' ] );
		expect( settings.applied.afcFilterUser ).toEqual( [ 'Jimbo Wales' ] );
		expect( settings.params.username ).toEqual( [ 'Jimbo Wales' ] );
		expect( saveOptionSpy ).not.toHaveBeenCalled();
		expect( mw.user.options.set ).not.toHaveBeenCalled();
	} );

	it( 'keeps the username filter when switching queue mode', () => {
		mockParams( { username: 'Alice' } );
		settings.loadApiParams();
		saveOptionSpy.mockClear();

		settings.updateImmediate( 'queueMode', 'afc' );

		expect( settings.immediate.queueMode ).toBe( 'afc' );
		expect( settings.params.username ).toEqual( [ 'Alice' ] );
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

	it( 'overlays status=reviewed without persisting', () => {
		mockParams( { status: 'reviewed' } );
		settings.loadApiParams();

		expect( settings.urlOverridesActive ).toBe( true );
		expect( settings.applied.nppIncludeReviewed ).toBe( true );
		expect( settings.applied.nppIncludeUnreviewed ).toBe( false );
		expect( settings.params.mode ).toBe( 'npp' );
		expect( settings.params.showreviewed ).toBe( 1 );
		expect( settings.params.showunreviewed ).toBeUndefined();
		expect( saveOptionSpy ).not.toHaveBeenCalled();
	} );

	it( 'overlays from and to date range without persisting', () => {
		mockParams( { from: '2026-08-01', to: '2026-08-31' } );
		settings.loadApiParams();

		expect( settings.urlOverridesActive ).toBe( true );
		expect( settings.applied.nppDate.from ).toBe( '2026-08-01' );
		expect( settings.applied.nppDate.to ).toBe( '2026-08-31' );
		expect( settings.applied.afcDate.from ).toBe( '2026-08-01' );
		expect( settings.applied.afcDate.to ).toBe( '2026-08-31' );
		// timecorrection ZoneInfo|-480| → subtract(-480 minutes) = +8 hours
		expect( settings.params.date_range_from ).toBe( '2026-08-01T08:00:00.000Z' );
		expect( settings.params.date_range_to ).toBe( '2026-09-01T07:59:59.000Z' );
		expect( saveOptionSpy ).not.toHaveBeenCalled();
	} );

	it( 'overlays ores class without persisting', () => {
		mockParams( { ores: 'stub,start' } );
		settings.loadApiParams();

		expect( settings.urlOverridesActive ).toBe( true );
		expect( settings.applied.nppPredictedRating.stub ).toBe( true );
		expect( settings.applied.nppPredictedRating.start ).toBe( true );
		expect( settings.applied.nppPredictedRating.featured ).toBe( false );
		expect( settings.applied.afcPredictedRating.stub ).toBe( true );
		expect( settings.params.show_predicted_class_stub ).toBe( 1 );
		expect( settings.params.show_predicted_class_start ).toBe( 1 );
		expect( saveOptionSpy ).not.toHaveBeenCalled();
	} );

	it( 'ignores ores class when ORES filters are hidden', () => {
		mw.config.get = jest.fn( ( key, fallback ) => {
			switch ( key ) {
				case 'wgPageTriageDraftNamespaceId':
					return 118;
				case 'wgNamespaceIds':
					return { draft: 118 };
				case 'wgShowOresFilters':
					return false;
				default:
					return fallback;
			}
		} );
		mockParams( { ores: 'stub,start' } );
		settings.loadApiParams();

		expect( settings.urlOverridesActive ).toBe( false );
		expect( settings.applied.nppPredictedRating.stub ).toBe( false );
		expect( settings.params.show_predicted_class_stub ).toBeUndefined();
	} );

	it( 'normalizes a saved string username', () => {
		mw.user.options.get = jest.fn( ( key, fallback ) => {
			switch ( key ) {
				case 'timecorrection':
					return 'ZoneInfo|-480|America/Los_Angeles';
				case 'userjs-NewPagesFeedFilterOptions':
					return JSON.stringify( {
						mode: 'npp',
						username: 'Jimbo_Wales'
					} );
				default:
					return fallback || null;
			}
		} );
		const { useSettingsStore } = require( '../../../../modules/ext.pageTriage.newPagesFeed/stores/settings.js' );
		setActivePinia( createPinia() );
		const stored = useSettingsStore();
		stored.loadApiParams();

		expect( stored.applied.nppFilter ).toBe( 'username' );
		expect( stored.applied.nppFilterUser ).toEqual( [ 'Jimbo Wales' ] );
	} );

	it( 'does not treat an empty saved username array as an active filter', () => {
		mw.user.options.get = jest.fn( ( key, fallback ) => {
			switch ( key ) {
				case 'timecorrection':
					return 'ZoneInfo|-480|America/Los_Angeles';
				case 'userjs-NewPagesFeedFilterOptions':
					return JSON.stringify( {
						mode: 'npp',
						username: []
					} );
				default:
					return fallback || null;
			}
		} );
		const { useSettingsStore } = require( '../../../../modules/ext.pageTriage.newPagesFeed/stores/settings.js' );
		setActivePinia( createPinia() );
		const stored = useSettingsStore();
		stored.loadApiParams();

		expect( stored.applied.nppFilter ).toBe( 'all' );
		expect( stored.applied.nppFilterUser ).toEqual( [] );
	} );
} );
