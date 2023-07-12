let defaultTagsOptions;

describe( 'defaultTagsOptions', () => {
	beforeEach( () => {
		mw.config.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'wgPageName':
					return 'PageName';
				default:
					return null;
			}
		} );
		// needs to be loaded after mw.config.get has been defined to avoid fatal.
		defaultTagsOptions = require( '../../modules/ext.pageTriage.defaultTagsOptions/main.js' );
	} );

	test( 'defaultDeletionTagsOptions', () => {
		expect( defaultTagsOptions.pageTriageDeletionTagsMultiple ).not.toBe( undefined );
		expect( defaultTagsOptions.pageTriageDeletionTagsOptions ).not.toBe( undefined );
	} );
} );
