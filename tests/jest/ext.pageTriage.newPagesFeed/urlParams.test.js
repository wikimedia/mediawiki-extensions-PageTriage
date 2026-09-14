const { applyUrlParams } = require( '../../../modules/ext.pageTriage.newPagesFeed/urlParams.js' );
const { MAX_FILTER_USERNAMES } = require( '../../../modules/ext.pageTriage.newPagesFeed/usernames.js' );

function makePredictedRating() {
	return {
		stub: false,
		start: false,
		c: false,
		b: false,
		good: false,
		featured: false
	};
}

function makeSettings( queueMode ) {
	return {
		immediate: { queueMode: queueMode || 'npp' },
		unsaved: {
			nppFilter: 'all',
			nppFilterUser: [],
			afcFilter: 'all',
			afcFilterUser: [],
			nppIncludeReviewed: false,
			nppIncludeUnreviewed: true,
			nppDate: { from: '', to: '' },
			afcDate: { from: '', to: '' },
			nppPredictedRating: makePredictedRating(),
			afcPredictedRating: makePredictedRating()
		}
	};
}

describe( 'urlParams.js', () => {
	const originalGetParamValue = mw.util.getParamValue;
	const originalConfigGet = mw.config.get;

	beforeEach( () => {
		mw.config.get = jest.fn( ( key, fallback ) => {
			if ( key === 'wgPageTriageDraftNamespaceId' ) {
				return 118;
			}
			if ( key === 'wgShowOresFilters' ) {
				return true;
			}
			return originalConfigGet.call( mw.config, key, fallback );
		} );
	} );

	afterEach( () => {
		mw.util.getParamValue = originalGetParamValue;
		mw.config.get = originalConfigGet;
	} );

	function mockParams( params ) {
		mw.util.getParamValue = jest.fn( ( name ) => (
			Object.prototype.hasOwnProperty.call( params, name ) ? params[ name ] : null
		) );
	}

	it( 'does nothing when no URL params are present', () => {
		mockParams( {} );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( false );
		expect( settings.immediate.queueMode ).toBe( 'npp' );
		expect( settings.unsaved.nppFilter ).toBe( 'all' );
		expect( settings.unsaved.nppFilterUser ).toEqual( [] );
	} );

	it( 'ignores empty param values', () => {
		mockParams( { feed: '', username: '' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( false );
	} );

	it( 'applies a valid feed param', () => {
		mockParams( { feed: 'afc' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.immediate.queueMode ).toBe( 'afc' );
	} );

	it( 'ignores an invalid feed param', () => {
		mockParams( { feed: 'invalid' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( false );
		expect( settings.immediate.queueMode ).toBe( 'npp' );
	} );

	it( 'ignores feed=afc when no draft namespace is configured', () => {
		mw.config.get = jest.fn( ( key, fallback ) => {
			if ( key === 'wgPageTriageDraftNamespaceId' ) {
				return false;
			}
			return originalConfigGet.call( mw.config, key, fallback );
		} );
		mockParams( { feed: 'afc' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( false );
		expect( settings.immediate.queueMode ).toBe( 'npp' );
	} );

	it( 'applies username to both npp and afc fields', () => {
		mockParams( { username: 'Alice' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.unsaved.nppFilter ).toBe( 'username' );
		expect( settings.unsaved.nppFilterUser ).toEqual( [ 'Alice' ] );
		expect( settings.unsaved.afcFilter ).toBe( 'username' );
		expect( settings.unsaved.afcFilterUser ).toEqual( [ 'Alice' ] );
	} );

	it( 'normalizes underscores in username to spaces', () => {
		mockParams( { username: 'Jimbo_Wales' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.unsaved.nppFilterUser ).toEqual( [ 'Jimbo Wales' ] );
		expect( settings.unsaved.afcFilterUser ).toEqual( [ 'Jimbo Wales' ] );
	} );

	it( 'applies several pipe-separated usernames', () => {
		mockParams( { username: 'Alice|Jimbo_Wales|Alice|' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.unsaved.nppFilterUser ).toEqual( [ 'Alice', 'Jimbo Wales' ] );
		expect( settings.unsaved.afcFilterUser ).toEqual( [ 'Alice', 'Jimbo Wales' ] );
	} );

	it( 'truncates the username list to the maximum', () => {
		const usernames = [];
		for ( let i = 0; i <= MAX_FILTER_USERNAMES; i++ ) {
			usernames.push( `User ${ i }` );
		}
		mockParams( { username: usernames.join( '|' ) } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.unsaved.nppFilterUser ).toHaveLength( MAX_FILTER_USERNAMES );
		expect( settings.unsaved.nppFilterUser ).toEqual(
			usernames.slice( 0, MAX_FILTER_USERNAMES )
		);
	} );

	it( 'ignores a username param with no usable names', () => {
		mockParams( { username: '|_|' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( false );
		expect( settings.unsaved.nppFilter ).toBe( 'all' );
	} );

	it( 'applies username to both fields when feed is afc', () => {
		mockParams( { feed: 'afc', username: 'Alice' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.immediate.queueMode ).toBe( 'afc' );
		expect( settings.unsaved.afcFilter ).toBe( 'username' );
		expect( settings.unsaved.afcFilterUser ).toEqual( [ 'Alice' ] );
		expect( settings.unsaved.nppFilter ).toBe( 'username' );
		expect( settings.unsaved.nppFilterUser ).toEqual( [ 'Alice' ] );
	} );

	it( 'still applies username when feed is invalid', () => {
		mockParams( { feed: 'nope', username: 'Bob' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.immediate.queueMode ).toBe( 'npp' );
		expect( settings.unsaved.nppFilterUser ).toEqual( [ 'Bob' ] );
	} );

	it( 'applies status=reviewed and clears unreviewed', () => {
		mockParams( { status: 'reviewed' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.unsaved.nppIncludeReviewed ).toBe( true );
		expect( settings.unsaved.nppIncludeUnreviewed ).toBe( false );
	} );

	it( 'applies both status values', () => {
		mockParams( { status: 'reviewed,unreviewed' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.unsaved.nppIncludeReviewed ).toBe( true );
		expect( settings.unsaved.nppIncludeUnreviewed ).toBe( true );
	} );

	it( 'ignores an invalid status param', () => {
		mockParams( { status: 'nope' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( false );
		expect( settings.unsaved.nppIncludeUnreviewed ).toBe( true );
		expect( settings.unsaved.nppIncludeReviewed ).toBe( false );
	} );

	it( 'applies from and to date params to both feeds', () => {
		mockParams( { from: '2026-08-01', to: '2026-08-31' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.unsaved.nppDate.from ).toBe( '2026-08-01' );
		expect( settings.unsaved.nppDate.to ).toBe( '2026-08-31' );
		expect( settings.unsaved.afcDate.from ).toBe( '2026-08-01' );
		expect( settings.unsaved.afcDate.to ).toBe( '2026-08-31' );
	} );

	it( 'trims date params', () => {
		mockParams( { from: ' 2026-08-01 ', to: ' 2026-08-31 ' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.unsaved.nppDate.from ).toBe( '2026-08-01' );
		expect( settings.unsaved.nppDate.to ).toBe( '2026-08-31' );
	} );

	it( 'ignores invalid date params', () => {
		mockParams( { from: 'yesterday', to: '08-01-2026' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( false );
		expect( settings.unsaved.nppDate.from ).toBe( '' );
		expect( settings.unsaved.nppDate.to ).toBe( '' );
	} );

	it( 'ignores impossible calendar dates', () => {
		mockParams( { from: '2026-13-01', to: '2026-02-30' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( false );
		expect( settings.unsaved.nppDate.from ).toBe( '' );
		expect( settings.unsaved.nppDate.to ).toBe( '' );
	} );

	it( 'clears a saved to when only from is in the URL', () => {
		mockParams( { from: '2026-08-01' } );
		const settings = makeSettings();
		settings.unsaved.nppDate.to = '2026-07-01';
		settings.unsaved.afcDate.to = '2026-07-01';
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.unsaved.nppDate.from ).toBe( '2026-08-01' );
		expect( settings.unsaved.nppDate.to ).toBe( '' );
		expect( settings.unsaved.afcDate.from ).toBe( '2026-08-01' );
		expect( settings.unsaved.afcDate.to ).toBe( '' );
	} );

	it( 'clears a saved from when only to is in the URL', () => {
		mockParams( { to: '2026-08-31' } );
		const settings = makeSettings();
		settings.unsaved.nppDate.from = '2026-01-01';
		settings.unsaved.afcDate.from = '2026-01-01';
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.unsaved.nppDate.from ).toBe( '' );
		expect( settings.unsaved.nppDate.to ).toBe( '2026-08-31' );
		expect( settings.unsaved.afcDate.from ).toBe( '' );
		expect( settings.unsaved.afcDate.to ).toBe( '2026-08-31' );
	} );

	it( 'applies a valid from and clears an impossible to', () => {
		mockParams( { from: '2026-08-01', to: '2026-02-30' } );
		const settings = makeSettings();
		settings.unsaved.nppDate.to = '2026-07-01';
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.unsaved.nppDate.from ).toBe( '2026-08-01' );
		expect( settings.unsaved.nppDate.to ).toBe( '' );
	} );

	it( 'applies ores classes and clears unspecified ones', () => {
		mockParams( { ores: 'stub,start' } );
		const settings = makeSettings();
		settings.unsaved.nppPredictedRating.featured = true;
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.unsaved.nppPredictedRating.stub ).toBe( true );
		expect( settings.unsaved.nppPredictedRating.start ).toBe( true );
		expect( settings.unsaved.nppPredictedRating.featured ).toBe( false );
		expect( settings.unsaved.afcPredictedRating.stub ).toBe( true );
		expect( settings.unsaved.afcPredictedRating.start ).toBe( true );
	} );

	it( 'ignores an invalid ores param', () => {
		mockParams( { ores: 'legendary' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( false );
		expect( settings.unsaved.nppPredictedRating.stub ).toBe( false );
	} );

	it( 'applies valid ores tokens and skips unknown ones', () => {
		mockParams( { ores: 'stub,legendary,c' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.unsaved.nppPredictedRating.stub ).toBe( true );
		expect( settings.unsaved.nppPredictedRating.c ).toBe( true );
		expect( settings.unsaved.nppPredictedRating.start ).toBe( false );
	} );

	it( 'ignores ores when ORES filters are not shown', () => {
		mw.config.get = jest.fn( ( key, fallback ) => {
			if ( key === 'wgShowOresFilters' ) {
				return false;
			}
			return originalConfigGet.call( mw.config, key, fallback );
		} );
		mockParams( { ores: 'stub' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( false );
		expect( settings.unsaved.nppPredictedRating.stub ).toBe( false );
	} );

	it( 'applies wiki-specific ores class keys and ignores others', () => {
		mockParams( { ores: 'needs-work,featured' } );
		const settings = makeSettings();
		settings.unsaved.nppPredictedRating = { stub: true, 'needs-work': false };
		settings.unsaved.afcPredictedRating = { stub: true, 'needs-work': false };
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.unsaved.nppPredictedRating.stub ).toBe( false );
		expect( settings.unsaved.nppPredictedRating[ 'needs-work' ] ).toBe( true );
		expect( settings.unsaved.nppPredictedRating.featured ).toBeUndefined();
		expect( settings.unsaved.afcPredictedRating[ 'needs-work' ] ).toBe( true );
	} );

	it( 'ignores ores when the form has no class keys', () => {
		mockParams( { ores: 'stub' } );
		const settings = makeSettings();
		settings.unsaved.nppPredictedRating = {};
		settings.unsaved.afcPredictedRating = {};
		expect( applyUrlParams( settings ) ).toBe( false );
	} );
} );
