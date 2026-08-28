const { applyUrlParams } = require( '../../../modules/ext.pageTriage.newPagesFeed/urlParams.js' );

function makeSettings( queueMode ) {
	return {
		immediate: { queueMode: queueMode || 'npp' },
		unsaved: {
			nppFilter: 'all',
			nppFilterUser: '',
			afcFilter: 'all',
			afcFilterUser: ''
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
		expect( settings.unsaved.nppFilterUser ).toBe( '' );
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
		expect( settings.unsaved.nppFilterUser ).toBe( 'Alice' );
		expect( settings.unsaved.afcFilter ).toBe( 'username' );
		expect( settings.unsaved.afcFilterUser ).toBe( 'Alice' );
	} );

	it( 'normalizes underscores in username to spaces', () => {
		mockParams( { username: 'Jimbo_Wales' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.unsaved.nppFilterUser ).toBe( 'Jimbo Wales' );
		expect( settings.unsaved.afcFilterUser ).toBe( 'Jimbo Wales' );
	} );

	it( 'applies username to both fields when feed is afc', () => {
		mockParams( { feed: 'afc', username: 'Alice' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.immediate.queueMode ).toBe( 'afc' );
		expect( settings.unsaved.afcFilter ).toBe( 'username' );
		expect( settings.unsaved.afcFilterUser ).toBe( 'Alice' );
		expect( settings.unsaved.nppFilter ).toBe( 'username' );
		expect( settings.unsaved.nppFilterUser ).toBe( 'Alice' );
	} );

	it( 'still applies username when feed is invalid', () => {
		mockParams( { feed: 'nope', username: 'Bob' } );
		const settings = makeSettings();
		expect( applyUrlParams( settings ) ).toBe( true );
		expect( settings.immediate.queueMode ).toBe( 'npp' );
		expect( settings.unsaved.nppFilterUser ).toBe( 'Bob' );
	} );
} );
