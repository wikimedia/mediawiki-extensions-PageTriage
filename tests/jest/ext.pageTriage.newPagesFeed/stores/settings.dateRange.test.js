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
 * Minimal utc() stub that honors add/subtract, toISOString and
 * format( 'YYYY-MM-DD' ) the way the settings store uses moment.
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

describe( 'settings store date range', () => {
	let settings;
	let saveOptionSpy;
	let storedParams;
	const originalGetParamValue = mw.util.getParamValue;
	const originalConfigGet = mw.config.get;
	const originalOptionsGet = mw.user.options.get;
	const originalIsNamed = mw.user.isNamed;
	const originalMoment = global.moment;

	/**
	 * Simulate a page load: a fresh store reading the stored filter options
	 *
	 * @return {Object} settings store
	 */
	function loadStore() {
		const { useSettingsStore } = require( '../../../../modules/ext.pageTriage.newPagesFeed/stores/settings.js' );
		setActivePinia( createPinia() );
		return useSettingsStore();
	}

	beforeEach( () => {
		global.moment = { utc: createMomentUtc };
		storedParams = null;
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
				case 'userjs-NewPagesFeedFilterOptions':
					return storedParams;
				default:
					return fallback || null;
			}
		} );
		mw.user.options.set = jest.fn( ( key, value ) => {
			if ( key === 'userjs-NewPagesFeedFilterOptions' ) {
				storedParams = value;
			}
		} );
		mw.user.isNamed = jest.fn().mockReturnValue( true );
		mw.storage.set = jest.fn();
		saveOptionSpy = jest.spyOn( mw.Api.prototype, 'saveOption' ).mockResolvedValue( true );
		mw.util.getParamValue = jest.fn().mockReturnValue( null );

		settings = loadStore();
	} );

	afterEach( () => {
		mw.util.getParamValue = originalGetParamValue;
		mw.config.get = originalConfigGet;
		mw.user.options.get = originalOptionsGet;
		mw.user.isNamed = originalIsNamed;
		global.moment = originalMoment;
		saveOptionSpy.mockRestore();
	} );

	it( 'stores the end of the chosen "to" day in local time', () => {
		settings.unsaved.nppDate.to = '2024-08-19';
		settings.saveFilters();

		// 23:59:59 on 19 August in UTC-8
		expect( settings.params.date_range_to ).toBe( '2024-08-20T07:59:59.000Z' );
	} );

	it( 'keeps an NPP "to" date unchanged across page loads (T374382)', () => {
		settings.unsaved.nppDate.to = '2024-08-19';
		settings.saveFilters();

		for ( let i = 0; i < 2; i++ ) {
			settings = loadStore();
			// Called by both ListFilterMenu and ShowingText on page load
			settings.loadApiParams();
			settings.loadApiParams();

			expect( settings.applied.nppDate ).toEqual( { from: '', to: '2024-08-19' } );
			expect( settings.params.date_range_to ).toBe( '2024-08-20T07:59:59.000Z' );
		}
	} );

	it( 'keeps an AFC "to" date unchanged across page loads (T374382)', () => {
		settings.updateImmediate( 'queueMode', 'afc' );
		settings.unsaved.afcDate.to = '2025-11-05';
		settings.saveFilters();

		settings = loadStore();
		settings.loadApiParams();
		settings.loadApiParams();

		expect( settings.applied.afcDate ).toEqual( { from: '', to: '2025-11-05' } );
		expect( settings.params.date_range_to ).toBe( '2025-11-06T07:59:59.000Z' );
	} );

	it( 'keeps a "from" date unchanged across page loads', () => {
		settings.unsaved.nppDate.from = '2024-08-01';
		settings.unsaved.nppDate.to = '2024-08-19';
		settings.saveFilters();

		settings = loadStore();
		settings.loadApiParams();

		expect( settings.applied.nppDate ).toEqual( { from: '2024-08-01', to: '2024-08-19' } );
		expect( settings.params.date_range_from ).toBe( '2024-08-01T08:00:00.000Z' );
	} );
} );
