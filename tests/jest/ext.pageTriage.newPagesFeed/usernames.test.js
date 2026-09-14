const { MAX_FILTER_USERNAMES, normalizeUsernames } = require( '../../../modules/ext.pageTriage.newPagesFeed/usernames.js' );

describe( 'usernames.js', () => {
	it( 'returns an empty list for missing values', () => {
		expect( normalizeUsernames() ).toEqual( [] );
		expect( normalizeUsernames( '' ) ).toEqual( [] );
		expect( normalizeUsernames( [] ) ).toEqual( [] );
	} );

	it( 'accepts a single username string', () => {
		expect( normalizeUsernames( 'Alice' ) ).toEqual( [ 'Alice' ] );
	} );

	it( 'splits, trims, de-duplicates, and converts underscores', () => {
		expect( normalizeUsernames( 'Alice|Jimbo_Wales|Alice| |_ ' ) ).toEqual( [
			'Alice',
			'Jimbo Wales'
		] );
	} );

	it( 'normalizes each name in an array', () => {
		expect( normalizeUsernames( [ 'Jimbo_Wales', ' Alice ', 'Alice', 1, '' ] ) ).toEqual( [
			'Jimbo Wales',
			'Alice'
		] );
	} );

	it( 'caps the list at the shared maximum', () => {
		const usernames = [];
		for ( let i = 0; i <= MAX_FILTER_USERNAMES; i++ ) {
			usernames.push( `User ${ i }` );
		}
		expect( normalizeUsernames( usernames ) ).toEqual(
			usernames.slice( 0, MAX_FILTER_USERNAMES )
		);
		expect( normalizeUsernames( usernames.join( '|' ) ) ).toHaveLength(
			MAX_FILTER_USERNAMES
		);
	} );
} );
